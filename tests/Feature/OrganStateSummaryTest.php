<?php

namespace Tests\Feature;

use App\Services\OrganState\OrganStateSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use Tests\TestCase;

class OrganStateSummaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-05T12:00:00+00:00'));

        foreach (['bloodstream', 'subconscious', 'impressions', 'sidecar', 'surface_viewer'] as $connection) {
            DB::purge($connection);
        }
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * @throws JsonException
     */
    public function test_it_returns_readable_summaries_for_all_known_organs_from_real_database_reads(): void
    {
        $this->configureSqliteOrgan('bloodstream');
        $this->configureSqliteOrgan('subconscious');
        $this->configureSqliteOrgan('impressions');
        $this->configureSqliteOrgan('sidecar');
        $this->configureSqliteOrgan('surface_viewer');

        $this->createBloodstreamMemory('2026-07-05 11:59:00');
        $this->createActivityTable('subconscious', 'dreamstate_runs', 'completed_at', '2026-07-05 11:58:00');
        $this->createActivityTable('impressions', 'sensemade_impressions', 'sensemade_at', '2026-07-05 11:57:00');
        $this->createActivityTable('sidecar', 'email_syncs', 'updated_at', '2026-07-05 11:56:00');
        $this->createActivityTable('surface_viewer', 'database_schema_snapshots', 'captured_at', '2026-07-05 11:55:00');

        DB::connection('impressions')->enableQueryLog();

        $summaries = app(OrganStateSummaryService::class)->all();

        $this->assertCount(5, $summaries);

        $bloodstream = $this->summary($summaries, 'bloodstream');
        $impressions = $this->summary($summaries, 'impressions');
        $surfaceViewer = $this->summary($summaries, 'surface_viewer');

        $this->assertSame('readable', $bloodstream['read_status']);
        $this->assertSame('fresh', $bloodstream['staleness_state']);
        $this->assertSame('2026-07-05T12:00:00.000000Z', $bloodstream['last_successful_read_at']);
        $this->assertSame('2026-07-05T11:59:00.000000Z', $bloodstream['last_observed_activity_at']);
        $this->assertSame('observer memory read succeeded', $bloodstream['latest_message']);
        $this->assertNull($bloodstream['latest_error']);
        $this->assertSame('bloodstream:subject_memory.last_seen_at', $bloodstream['source']);

        $this->assertSame('readable', $impressions['read_status']);
        $this->assertSame('fresh', $impressions['staleness_state']);
        $this->assertSame('2026-07-05T11:57:00.000000Z', $impressions['last_observed_activity_at']);
        $this->assertSame('impressions:sensemade_impressions.sensemade_at', $impressions['source']);

        $this->assertSame('readable', $surfaceViewer['read_status']);
        $this->assertSame('2026-07-05T11:55:00.000000Z', $surfaceViewer['last_observed_activity_at']);
        $this->assertSame('surface_viewer:database_schema_snapshots.captured_at', $surfaceViewer['source']);

        $this->assertOrganQueriesWereReadOnly('impressions');
    }

    public function test_missing_config_produces_disabled_unknown_summary(): void
    {
        config(['database.connections.sidecar' => []]);

        $summary = app(OrganStateSummaryService::class)->forConnection('sidecar')->toArray();

        $this->assertSame('sidecar', $summary['key']);
        $this->assertSame('Sidecar', $summary['label']);
        $this->assertSame('disabled', $summary['read_status']);
        $this->assertSame('unknown', $summary['staleness_state']);
        $this->assertNull($summary['last_successful_read_at']);
        $this->assertNull($summary['last_observed_activity_at']);
        $this->assertSame('database connection is not configured', $summary['latest_message']);
        $this->assertNull($summary['latest_error']);
    }

    public function test_old_observed_activity_is_reported_as_stale(): void
    {
        $this->configureSqliteOrgan('impressions');
        $this->createActivityTable('impressions', 'sensemade_impressions', 'sensemade_at', '2026-07-05 10:30:00');

        $summary = app(OrganStateSummaryService::class)->forConnection('impressions')->toArray();

        $this->assertSame('readable', $summary['read_status']);
        $this->assertSame('stale', $summary['staleness_state']);
        $this->assertSame('2026-07-05T10:30:00.000000Z', $summary['last_observed_activity_at']);
        $this->assertSame('no new activity since 2026-07-05T10:30:00.000000Z', $summary['latest_message']);
    }

    public function test_failed_read_produces_error_summary_with_message(): void
    {
        config([
            'database.connections.sidecar' => [
                'driver' => 'sqlite',
                'database' => database_path('missing-sidecar.sqlite'),
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('sidecar');

        $summary = app(OrganStateSummaryService::class)->forConnection('sidecar')->toArray();

        $this->assertSame('error', $summary['read_status']);
        $this->assertSame('error', $summary['staleness_state']);
        $this->assertNull($summary['last_successful_read_at']);
        $this->assertNull($summary['last_observed_activity_at']);
        $this->assertSame('database read failed', $summary['latest_message']);
        $this->assertIsString($summary['latest_error']);
    }

    public function test_api_returns_vue_ready_read_only_organ_state_payload(): void
    {
        $this->configureSqliteOrgan('bloodstream');
        $this->createBloodstreamMemory('2026-07-05 11:59:00');

        foreach (['subconscious', 'impressions', 'sidecar', 'surface_viewer'] as $connection) {
            config(["database.connections.{$connection}" => []]);
        }

        $this->getJson('/api/organs/state')
            ->assertOk()
            ->assertJsonPath('meta.read_only', true)
            ->assertJsonPath('meta.source', 'organ_databases')
            ->assertJsonPath('data.0.key', 'surface_viewer')
            ->assertJsonPath('data.1.key', 'bloodstream')
            ->assertJsonPath('data.1.read_status', 'readable')
            ->assertJsonPath('data.1.staleness_state', 'fresh')
            ->assertJsonPath('data.2.key', 'subconscious')
            ->assertJsonPath('data.2.read_status', 'disabled');
    }

    private function configureSqliteOrgan(string $connection): void
    {
        config([
            "database.connections.{$connection}" => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge($connection);
    }

    /**
     * @throws JsonException
     */
    private function createBloodstreamMemory(string $lastSeenAt): void
    {
        Schema::connection('bloodstream')->create('contract_memory', function ($table): void {
            $table->id();
            $table->text('contract_key');
            $table->text('status')->default('unknown');
            $table->json('schema_json')->default('{}');
            $table->json('metadata_json')->default('{}');
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
        });

        Schema::connection('bloodstream')->create('subject_memory', function ($table): void {
            $table->id();
            $table->text('subject');
            $table->text('status')->default('unknown');
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->json('metadata_json')->default('{}');
            $table->timestampTz('deleted_at')->nullable();
        });

        DB::connection('bloodstream')->table('contract_memory')->insert([
            'contract_key' => 'olo.impressions.events.impression.created.v1',
            'status' => 'discovered',
            'schema_json' => json_encode([], JSON_THROW_ON_ERROR),
            'metadata_json' => json_encode([], JSON_THROW_ON_ERROR),
            'updated_at' => '2026-07-05 11:45:00',
            'deleted_at' => null,
        ]);

        DB::connection('bloodstream')->table('subject_memory')->insert([
            'subject' => 'olo.impressions.events.impression.created.v1',
            'status' => 'active',
            'last_seen_at' => $lastSeenAt,
            'updated_at' => '2026-07-05 11:50:00',
            'metadata_json' => json_encode([], JSON_THROW_ON_ERROR),
            'deleted_at' => null,
        ]);
    }

    private function createActivityTable(string $connection, string $tableName, string $timestampColumn, string $timestamp): void
    {
        Schema::connection($connection)->create($tableName, function ($table) use ($timestampColumn): void {
            $table->id();
            $table->text('name')->nullable();
            $table->timestampTz($timestampColumn)->nullable();
        });

        DB::connection($connection)->table($tableName)->insert([
            'name' => 'observed',
            $timestampColumn => $timestamp,
        ]);
    }

    private function summary(array $summaries, string $key): array
    {
        $summary = collect($summaries)->firstWhere('key', $key);

        $this->assertIsArray($summary);

        return $summary;
    }

    private function assertOrganQueriesWereReadOnly(string $connection): void
    {
        foreach (DB::connection($connection)->getQueryLog() as $query) {
            $sql = strtolower(trim($query['query']));

            $this->assertTrue(
                str_starts_with($sql, 'select') || str_starts_with($sql, 'pragma'),
                "Expected read-only query for {$connection}; got [{$query['query']}].",
            );
        }
    }
}
