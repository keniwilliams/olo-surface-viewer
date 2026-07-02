<?php

namespace Tests\Unit\Bloodstream;

use App\Models\Bloodstream\ContractMemory;
use App\Models\Bloodstream\ReadOnlyBloodstreamBuilder;
use App\Models\Bloodstream\SubjectMemory;
use LogicException;
use Tests\TestCase;

class BloodstreamReadOnlyModelsTest extends TestCase
{
    public function test_contract_memory_uses_bloodstream_connection_and_table(): void
    {
        $model = new ContractMemory;

        $this->assertSame('bloodstream', $model->getConnectionName());
        $this->assertSame('contract_memory', $model->getTable());
        $this->assertSame(['*'], $model->getGuarded());
    }

    public function test_subject_memory_uses_bloodstream_connection_and_table(): void
    {
        $model = new SubjectMemory;

        $this->assertSame('bloodstream', $model->getConnectionName());
        $this->assertSame('subject_memory', $model->getTable());
        $this->assertSame(['*'], $model->getGuarded());
    }

    public function test_contract_memory_casts_observer_json_and_timestamps(): void
    {
        $model = new ContractMemory;

        $this->assertSame('array', $model->getCasts()['schema_json']);
        $this->assertSame('array', $model->getCasts()['metadata_json']);
        $this->assertSame('datetime', $model->getCasts()['created_at']);
        $this->assertSame('datetime', $model->getCasts()['updated_at']);
        $this->assertSame('datetime', $model->getCasts()['deleted_at']);
    }

    public function test_subject_memory_casts_observer_json_counts_and_timestamps(): void
    {
        $model = new SubjectMemory;

        $this->assertSame('datetime', $model->getCasts()['first_seen_at']);
        $this->assertSame('datetime', $model->getCasts()['last_seen_at']);
        $this->assertSame('integer', $model->getCasts()['seen_count']);
        $this->assertSame('array', $model->getCasts()['metadata_json']);
        $this->assertSame('datetime', $model->getCasts()['created_at']);
        $this->assertSame('datetime', $model->getCasts()['updated_at']);
        $this->assertSame('datetime', $model->getCasts()['deleted_at']);
    }

    public function test_bloodstream_models_use_a_read_only_builder(): void
    {
        $this->assertInstanceOf(ReadOnlyBloodstreamBuilder::class, ContractMemory::query());
        $this->assertInstanceOf(ReadOnlyBloodstreamBuilder::class, SubjectMemory::query());
    }

    public function test_model_writes_are_rejected_before_touching_bloodstream(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Bloodstream models are read-only for Surface Viewer.');

        (new ContractMemory)->save();
    }

    public function test_bulk_updates_are_rejected_before_touching_bloodstream(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Bloodstream models are read-only for Surface Viewer.');

        SubjectMemory::query()->update(['status' => 'declared']);
    }

    public function test_bulk_deletes_are_rejected_before_touching_bloodstream(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Bloodstream models are read-only for Surface Viewer.');

        ContractMemory::query()->delete();
    }

    public function test_passthrough_inserts_are_rejected_before_touching_bloodstream(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Bloodstream models are read-only for Surface Viewer.');

        SubjectMemory::query()->insert(['subject' => 'olo.example']);
    }
}
