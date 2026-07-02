<?php

namespace App\Services\Bloodstream;

use App\Models\Bloodstream\ContractMemory;
use App\Models\Bloodstream\SubjectMemory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class BloodstreamMemoryQuery
{
    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 100;

    public function contracts(array $filters = []): array
    {
        $query = ContractMemory::query();

        $this->applyFilters($query, $filters, [
            'contract_key' => 'contract_key',
            'organ' => 'organ',
            'role' => 'role',
            'source' => 'source',
            'status' => 'status',
        ]);

        $models = $query
            ->orderByDesc('updated_at')
            ->orderBy('contract_key')
            ->limit($this->limit($filters['limit'] ?? null))
            ->get()
            ->all();

        return array_map(
            fn (ContractMemory $memory): array => [
                'id' => (int) $memory->getKey(),
                'contract_key' => $memory->getAttribute('contract_key'),
                'created_at' => $this->timestamp($memory->getAttribute('created_at')),
                'metadata' => $memory->getAttribute('metadata_json') ?? [],
                'organ' => $memory->getAttribute('organ'),
                'role' => $memory->getAttribute('role'),
                'schema' => $memory->getAttribute('schema_json') ?? [],
                'source' => $memory->getAttribute('source'),
                'status' => $memory->getAttribute('status'),
                'updated_at' => $this->timestamp($memory->getAttribute('updated_at')),
                'version' => $memory->getAttribute('version'),
            ],
            $models,
        );
    }

    public function subjects(array $filters = []): array
    {
        $query = SubjectMemory::query();

        $this->applyFilters($query, $filters, [
            'contract_key' => 'contract_key',
            'organ' => 'organ',
            'role' => 'role',
            'source' => 'source',
            'status' => 'status',
            'subject' => 'subject',
        ]);

        $models = $query
            ->orderByDesc('last_seen_at')
            ->orderBy('subject')
            ->limit($this->limit($filters['limit'] ?? null))
            ->get()
            ->all();

        return array_map(
            fn (SubjectMemory $memory): array => [
                'id' => (int) $memory->getKey(),
                'contract_key' => $memory->getAttribute('contract_key'),
                'contract_version' => $memory->getAttribute('contract_version'),
                'created_at' => $this->timestamp($memory->getAttribute('created_at')),
                'first_seen_at' => $this->timestamp($memory->getAttribute('first_seen_at')),
                'last_seen_at' => $this->timestamp($memory->getAttribute('last_seen_at')),
                'metadata' => $memory->getAttribute('metadata_json') ?? [],
                'organ' => $memory->getAttribute('organ'),
                'role' => $memory->getAttribute('role'),
                'seen_count' => (int) $memory->getAttribute('seen_count'),
                'source' => $memory->getAttribute('source'),
                'status' => $memory->getAttribute('status'),
                'subject' => $memory->getAttribute('subject'),
                'updated_at' => $this->timestamp($memory->getAttribute('updated_at')),
            ],
            $models,
        );
    }

    private function applyFilters(Builder $query, array $filters, array $columns): void
    {
        foreach ($columns as $filter => $column) {
            if (filled($filters[$filter] ?? null)) {
                $query->where($column, $filters[$filter]);
            }
        }
    }

    private function limit(mixed $limit): int
    {
        if (! is_numeric($limit)) {
            return self::DEFAULT_LIMIT;
        }

        return min(max((int) $limit, 1), self::MAX_LIMIT);
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toJSON();
        }

        return filled($value) ? (string) $value : null;
    }
}
