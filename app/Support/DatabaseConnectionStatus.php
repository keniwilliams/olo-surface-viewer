<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseConnectionStatus
{
    public static function all(): array
    {
        $connections = [
            'surface_viewer' => 'Surface Viewer',
            'bloodstream' => 'Bloodstream',
            'subconscious' => 'Subconscious / Dreamstate',
            'impressions' => 'Impressions',
            'sidecar' => 'Sidecar',
        ];

        return collect($connections)
            ->map(fn (string $label, string $connection) => self::check($connection, $label))
            ->values()
            ->all();
    }

    public static function check(string $connection, string $label): array
    {
        $config = config("database.connections.$connection", []);

        try {
            $result = DB::connection($connection)
                ->selectOne('select current_database() as database, current_user as username');

            $tableCount = DB::connection($connection)
                ->selectOne("
                    select count(*) as count
                    from information_schema.tables
                    where table_schema not in ('pg_catalog', 'information_schema')
                ");

            return [
                'connection' => $connection,
                'label' => $label,
                'status' => 'online',
                'host' => $config['host'] ?? null,
                'port' => $config['port'] ?? null,
                'database' => $result->database ?? ($config['database'] ?? null),
                'username' => $result->username ?? ($config['username'] ?? null),
                'table_count' => (int) ($tableCount->count ?? 0),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'connection' => $connection,
                'label' => $label,
                'status' => 'offline',
                'host' => $config['host'] ?? null,
                'port' => $config['port'] ?? null,
                'database' => $config['database'] ?? null,
                'username' => $config['username'] ?? null,
                'table_count' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }
}
