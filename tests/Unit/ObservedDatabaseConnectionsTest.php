<?php

namespace Tests\Unit;

use App\Support\ObservedDatabaseConnections;
use Tests\TestCase;

class ObservedDatabaseConnectionsTest extends TestCase
{
    public function test_it_defines_all_observed_database_connection_keys(): void
    {
        $connections = new ObservedDatabaseConnections();

        $this->assertSame(
            [
                'surface_viewer',
                'bloodstream',
                'subconscious',
                'impressions',
                'sidecar',
            ],
            array_keys($connections->all()),
        );
    }

    public function test_it_marks_a_connection_enabled_when_required_config_is_present(): void
    {
        config([
            'database.connections.impressions.host' => 'db.example.test',
            'database.connections.impressions.port' => '5432',
            'database.connections.impressions.database' => 'example_database',
            'database.connections.impressions.username' => 'example_reader',
        ]);

        $definition = (new ObservedDatabaseConnections())
            ->definition('impressions');

        $this->assertSame('Impressions', $definition['name']);
        $this->assertSame('impressions', $definition['connection_key']);
        $this->assertSame('db.example.test', $definition['host']);
        $this->assertSame('5432', $definition['port']);
        $this->assertSame('example_database', $definition['database']);
        $this->assertSame('example_reader', $definition['username']);
        $this->assertTrue($definition['is_enabled']);
    }

    public function test_it_marks_a_connection_disabled_when_required_config_is_missing(): void
    {
        config([
            'database.connections.sidecar.host' => null,
            'database.connections.sidecar.port' => null,
            'database.connections.sidecar.database' => null,
            'database.connections.sidecar.username' => null,
        ]);

        $definition = (new ObservedDatabaseConnections())
            ->definition('sidecar');

        $this->assertSame('Sidecar', $definition['name']);
        $this->assertSame('sidecar', $definition['connection_key']);
        $this->assertFalse($definition['is_enabled']);
    }

    public function test_it_does_not_expose_passwords_in_registry_definitions(): void
    {
        config([
            'database.connections.bloodstream.host' => 'db.example.test',
            'database.connections.bloodstream.port' => '5432',
            'database.connections.bloodstream.database' => 'example_database',
            'database.connections.bloodstream.username' => 'example_reader',
            'database.connections.bloodstream.password' => 'secret-value',
        ]);

        $definition = (new ObservedDatabaseConnections())
            ->definition('bloodstream');

        $this->assertArrayNotHasKey('password', $definition);
    }
}
