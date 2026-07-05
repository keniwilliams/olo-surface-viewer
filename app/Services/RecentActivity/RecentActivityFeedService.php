<?php

namespace App\Services\RecentActivity;

use App\Services\Bloodstream\BloodstreamObserverPanelState;
use App\Services\OrganState\OrganStateSummaryService;
use Carbon\CarbonImmutable;
use Throwable;

class RecentActivityFeedService
{
    private const BLOODSTREAM_LABEL = 'Bloodstream';

    public function __construct(
        private readonly BloodstreamObserverPanelState $bloodstreamObserver,
        private readonly OrganStateSummaryService $organState,
    ) {}

    public function all(?int $limit = null): array
    {
        $items = [
            ...$this->bloodstreamObserverItems(),
            ...$this->organSummaryItems(),
        ];

        usort($items, function (RecentActivityItem $a, RecentActivityItem $b): int {
            $aTimestamp = $this->sortTimestamp($a->activityTimestamp);
            $bTimestamp = $this->sortTimestamp($b->activityTimestamp);

            if ($aTimestamp === $bTimestamp) {
                return strcmp($a->activityType, $b->activityType);
            }

            return $bTimestamp <=> $aTimestamp;
        });

        if ($limit !== null) {
            $items = array_slice($items, 0, max($limit, 0));
        }

        return array_map(
            fn (RecentActivityItem $item): array => $item->toArray(),
            $items,
        );
    }

    /**
     * @return array<int, RecentActivityItem>
     */
    private function bloodstreamObserverItems(): array
    {
        $state = $this->bloodstreamObserver->snapshot();
        $items = [];
        $ping = $state['latest_ping'] ?? null;

        if (is_array($ping) && filled($ping['received_at'] ?? null)) {
            $items[] = new RecentActivityItem(
                sourceOrganKey: 'bloodstream',
                sourceOrganLabel: self::BLOODSTREAM_LABEL,
                activityType: 'bloodstream_observer_changed_ping',
                activityTimestamp: (string) $ping['received_at'],
                status: 'received',
                message: sprintf(
                    'Bloodstream Observer changed ping received%s.',
                    filled($ping['subject'] ?? null) ? " on {$ping['subject']}" : '',
                ),
                error: null,
                sourceReference: filled($ping['subject'] ?? null)
                    ? (string) $ping['subject']
                    : 'bloodstream_observer.latest_ping',
            );
        }

        if (filled($state['last_refresh_attempt_at'] ?? null)) {
            $status = (string) ($state['status'] ?? 'unknown');
            $error = filled($state['error'] ?? null) ? (string) $state['error'] : null;

            $items[] = new RecentActivityItem(
                sourceOrganKey: 'bloodstream',
                sourceOrganLabel: self::BLOODSTREAM_LABEL,
                activityType: 'bloodstream_observer_refresh',
                activityTimestamp: (string) $state['last_refresh_attempt_at'],
                status: $status,
                message: $this->bloodstreamRefreshMessage($status, $error),
                error: $error,
                sourceReference: 'bloodstream_observer.refresh',
            );
        }

        return $items;
    }

    /**
     * @return array<int, RecentActivityItem>
     */
    private function organSummaryItems(): array
    {
        $items = [];

        foreach ($this->organState->all() as $summary) {
            if ($summary['read_status'] === 'readable') {
                $items[] = new RecentActivityItem(
                    sourceOrganKey: $summary['key'],
                    sourceOrganLabel: $summary['label'],
                    activityType: 'organ_read_succeeded',
                    activityTimestamp: $summary['last_successful_read_at'],
                    status: 'readable',
                    message: $summary['latest_message'],
                    error: null,
                    sourceReference: $summary['source'],
                );
            } elseif ($summary['read_status'] === 'error') {
                $items[] = new RecentActivityItem(
                    sourceOrganKey: $summary['key'],
                    sourceOrganLabel: $summary['label'],
                    activityType: 'organ_read_failed',
                    activityTimestamp: null,
                    status: 'error',
                    message: $summary['latest_message'],
                    error: $summary['latest_error'],
                    sourceReference: $summary['source'],
                );
            } elseif ($summary['read_status'] === 'disabled') {
                $items[] = new RecentActivityItem(
                    sourceOrganKey: $summary['key'],
                    sourceOrganLabel: $summary['label'],
                    activityType: 'organ_read_disabled',
                    activityTimestamp: null,
                    status: 'disabled',
                    message: $summary['latest_message'],
                    error: null,
                    sourceReference: $summary['source'],
                );
            }

            if (filled($summary['last_observed_activity_at'] ?? null)) {
                $items[] = new RecentActivityItem(
                    sourceOrganKey: $summary['key'],
                    sourceOrganLabel: $summary['label'],
                    activityType: 'organ_observed_activity',
                    activityTimestamp: $summary['last_observed_activity_at'],
                    status: $summary['staleness_state'],
                    message: "latest observed activity from {$summary['label']}",
                    error: null,
                    sourceReference: $summary['source'],
                );
            }
        }

        return $items;
    }

    private function bloodstreamRefreshMessage(string $status, ?string $error): string
    {
        if ($error !== null) {
            return 'Bloodstream Observer refresh failed.';
        }

        return match ($status) {
            'fresh' => 'Bloodstream Observer refresh succeeded.',
            'disabled' => 'Bloodstream Observer refresh is disabled.',
            'stale' => 'Bloodstream Observer refresh is stale.',
            default => 'Bloodstream Observer refresh attempted.',
        };
    }

    private function sortTimestamp(?string $timestamp): int
    {
        if (! filled($timestamp)) {
            return PHP_INT_MIN;
        }

        try {
            return CarbonImmutable::parse($timestamp)->getTimestamp();
        } catch (Throwable) {
            return PHP_INT_MIN;
        }
    }
}
