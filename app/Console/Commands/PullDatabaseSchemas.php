<?php

namespace App\Console\Commands;

use App\Models\DatabaseConnection;
use App\Models\DatabaseSchemaSnapshot;
use App\Support\DatabaseSchemaInspector;
use Illuminate\Console\Command;
use Throwable;

class PullDatabaseSchemas extends Command
{
    protected $signature = 'olo:database-schemas:pull {connection? : Optional connection key to inspect}';

    protected $description = 'Pull schema metadata from configured OLO observed database connections.';

    public function handle(DatabaseSchemaInspector $inspector): int
    {
        $requestedConnection = $this->argument('connection');

        $connections = DatabaseConnection::query()
            ->where('is_enabled', true)
            ->when($requestedConnection, fn ($query) => $query->where('connection_key', $requestedConnection))
            ->orderBy('connection_key')
            ->get();

        if ($connections->isEmpty()) {
            $this->warn('No enabled database connections matched.');

            return self::SUCCESS;
        }

        foreach ($connections as $connection) {
            $this->line("Pulling schema for [{$connection->connection_key}]...");

            $snapshot = DatabaseSchemaSnapshot::query()->create([
                'database_connection_id' => $connection->id,
                'connection_key' => $connection->connection_key,
                'status' => 'running',
                'captured_at' => now(),
            ]);

            try {
                $result = $inspector->inspect($connection->connection_key);
                $tableCount = count($result['tables']);
                $columnCount = collect($result['tables'])->sum(fn (array $table): int => count($table['columns']));

                foreach ($result['tables'] as $table) {
                    $snapshot->tables()->create([
                        'schema_name' => $table['schema_name'],
                        'table_name' => $table['table_name'],
                        'table_type' => $table['table_type'] ?? null,
                        'row_count' => $table['row_count'] ?? null,
                        'columns' => $table['columns'],
                        'primary_keys' => $table['primary_keys'],
                        'foreign_keys' => $table['foreign_keys'],
                        'indexes' => $table['indexes'],
                    ]);
                }

                $snapshot->update([
                    'status' => 'completed',
                    'schema_count' => count($result['schemas']),
                    'table_count' => $tableCount,
                    'column_count' => $columnCount,
                ]);

                $this->info("Captured {$tableCount} tables for [{$connection->connection_key}].");
            } catch (Throwable $exception) {
                $snapshot->update([
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                ]);

                $this->error("Failed to pull schema for [{$connection->connection_key}]: {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
