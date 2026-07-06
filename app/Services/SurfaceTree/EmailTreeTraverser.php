<?php

namespace App\Services\SurfaceTree;

use App\Services\SurfaceTree\Concerns\BuildsSurfaceTreeNodes;

class EmailTreeTraverser implements SurfaceTreeDomainTraverser
{
    use BuildsSurfaceTreeNodes;

    public function __construct(
        private readonly EmailImpressionsFeed $feed,
    ) {}

    /**
     * @return list<SurfaceTreeNode>
     */
    public function children(string $nodeKey, int $fromDepth, int $depthWindow): array
    {
        if (str_starts_with($nodeKey, 'impression:')) {
            return [];
        }

        $rows = $this->feed->latestEmailRows();

        if ($rows === []) {
            return [];
        }

        $sidecar = $this->sidecarEmailsByReference();
        $childDepth = $fromDepth + 1;

        if ($nodeKey === 'domain:email') {
            return $this->senderNodes($rows, $sidecar, $childDepth, $fromDepth, $depthWindow);
        }

        if (str_starts_with($nodeKey, 'sender:email:')) {
            $sender = $this->decodeKeyPart(substr($nodeKey, strlen('sender:email:')));

            return $sender === null
                ? []
                : $this->impressionNodesForSender($rows, $sidecar, $sender, $childDepth, $fromDepth, $depthWindow);
        }

        return [];
    }

    /**
     * @param  list<object>  $rows
     * @param  array<string, object>  $sidecar
     * @return list<SurfaceTreeNode>
     */
    private function senderNodes(array $rows, array $sidecar, int $childDepth, int $fromDepth, int $depthWindow): array
    {
        $senders = [];

        foreach ($rows as $row) {
            $sender = $this->senderFor($row, $sidecar);
            $key = 'sender:email:'.$this->encodeKeyPart($sender);

            $senders[$key] = $this->folderNode(
                key: $key,
                label: $sender,
                domain: 'email',
                depth: $childDepth,
                hasChildren: true,
                isTerminalDepth: $this->reachesTerminalDepth($childDepth, $fromDepth, $depthWindow),
                relation: 'from_sender',
            );
        }

        return array_values($senders);
    }

    /**
     * @param  list<object>  $rows
     * @param  array<string, object>  $sidecar
     * @return list<SurfaceTreeNode>
     */
    private function impressionNodesForSender(array $rows, array $sidecar, string $sender, int $childDepth, int $fromDepth, int $depthWindow): array
    {
        $impressions = [];

        foreach ($rows as $row) {
            if ($this->senderFor($row, $sidecar) !== $sender) {
                continue;
            }

            $impression = $this->emailImpressionNode($row, $sidecar, $childDepth, $this->reachesTerminalDepth($childDepth, $fromDepth, $depthWindow));

            if ($impression) {
                $impressions[$impression->key] = $impression;
            }
        }

        return array_values($impressions);
    }

    /**
     * @return array<string, object>
     */
    private function sidecarEmailsByReference(): array
    {
        $indexed = [];

        foreach ($this->feed->sidecarEmailRows() as $row) {
            foreach (['source_ref', 'source_reference', 'message_id', 'email_id', 'id'] as $column) {
                $value = $this->value($row, [$column]);

                if ($value !== null) {
                    $indexed[$value] = $row;
                }
            }
        }

        return $indexed;
    }

    /**
     * @param  array<string, object>  $sidecar
     */
    private function senderFor(object $row, array $sidecar): string
    {
        $sidecarRow = $this->sidecarFor($row, $sidecar);

        return $this->value($row, ['sender', 'from_address', 'from_email', 'author'])
            ?? ($sidecarRow ? $this->value($sidecarRow, ['sender', 'from_address', 'from_email', 'author']) : null)
            ?? $this->threadFor($row, $sidecar)
            ?? 'unknown sender';
    }

    /**
     * @param  array<string, object>  $sidecar
     */
    private function threadFor(object $row, array $sidecar): ?string
    {
        $sidecarRow = $this->sidecarFor($row, $sidecar);

        return $this->value($row, ['thread_id', 'thread_key', 'conversation_id'])
            ?? ($sidecarRow ? $this->value($sidecarRow, ['thread_id', 'thread_key', 'conversation_id']) : null);
    }

    /**
     * @param  array<string, object>  $sidecar
     */
    private function sidecarFor(object $row, array $sidecar): ?object
    {
        foreach (['source_ref', 'source_reference', 'message_id', 'email_id', 'id'] as $column) {
            $value = $this->value($row, [$column]);

            if ($value !== null && isset($sidecar[$value])) {
                return $sidecar[$value];
            }
        }

        return null;
    }

    /**
     * @param  array<string, object>  $sidecar
     */
    private function emailImpressionNode(object $row, array $sidecar, int $depth, bool $isTerminalDepth): ?SurfaceTreeNode
    {
        $impressionId = $this->value($row, ['impression_id', 'uuid', 'id']);

        if ($impressionId === null) {
            return null;
        }

        $sidecarRow = $this->sidecarFor($row, $sidecar);
        $sourceRef = $this->value($row, ['source_ref', 'source_reference', 'message_id', 'email_id']);

        return $this->impressionNode(
            impressionId: $impressionId,
            label: $this->value($row, ['label', 'title', 'subject'])
                ?? ($sidecarRow ? $this->value($sidecarRow, ['subject', 'title']) : null)
                ?? $this->titleFromIdentifier($sourceRef, 'Email impression'),
            domain: 'email',
            depth: $depth,
            isTerminalDepth: $isTerminalDepth,
            meta: [
                'kind' => $this->value($row, ['kind', 'memory_kind']) ?? 'email',
                'status' => $this->value($row, ['status', 'process_status']) ?? ($sidecarRow ? $this->value($sidecarRow, ['status', 'sync_status']) : null),
                'observed_at' => $this->value($row, ['observed_at', 'sensemade_at', 'created_at']),
                'source_ref' => $sourceRef,
                'sender' => $this->senderFor($row, $sidecar),
                'thread_id' => $this->threadFor($row, $sidecar),
            ],
        );
    }
}
