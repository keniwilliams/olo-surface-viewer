<?php

namespace App\Services\SurfaceTree;

use App\Services\SurfaceTree\Concerns\BuildsSurfaceTreeNodes;

/**
 * Traverser for the dreamstate and camera_lens domain roots. Dreamstate has
 * no folder hierarchy: its root lists impressions directly. Camera Lens
 * splits into two folders, because it has two independent evidence sources
 * (OCL-19): the camera_lens_scene_payloads table (scene payloads it
 * publishes to Impressions) and Loki-backed runtime telemetry (the
 * olo.camera_lens.runtime.event journey evidence olo-nats-tap captures).
 */
class DomainImpressionsTraverser implements SurfaceTreeDomainTraverser
{
    use BuildsSurfaceTreeNodes;

    public const DOMAINS = ['dreamstate', 'camera_lens'];

    private const CAMERA_LENS_SCENES_FOLDER = 'folder:camera_lens:scenes';

    private const CAMERA_LENS_TELEMETRY_FOLDER = 'folder:camera_lens:telemetry';

    public function __construct(
        private readonly DomainImpressionsFeed $feed,
        private readonly CameraLensTelemetryFeed $telemetryFeed,
    ) {}

    /**
     * @return list<SurfaceTreeNode>
     */
    public function children(string $nodeKey, int $fromDepth, int $depthWindow): array
    {
        if ($nodeKey === 'domain:camera_lens') {
            return $this->cameraLensFolders($fromDepth, $depthWindow);
        }

        if ($nodeKey === self::CAMERA_LENS_SCENES_FOLDER) {
            return $this->impressionChildren('camera_lens', $fromDepth, $depthWindow);
        }

        if ($nodeKey === self::CAMERA_LENS_TELEMETRY_FOLDER) {
            return $this->telemetryChildren($fromDepth, $depthWindow);
        }

        $domain = $this->domainFromNodeKey($nodeKey);

        if ($domain === null) {
            return [];
        }

        return $this->impressionChildren($domain, $fromDepth, $depthWindow);
    }

    /**
     * @return list<SurfaceTreeNode>
     */
    private function cameraLensFolders(int $fromDepth, int $depthWindow): array
    {
        $childDepth = $fromDepth + 1;
        $isTerminalDepth = $this->reachesTerminalDepth($childDepth, $fromDepth, $depthWindow);

        return [
            $this->folderNode(
                key: self::CAMERA_LENS_SCENES_FOLDER,
                label: 'Scene Payloads',
                domain: 'camera_lens',
                depth: $childDepth,
                hasChildren: true,
                isTerminalDepth: $isTerminalDepth,
            ),
            $this->folderNode(
                key: self::CAMERA_LENS_TELEMETRY_FOLDER,
                label: 'Telemetry',
                domain: 'camera_lens',
                depth: $childDepth,
                hasChildren: true,
                isTerminalDepth: $isTerminalDepth,
            ),
        ];
    }

    /**
     * @return list<SurfaceTreeNode>
     */
    private function impressionChildren(string $domain, int $fromDepth, int $depthWindow): array
    {
        $childDepth = $fromDepth + 1;
        $impressions = [];

        foreach ($this->feed->latestRowsForDomain($domain) as $row) {
            $impressionId = $this->value($row, ['impression_id', 'uuid', 'id', 'housed_source_id']);

            if ($impressionId === null) {
                continue;
            }

            $impression = $this->impressionNode(
                impressionId: $impressionId,
                label: $this->value($row, ['label', 'title', 'name'])
                    ?? $this->titleFromIdentifier(
                        $this->value($row, ['source_ref', 'source_path', 'schema']),
                        ucfirst(str_replace('_', ' ', $domain)).' impression',
                    ),
                domain: $domain,
                depth: $childDepth,
                isTerminalDepth: $this->reachesTerminalDepth($childDepth, $fromDepth, $depthWindow),
                meta: [
                    'kind' => $this->value($row, ['kind', 'memory_kind', 'source_kind']),
                    'status' => $this->value($row, ['status', 'process_status']),
                    'observed_at' => $this->value($row, ['observed_at', 'sensemade_at', 'created_at']),
                    'source_ref' => $this->value($row, ['source_ref']),
                    'source_path' => $this->value($row, ['source_path']),
                    'schema' => $this->value($row, ['schema']),
                ],
            );

            $impressions[$impression->key] = $impression;
        }

        return array_values($impressions);
    }

    /**
     * @return list<SurfaceTreeNode>
     */
    private function telemetryChildren(int $fromDepth, int $depthWindow): array
    {
        $childDepth = $fromDepth + 1;
        $records = [];

        foreach ($this->telemetryFeed->latestEvents() as $event) {
            $timestampNs = $this->value($event, ['timestamp_ns']);

            if ($timestampNs === null) {
                continue;
            }

            $key = 'record:camera_lens_telemetry:'.$this->encodeKeyPart($timestampNs);

            $records[$key] = $this->recordNode(
                key: $key,
                label: $this->value($event, ['event']) ?? 'Camera Lens telemetry event',
                domain: 'camera_lens',
                depth: $childDepth,
                relation: 'runtime_event',
                meta: [
                    'timestamp' => $this->value($event, ['timestamp']),
                    'final_status' => $this->value($event, ['final_status']),
                    'error' => $this->value($event, ['error']),
                    'decision' => $this->value($event, ['decision']),
                    'reason' => $this->value($event, ['reason']),
                    'skip_reason' => $this->value($event, ['skip_reason']),
                    'trigger_source' => $this->value($event, ['trigger_source']),
                    'trigger_type' => $this->value($event, ['trigger_type']),
                    'publish_subject' => $this->value($event, ['publish_subject']),
                    'correlation_id' => $this->value($event, ['correlation_id']),
                ],
            );
        }

        return array_values($records);
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
