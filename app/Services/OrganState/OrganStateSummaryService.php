<?php

namespace App\Services\OrganState;

use App\Services\OrganState\ActivitySources\BloodstreamActivitySource;
use App\Services\OrganState\ActivitySources\ImpressionsActivitySource;
use App\Services\OrganState\ActivitySources\OrganActivitySource;
use App\Services\OrganState\ActivitySources\SidecarActivitySource;
use App\Services\OrganState\ActivitySources\SubconsciousActivitySource;
use App\Services\OrganState\ActivitySources\SurfaceViewerActivitySource;
use App\Support\ObservedDatabaseConnections;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Orchestrator only: iterates the explicit organ activity providers and
 * converts their snapshots into summaries. All database access lives in the
 * providers, which read through explicit read-only Eloquent models.
 */
class OrganStateSummaryService
{
    private const FRESH_FOR_MINUTES = 15;

    private const LABELS = [
        'bloodstream' => 'Bloodstream',
        'subconscious' => 'Subconscious / Dreamstate',
        'impressions' => 'Impressions',
        'sidecar' => 'Sidecar',
        'surface_viewer' => 'Surface Viewer',
    ];

    /**
     * @var array<string, OrganActivitySource>
     */
    private readonly array $sources;

    public function __construct(
        SurfaceViewerActivitySource $surfaceViewer,
        BloodstreamActivitySource $bloodstream,
        SubconsciousActivitySource $subconscious,
        ImpressionsActivitySource $impressions,
        SidecarActivitySource $sidecar,
    ) {
        $this->sources = collect([$surfaceViewer, $bloodstream, $subconscious, $impressions, $sidecar])
            ->keyBy(fn (OrganActivitySource $source): string => $source->connectionKey())
            ->all();
    }

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
        $source = $this->sources[$connection] ?? null;

        if ($source === null || ! $this->isConfigured($connection)) {
            return new OrganStateSummary(
                key: $connection,
                label: $label,
                readStatus: 'disabled',
                lastSuccessfulReadAt: null,
                lastObservedActivityAt: null,
                stalenessState: 'unknown',
                latestMessage: $source === null
                    ? 'no activity source is registered for this connection'
                    : 'database connection is not configured',
                latestError: null,
                source: $connection,
            );
        }

        try {
            $activity = $source->latestActivity();

            $readAt = CarbonImmutable::now();
            $observedAt = $activity->observedAt;
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
                source: $activity->source,
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
