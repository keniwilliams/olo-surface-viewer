<?php

namespace Tests\Unit;

use App\Models\DatabaseConnection;
use App\Models\DatabaseSchemaSnapshot;
use App\Models\DatabaseTableSchema;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class DatabaseSchemaModelsTest extends TestCase
{
    public function test_database_connection_has_schema_snapshot_relationships(): void
    {
        $model = new DatabaseConnection();

        $this->assertInstanceOf(HasMany::class, $model->schemaSnapshots());
        $this->assertInstanceOf(HasOne::class, $model->latestSchemaSnapshot());
    }

    public function test_database_schema_snapshot_has_expected_relationships(): void
    {
        $model = new DatabaseSchemaSnapshot();

        $this->assertInstanceOf(BelongsTo::class, $model->databaseConnection());
        $this->assertInstanceOf(HasMany::class, $model->tables());
    }

    public function test_database_table_schema_belongs_to_snapshot(): void
    {
        $model = new DatabaseTableSchema();

        $this->assertInstanceOf(BelongsTo::class, $model->snapshot());
    }

    public function test_database_schema_snapshot_casts_are_configured(): void
    {
        $model = new DatabaseSchemaSnapshot();

        $this->assertSame('datetime', $model->getCasts()['captured_at']);
        $this->assertSame('integer', $model->getCasts()['schema_count']);
        $this->assertSame('integer', $model->getCasts()['table_count']);
        $this->assertSame('integer', $model->getCasts()['column_count']);
    }

    public function test_database_table_schema_casts_are_configured(): void
    {
        $model = new DatabaseTableSchema();

        $this->assertSame('integer', $model->getCasts()['row_count']);
        $this->assertSame('array', $model->getCasts()['columns']);
        $this->assertSame('array', $model->getCasts()['primary_keys']);
        $this->assertSame('array', $model->getCasts()['foreign_keys']);
        $this->assertSame('array', $model->getCasts()['indexes']);
    }

    public function test_database_connection_casts_are_still_configured(): void
    {
        $model = new DatabaseConnection();

        $this->assertSame('integer', $model->getCasts()['port']);
        $this->assertSame('boolean', $model->getCasts()['is_enabled']);
    }
}
