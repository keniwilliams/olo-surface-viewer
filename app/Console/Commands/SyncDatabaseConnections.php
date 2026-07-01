<?php

namespace App\Console\Commands;

use App\Models\DatabaseConnection;
use App\Support\ObservedDatabaseConnections;
use Illuminate\Console\Command;

class SyncDatabaseConnections extends Command
{
    protected $signature = 'olo:database-connections:sync';

    protected $description = 'Sync configured OLO observed database connections into the Surface Viewer registry.';

    public function handle(ObservedDatabaseConnections $observedDatabases): int
    {
        foreach ($observedDatabases->all() as $connectionKey => $definition) {
            DatabaseConnection::query()->updateOrCreate(
                ['connection_key' => $connectionKey],
                $definition,
            );

            $state = $definition['is_enabled'] ? 'enabled' : 'disabled';

            $this->line("Synced [{$connectionKey}] as {$state}.");
        }

        return self::SUCCESS;
    }
}
