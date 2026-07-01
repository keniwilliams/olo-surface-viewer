<?php

namespace Tests\Unit;

use App\Models\DatabaseConnection;
use PHPUnit\Framework\TestCase;

class DatabaseConnectionModelTest extends TestCase
{
    public function test_database_connection_casts_are_configured(): void
    {
        $model = new DatabaseConnection();

        $this->assertSame('integer', $model->getCasts()['port']);
        $this->assertSame('boolean', $model->getCasts()['is_enabled']);
    }

    public function test_database_connection_can_hold_registry_values(): void
    {
        $model = new DatabaseConnection([
            'name' => 'Example Organ',
            'connection_key' => 'Example Organ',
            'host' => 'organ-db.example.test',
            'port' => 5432,
            'database' => 'organ_database',
            'username' => 'organ_reader',
            'description' => 'Example observed organ database',
            'is_enabled' => true,
        ]);

        $this->assertSame('Example Organ', $model->name);
        $this->assertSame('Example Organ', $model->connection_key);
        $this->assertSame('organ-db.example.test', $model->host);
        $this->assertSame(5432, $model->port);
        $this->assertSame('organ_database', $model->database);
        $this->assertSame('organ_reader', $model->username);
        $this->assertSame('Example observed organ database', $model->description);
        $this->assertTrue($model->is_enabled);
    }
}

