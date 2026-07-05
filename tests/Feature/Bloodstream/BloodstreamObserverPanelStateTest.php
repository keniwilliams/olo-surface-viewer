<?php

namespace Tests\Feature\Bloodstream;

use App\Services\Bloodstream\BloodstreamObserverPanelState;
use App\Support\Bloodstream\BloodstreamObserverChangedPingHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use LaravelNats\Subscriber\InboundMessage;
use Tests\TestCase;

class BloodstreamObserverPanelStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(BloodstreamObserverPanelState::class)->reset();
        DB::purge('bloodstream');
    }

    /**
     * @throws JsonException
     */
    public function test_ping_received_records_receipt_time_and_metadata_without_display_payload(): void
    {
        config(['database.connections.bloodstream' => []]);

        app(BloodstreamObserverChangedPingHandler::class)->handle(new InboundMessage(
            subject: 'olo.bloodstream.observer.changed.v1',
            body: json_encode([
                'owner' => 'olo-bloodstream-observer',
                'event' => 'changed',
                'publisher' => 'impressions',
                'published_at' => '2026-07-02T00:12:03.104112+00:00',
                'emitted_at' => '2026-07-02T00:12:03.201933+00:00',
                'payload' => ['must_not_be_displayed' => true],
            ], JSON_THROW_ON_ERROR),
            headers: [],
            replyTo: null,
        ));

        $state = app(BloodstreamObserverPanelState::class)->snapshot();

        $this->assertSame('disabled', $state['status']);
        $this->assertSame('olo.bloodstream.observer.changed.v1', $state['latest_ping']['subject']);
        $this->assertNotNull($state['latest_ping']['received_at']);
        $this->assertSame('olo-bloodstream-observer', $state['latest_ping']['metadata']['owner']);
        $this->assertSame('changed', $state['latest_ping']['metadata']['event']);
        $this->assertSame('impressions', $state['latest_ping']['metadata']['publisher']);
        $this->assertArrayNotHasKey('payload', $state['latest_ping']);
        $this->assertArrayNotHasKey('payload', $state['latest_ping']['metadata']);
    }

    /**
     * @throws JsonException
     */
    public function test_changed_ping_performs_read_only_refresh_from_bloodstream(): void
    {
        $this->configureBloodstreamSqlite();
        $this->createBloodstreamTables();

        DB::connection('bloodstream')->table('contract_memory')->insert([
            'contract_key' => 'olo.impressions.events.impression.created.v1',
            'organ' => 'impressions',
            'role' => 'producer',
            'version' => '1.0',
            'status' => 'discovered',
            'source' => 'observer',
            'schema_json' => json_encode(['type' => 'object'], JSON_THROW_ON_ERROR),
            'metadata_json' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => '2026-07-01 12:00:00',
            'updated_at' => '2026-07-01 12:30:00',
            'deleted_at' => null,
        ]);

        DB::connection('bloodstream')->table('subject_memory')->insert([
            'subject' => 'olo.impressions.events.impression.created.v1',
            'organ' => 'impressions',
            'role' => 'producer',
            'contract_key' => 'olo.impressions.events.impression.created.v1',
            'contract_version' => '1.0',
            'status' => 'active',
            'source' => 'observer',
            'first_seen_at' => '2026-07-01 12:00:00',
            'last_seen_at' => '2026-07-01 12:35:00',
            'seen_count' => 4,
            'metadata_json' => json_encode(['sampled' => true], JSON_THROW_ON_ERROR),
            'created_at' => '2026-07-01 12:00:00',
            'updated_at' => '2026-07-01 12:35:00',
            'deleted_at' => null,
        ]);

        DB::connection('bloodstream')->enableQueryLog();

        app(BloodstreamObserverChangedPingHandler::class)->handle(new InboundMessage(
            subject: 'olo.bloodstream.observer.changed.v1',
            body: json_encode([
                'owner' => 'olo-bloodstream-observer',
                'event' => 'changed',
                'publisher' => 'impressions',
            ], JSON_THROW_ON_ERROR),
            headers: [],
            replyTo: null,
        ));

        $state = app(BloodstreamObserverPanelState::class)->snapshot();

        $this->assertSame('fresh', $state['status']);
        $this->assertFalse($state['is_dirty']);
        $this->assertNotNull($state['last_refresh_attempt_at']);
        $this->assertNotNull($state['last_successful_read_at']);
        $this->assertSame(1, $state['summary']['contracts_total']);
        $this->assertSame(1, $state['summary']['subjects_total']);
        $this->assertSame(['active' => 1], $state['summary']['subjects_by_status']);
        $this->assertSame('olo.impressions.events.impression.created.v1', $state['summary']['latest_subject']['subject']);
        $this->assertSame(4, $state['summary']['latest_subject']['seen_count']);
        $this->assertBloodstreamQueriesWereReadOnly();
    }

    public function test_refresh_failure_sets_error_state_and_message(): void
    {
        $this->configureBloodstreamSqlite();

        $state = app(BloodstreamObserverPanelState::class)->refresh();

        $this->assertSame('error', $state['status']);
        $this->assertNotNull($state['last_refresh_attempt_at']);
        $this->assertNull($state['last_successful_read_at']);
        $this->assertIsString($state['error']);
        $this->assertStringContainsString('no such table', $state['error']);
    }

    public function test_missing_bloodstream_config_sets_disabled_state_without_reading(): void
    {
        config(['database.connections.bloodstream' => []]);

        $state = app(BloodstreamObserverPanelState::class)->refresh();

        $this->assertSame('disabled', $state['status']);
        $this->assertFalse($state['is_dirty']);
        $this->assertNotNull($state['last_refresh_attempt_at']);
        $this->assertNull($state['last_successful_read_at']);
        $this->assertSame('Bloodstream database connection is not configured.', $state['error']);
    }

    private function configureBloodstreamSqlite(): void
    {
        config([
            'database.connections.bloodstream' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('bloodstream');
    }

    private function createBloodstreamTables(): void
    {
        Schema::connection('bloodstream')->create('contract_memory', function ($table): void {
            $table->id();
            $table->text('contract_key');
            $table->text('organ')->nullable();
            $table->text('role')->nullable();
            $table->text('version')->nullable();
            $table->text('status')->default('unknown');
            $table->text('source')->default('unknown');
            $table->json('schema_json')->default('{}');
            $table->json('metadata_json')->default('{}');
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
        });

        Schema::connection('bloodstream')->create('subject_memory', function ($table): void {
            $table->id();
            $table->text('subject');
            $table->text('organ')->nullable();
            $table->text('role')->nullable();
            $table->text('contract_key')->nullable();
            $table->text('contract_version')->nullable();
            $table->text('status')->default('unknown');
            $table->text('source')->default('unknown');
            $table->timestampTz('first_seen_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->unsignedBigInteger('seen_count')->default(0);
            $table->json('metadata_json')->default('{}');
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
        });
    }

    private function assertBloodstreamQueriesWereReadOnly(): void
    {
        foreach (DB::connection('bloodstream')->getQueryLog() as $query) {
            $this->assertStringStartsWith('select', strtolower(trim($query['query'])));
        }
    }
}
