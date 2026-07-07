<?php

namespace App\Services\SurfaceTree;

use App\Services\SurfaceTree\Concerns\BuildsSurfaceTreeNodes;

class EmailTreeTraverser implements SurfaceTreeDomainTraverser
{
    use BuildsSurfaceTreeNodes;

    private const array BODY_CANDIDATES = [
        'email_body',
        'emailbody',
        'normalised_body',
        'normalized_body',
        'body',
        'body_text',
        'text_body',
        'plain_text',
        'message_body',
        'content',
    ];

    private const array BODY_PREVIEW_CANDIDATES = [
        'body_preview',
        'preview',
        'snippet',
        'body_snippet',
    ];

    public function __construct(
        private readonly EmailImpressionsFeed $feed,
    ) {}

    /**
     * @return list<SurfaceTreeNode>
     */
    public function children(string $nodeKey, int $fromDepth, int $depthWindow): array
    {
        if (str_starts_with($nodeKey, 'impression:') || str_starts_with($nodeKey, 'record:email:')) {
            return [];
        }

        $childDepth = $fromDepth + 1;

        if ($nodeKey === 'domain:email') {
            $rows = $this->feed->latestEmailRows();

            if ($rows === []) {
                return [];
            }

            return $this->senderNodes($rows, $childDepth, $fromDepth, $depthWindow);
        }

        if (str_starts_with($nodeKey, 'sender:email:')) {
            $sender = $this->decodeKeyPart(substr($nodeKey, strlen('sender:email:')));

            if ($sender === null) {
                return [];
            }

            $rows = $this->feed->emailRowsForSender($sender);

            return $this->recordNodesForSender($rows, $sender, $childDepth);
        }

        return [];
    }

    /**
     * @param  list<object>  $rows
     * @return list<SurfaceTreeNode>
     */
    private function senderNodes(array $rows, int $childDepth, int $fromDepth, int $depthWindow): array
    {
        /** @var array<string, array{key: string, label: string, is_terminal_depth: bool, meta: array<string, mixed>}> $senders */
        $senders = [];

        foreach ($rows as $row) {
            $sender = $this->senderFor($row);
            $key = 'sender:email:'.$this->encodeKeyPart($sender);

            if (! isset($senders[$key])) {
                $senders[$key] = [
                    'key' => $key,
                    'label' => $sender,
                    'is_terminal_depth' => $this->reachesTerminalDepth($childDepth, $fromDepth, $depthWindow),
                    'meta' => [
                        'sender' => $sender,
                        'message_count' => 0,
                        'latest_subject' => $this->value($row, ['subject', 'title']),
                        'latest_received_at' => $this->value($row, ['received_at']),
                        'latest_observed_at' => $this->value($row, ['observed_at', 'sensemade_at', 'created_at', 'updated_at']),
                        'latest_source_ref' => $this->sourceRefFor($row),
                        'latest_status' => $this->value($row, ['status', 'sync_status', 'process_status']),
                        'latest_body_preview' => $this->value($row, self::BODY_PREVIEW_CANDIDATES),
                        'latest_human_summary' => $this->value($row, ['human_summary']),
                        'latest_sensemade_text' => $this->value($row, ['sensemade_text']),
                        'latest_why_it_matters' => $this->value($row, ['why_it_matters']),
                        'latest_recommended_next_step' => $this->value($row, ['recommended_next_step']),
                    ],
                ];
            }

            $senders[$key]['meta']['message_count']++;
        }

        return array_map(
            fn (array $sender): SurfaceTreeNode => $this->folderNode(
                key: $sender['key'],
                label: $sender['label'],
                domain: 'email',
                depth: $childDepth,
                hasChildren: true,
                isTerminalDepth: $sender['is_terminal_depth'],
                relation: 'from_sender',
                meta: array_filter($sender['meta'], fn (mixed $value): bool => $value !== null && $value !== ''),
            ),
            array_values($senders),
        );
    }

    /**
     * @param  list<object>  $rows
     * @return list<SurfaceTreeNode>
     */
    private function recordNodesForSender(array $rows, string $sender, int $childDepth): array
    {
        $records = [];

        foreach ($rows as $row) {
            if ($this->senderFor($row) !== $sender) {
                continue;
            }

            $record = $this->emailRecordNode($row, $childDepth);

            if ($record) {
                $records[$record->key] = $record;
            }
        }

        return array_values($records);
    }

    private function senderFor(object $row): string
    {
        return $this->value($row, ['sender', 'from_email', 'from_address', 'from', 'mail_from', 'from_name', 'author'])
            ?? 'unknown sender';
    }

    private function threadFor(object $row): ?string
    {
        return $this->value($row, ['thread_id', 'thread_key', 'conversation_id']);
    }

    private function emailRecordNode(object $row, int $depth): ?SurfaceTreeNode
    {
        $sourceRef = $this->sourceRefFor($row);

        if ($sourceRef === null) {
            return null;
        }

        $sender = $this->senderFor($row);
        $subject = $this->value($row, ['subject', 'title']);
        $receivedAt = $this->value($row, ['received_at']);
        $observedAt = $this->value($row, ['observed_at', 'sensemade_at', 'created_at', 'updated_at']);
        $bodyPreview = $this->value($row, self::BODY_PREVIEW_CANDIDATES);
        $emailBody = $this->value($row, self::BODY_CANDIDATES);

        return $this->recordNode(
            key: 'record:email:'.$this->encodeKeyPart($sourceRef),
            label: $this->emailLabel($row, $sender, $sourceRef),
            domain: 'email',
            depth: $depth,
            relation: 'email_listing',
            meta: [
                'kind' => $this->value($row, ['kind', 'memory_kind', 'type']) ?? 'email',
                'status' => $this->value($row, ['status', 'sync_status', 'process_status']),
                'subject' => $subject,
                'received_at' => $receivedAt,
                'observed_at' => $observedAt,
                'source_ref' => $sourceRef,
                'sender' => $sender,
                'from_name' => $this->value($row, ['from_name', 'fromName']),
                'from_email' => $this->value($row, ['from_email', 'fromEmail', 'from_address', 'fromAddress']),
                'thread_id' => $this->threadFor($row),
                'related_impression_id' => $this->value($row, ['related_impression_id', 'impression_id', 'uuid']),
                'body_preview' => $bodyPreview,
                'email_body' => $emailBody,
                'human_summary' => $this->value($row, ['human_summary']),
                'sensemade_text' => $this->value($row, ['sensemade_text']),
                'why_it_matters' => $this->value($row, ['why_it_matters']),
                'recommended_next_step' => $this->value($row, ['recommended_next_step']),
            ],
        );
    }

    private function emailLabel(object $row, string $sender, string $sourceRef): string
    {
        $subject = $this->value($row, ['subject', 'title']);

        if ($subject !== null) {
            return $subject;
        }

        foreach (['from_name', 'from_email', 'from_address'] as $candidate) {
            $value = $this->value($row, [$candidate]);

            if ($value !== null) {
                return $value;
            }
        }

        if ($sender !== 'unknown sender') {
            return $sender;
        }

        $timestamp = $this->value($row, ['received_at', 'observed_at', 'sensemade_at', 'created_at', 'updated_at']);

        if ($timestamp !== null) {
            return 'Email received '.$timestamp;
        }

        return 'Untitled email';
    }

    private function sourceRefFor(object $row): ?string
    {
        return $this->value($row, ['source_ref', 'source_reference', 'message_id', 'email_id', 'id']);
    }
}

