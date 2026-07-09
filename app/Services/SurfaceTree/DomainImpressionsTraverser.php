<?php

namespace App\Services\SurfaceTree;

use App\Models\Impressions\CameraLensScenePayload;
use App\Models\Impressions\EmailImpression;
use App\Models\Impressions\ImpressionDreamstateFeed;
use App\Models\Sidecar\Email;
use App\Services\SurfaceTree\Concerns\BuildsSurfaceTreeNodes;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Traverser for the dreamstate, camera_lens, and email domain roots.
 * Dreamstate has no folder hierarchy: its root lists impressions directly.
 * Camera Lens splits into two folders, because it has two independent
 * evidence sources (OCL-19): the camera_lens_scene_payloads table (scene
 * payloads it publishes to Impressions) and Loki-backed runtime telemetry
 * (the olo.camera_lens.runtime.event journey evidence olo-nats-tap
 * captures). Email groups the canonical sidecar emails by sender and lists
 * per-sender records, enriched with sensemade state from the impressions
 * organism when it can vouch for a message.
 */
class DomainImpressionsTraverser implements SurfaceTreeDomainTraverser
{
    use BuildsSurfaceTreeNodes;

    private const CAMERA_LENS_SCENES_FOLDER = 'folder:camera_lens:scenes';

    private const CAMERA_LENS_TELEMETRY_FOLDER = 'folder:camera_lens:telemetry';

    private const int DREAMSTATE_ROW_WINDOW = 200;

    private const int CAMERA_LENS_ROW_WINDOW = 200;

    private const int ROOT_EMAIL_WINDOW = 100;

    private const int SENDER_CHILD_LIMIT = 50;

    public function __construct(
        private readonly CameraLensTelemetryFeed $telemetryFeed,
        private readonly DreamstateProvenanceResolver $provenance,
        private readonly DreamstateEvolutionResolver $evolution,
        private readonly DreamstateContainsPresenter $contains,
        private readonly DreamstateConnectionsPresenter $connections,
    ) {}

    /**
     * @return list<SurfaceTreeNode>
     */
    public function children(string $nodeKey, int $fromDepth, int $depthWindow): array
    {
        if ($nodeKey === 'domain:email' || str_starts_with($nodeKey, 'sender:email:')) {
            return $this->emailChildren($nodeKey, $fromDepth, $depthWindow);
        }

        if ($nodeKey === 'domain:dreamstate') {
            return $this->dreamstateChildren($fromDepth, $depthWindow);
        }

        if ($nodeKey === 'domain:camera_lens') {
            return $this->cameraLensFolders($fromDepth, $depthWindow);
        }

        if ($nodeKey === self::CAMERA_LENS_SCENES_FOLDER) {
            return $this->cameraLensSceneChildren($fromDepth, $depthWindow);
        }

        if ($nodeKey === self::CAMERA_LENS_TELEMETRY_FOLDER) {
            return $this->telemetryChildren($fromDepth, $depthWindow);
        }

        return [];
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
    private function dreamstateChildren(int $fromDepth, int $depthWindow): array
    {
        $childDepth = $fromDepth + 1;

        try {
            $rows = ImpressionDreamstateFeed::latestForSurfaceTree(self::DREAMSTATE_ROW_WINDOW);
        } catch (Throwable) {
            return [];
        }

        /** @var array<string, ImpressionDreamstateFeed> $rowsById */
        $rowsById = [];

        foreach ($rows as $row) {
            $impressionId = $this->text($row->impression_id);

            if ($impressionId !== null && ! array_key_exists($impressionId, $rowsById)) {
                $rowsById[$impressionId] = $row;
            }
        }

        if ($rowsById === []) {
            return [];
        }

        // Dreamstate rows only retain lineage; their type identity is
        // resolved back through the canonical Impressions feed in one batch.
        $provenanceById = $this->provenance->resolveMany(array_keys($rowsById));

        // How far each impression moved through Dreamstate, from the
        // subconscious lineage tables, also in one batch per source.
        $evolutionById = $this->evolution->resolveMany(array_map(strval(...), array_keys($rowsById)));

        $emailsByReference = $this->sidecarEmailsByReference($this->emailReferences($provenanceById));

        $emailRowsById = [];

        foreach ($rowsById as $impressionId => $row) {
            $reference = $provenanceById[$impressionId]['memory_source_ref'] ?? null;
            $emailRowsById[$impressionId] = is_string($reference) ? ($emailsByReference[$reference] ?? null) : null;
        }

        // Grouped plain-language connection summaries, from the listing rows
        // plus batched sidecar/subconscious counts.
        $connectionsById = $this->connections->resolveMany($rowsById, $evolutionById, $emailRowsById);

        // Which database view fed this listing — technical-drawer receipt.
        $sourceView = (new ImpressionDreamstateFeed)->getConnectionName().':'.(new ImpressionDreamstateFeed)->getTable();

        $impressions = [];

        foreach ($rowsById as $impressionId => $row) {
            $rowProvenance = $provenanceById[$impressionId] ?? [];
            $memoryKind = $rowProvenance['memory_kind'] ?? null;

            $impression = $this->impressionNode(
                impressionId: (string) $impressionId,
                label: $this->titleFromIdentifier(
                    $this->text($row->memory_source_ref) ?? $this->text($row->source_path),
                    'Dreamstate impression',
                ),
                domain: 'dreamstate',
                depth: $childDepth,
                isTerminalDepth: $this->reachesTerminalDepth($childDepth, $fromDepth, $depthWindow),
                meta: [
                    'kind' => $this->text($row->kind) ?? $this->text($row->memory_kind),
                    'status' => $this->text($row->process_status),
                    'observed_at' => $row->observed_at === null ? null : (string) $row->observed_at,
                    'source_path' => $this->text($row->source_path),
                    'summary' => $row->summarySentence(),
                    'source_view' => $sourceView,
                    ...$this->contains->containsMetaFor(
                        $row,
                        is_string($memoryKind) ? $memoryKind : null,
                        $emailRowsById[$impressionId] ?? null,
                    ),
                    ...$rowProvenance,
                    ...($evolutionById[$impressionId] ?? []),
                    ...($connectionsById[$impressionId] ?? []),
                ],
            );

            $impressions[$impression->key] = $impression;
        }

        return array_values($impressions);
    }

    /**
     * @return list<SurfaceTreeNode>
     */
    private function cameraLensSceneChildren(int $fromDepth, int $depthWindow): array
    {
        $childDepth = $fromDepth + 1;

        try {
            $rows = CameraLensScenePayload::latestForSurfaceTree(self::CAMERA_LENS_ROW_WINDOW);
        } catch (Throwable) {
            return [];
        }

        $impressions = [];

        foreach ($rows as $row) {
            $impressionId = $this->text($row->housed_source_id);

            if ($impressionId === null) {
                continue;
            }

            $impression = $this->impressionNode(
                impressionId: $impressionId,
                label: $this->titleFromIdentifier($this->text($row->schema), 'Camera lens impression'),
                domain: 'camera_lens',
                depth: $childDepth,
                isTerminalDepth: $this->reachesTerminalDepth($childDepth, $fromDepth, $depthWindow),
                meta: [
                    'kind' => $this->text($row->source_kind),
                    'observed_at' => $this->text($row->observed_at) ?? $this->text($row->created_at),
                    'schema' => $this->text($row->schema),
                ],
            );

            $impressions[$impression->key] ??= $impression;
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
            $timestampNs = $this->eventValue($event, 'timestamp_ns');

            if ($timestampNs === null) {
                continue;
            }

            $key = 'record:camera_lens_telemetry:'.$this->encodeKeyPart($timestampNs);

            $records[$key] = $this->recordNode(
                key: $key,
                label: $this->eventValue($event, 'event') ?? 'Camera Lens telemetry event',
                domain: 'camera_lens',
                depth: $childDepth,
                relation: 'runtime_event',
                meta: [
                    'timestamp' => $this->eventValue($event, 'timestamp'),
                    'final_status' => $this->eventValue($event, 'final_status'),
                    'error' => $this->eventValue($event, 'error'),
                    'decision' => $this->eventValue($event, 'decision'),
                    'reason' => $this->eventValue($event, 'reason'),
                    'skip_reason' => $this->eventValue($event, 'skip_reason'),
                    'trigger_source' => $this->eventValue($event, 'trigger_source'),
                    'trigger_type' => $this->eventValue($event, 'trigger_type'),
                    'publish_subject' => $this->eventValue($event, 'publish_subject'),
                    'correlation_id' => $this->eventValue($event, 'correlation_id'),
                ],
            );
        }

        return array_values($records);
    }

    /**
     * Loki telemetry events are HTTP array payloads, not Eloquent rows, so
     * array access is the honest shape here.
     *
     * @param  array<string, mixed>  $event
     */
    private function eventValue(array $event, string $key): ?string
    {
        return $this->text($event[$key] ?? null);
    }

    /**
     * Memory source references of the email-kind impressions in one listing,
     * so their sidecar details load in a single batch.
     *
     * @param  array<int|string, array<string, mixed>>  $provenanceById
     * @return list<string>
     */
    private function emailReferences(array $provenanceById): array
    {
        $references = [];

        foreach ($provenanceById as $provenance) {
            if (($provenance['memory_kind'] ?? null) !== 'email') {
                continue;
            }

            $reference = $provenance['memory_source_ref'] ?? null;

            if (is_string($reference) && $reference !== '') {
                $references[$reference] = true;
            }
        }

        return array_map(strval(...), array_keys($references));
    }

    /**
     * Batched sidecar lookup for the email-kind impressions in one listing,
     * indexed by every reference variant so either the plain or the
     * outlook-prefixed form matches. A missing or unreadable sidecar simply
     * yields no enrichment.
     *
     * @param  list<string>  $references
     * @return array<string, Email>
     */
    private function sidecarEmailsByReference(array $references): array
    {
        $variants = [];

        foreach ($references as $reference) {
            foreach (Email::referenceVariants($reference) as $variant) {
                $variants[$variant] = true;
            }
        }

        if ($variants === []) {
            return [];
        }

        try {
            $emails = Email::forMessageReferences(array_map(strval(...), array_keys($variants)))->get();
        } catch (Throwable) {
            return [];
        }

        $byReference = [];

        foreach ($emails as $email) {
            foreach (Email::referenceVariants($email->id) as $variant) {
                $byReference[$variant] ??= $email;
            }
        }

        return $byReference;
    }

    /**
     * @return list<SurfaceTreeNode>
     */
    private function emailChildren(string $nodeKey, int $fromDepth, int $depthWindow): array
    {
        $childDepth = $fromDepth + 1;

        if ($nodeKey === 'domain:email') {
            $emails = $this->safeEmails(fn () => Email::latestForSurfaceTree(self::ROOT_EMAIL_WINDOW));

            if ($emails->isEmpty()) {
                return [];
            }

            return $this->senderNodes($emails, $childDepth, $fromDepth, $depthWindow);
        }

        $sender = $this->decodeKeyPart(substr($nodeKey, strlen('sender:email:')));

        if ($sender === null) {
            return [];
        }

        $emails = $this->safeEmails(fn () => Email::forSurfaceTreeSender($sender, self::SENDER_CHILD_LIMIT));

        return $this->recordNodesForSender($emails, $childDepth);
    }

    /**
     * @param  callable(): Collection<int, Email>  $read
     * @return Collection<int, Email>
     */
    private function safeEmails(callable $read): Collection
    {
        try {
            return $read();
        } catch (Throwable) {
            return new Collection;
        }
    }

    /**
     * @param  Collection<int, Email>  $emails
     * @return list<SurfaceTreeNode>
     */
    private function senderNodes(Collection $emails, int $childDepth, int $fromDepth, int $depthWindow): array
    {
        $sensemadeByReference = $this->sensemadeByReference($emails);

        /** @var array<string, array{key: string, label: string, is_terminal_depth: bool, meta: array<string, mixed>}> $senders */
        $senders = [];

        foreach ($emails as $email) {
            $sender = $this->senderFor($email);
            $key = 'sender:email:'.$this->encodeKeyPart($sender);
            $sensemade = $this->sensemadeFor($email, $sensemadeByReference);

            if (! isset($senders[$key])) {
                $senders[$key] = [
                    'key' => $key,
                    'label' => $sender,
                    'is_terminal_depth' => $this->reachesTerminalDepth($childDepth, $fromDepth, $depthWindow),
                    'meta' => [
                        'sender' => $sender,
                        'message_count' => 0,
                        'latest_subject' => $this->text($email->subject),
                        'latest_received_at' => $this->text($email->received_at),
                        'latest_source_ref' => $this->text($email->id),
                        'latest_body_preview' => $this->text($email->body_preview),
                        'latest_human_summary' => $sensemade?->sensemadeHumanSummary(),
                        'latest_sensemade_text' => $sensemade?->sensemadeText(),
                        'latest_why_it_matters' => $sensemade?->sensemadeWhyItMatters(),
                        'latest_recommended_next_step' => $sensemade?->sensemadeRecommendedNextStep(),
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
     * @param  Collection<int, Email>  $emails
     * @return list<SurfaceTreeNode>
     */
    private function recordNodesForSender(Collection $emails, int $childDepth): array
    {
        $sensemadeByReference = $this->sensemadeByReference($emails);
        $records = [];

        foreach ($emails as $email) {
            $record = $this->emailRecordNode($email, $this->sensemadeFor($email, $sensemadeByReference), $childDepth);

            if ($record) {
                $records[$record->key] = $record;
            }
        }

        return array_values($records);
    }

    private function emailRecordNode(Email $email, ?EmailImpression $sensemade, int $depth): ?SurfaceTreeNode
    {
        $sourceRef = $this->text($email->id);

        if ($sourceRef === null) {
            return null;
        }

        $sender = $this->senderFor($email);

        return $this->recordNode(
            key: 'record:email:'.$this->encodeKeyPart($sourceRef),
            label: $this->emailLabel($email, $sender),
            domain: 'email',
            depth: $depth,
            relation: 'email_listing',
            meta: [
                'kind' => 'email',
                'subject' => $this->text($email->subject),
                'received_at' => $this->text($email->received_at),
                'source_ref' => $sourceRef,
                'sender' => $sender,
                'thread_id' => $this->text($email->thread_id),
                'related_impression_id' => $sensemade?->impression_id,
                'body_preview' => $this->text($email->body_preview),
                'email_body' => $this->text($email->normalised_body),
                'human_summary' => $sensemade?->sensemadeHumanSummary(),
                'sensemade_text' => $sensemade?->sensemadeText(),
                'why_it_matters' => $sensemade?->sensemadeWhyItMatters(),
                'recommended_next_step' => $sensemade?->sensemadeRecommendedNextStep(),
            ],
        );
    }

    private function emailLabel(Email $email, string $sender): string
    {
        $subject = $this->text($email->subject);

        if ($subject !== null) {
            return $subject;
        }

        if ($sender !== Email::UNKNOWN_SENDER) {
            return $sender;
        }

        $receivedAt = $this->text($email->received_at);

        if ($receivedAt !== null) {
            return 'Email received '.$receivedAt;
        }

        return 'Untitled email';
    }

    private function senderFor(Email $email): string
    {
        return $this->text($email->sender) ?? Email::UNKNOWN_SENDER;
    }

    /**
     * Batched sensemade-state lookup: one EmailImpression query per listing,
     * matched by every reference variant. A missing or unreadable
     * impressions source simply yields no enrichment.
     *
     * @param  Collection<int, Email>  $emails
     * @return array<string, EmailImpression> keyed by reference variant
     */
    private function sensemadeByReference(Collection $emails): array
    {
        $references = [];

        foreach ($emails as $email) {
            foreach (Email::referenceVariants($email->id) as $variant) {
                $references[$variant] = true;
            }
        }

        if ($references === []) {
            return [];
        }

        try {
            $impressions = EmailImpression::forSourceReferences(array_map(strval(...), array_keys($references)))->get();
        } catch (Throwable) {
            return [];
        }

        $indexed = [];

        foreach ($impressions as $impression) {
            foreach ($impression->referenceCandidates() as $candidate) {
                foreach (Email::referenceVariants($candidate) as $variant) {
                    $indexed[$variant] ??= $impression;
                }
            }
        }

        return $indexed;
    }

    /**
     * @param  array<string, EmailImpression>  $sensemadeByReference
     */
    private function sensemadeFor(Email $email, array $sensemadeByReference): ?EmailImpression
    {
        $reference = $this->text($email->id);

        return $reference === null ? null : ($sensemadeByReference[$reference] ?? null);
    }

}
