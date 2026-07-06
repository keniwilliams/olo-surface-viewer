<?php

namespace Tests\Feature;

use App\Services\SurfaceTree\EmailTreeTraverser;
use App\Services\SurfaceTree\FilesystemTreeTraverser;
use Illuminate\Support\Facades\DB;
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

    public function test_email_traverser_groups_impressions_by_sender_and_enriches_from_sidecar(): void
    {
        $traverser = app(EmailTreeTraverser::class);

        $senders = $traverser->children('domain:email', 0, 3);
        $this->assertCount(1, $senders);
        $this->assertSame('sender@example.com', $senders[0]->label);
        $this->assertSame('folder', $senders[0]->type);
        $this->assertSame('from_sender', $senders[0]->relation);

        $impressions = $traverser->children($senders[0]->key, 1, 3);
        $this->assertCount(1, $impressions);
        $this->assertSame('impression:email-uuid', $impressions[0]->key);
        $this->assertSame('impression', $impressions[0]->type);
        $this->assertSame('email-uuid', $impressions[0]->impressionId);
        $this->assertSame('/impressions/email-uuid', $impressions[0]->href);
        $this->assertSame('message-1', $impressions[0]->meta['source_ref']);
        $this->assertSame('sender@example.com', $impressions[0]->meta['sender']);
        $this->assertSame('thread-1', $impressions[0]->meta['thread_id']);
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
            'received_at' => '2026-07-05 11:58:00',
        ]);
    }
}
