<?php

namespace App\Services\OrganState;

use App\Support\ObservedDatabaseConnections;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrganStateSummaryService
{
    private const FRESH_FOR_MINUTES = 15;

    private const TIMESTAMP_COLUMN_PREFERENCE = [
        'last_observed_activity_at',
        'observed_at',
        'last_seen_at',
        'sensemade_at',
        'processed_at',
        'completed_at',
        'updated_at',
        'created_at',
        'published_at',
        'emitted_at',
        'received_at',
        'sent_at',
        'inserted_at',
    ];

    private const LABELS = [
        'bloodstream' => 'Bloodstream',
        'subconscious' => 'Subconscious / Dreamstate',
        'impressions' => 'Impressions',
        'sidecar' => 'Sidecar',
        'surface_viewer' => 'Surface Viewer',
    ];

    public function all(): array
    {
        return array_map(
            fn (OrganStateSummary $summary): array => $summary->toArray(),
            $this->summaries(),
        );
    }

    /**
     * @return array<int, OrganStateSummary>
     */
    public function summaries(): array
    {
        return collect(ObservedDatabaseConnections::CONNECTION_KEYS)
            ->map(fn (string $connection): OrganStateSummary => $this->forConnection($connection))
            ->values()
            ->all();
    }

    public function forConnection(string $connection): OrganStateSummary
    {
        $label = self::LABELS[$connection] ?? str($connection)->replace('_', ' ')->title()->toString();

        if (! $this->isConfigured($connection)) {
            return new OrganStateSummary(
                key: $connection,
                label: $label,
                readStatus: 'disabled',
                lastSuccessfulReadAt: null,
                lastObservedActivityAt: null,
                stalenessState: 'unknown',
                latestMessage: 'database connection is not configured',
                latestError: null,
                source: $connection,
            );
        }

        try {
            $activity = $connection === 'bloodstream'
                ? $this->bloodstreamObserverActivity($connection)
                : $this->latestTimestampedActivity($connection);

            $readAt = CarbonImmutable::now();
            $observedAt = $activity['observed_at'];
            $staleness = $this->stalenessState($observedAt, $readAt);

            return new OrganStateSummary(
                key: $connection,
                label: $label,
                readStatus: 'readable',
                lastSuccessfulReadAt: $readAt->toJSON(),
                lastObservedActivityAt: $observedAt?->toJSON(),
                stalenessState: $staleness,
                latestMessage: $this->readMessage($connection, $observedAt, $staleness),
                latestError: null,
                source: $activity['source'],
            );
        } catch (Throwable $exception) {
            return new OrganStateSummary(
                key: $connection,
                label: $label,
                readStatus: 'error',
                lastSuccessfulReadAt: null,
                lastObservedActivityAt: null,
                stalenessState: 'error',
                latestMessage: 'database read failed',
                latestError: $exception->getMessage(),
                source: $connection,
            );
        }
    }

    private function bloodstreamObserverActivity(string $connection): array
    {
        $candidates = [
            ['table' => 'subject_memory', 'column' => 'last_seen_at'],
            ['table' => 'subject_memory', 'column' => 'updated_at'],
            ['table' => 'contract_memory', 'column' => 'updated_at'],
        ];

        return $this->latestFromCandidates($connection, $candidates, 'bloodstream:observer_memory');
    }

    private function latestTimestampedActivity(string $connection): array
    {
        $tables = $this->tables($connection);
        $candidates = [];

        foreach ($tables as $table) {
            foreach ($this->timestampColumns($connection, $table['schema'], $table['name']) as $column) {
                $candidates[] = [
                    'schema' => $table['schema'],
                    'table' => $table['name'],
                    'column' => $column,
                ];
            }
        }

        return $this->latestFromCandidates($connection, $candidates, $connection);
    }

    private function latestFromCandidates(string $connection, array $candidates, string $emptySource): array
    {
        $latest = null;
        $source = $emptySource;

        foreach ($candidates as $candidate) {
            $value = DB::connection($connection)
                ->table($this->qualifiedTable($connection, $candidate['table'], $candidate['schema'] ?? null))
                ->max($candidate['column']);
            $timestamp = $this->timestamp($value);

            if ($timestamp === null || ($latest !== null && $timestamp->lessThanOrEqualTo($latest))) {
                continue;
            }

            $latest = $timestamp;
            $source = sprintf(
                '%s:%s.%s',
                $connection,
                $candidate['table'],
                $candidate['column'],
            );
        }

        return [
            'observed_at' => $latest,
            'source' => $source,
        ];
    }

    private function tables(string $connection): array
    {
        $db = DB::connection($connection);

        return match ($db->getDriverName()) {
            'sqlite' => $this->sqliteTables($db),
            default => $this->informationSchemaTables($connection),
        };
    }

    private function sqliteTables(ConnectionInterface $db): array
    {
        $rows = $db->select(
            <<<'SQL'
            select name
            from sqlite_master
            where type in ('table', 'view')
              and name not like 'sqlite_%'
            order by name
            SQL
        );

        return array_map(
            fn (object $row): array => ['schema' => null, 'name' => (string) $row->name],
            $rows,
        );
    }

    private function informationSchemaTables(string $connection): array
    {
        $rows = DB::connection($connection)->select(
            <<<'SQL'
            select table_schema, table_name
            from information_schema.tables
            where table_schema not in ('pg_catalog', 'information_schema')
            order by table_schema, table_name
            SQL
        );

        return array_map(
            fn (object $row): array => [
                'schema' => (string) $row->table_schema,
                'name' => (string) $row->table_name,
            ],
            $rows,
        );
    }

    private function timestampColumns(string $connection, ?string $schema, string $table): array
    {
        $db = DB::connection($connection);

        $columns = match ($db->getDriverName()) {
            'sqlite' => $this->sqliteTimestampColumns($db, $table),
            default => $this->informationSchemaTimestampColumns($connection, $schema, $table),
        };

        $preferred = collect(self::TIMESTAMP_COLUMN_PREFERENCE)
            ->filter(fn (string $candidate): bool => in_array($candidate, $columns, true))
            ->values()
            ->all();

        $remaining = collect($columns)
            ->reject(fn (string $column): bool => in_array($column, $preferred, true))
            ->sort()
            ->values()
            ->all();

        return [
            ...$preferred,
            ...$remaining,
        ];
    }

    private function sqliteTimestampColumns(ConnectionInterface $db, string $table): array
    {
        $quoted = str_replace("'", "''", $table);
        $rows = $db->select("pragma table_info('{$quoted}')");

        return collect($rows)
            ->filter(function (object $row): bool {
                $type = strtolower((string) ($row->type ?? ''));

                return str_contains($type, 'date')
                    || str_contains($type, 'time')
                    || in_array((string) $row->name, self::TIMESTAMP_COLUMN_PREFERENCE, true);
            })
            ->map(fn (object $row): string => (string) $row->name)
            ->values()
            ->all();
    }

    private function informationSchemaTimestampColumns(string $connection, ?string $schema, string $table): array
    {
        $rows = DB::connection($connection)->select(
            <<<'SQL'
            select column_name
            from information_schema.columns
            where table_schema = ?
              and table_name = ?
              and (
                    data_type like '%timestamp%'
                 or data_type like '%date%'
                 or column_name in (
                    'last_observed_activity_at',
                    'observed_at',
                    'last_seen_at',
                    'sensemade_at',
                    'processed_at',
                    'completed_at',
                    'updated_at',
                    'created_at',
                    'published_at',
                    'emitted_at',
                    'received_at',
                    'sent_at',
                    'inserted_at'
                 )
              )
            SQL,
            [$schema, $table],
        );

        return array_map(
            fn (object $row): string => (string) $row->column_name,
            $rows,
        );
    }

    private function qualifiedTable(string $connection, string $table, ?string $schema): string
    {
        if (DB::connection($connection)->getDriverName() === 'sqlite' || blank($schema)) {
            return $table;
        }

        return "{$schema}.{$table}";
    }

    private function stalenessState(?CarbonImmutable $observedAt, CarbonImmutable $readAt): string
    {
        if ($observedAt === null) {
            return 'unknown';
        }

        return $observedAt->greaterThanOrEqualTo($readAt->subMinutes(self::FRESH_FOR_MINUTES))
            ? 'fresh'
            : 'stale';
    }

    private function readMessage(string $connection, ?CarbonImmutable $observedAt, string $staleness): string
    {
        if ($observedAt === null) {
            return 'read succeeded; no timestamped activity was found';
        }

        if ($staleness === 'stale') {
            return "no new activity since {$observedAt->toJSON()}";
        }

        if ($connection === 'bloodstream') {
            return 'observer memory read succeeded';
        }

        return 'organ database read succeeded';
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value->toDateTimeImmutable());
        }

        if (! filled($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function isConfigured(string $connection): bool
    {
        $config = config("database.connections.{$connection}");

        if (! is_array($config) || blank($config)) {
            return false;
        }

        if (filled($config['url'] ?? null)) {
            return true;
        }

        if (($config['driver'] ?? null) === 'sqlite') {
            return filled($config['database'] ?? null);
        }

        return filled($config['host'] ?? null)
            && filled($config['database'] ?? null)
            && filled($config['username'] ?? null);
    }
}
