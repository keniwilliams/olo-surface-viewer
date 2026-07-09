<?php

namespace Tests\Feature;

use App\Models\Impressions\EmailImpression;
use App\Models\Sidecar\Email;
use App\Services\SurfaceTree\DomainImpressionsTraverser;
use App\Services\SurfaceTree\EmailTreeTraverser;
use App\Services\SurfaceTree\FilesystemTreeTraverser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SurfaceTreeTraverserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureSqliteOrgan('impressions');
        $this->configureSqliteOrgan('sidecar');
        $this->configureSqliteOrgan('subconscious');
        $this->createImpressionsTable();
        $this->createEmailImpressionsTable();
        $this->createSidecarEmailsTable();
        $this->seedSurfaceTreeRecords();
    }

    public function test_filesystem_traverser_groups_impressions_by_path_segments(): void
    {
        $traverser = app(FilesystemTreeTraverser::class);

        $drive = $traverser->children('domain:filesystem', 0, 3);
        $this->assertCount(1, $drive);
        $this->assertSame('D:', $drive[0]->label);
        $this->assertSame('folder', $drive[0]->type);
        $this->assertSame('filesystem', $drive[0]->domain);
        $this->assertTrue($drive[0]->hasChildren);

        $software = $traverser->children($drive[0]->key, 1, 3);
        $this->assertSame('OLO-Software', $software[0]->label);

        $project = $traverser->children($software[0]->key, 2, 3);
        $this->assertSame('olo-impressions', $project[0]->label);

        $impressions = $traverser->children($project[0]->key, 3, 3);
        $this->assertSame('impression:fs-uuid', $impressions[0]->key);
        $this->assertSame('impression', $impressions[0]->type);
        $this->assertSame('fs-uuid', $impressions[0]->impressionId);
        $this->assertSame('/impressions/fs-uuid', $impressions[0]->href);
        $this->assertSame('D:\OLO-Software\olo-impressions\first.md', $impressions[0]->meta['source_path']);
        $this->assertSame('file', $impressions[0]->meta['kind']);
    }

    public function test_filesystem_traverser_marks_depth_window_terminal_nodes(): void
    {
        $node = app(FilesystemTreeTraverser::class)->children('domain:filesystem', 0, 1)[0];

        $this->assertSame('D:', $node->label);
        $this->assertTrue($node->hasChildren);
        $this->assertTrue($node->isTerminalDepth);
    }

    public function test_filesystem_root_returns_children_when_dreamstate_feed_has_source_path_rows(): void
    {
        $this->createDreamstateFeedTable();

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow('feed-uuid', 'D:\\OLO-Software\\olo-surface-viewer\\readme.md'),
        ]);

        $children = app(FilesystemTreeTraverser::class)->children('domain:filesystem', 0, 3);

        $this->assertCount(1, $children);
        $this->assertSame('D:', $children[0]->label);
        $this->assertSame('folder', $children[0]->type);
        $this->assertTrue($children[0]->hasChildren);
    }

    public function test_filesystem_root_does_not_require_filesystem_domain(): void
    {
        $this->createDreamstateFeedTable();

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow('feed-uuid', 'D:\\Projects\\notes.md', domain: 'dreamstate'),
        ]);

        $children = app(FilesystemTreeTraverser::class)->children('domain:filesystem', 0, 3);

        $this->assertCount(1, $children);
        $this->assertSame('D:', $children[0]->label);
    }

    public function test_filesystem_row_limit_is_applied_after_path_bearing_filtering(): void
    {
        $this->createDreamstateFeedTable();

        // 600 newer rows without a source_path must not crowd the older path-bearing row out of the 500-row window.
        $fillers = [];
        foreach (range(1, 600) as $index) {
            $fillers[] = $this->dreamstateRow("filler-{$index}", null, observedAt: '2026-07-06 12:00:00');
        }
        foreach (array_chunk($fillers, 100) as $chunk) {
            DB::connection('impressions')->table('impressions_dreamstate_feed')->insert($chunk);
        }

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow('feed-uuid', 'D:\\OLO-Software\\olo-surface-viewer\\readme.md', observedAt: '2026-01-01 00:00:00'),
        ]);

        $children = app(FilesystemTreeTraverser::class)->children('domain:filesystem', 0, 3);

        $this->assertCount(1, $children);
        $this->assertSame('D:', $children[0]->label);
    }

    public function test_filesystem_root_is_empty_only_when_no_usable_path_like_fields_exist(): void
    {
        $this->createDreamstateFeedTable();

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow('feed-uuid', null),
            $this->dreamstateRow('feed-uuid-2', ''),
        ]);

        $this->assertSame([], app(FilesystemTreeTraverser::class)->children('domain:filesystem', 0, 3));
    }

    public function test_filesystem_children_are_sorted_alphabetically_with_folders_before_impressions(): void
    {
        $this->createDreamstateFeedTable();

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow('folder-zeta', 'zeta/notes.md'),
            $this->dreamstateRow('leaf-zebra', 'zebra.md'),
            $this->dreamstateRow('folder-item10', 'item10/notes.md'),
            $this->dreamstateRow('folder-beta', 'Beta/notes.md'),
            $this->dreamstateRow('leaf-apple', 'Apple.md'),
            $this->dreamstateRow('folder-alpha', 'alpha/notes.md'),
            $this->dreamstateRow('folder-item2', 'item2/notes.md'),
        ]);

        $children = app(FilesystemTreeTraverser::class)->children('domain:filesystem', 0, 3);

        $this->assertSame(
            ['alpha', 'Beta', 'item2', 'item10', 'zeta', 'Apple.md', 'zebra.md'],
            array_map(fn ($node) => $node->label, $children),
        );
        $this->assertSame(
            ['folder', 'folder', 'folder', 'folder', 'folder', 'impression', 'impression'],
            array_map(fn ($node) => $node->type, $children),
        );
    }

    public function test_filesystem_raw_corpus_is_looked_up_per_impression_not_embedded_in_children(): void
    {
        $this->createDreamstateFeedTable();

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow('feed-uuid', 'notes.md', rawCorpus: 'Full observed corpus text.'),
        ]);

        $traverser = app(FilesystemTreeTraverser::class);
        $children = $traverser->children('domain:filesystem', 0, 3);

        $this->assertCount(1, $children);
        $this->assertSame('impression', $children[0]->type);
        $this->assertArrayNotHasKey('raw_corpus', $children[0]->meta);

        $this->assertSame('Full observed corpus text.', $traverser->rawCorpus('feed-uuid'));
        $this->assertNull($traverser->rawCorpus('missing-uuid'));
    }

    public function test_domain_traverser_lists_dreamstate_impressions_from_the_dedicated_feed(): void
    {
        $this->createDreamstateFeedTable();

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow('dream-old', null, observedAt: '2026-07-04 12:00:00'),
            $this->dreamstateRow('dream-new', null, observedAt: '2026-07-05 12:00:00'),
        ]);

        $dreamstate = app(DomainImpressionsTraverser::class)->children('domain:dreamstate', 0, 3);

        $this->assertSame(['impression:dream-new', 'impression:dream-old'], array_map(fn ($node) => $node->key, $dreamstate));
        $this->assertSame('impression', $dreamstate[0]->type);
        $this->assertSame('dreamstate', $dreamstate[0]->domain);
        $this->assertSame('dream-new', $dreamstate[0]->impressionId);
        $this->assertSame('/impressions/dream-new', $dreamstate[0]->href);
        $this->assertSame('file', $dreamstate[0]->meta['kind']);
    }

    public function test_dreamstate_impressions_resolve_memory_kind_from_the_impressions_feed(): void
    {
        $this->createDreamstateFeedTable();

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow('dream-email', null, memoryKind: 'email', sourceRef: 'imap://inbox/42'),
        ]);

        $node = app(DomainImpressionsTraverser::class)->children('domain:dreamstate', 0, 3)[0];

        $this->assertSame('email', $node->meta['memory_kind']);
        $this->assertSame('imap://inbox/42', $node->meta['memory_source_ref']);
        $this->assertSame('impressions_dreamstate_feed_v1', $node->meta['contract_version']);
        $this->assertTrue($node->meta['provenance_resolved']);
        $this->assertArrayNotHasKey('provenance_resolution_error', $node->meta);
    }

    public function test_dreamstate_provenance_is_unresolved_when_the_feed_contract_version_is_unexpected(): void
    {
        $this->createDreamstateFeedTable();

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow('dream-drifted', null, memoryKind: 'email', contractVersion: 'impressions_dreamstate_feed_v2'),
        ]);

        $node = app(DomainImpressionsTraverser::class)->children('domain:dreamstate', 0, 3)[0];

        $this->assertFalse($node->meta['provenance_resolved']);
        $this->assertArrayNotHasKey('memory_kind', $node->meta);
        $this->assertStringContainsString('contract_version', $node->meta['provenance_resolution_error']);
    }

    public function test_dreamstate_listing_survives_when_the_impressions_feed_is_missing(): void
    {
        // Only the legacy source from setUp exists (no feed table); the
        // listing still renders and every impression is reported unresolved
        // instead of guessed at.
        DB::connection('impressions')->table('sensemade_impressions')->insert([
            [
                'impression_id' => 'dream-legacy',
                'domain' => 'dreamstate',
                'label' => 'Legacy impression',
                'kind' => 'file',
                'status' => 'observed',
                'source_path' => null,
                'source_ref' => null,
                'thread_id' => null,
                'observed_at' => '2026-07-06 12:00:00',
            ],
        ]);

        $nodes = app(DomainImpressionsTraverser::class)->children('domain:dreamstate', 0, 3);
        $node = collect($nodes)->firstWhere('key', 'impression:dream-legacy');

        $this->assertNotNull($node);
        $this->assertFalse($node->meta['provenance_resolved']);
        $this->assertArrayNotHasKey('memory_kind', $node->meta);
        $this->assertSame('impressions_dreamstate_feed is not available', $node->meta['provenance_resolution_error']);
    }

    public function test_dreamstate_impressions_carry_a_one_sentence_summary_from_the_raw_corpus(): void
    {
        $this->createDreamstateFeedTable();

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow(
                'dream-with-corpus',
                null,
                rawCorpus: "# Weekly notes\n\nThe deployment pipeline was reworked to publish scene payloads nightly. Further detail follows in later paragraphs.",
            ),
            $this->dreamstateRow('dream-without-corpus', null, observedAt: '2026-07-04 12:00:00'),
        ]);

        $dreamstate = app(DomainImpressionsTraverser::class)->children('domain:dreamstate', 0, 3);

        $this->assertSame(
            'Weekly notes The deployment pipeline was reworked to publish scene payloads nightly.',
            $dreamstate[0]->meta['summary'],
        );
        $this->assertArrayNotHasKey('summary', $dreamstate[1]->meta);
    }

    public function test_dreamstate_impressions_report_the_highest_known_evolution_stage(): void
    {
        $this->createDreamstateFeedTable();
        $this->createDreamstateLineageTables();

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow('dream-settled', null),
        ]);

        DB::connection('subconscious')->table('dreamstate_schema.dreamstate_candidates')->insert([
            ['candidate_id' => 'cand-1', 'run_id' => 'run-1', 'impression_id' => 'dream-settled', 'status' => 'pending'],
        ]);
        DB::connection('subconscious')->table('dreamstate_schema.dreamstate_return_packet')->insert([
            ['packet_id' => 'packet-1', 'run_id' => 'run-1', 'status' => 'ready'],
        ]);
        DB::connection('subconscious')->table('dreamstate_schema.dreamstate_sensemaker_request')->insert([
            ['request_id' => 'req-1', 'run_id' => 'run-1', 'impression_id' => 'dream-settled', 'status' => 'complete', 'completed_at' => '2026-07-05 13:00:00'],
        ]);

        $node = app(DomainImpressionsTraverser::class)->children('domain:dreamstate', 0, 3)[0];

        $this->assertSame('settled', $node->meta['evolution_stage']);
        $this->assertSame('Settled by Sensemaker', $node->meta['evolution_label']);
        $this->assertSame(
            ['Selected for Dreamstate', 'Became candidate', 'Returned from Dreamstate', 'Settled by Sensemaker'],
            $node->meta['evolution_steps'],
        );

        // Technical lineage stays available for the technical drawer.
        $this->assertSame('run-1', $node->meta['run_id']);
        $this->assertSame('cand-1', $node->meta['candidate_id']);
        $this->assertSame('packet-1', $node->meta['packet_id']);
        $this->assertSame('req-1', $node->meta['sensemaker_request_id']);
        $this->assertSame('complete', $node->meta['sensemaker_status']);
    }

    public function test_dreamstate_impressions_with_only_a_candidate_stop_at_that_stage(): void
    {
        $this->createDreamstateFeedTable();
        $this->createDreamstateLineageTables();

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow('dream-candidate', null),
        ]);

        DB::connection('subconscious')->table('dreamstate_schema.dreamstate_candidates')->insert([
            ['candidate_id' => 'cand-2', 'run_id' => 'run-2', 'impression_id' => 'dream-candidate', 'status' => 'pending'],
        ]);

        $node = app(DomainImpressionsTraverser::class)->children('domain:dreamstate', 0, 3)[0];

        $this->assertSame('candidate', $node->meta['evolution_stage']);
        $this->assertSame('Became candidate', $node->meta['evolution_label']);
        $this->assertSame(['Selected for Dreamstate', 'Became candidate'], $node->meta['evolution_steps']);
        $this->assertArrayNotHasKey('packet_id', $node->meta);
        $this->assertArrayNotHasKey('sensemaker_request_id', $node->meta);
    }

    public function test_dreamstate_impressions_without_lineage_report_not_evolved_yet(): void
    {
        $this->createDreamstateFeedTable();
        $this->createDreamstateLineageTables();

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow('dream-quiet', null),
        ]);

        $node = app(DomainImpressionsTraverser::class)->children('domain:dreamstate', 0, 3)[0];

        $this->assertSame('observed', $node->meta['evolution_stage']);
        $this->assertSame('Not evolved yet', $node->meta['evolution_label']);
        $this->assertArrayNotHasKey('evolution_steps', $node->meta);
        $this->assertArrayNotHasKey('run_id', $node->meta);
    }

    public function test_dreamstate_listing_survives_when_the_subconscious_lineage_is_missing(): void
    {
        // No lineage tables exist on the sqlite subconscious double: the
        // listing renders without any evolution claim at all.
        $this->createDreamstateFeedTable();

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow('dream-unlinked', null),
        ]);

        $node = app(DomainImpressionsTraverser::class)->children('domain:dreamstate', 0, 3)[0];

        $this->assertSame('impression:dream-unlinked', $node->key);
        $this->assertArrayNotHasKey('evolution_stage', $node->meta);
        $this->assertArrayNotHasKey('evolution_steps', $node->meta);
    }

    public function test_domain_traverser_splits_camera_lens_into_scenes_and_telemetry_folders(): void
    {
        $folders = app(DomainImpressionsTraverser::class)->children('domain:camera_lens', 0, 3);

        $this->assertSame(
            ['folder:camera_lens:scenes', 'folder:camera_lens:telemetry'],
            array_map(fn ($node) => $node->key, $folders),
        );
        $this->assertSame(['Scene Payloads', 'Telemetry'], array_map(fn ($node) => $node->label, $folders));
        $this->assertSame(['folder', 'folder'], array_map(fn ($node) => $node->type, $folders));
        $this->assertTrue($folders[0]->hasChildren);
        $this->assertTrue($folders[1]->hasChildren);

        $this->assertSame([], app(DomainImpressionsTraverser::class)->children('domain:filesystem', 0, 3));
    }

    public function test_domain_traverser_lists_camera_lens_scene_payloads_from_the_dedicated_table(): void
    {
        $this->createCameraLensScenePayloadsTable();

        DB::connection('impressions')->table('camera_lens_scene_payloads')->insert([
            $this->cameraLensRow('lens-uuid'),
        ]);

        $cameraLens = app(DomainImpressionsTraverser::class)->children('folder:camera_lens:scenes', 1, 3);

        $this->assertSame(['impression:lens-uuid'], array_map(fn ($node) => $node->key, $cameraLens));
        $this->assertSame('impression', $cameraLens[0]->type);
        $this->assertSame('camera_lens', $cameraLens[0]->domain);
        $this->assertSame('lens-uuid', $cameraLens[0]->impressionId);
        $this->assertSame('/impressions/lens-uuid', $cameraLens[0]->href);
        $this->assertSame('camera_lens_scene', $cameraLens[0]->meta['kind']);
        $this->assertSame('olo-camera-lens.scene.v1', $cameraLens[0]->meta['schema']);
    }

    public function test_domain_traverser_returns_empty_when_camera_lens_scene_table_does_not_exist(): void
    {
        $this->assertSame([], app(DomainImpressionsTraverser::class)->children('folder:camera_lens:scenes', 1, 3));
    }

    public function test_domain_traverser_lists_camera_lens_telemetry_from_loki(): void
    {
        Http::fake([
            '*/loki/api/v1/query_range*' => Http::response([
                'status' => 'success',
                'data' => [
                    'resultType' => 'streams',
                    'result' => [
                        [
                            'stream' => ['container' => '/olo-nats-tap'],
                            'values' => [
                                [
                                    '1751702400000000000',
                                    json_encode([
                                        'event' => 'nats.message',
                                        'subject' => 'olo.camera_lens.runtime.event',
                                        'correlation_id' => 'camera-lens:abc',
                                        'payload' => [
                                            'event' => 'camera_lens.journey.completed',
                                            'timestamp' => '2026-07-05T08:00:00Z',
                                            'organism' => 'camera_lens',
                                            'final_status' => 'published',
                                            'error' => null,
                                            'decision' => 'go',
                                            'reason' => 'scene energy exceeded configured wobble band',
                                        ],
                                    ], JSON_THROW_ON_ERROR),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $telemetry = app(DomainImpressionsTraverser::class)->children('folder:camera_lens:telemetry', 1, 3);

        $this->assertCount(1, $telemetry);
        $this->assertSame('record', $telemetry[0]->type);
        $this->assertSame('camera_lens', $telemetry[0]->domain);
        $this->assertNull($telemetry[0]->href);
        $this->assertSame('camera_lens.journey.completed', $telemetry[0]->label);
        $this->assertSame('runtime_event', $telemetry[0]->relation);
        $this->assertSame('published', $telemetry[0]->meta['final_status']);
        $this->assertSame('go', $telemetry[0]->meta['decision']);
        $this->assertSame('scene energy exceeded configured wobble band', $telemetry[0]->meta['reason']);
        $this->assertSame('camera-lens:abc', $telemetry[0]->meta['correlation_id']);
    }

    public function test_domain_traverser_returns_empty_telemetry_when_loki_is_unreachable(): void
    {
        Http::fake([
            '*/loki/api/v1/query_range*' => Http::response([], 500),
        ]);

        $this->assertSame([], app(DomainImpressionsTraverser::class)->children('folder:camera_lens:telemetry', 1, 3));
    }

    public function test_email_traverser_groups_email_records_by_sender_and_exposes_useful_meta(): void
    {
        $traverser = app(EmailTreeTraverser::class);

        $senders = $traverser->children('domain:email', 0, 3);
        $this->assertCount(1, $senders);
        $this->assertSame('sender@example.com', $senders[0]->label);
        $this->assertSame('folder', $senders[0]->type);
        $this->assertSame('from_sender', $senders[0]->relation);
        $this->assertSame('sender@example.com', $senders[0]->meta['sender']);
        $this->assertSame(1, $senders[0]->meta['message_count']);
        $this->assertSame('Sidecar subject', $senders[0]->meta['latest_subject']);
        $this->assertSame('2026-07-05 11:58:00', $senders[0]->meta['latest_received_at']);
        $this->assertSame('message-1', $senders[0]->meta['latest_source_ref']);
        $this->assertSame('Impressions human summary.', $senders[0]->meta['latest_human_summary']);

        $records = $traverser->children($senders[0]->key, 1, 3);
        $this->assertCount(1, $records);
        $this->assertSame('record', $records[0]->type);
        $this->assertSame('email', $records[0]->domain);
        $this->assertNull($records[0]->impressionId);
        $this->assertNull($records[0]->href);
        $this->assertSame('email_listing', $records[0]->relation);
        $this->assertSame('Sidecar subject', $records[0]->label);
        $this->assertSame('message-1', $records[0]->meta['source_ref']);
        $this->assertSame('Sidecar subject', $records[0]->meta['subject']);
        $this->assertSame('sender@example.com', $records[0]->meta['sender']);
        $this->assertSame('thread-1', $records[0]->meta['thread_id']);
        $this->assertSame('email-impression-uuid', $records[0]->meta['related_impression_id']);
        $this->assertSame('2026-07-05 11:58:00', $records[0]->meta['received_at']);
        $this->assertSame('Short preview.', $records[0]->meta['body_preview']);
        $this->assertSame('Full normalised email body.', $records[0]->meta['email_body']);
        $this->assertSame('Impressions human summary.', $records[0]->meta['human_summary']);
        $this->assertSame('Impressions sensemade text.', $records[0]->meta['sensemade_text']);
        $this->assertSame('This affects the current work.', $records[0]->meta['why_it_matters']);
        $this->assertSame('Reply with the requested detail.', $records[0]->meta['recommended_next_step']);
    }

    public function test_email_traverser_does_not_list_generic_impressions_as_email_nodes(): void
    {
        Schema::connection('impressions')->drop('sensemade_impressions');
        Schema::connection('impressions')->create('sensemade_impressions', function ($table): void {
            $table->id();
            $table->string('impression_id');
            $table->string('label')->nullable();
            $table->string('kind')->nullable();
            $table->string('status')->nullable();
            $table->string('source_path')->nullable();
            $table->string('source_ref')->nullable();
            $table->string('thread_id')->nullable();
            $table->timestampTz('observed_at')->nullable();
        });

        DB::connection('impressions')->table('sensemade_impressions')->insert([
            [
                'impression_id' => 'canon-uuid',
                'label' => 'Canon document',
                'kind' => 'canon',
                'status' => 'observed',
                'source_path' => null,
                'source_ref' => null,
                'thread_id' => null,
                'observed_at' => '2026-07-05 12:02:00',
            ],
            [
                'impression_id' => 'living-doc-uuid',
                'label' => 'Living document',
                'kind' => 'living_document',
                'status' => 'observed',
                'source_path' => null,
                'source_ref' => null,
                'thread_id' => null,
                'observed_at' => '2026-07-05 12:03:00',
            ],
        ]);

        $senders = app(EmailTreeTraverser::class)->children('domain:email', 0, 3);

        $this->assertSame(['sender@example.com'], array_map(fn ($node) => $node->label, $senders));

        $records = app(EmailTreeTraverser::class)->children($senders[0]->key, 1, 3);

        $this->assertSame(['Sidecar subject'], array_map(fn ($node) => $node->label, $records));
    }

    public function test_email_traverser_uses_unknown_sender_only_when_email_sender_is_missing(): void
    {
        DB::connection('sidecar')->table('emails')->insert([
            'message_id' => 'message-2',
            'thread_id' => 'thread-2',
            'sender' => null,
            'subject' => 'Senderless subject',
            'status' => 'synced',
            'received_at' => '2026-07-05 11:59:00',
        ]);

        $senders = app(EmailTreeTraverser::class)->children('domain:email', 0, 3);
        $labels = array_map(fn ($node) => $node->label, $senders);

        $this->assertContains('sender@example.com', $labels);
        $this->assertContains('unknown sender', $labels);

        $unknownSender = array_values(array_filter($senders, fn ($node) => $node->label === 'unknown sender'))[0];
        $records = app(EmailTreeTraverser::class)->children($unknownSender->key, 1, 3);

        $this->assertCount(1, $records);
        $this->assertSame('Senderless subject', $records[0]->label);
        $this->assertSame('unknown sender', $records[0]->meta['sender']);
        $this->assertSame('message-2', $records[0]->meta['source_ref']);
    }

    public function test_email_children_are_bounded_to_sender_window_and_do_not_hydrate_models(): void
    {
        $retrieved = [
            Email::class => 0,
            EmailImpression::class => 0,
        ];

        Event::listen('eloquent.retrieved: '.Email::class, function () use (&$retrieved): void {
            $retrieved[Email::class]++;
        });
        Event::listen('eloquent.retrieved: '.EmailImpression::class, function () use (&$retrieved): void {
            $retrieved[EmailImpression::class]++;
        });

        $rows = [];

        foreach (range(2, 76) as $index) {
            $rows[] = [
                'message_id' => "sender-message-{$index}",
                'thread_id' => "sender-thread-{$index}",
                'sender' => 'sender@example.com',
                'subject' => "Sender subject {$index}",
                'status' => 'synced',
                'received_at' => sprintf('2026-07-05 12:%02d:00', $index % 60),
            ];
        }

        $rows[] = [
            'message_id' => 'other-message',
            'thread_id' => 'other-thread',
            'sender' => 'other@example.com',
            'subject' => 'Other sender subject',
            'status' => 'synced',
            'received_at' => '2026-07-05 13:00:00',
        ];

        foreach (array_chunk($rows, 25) as $chunk) {
            DB::connection('sidecar')->table('emails')->insert($chunk);
        }

        $records = app(EmailTreeTraverser::class)->children($this->senderNodeKey('sender@example.com'), 1, 3);

        $this->assertCount(50, $records);
        $this->assertSame([Email::class => 0, EmailImpression::class => 0], $retrieved);
        $this->assertNotContains('Other sender subject', array_map(fn ($node) => $node->label, $records));

        foreach ($records as $record) {
            $this->assertSame('sender@example.com', $record->meta['sender']);
        }
    }

    public function test_email_record_labels_fall_back_to_from_name_then_from_email(): void
    {
        Schema::connection('sidecar')->drop('emails');
        Schema::connection('sidecar')->create('emails', function ($table): void {
            $table->string('id')->primary();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();
            $table->text('normalised_body')->nullable();
            $table->timestampTz('received_at')->nullable();
        });

        DB::connection('sidecar')->table('emails')->insert([
            [
                'id' => 'from-name-message',
                'from_email' => 'from@example.com',
                'from_name' => 'Useful Sender Name',
                'subject' => null,
                'normalised_body' => 'Body from named sender.',
                'received_at' => '2026-07-06 09:00:00',
            ],
            [
                'id' => 'from-email-message',
                'from_email' => 'email-only@example.com',
                'from_name' => null,
                'subject' => null,
                'normalised_body' => 'Body from email-only sender.',
                'received_at' => '2026-07-06 09:01:00',
            ],
        ]);

        $fromNameRecords = app(EmailTreeTraverser::class)->children($this->senderNodeKey('from@example.com'), 1, 3);
        $fromEmailRecords = app(EmailTreeTraverser::class)->children($this->senderNodeKey('email-only@example.com'), 1, 3);
        $senderLabels = array_map(fn ($node) => $node->label, app(EmailTreeTraverser::class)->children('domain:email', 0, 3));

        $this->assertSame('Useful Sender Name', $fromNameRecords[0]->label);
        $this->assertSame('from@example.com', $fromNameRecords[0]->meta['sender']);
        $this->assertSame('Useful Sender Name', $fromNameRecords[0]->meta['from_name']);
        $this->assertSame('from@example.com', $fromNameRecords[0]->meta['from_email']);
        $this->assertSame('email-only@example.com', $fromEmailRecords[0]->label);
        $this->assertSame('email-only@example.com', $fromEmailRecords[0]->meta['sender']);
        $this->assertArrayNotHasKey('from_name', $fromEmailRecords[0]->meta);
        $this->assertNotContains('unknown sender', $senderLabels);
    }

    public function test_email_traverser_preserves_native_sidecar_string_ids_when_no_message_id_column_exists(): void
    {
        Schema::connection('sidecar')->drop('emails');
        Schema::connection('sidecar')->create('emails', function ($table): void {
            $table->string('id')->primary();
            $table->string('sender')->nullable();
            $table->string('subject')->nullable();
            $table->text('normalised_body')->nullable();
            $table->timestampTz('received_at')->nullable();
        });

        DB::connection('sidecar')->table('emails')->insert([
            'id' => 'native-message-id',
            'sender' => 'native@example.com',
            'subject' => 'Native id subject',
            'normalised_body' => 'Native body.',
            'received_at' => '2026-07-06 09:00:00',
        ]);

        $senders = app(EmailTreeTraverser::class)->children('domain:email', 0, 3);
        $records = app(EmailTreeTraverser::class)->children($senders[0]->key, 1, 3);

        $this->assertSame('native@example.com', $senders[0]->label);
        $this->assertSame('native-message-id', $records[0]->meta['source_ref']);
        $this->assertStringNotContainsString('record:email:MA', $records[0]->key);
        $this->assertSame('Native body.', $records[0]->meta['email_body']);
    }

    private function configureSqliteOrgan(string $connection): void
    {
        config([
            "database.connections.{$connection}" => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge($connection);
    }

    private function senderNodeKey(string $sender): string
    {
        return 'sender:email:'.rtrim(strtr(base64_encode($sender), '+/', '-_'), '=');
    }

    private function createImpressionsTable(): void
    {
        Schema::connection('impressions')->create('sensemade_impressions', function ($table): void {
            $table->id();
            $table->string('impression_id');
            $table->string('domain');
            $table->string('label')->nullable();
            $table->string('kind')->nullable();
            $table->string('status')->nullable();
            $table->string('source_path')->nullable();
            $table->string('source_ref')->nullable();
            $table->string('thread_id')->nullable();
            $table->timestampTz('observed_at')->nullable();
        });
    }

    private function createDreamstateFeedTable(): void
    {
        Schema::connection('impressions')->create('impressions_dreamstate_feed', function ($table): void {
            $table->id();
            $table->string('impression_id');
            $table->string('domain')->nullable();
            $table->string('label')->nullable();
            $table->string('kind')->nullable();
            $table->string('memory_kind')->nullable();
            $table->string('status')->nullable();
            $table->string('source_path')->nullable();
            $table->string('source_ref')->nullable();
            $table->string('contract_version')->nullable();
            $table->text('raw_corpus')->nullable();
            $table->timestampTz('observed_at')->nullable();
        });
    }

    private function createCameraLensScenePayloadsTable(): void
    {
        Schema::connection('impressions')->create('camera_lens_scene_payloads', function ($table): void {
            $table->string('housed_source_id')->primary();
            $table->string('source_kind');
            $table->string('schema');
            $table->timestampTz('observed_at')->nullable();
            $table->text('raw_corpus')->nullable();
            $table->string('raw_corpus_encoding')->nullable();
            $table->timestampTz('created_at')->nullable();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function cameraLensRow(string $housedSourceId, string $observedAt = '2026-07-05 12:00:00'): array
    {
        return [
            'housed_source_id' => $housedSourceId,
            'source_kind' => 'camera_lens_scene',
            'schema' => 'olo-camera-lens.scene.v1',
            'observed_at' => $observedAt,
            'raw_corpus' => null,
            'raw_corpus_encoding' => 'utf8',
            'created_at' => $observedAt,
        ];
    }

    private function createEmailImpressionsTable(): void
    {
        Schema::connection('impressions')->create('email_impressions', function ($table): void {
            $table->string('impression_id')->primary();
            $table->string('source_ref');
            $table->text('email')->nullable();
            $table->text('state')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function createDreamstateLineageTables(): void
    {
        // The dreamstate lineage lives in a Postgres schema; on the sqlite
        // double an attached database answers the schema-qualified names.
        DB::connection('subconscious')->statement("ATTACH DATABASE ':memory:' AS dreamstate_schema");

        Schema::connection('subconscious')->create('dreamstate_schema.dreamstate_candidates', function ($table): void {
            $table->string('candidate_id');
            $table->string('run_id')->nullable();
            $table->string('impression_id')->nullable();
            $table->string('status')->nullable();
        });

        Schema::connection('subconscious')->create('dreamstate_schema.dreamstate_return_packet', function ($table): void {
            $table->string('packet_id');
            $table->string('run_id')->nullable();
            $table->string('status')->nullable();
        });

        Schema::connection('subconscious')->create('dreamstate_schema.dreamstate_sensemaker_request', function ($table): void {
            $table->string('request_id');
            $table->string('run_id')->nullable();
            $table->string('impression_id')->nullable();
            $table->string('status')->nullable();
            $table->timestampTz('completed_at')->nullable();
        });
    }

    private function dreamstateRow(
        string $impressionId,
        ?string $sourcePath,
        string $domain = 'filesystem',
        string $observedAt = '2026-07-05 12:00:00',
        ?string $rawCorpus = null,
        ?string $memoryKind = null,
        ?string $sourceRef = null,
        ?string $contractVersion = 'impressions_dreamstate_feed_v1',
    ): array {
        return [
            'impression_id' => $impressionId,
            'domain' => $domain,
            'label' => null,
            'kind' => 'file',
            'memory_kind' => $memoryKind,
            'status' => 'observed',
            'source_path' => $sourcePath,
            'source_ref' => $sourceRef,
            'contract_version' => $contractVersion,
            'raw_corpus' => $rawCorpus,
            'observed_at' => $observedAt,
        ];
    }

    private function createSidecarEmailsTable(): void
    {
        Schema::connection('sidecar')->create('emails', function ($table): void {
            $table->id();
            $table->string('message_id');
            $table->string('thread_id')->nullable();
            $table->string('sender')->nullable();
            $table->string('subject')->nullable();
            $table->string('status')->nullable();
            $table->text('body_preview')->nullable();
            $table->text('normalised_body')->nullable();
            $table->text('human_summary')->nullable();
            $table->text('sensemade_text')->nullable();
            $table->text('why_it_matters')->nullable();
            $table->text('recommended_next_step')->nullable();
            $table->timestampTz('received_at')->nullable();
        });
    }

    private function seedSurfaceTreeRecords(): void
    {
        DB::connection('impressions')->table('sensemade_impressions')->insert([
            [
                'impression_id' => 'fs-uuid',
                'domain' => 'filesystem',
                'label' => 'Filesystem impression',
                'kind' => 'file',
                'status' => 'observed',
                'source_path' => 'D:\\OLO-Software\\olo-impressions\\first.md',
                'source_ref' => null,
                'thread_id' => null,
                'observed_at' => '2026-07-05 12:00:00',
            ],
            [
                'impression_id' => 'email-uuid',
                'domain' => 'email',
                'label' => 'Email impression',
                'kind' => 'message',
                'status' => 'observed',
                'source_path' => null,
                'source_ref' => 'message-1',
                'thread_id' => null,
                'observed_at' => '2026-07-05 12:01:00',
            ],
        ]);

        DB::connection('sidecar')->table('emails')->insert([
            'message_id' => 'message-1',
            'thread_id' => 'thread-1',
            'sender' => 'sender@example.com',
            'subject' => 'Sidecar subject',
            'status' => 'synced',
            'body_preview' => 'Short preview.',
            'normalised_body' => 'Full normalised email body.',
            'human_summary' => 'Human-readable summary.',
            'sensemade_text' => 'Sensemade interpretation.',
            'why_it_matters' => 'This affects the current work.',
            'recommended_next_step' => 'Reply with the requested detail.',
            'received_at' => '2026-07-05 11:58:00',
        ]);

        DB::connection('impressions')->table('email_impressions')->insert([
            'impression_id' => 'email-impression-uuid',
            'source_ref' => 'outlook:message-1',
            'email' => json_encode([
                'message_id' => 'message-1',
                'source_ref' => 'outlook:message-1',
            ], JSON_THROW_ON_ERROR),
            'state' => json_encode([
                'sensemade_result' => [
                    'human_summary' => 'Impressions human summary.',
                    'sensemade_text' => 'Impressions sensemade text.',
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => '2026-07-05 12:00:00',
            'updated_at' => '2026-07-05 12:01:00',
        ]);
    }
}

