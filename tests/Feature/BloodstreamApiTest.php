<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BloodstreamApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.bloodstream' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('bloodstream');

        $this->createBloodstreamTables();
    }

    public function test_contracts_endpoint_reads_visible_contract_memory_from_bloodstream(): void
    {
        DB::connection('bloodstream')->table('contract_memory')->insert([
            [
                'contract_key' => 'surface.contract.created',
                'organ' => 'surface',
                'role' => 'producer',
                'version' => '1.0',
                'status' => 'discovered',
                'source' => 'observer',
                'schema_json' => json_encode(['type' => 'object']),
                'metadata_json' => json_encode(['observed' => true]),
                'created_at' => '2026-07-01 12:00:00',
                'updated_at' => '2026-07-01 12:30:00',
                'deleted_at' => null,
            ],
            [
                'contract_key' => 'surface.contract.deleted',
                'organ' => 'surface',
                'role' => 'producer',
                'version' => '1.0',
                'status' => 'deprecated',
                'source' => 'observer',
                'schema_json' => json_encode([]),
                'metadata_json' => json_encode([]),
                'created_at' => '2026-07-01 12:00:00',
                'updated_at' => '2026-07-01 12:30:00',
                'deleted_at' => '2026-07-01 13:00:00',
            ],
        ]);

        DB::connection('bloodstream')->enableQueryLog();

        $response = $this->getJson('/api/bloodstream/contracts');

        $response
            ->assertOk()
            ->assertJsonPath('meta.read_only', true)
            ->assertJsonPath('meta.source', 'bloodstream')
            ->assertJsonPath('data.0.contract_key', 'surface.contract.created')
            ->assertJsonPath('data.0.schema.type', 'object')
            ->assertJsonPath('data.0.metadata.observed', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.deleted_at');

        $this->assertBloodstreamQueriesWereReadOnly();
    }

    public function test_subjects_endpoint_filters_subject_memory_from_bloodstream(): void
    {
        DB::connection('bloodstream')->table('subject_memory')->insert([
            [
                'subject' => 'surface.contract.created',
                'organ' => 'surface',
                'role' => 'producer',
                'contract_key' => 'surface.contract.created',
                'contract_version' => '1.0',
                'status' => 'discovered',
                'source' => 'observer',
                'first_seen_at' => '2026-07-01 12:00:00',
                'last_seen_at' => '2026-07-01 12:30:00',
                'seen_count' => 3,
                'metadata_json' => json_encode(['sampled' => true]),
                'created_at' => '2026-07-01 12:00:00',
                'updated_at' => '2026-07-01 12:30:00',
                'deleted_at' => null,
            ],
            [
                'subject' => 'surface.contract.deprecated',
                'organ' => 'surface',
                'role' => 'producer',
                'contract_key' => 'surface.contract.deprecated',
                'contract_version' => '1.0',
                'status' => 'deprecated',
                'source' => 'observer',
                'first_seen_at' => '2026-07-01 11:00:00',
                'last_seen_at' => '2026-07-01 11:30:00',
                'seen_count' => 1,
                'metadata_json' => json_encode([]),
                'created_at' => '2026-07-01 11:00:00',
                'updated_at' => '2026-07-01 11:30:00',
                'deleted_at' => null,
            ],
        ]);

        DB::connection('bloodstream')->enableQueryLog();

        $response = $this->getJson('/api/bloodstream/subjects?status=discovered');

        $response
            ->assertOk()
            ->assertJsonPath('meta.read_only', true)
            ->assertJsonPath('meta.source', 'bloodstream')
            ->assertJsonPath('data.0.subject', 'surface.contract.created')
            ->assertJsonPath('data.0.seen_count', 3)
            ->assertJsonPath('data.0.metadata.sampled', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.deleted_at');

        $this->assertBloodstreamQueriesWereReadOnly();
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
