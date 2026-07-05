<?php

namespace App\Services\Bloodstream;

use App\Events\Bloodstream\BloodstreamObserverChanged;
use App\Models\Bloodstream\ContractMemory;
use App\Models\Bloodstream\SubjectMemory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Throwable;

class BloodstreamObserverPanelState
{
    private const CACHE_KEY = 'bloodstream.observer.panel_state';

    public function recordChangedPing(BloodstreamObserverChanged $event): array
    {
        $state = $this->snapshot();
        $state['latest_ping'] = [
            'subject' => $event->subject,
            'received_at' => $event->receivedAt->toJSON(),
            'metadata' => [
                'owner' => $event->owner,
                'event' => $event->event,
                'publisher' => $event->publisher,
                'published_at' => $event->publishedAt?->toJSON(),
                'emitted_at' => $event->emittedAt?->toJSON(),
            ],
        ];
        $state['is_dirty'] = true;
        $state['status'] = 'dirty';

        $this->store($state);

        return $this->refresh();
    }

    public function refresh(): array
    {
        $state = $this->snapshot();
        $state['last_refresh_attempt_at'] = CarbonImmutable::now()->toJSON();

        if (! $this->isBloodstreamConfigured()) {
            $state['status'] = 'disabled';
            $state['is_dirty'] = false;
            $state['error'] = 'Bloodstream database connection is not configured.';
            $this->store($state);

            return $state;
        }

        try {
            $state['summary'] = $this->readSummary();
            $state['last_successful_read_at'] = CarbonImmutable::now()->toJSON();
            $state['status'] = 'fresh';
            $state['is_dirty'] = false;
            $state['error'] = null;
        } catch (Throwable $exception) {
            $state['status'] = filled($state['last_successful_read_at'] ?? null) ? 'stale' : 'error';
            $state['error'] = $exception->getMessage();
        }

        $this->store($state);

        return $state;
    }

    public function snapshot(): array
    {
        $state = Cache::get(self::CACHE_KEY, []);

        return array_replace_recursive($this->emptyState(), is_array($state) ? $state : []);
    }

    public function reset(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function emptyState(): array
    {
        return [
            'status' => 'unknown',
            'is_dirty' => false,
            'latest_ping' => null,
            'last_refresh_attempt_at' => null,
            'last_successful_read_at' => null,
            'summary' => null,
            'error' => null,
        ];
    }

    private function readSummary(): array
    {
        $latestSubject = SubjectMemory::query()
            ->orderByDesc('last_seen_at')
            ->orderBy('subject')
            ->first([
                'subject',
                'organ',
                'role',
                'contract_key',
                'contract_version',
                'status',
                'source',
                'last_seen_at',
                'seen_count',
                'updated_at',
            ]);

        $latestContract = ContractMemory::query()
            ->orderByDesc('updated_at')
            ->orderBy('contract_key')
            ->first([
                'contract_key',
                'organ',
                'role',
                'version',
                'status',
                'source',
                'updated_at',
            ]);

        return [
            'contracts_total' => ContractMemory::query()->count(),
            'subjects_total' => SubjectMemory::query()->count(),
            'contracts_by_status' => $this->statusCounts(ContractMemory::class),
            'subjects_by_status' => $this->statusCounts(SubjectMemory::class),
            'latest_contract' => $latestContract ? [
                'contract_key' => $latestContract->getAttribute('contract_key'),
                'organ' => $latestContract->getAttribute('organ'),
                'role' => $latestContract->getAttribute('role'),
                'version' => $latestContract->getAttribute('version'),
                'status' => $latestContract->getAttribute('status'),
                'source' => $latestContract->getAttribute('source'),
                'updated_at' => $this->timestamp($latestContract->getAttribute('updated_at')),
            ] : null,
            'latest_subject' => $latestSubject ? [
                'subject' => $latestSubject->getAttribute('subject'),
                'organ' => $latestSubject->getAttribute('organ'),
                'role' => $latestSubject->getAttribute('role'),
                'contract_key' => $latestSubject->getAttribute('contract_key'),
                'contract_version' => $latestSubject->getAttribute('contract_version'),
                'status' => $latestSubject->getAttribute('status'),
                'source' => $latestSubject->getAttribute('source'),
                'last_seen_at' => $this->timestamp($latestSubject->getAttribute('last_seen_at')),
                'seen_count' => (int) $latestSubject->getAttribute('seen_count'),
                'updated_at' => $this->timestamp($latestSubject->getAttribute('updated_at')),
            ] : null,
        ];
    }

    /**
     * @param  class-string<ContractMemory|SubjectMemory>  $model
     */
    private function statusCounts(string $model): array
    {
        return $model::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                filled($row->getAttribute('status')) ? $row->getAttribute('status') : 'unknown' => (int) $row->getAttribute('aggregate'),
            ])
            ->all();
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toJSON();
        }

        return filled($value) ? (string) $value : null;
    }

    private function isBloodstreamConfigured(): bool
    {
        $config = config('database.connections.bloodstream');

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

    private function store(array $state): void
    {
        Cache::forever(self::CACHE_KEY, $state);
    }
}
