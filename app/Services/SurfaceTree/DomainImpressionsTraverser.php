<?php

namespace App\Services\SurfaceTree;

use App\Services\SurfaceTree\Concerns\BuildsSurfaceTreeNodes;

/**
 * Flat traverser for domains whose impressions have no folder hierarchy:
 * the domain root lists its impressions directly, newest first.
 */
class DomainImpressionsTraverser implements SurfaceTreeDomainTraverser
{
    use BuildsSurfaceTreeNodes;

    public const DOMAINS = ['dreamstate', 'camera_lens'];

    public function __construct(
        private readonly DomainImpressionsFeed $feed,
    ) {}

    /**
     * @return list<SurfaceTreeNode>
     */
    public function children(string $nodeKey, int $fromDepth, int $depthWindow): array
    {
        $domain = $this->domainFromNodeKey($nodeKey);

        if ($domain === null) {
            return [];
        }

        $childDepth = $fromDepth + 1;
        $impressions = [];

        foreach ($this->feed->latestRowsForDomain($domain) as $row) {
            $impressionId = $this->value($row, ['impression_id', 'uuid', 'id']);

            if ($impressionId === null) {
                continue;
            }

            $impression = $this->impressionNode(
                impressionId: $impressionId,
                label: $this->value($row, ['label', 'title', 'name'])
                    ?? $this->titleFromIdentifier(
                        $this->value($row, ['source_ref', 'source_path']),
                        ucfirst(str_replace('_', ' ', $domain)).' impression',
                    ),
                domain: $domain,
                depth: $childDepth,
                isTerminalDepth: $this->reachesTerminalDepth($childDepth, $fromDepth, $depthWindow),
                meta: [
                    'kind' => $this->value($row, ['kind', 'memory_kind']),
                    'status' => $this->value($row, ['status', 'process_status']),
                    'observed_at' => $this->value($row, ['observed_at', 'sensemade_at', 'created_at']),
                    'source_ref' => $this->value($row, ['source_ref']),
                    'source_path' => $this->value($row, ['source_path']),
                ],
            );

            $impressions[$impression->key] = $impression;
        }

        return array_values($impressions);
    }

    private function domainFromNodeKey(string $nodeKey): ?string
    {
        if (! str_starts_with($nodeKey, 'domain:')) {
            return null;
        }

        $domain = substr($nodeKey, strlen('domain:'));

        return in_array($domain, self::DOMAINS, true) ? $domain : null;
    }
}
