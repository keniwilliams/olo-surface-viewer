<?php

namespace Tests\Feature;

use App\Models\Impressions\EmailImpression;
use App\Models\Sidecar\Email;
use App\Services\SurfaceTree\DomainImpressionsTraverser;
use App\Services\SurfaceTree\EmailTreeTraverser;
use App\Services\SurfaceTree\FilesystemTreeTraverser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SurfaceTreeTraverserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureSqliteOrgan('impressions');
        $this->configureSqliteOrgan('sidecar');
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

    public function test_domain_traverser_lists_impressions_for_dreamstate_and_camera_lens(): void
    {
        $this->createDreamstateFeedTable();

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            $this->dreamstateRow('dream-old', null, domain: 'dreamstate', observedAt: '2026-07-04 12:00:00'),
            $this->dreamstateRow('dream-new', null, domain: 'dreamstate', observedAt: '2026-07-05 12:00:00'),
            $this->dreamstateRow('lens-uuid', null, domain: 'camera_lens'),
            $this->dreamstateRow('fs-only', 'D:\\Projects\\notes.md', domain: 'filesystem'),
        ]);

        $traverser = app(DomainImpressionsTraverser::class);

        $dreamstate = $traverser->children('domain:dreamstate', 0, 3);
        $this->assertSame(['impression:dream-new', 'impression:dream-old'], array_map(fn ($node) => $node->key, $dreamstate));
        $this->assertSame('impression', $dreamstate[0]->type);
        $this->assertSame('dreamstate', $dreamstate[0]->domain);
        $this->assertSame('dream-new', $dreamstate[0]->impressionId);
        $this->assertSame('/impressions/dream-new', $dreamstate[0]->href);
        $this->assertSame('file', $dreamstate[0]->meta['kind']);

        $cameraLens = $traverser->children('domain:camera_lens', 0, 3);
        $this->assertSame(['impression:lens-uuid'], array_map(fn ($node) => $node->key, $cameraLens));
        $this->assertSame('camera_lens', $cameraLens[0]->domain);

        $this->assertSame([], $traverser->children('domain:filesystem', 0, 3));
    }

    public function test_domain_traverser_returns_empty_when_source_has_no_domain_column(): void
    {
        Schema::connection('impressions')->drop('sensemade_impressions');
        Schema::connection('impressions')->create('sensemade_impressions', function ($table): void {
            $table->id();
            $table->string('impression_id');
            $table->timestampTz('observed_at')->nullable();
        });

        DB::connection('impressions')->table('sensemade_impressions')->insert([
            'impression_id' => 'no-domain-uuid',
            'observed_at' => '2026-07-05 12:00:00',
        ]);

        $this->assertSame([], app(DomainImpressionsTraverser::class)->children('domain:dreamstate', 0, 3));
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
            $table->string('status')->nullable();
            $table->string('source_path')->nullable();
            $table->string('source_ref')->nullable();
            $table->text('raw_corpus')->nullable();
            $table->timestampTz('observed_at')->nullable();
        });
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
    private function dreamstateRow(
        string $impressionId,
        ?string $sourcePath,
        string $domain = 'filesystem',
        string $observedAt = '2026-07-05 12:00:00',
        ?string $rawCorpus = null,
    ): array {
        return [
            'impression_id' => $impressionId,
            'domain' => $domain,
            'label' => null,
            'kind' => 'file',
            'status' => 'observed',
            'source_path' => $sourcePath,
            'source_ref' => null,
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

