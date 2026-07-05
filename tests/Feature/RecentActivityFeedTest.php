<?php

namespace Tests\Feature;

use App\Services\Bloodstream\BloodstreamObserverPanelState;
use App\Services\RecentActivity\RecentActivityFeedService;
use App\Support\Bloodstream\BloodstreamObserverChangedPingHandler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use LaravelNats\Subscriber\InboundMessage;
use Tests\TestCase;

class RecentActivityFeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-05T12:00:00+00:00'));
        app(BloodstreamObserverPanelState::class)->reset();

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
    public function test_feed_includes_bloodstream_observer_ping_and_refresh_activity(): void
    {
        $this->configureSqliteOrgan('bloodstream');
        $this->createBloodstreamMemory('2026-07-05 11:58:00');
        $this->disableOrgansExcept('bloodstream');

        app(BloodstreamObserverChangedPingHandler::class)->handle(new InboundMessage(
            subject: 'olo.bloodstream.observer.changed.v1',
            body: json_encode([
                'owner' => 'olo-bloodstream-observer',
                'event' => 'changed',
                'publisher' => 'impressions',
                'payload' => ['not_display_state' => true],
            ], JSON_THROW_ON_ERROR),
            headers: [],
            replyTo: null,
        ));

        $feed = app(RecentActivityFeedService::class)->all();

        $ping = $this->activity($feed, 'bloodstream_observer_changed_ping');
        $refresh = $this->activity($feed, 'bloodstream_observer_refresh');

        $this->assertSame('bloodstream', $ping['source_organ_key']);
        $this->assertSame('Bloodstream', $ping['source_organ_label']);
        $this->assertSame('2026-07-05T12:00:00.000000Z', $ping['activity_timestamp']);
        $this->assertSame('received', $ping['status']);
        $this->assertSame('olo.bloodstream.observer.changed.v1', $ping['source_reference']);
        $this->assertStringContainsString('changed ping received', $ping['message']);
        $this->assertNull($ping['error']);

        $this->assertSame('fresh', $refresh['status']);
        $this->assertSame('Bloodstream Observer refresh succeeded.', $refresh['message']);
        $this->assertNull($refresh['error']);
    }

    public function test_feed_includes_successful_organ_read_and_observed_activity_items(): void
    {
        $this->configureSqliteOrgan('impressions');
        $this->createActivityTable('impressions', 'sensemade_impressions', 'sensemade_at', '2026-07-05 11:57:00');
        $this->disableOrgansExcept('impressions');

        $feed = app(RecentActivityFeedService::class)->all();

        $read = $this->activityForOrgan($feed, 'impressions', 'organ_read_succeeded');
        $observed = $this->activityForOrgan($feed, 'impressions', 'organ_observed_activity');

        $this->assertSame('readable', $read['status']);
        $this->assertSame('organ database read succeeded', $read['message']);
        $this->assertSame('impressions:sensemade_impressions.sensemade_at', $read['source_reference']);
        $this->assertSame('2026-07-05T12:00:00.000000Z', $read['activity_timestamp']);

        $this->assertSame('fresh', $observed['status']);
        $this->assertSame('2026-07-05T11:57:00.000000Z', $observed['activity_timestamp']);
        $this->assertSame('latest observed activity from Impressions', $observed['message']);
    }

    public function test_failed_organ_read_appears_as_error_activity(): void
    {
        config([
            'database.connections.sidecar' => [
                'driver' => 'sqlite',
                'database' => database_path('missing-sidecar-feed.sqlite'),
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        $this->disableOrgansExcept('sidecar');
        DB::purge('sidecar');

        $feed = app(RecentActivityFeedService::class)->all();
        $failed = $this->activityForOrgan($feed, 'sidecar', 'organ_read_failed');

        $this->assertSame('error', $failed['status']);
        $this->assertSame('database read failed', $failed['message']);
        $this->assertIsString($failed['error']);
        $this->assertNull($failed['activity_timestamp']);
        $this->assertSame('sidecar', $failed['source_reference']);
    }

    public function test_missing_config_appears_as_disabled_activity(): void
    {
        config(['database.connections.subconscious' => []]);
        $this->disableOrgansExcept('subconscious');

        $feed = app(RecentActivityFeedService::class)->all();
        $disabled = $this->activityForOrgan($feed, 'subconscious', 'organ_read_disabled');

        $this->assertSame('disabled', $disabled['status']);
        $this->assertSame('database connection is not configured', $disabled['message']);
        $this->assertNull($disabled['activity_timestamp']);
        $this->assertNull($disabled['error']);
        $this->assertSame('subconscious', $disabled['source_reference']);
    }

    public function test_feed_is_ordered_newest_first_with_untimestamped_items_last(): void
    {
        $this->configureSqliteOrgan('impressions');
        $this->configureSqliteOrgan('surface_viewer');
        $this->createActivityTable('impressions', 'sensemade_impressions', 'sensemade_at', '2026-07-05 11:10:00');
        $this->createActivityTable('surface_viewer', 'database_schema_snapshots', 'captured_at', '2026-07-05 11:55:00');

        config(['database.connections.sidecar' => []]);
        $this->disableOrgansExcept('impressions', 'surface_viewer', 'sidecar');

        $feed = app(RecentActivityFeedService::class)->all();
        $timestamps = array_map(fn (array $item): ?string => $item['activity_timestamp'], $feed);

        $this->assertSame('2026-07-05T12:00:00.000000Z', $timestamps[0]);
        $this->assertSame('2026-07-05T12:00:00.000000Z', $timestamps[1]);
        $this->assertSame('2026-07-05T11:55:00.000000Z', $timestamps[2]);
        $this->assertSame('2026-07-05T11:10:00.000000Z', $timestamps[3]);
        $this->assertNull($timestamps[array_key_last($timestamps)]);
    }

    /**
     * @throws JsonException
     */
    public function test_api_returns_recent_activity_payload_shape(): void
    {
        $this->configureSqliteOrgan('bloodstream');
        $this->createBloodstreamMemory('2026-07-05 11:58:00');
        $this->disableOrgansExcept('bloodstream');

        app(BloodstreamObserverChangedPingHandler::class)->handle(new InboundMessage(
            subject: 'olo.bloodstream.observer.changed.v1',
            body: json_encode(['owner' => 'olo-bloodstream-observer'], JSON_THROW_ON_ERROR),
            headers: [],
            replyTo: null,
        ));

        $this->getJson('/api/activity/recent?limit=3')
            ->assertOk()
            ->assertJsonPath('meta.read_only', true)
            ->assertJsonPath('meta.source', 'surface_viewer_observation_state')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'source_organ_key',
                        'source_organ_label',
                        'activity_type',
                        'activity_timestamp',
                        'status',
                        'message',
                        'error',
                        'source_reference',
                    ],
                ],
                'meta' => [
                    'read_only',
                    'source',
                ],
            ])
            ->assertJsonCount(3, 'data');
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

    private function disableOrgansExcept(string ...$enabled): void
    {
        foreach (['bloodstream', 'subconscious', 'impressions', 'sidecar', 'surface_viewer'] as $connection) {
            if (in_array($connection, $enabled, true)) {
                continue;
            }

            config(["database.connections.{$connection}" => []]);
        }
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

    private function activity(array $feed, string $type): array
    {
        $activity = collect($feed)->firstWhere('activity_type', $type);

        $this->assertIsArray($activity);

        return $activity;
    }

    private function activityForOrgan(array $feed, string $organ, string $type): array
    {
        $activity = collect($feed)->first(
            fn (array $item): bool => $item['source_organ_key'] === $organ
                && $item['activity_type'] === $type,
        );

        $this->assertIsArray($activity);

        return $activity;
    }
}
