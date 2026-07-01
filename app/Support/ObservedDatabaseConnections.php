<?php

namespace App\Support;

class ObservedDatabaseConnections
{
    public const CONNECTION_KEYS = [
        'surface_viewer',
        'bloodstream',
        'subconscious',
        'impressions',
        'sidecar',
    ];

    public function all(): array
    {
        return collect(self::CONNECTION_KEYS)
            ->mapWithKeys(fn (string $connectionKey): array => [
                $connectionKey => $this->definition($connectionKey),
            ])
            ->all();
    }

    public function definition(string $connectionKey): array
    {
        $connection = config("database.connections.{$connectionKey}", []);

        return [
            'name' => $this->nameFor($connectionKey),
            'connection_key' => $connectionKey,
            'host' => $connection['host'] ?? null,
            'port' => $connection['port'] ?? null,
            'database' => $connection['database'] ?? null,
            'username' => $connection['username'] ?? null,
            'description' => $this->descriptionFor($connectionKey),
            'is_enabled' => $this->isConfigured($connection),
        ];
    }

    private function isConfigured(array $connection): bool
    {
        return filled($connection['host'] ?? null)
            && filled($connection['port'] ?? null)
            && filled($connection['database'] ?? null)
            && filled($connection['username'] ?? null);
    }

    private function nameFor(string $connectionKey): string
    {
        return match ($connectionKey) {
            'surface_viewer' => 'Surface Viewer',
            'bloodstream' => 'Bloodstream',
            'subconscious' => 'Subconscious',
            'impressions' => 'Impressions',
            'sidecar' => 'Sidecar',
            default => str($connectionKey)->replace('_', ' ')->title()->toString(),
        };
    }

    private function descriptionFor(string $connectionKey): string
    {
        return match ($connectionKey) {
            'surface_viewer' => 'Surface Viewer application database.',
            'bloodstream' => 'Observed Bloodstream organ database.',
            'subconscious' => 'Observed Subconscious / Dreamstate database.',
            'impressions' => 'Observed Impressions organ database.',
            'sidecar' => 'Observed Sidecar database.',
            default => 'Observed database connection.',
        };
    }
}
