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
