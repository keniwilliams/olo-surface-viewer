<?php

namespace Tests\Feature;

use App\Models\DatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncDatabaseConnectionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_incomplete_connections_as_disabled_registry_rows(): void
    {
        config([
            'database.connections.impressions.host' => null,
            'database.connections.impressions.port' => '5432',
            'database.connections.impressions.database' => 'example_database',
            'database.connections.impressions.username' => 'example_reader',
        ]);

        $this->artisan('olo:database-connections:sync')
            ->assertSuccessful();

        $connection = DatabaseConnection::query()
            ->where('connection_key', 'impressions')
            ->firstOrFail();

        $this->assertSame('Impressions', $connection->name);
        $this->assertNull($connection->host);
        $this->assertSame(5432, $connection->port);
        $this->assertSame('example_database', $connection->database);
        $this->assertSame('example_reader', $connection->username);
        $this->assertFalse($connection->is_enabled);
    }

    public function test_it_does_not_store_passwords_when_syncing_registry_rows(): void
    {
        config([
            'database.connections.sidecar.host' => 'db.example.test',
            'database.connections.sidecar.port' => '5432',
            'database.connections.sidecar.database' => 'example_database',
            'database.connections.sidecar.username' => 'example_reader',
            'database.connections.sidecar.password' => 'secret-value',
        ]);

        $this->artisan('olo:database-connections:sync')
            ->assertSuccessful();

        $connection = DatabaseConnection::query()
            ->where('connection_key', 'sidecar')
            ->firstOrFail();

        $this->assertSame('Sidecar', $connection->name);
        $this->assertSame('db.example.test', $connection->host);
        $this->assertSame(5432, $connection->port);
        $this->assertSame('example_database', $connection->database);
        $this->assertSame('example_reader', $connection->username);
        $this->assertTrue($connection->is_enabled);

        $this->assertArrayNotHasKey('password', $connection->getAttributes());
    }
}

