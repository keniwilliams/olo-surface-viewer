<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SurfaceTreeNodeApiTest extends TestCase
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

    public function test_roots_endpoint_returns_domain_root_nodes(): void
    {
        $this->getJson('/surface-tree/nodes')
            ->assertOk()
            ->assertJsonPath('meta.depth_window', 3)
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.key', 'domain:filesystem')
            ->assertJsonPath('data.0.label', 'Filesystem')
            ->assertJsonPath('data.0.type', 'domain')
            ->assertJsonPath('data.0.domain', 'filesystem')
            ->assertJsonPath('data.0.impression_id', null)
            ->assertJsonPath('data.0.relation', null)
            ->assertJsonPath('data.0.depth', 0)
            ->assertJsonPath('data.0.has_children', true)
            ->assertJsonPath('data.0.is_terminal_depth', false)
            ->assertJsonPath('data.0.href', null)
            ->assertJsonPath('data.0.meta', [])
            ->assertJsonPath('data.1.key', 'domain:email')
            ->assertJsonPath('data.2.key', 'domain:dreamstate')
            ->assertJsonPath('data.3.key', 'domain:camera_lens');
    }

    public function test_children_endpoint_returns_normalised_child_nodes(): void
    {
        $this->getJson('/surface-tree/nodes/domain:filesystem/children?depth_window=3')
            ->assertOk()
            ->assertJsonPath('meta.depth_window', 3)
            ->assertJsonPath('meta.parent_key', 'domain:filesystem')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'D:')
            ->assertJsonPath('data.0.type', 'folder')
            ->assertJsonPath('data.0.domain', 'filesystem')
            ->assertJsonPath('data.0.impression_id', null)
            ->assertJsonPath('data.0.relation', 'contains')
            ->assertJsonPath('data.0.depth', 1)
            ->assertJsonPath('data.0.has_children', true)
            ->assertJsonPath('data.0.is_terminal_depth', false)
            ->assertJsonPath('data.0.href', null)
            ->assertJsonPath('data.0.meta.source_path', 'D:');
    }

    public function test_children_endpoint_returns_impression_nodes_for_known_folder_node(): void
    {
        $domainChildren = $this->getJson('/surface-tree/nodes/domain:filesystem/children?depth_window=3')->json('data');
        $driveChildren = $this->getJson($this->childrenUrl($domainChildren[0]['key'], 3))->json('data');
        $softwareChildren = $this->getJson($this->childrenUrl($driveChildren[0]['key'], 3))->json('data');

        $this->assertSame('olo-impressions', $softwareChildren[0]['label']);

        $this->getJson($this->childrenUrl($softwareChildren[0]['key'], 3))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'impression:fs-uuid')
            ->assertJsonPath('data.0.type', 'impression')
            ->assertJsonPath('data.0.domain', 'filesystem')
            ->assertJsonPath('data.0.impression_id', 'fs-uuid')
            ->assertJsonPath('data.0.depth', 4)
            ->assertJsonPath('data.0.has_children', false)
            ->assertJsonPath('data.0.is_terminal_depth', false)
            ->assertJsonPath('data.0.href', '/impressions/fs-uuid')
            ->assertJsonPath('data.0.meta.kind', 'file');
    }

    public function test_email_children_endpoint_returns_sender_and_email_record_nodes(): void
    {
        $senderChildren = $this->getJson('/surface-tree/nodes/domain:email/children?depth_window=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'sender@example.com')
            ->assertJsonPath('data.0.type', 'folder')
            ->assertJsonPath('data.0.domain', 'email')
            ->assertJsonPath('data.0.relation', 'from_sender')
            ->assertJsonPath('data.0.meta.sender', 'sender@example.com')
            ->assertJsonPath('data.0.meta.message_count', 1)
            ->assertJsonPath('data.0.meta.latest_subject', 'Sidecar subject')
            ->assertJsonPath('data.0.meta.latest_received_at', '2026-07-05 11:58:00')
            ->assertJsonPath('data.0.meta.latest_source_ref', 'message-1')
            ->assertJsonPath('data.0.meta.latest_human_summary', 'Impressions human summary.')
            ->json('data');

        $this->getJson($this->childrenUrl($senderChildren[0]['key'], 3))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'record')
            ->assertJsonPath('data.0.domain', 'email')
            ->assertJsonPath('data.0.impression_id', null)
            ->assertJsonPath('data.0.href', null)
            ->assertJsonPath('data.0.relation', 'email_listing')
            ->assertJsonPath('data.0.label', 'Sidecar subject')
            ->assertJsonPath('data.0.meta.source_ref', 'message-1')
            ->assertJsonPath('data.0.meta.subject', 'Sidecar subject')
            ->assertJsonPath('data.0.meta.sender', 'sender@example.com')
            ->assertJsonPath('data.0.meta.thread_id', 'thread-1')
            ->assertJsonPath('data.0.meta.related_impression_id', 'email-impression-uuid')
            ->assertJsonPath('data.0.meta.received_at', '2026-07-05 11:58:00')
            ->assertJsonPath('data.0.meta.body_preview', 'Short preview.')
            ->assertJsonPath('data.0.meta.email_body', 'Full normalised email body.')
            ->assertJsonPath('data.0.meta.human_summary', 'Impressions human summary.')
            ->assertJsonPath('data.0.meta.sensemade_text', 'Impressions sensemade text.')
            ->assertJsonPath('data.0.meta.why_it_matters', 'This affects the current work.')
            ->assertJsonPath('data.0.meta.recommended_next_step', 'Reply with the requested detail.');
    }

    public function test_corpus_endpoint_returns_raw_corpus_for_impression(): void
    {
        Schema::connection('impressions')->create('impressions_dreamstate_feed', function ($table): void {
            $table->string('impression_id');
            $table->string('source_path')->nullable();
            $table->text('raw_corpus')->nullable();
            $table->string('raw_corpus_encoding')->nullable();
            $table->timestampTz('observed_at')->nullable();
        });

        DB::connection('impressions')->table('impressions_dreamstate_feed')->insert([
            'impression_id' => 'feed-uuid',
            'source_path' => 'notes.md',
            'raw_corpus' => "# Heading\n\nBody text.",
            'raw_corpus_encoding' => 'utf8',
            'observed_at' => '2026-07-05 12:00:00',
        ]);

        $this->getJson('/surface-tree/impressions/feed-uuid/corpus')
            ->assertOk()
            ->assertJsonPath('data.impression_id', 'feed-uuid')
            ->assertJsonPath('data.raw_corpus', "# Heading\n\nBody text.")
            ->assertJsonPath('meta.read_only', true);
    }

    public function test_corpus_endpoint_returns_null_for_unknown_impression(): void
    {
        $this->getJson('/surface-tree/impressions/missing-uuid/corpus')
            ->assertOk()
            ->assertJsonPath('data.impression_id', 'missing-uuid')
            ->assertJsonPath('data.raw_corpus', null);
    }

    public function test_depth_window_marks_returned_nodes_at_the_terminal_depth(): void
    {
        $this->getJson('/surface-tree/nodes/domain:filesystem/children?depth_window=1')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'D:')
            ->assertJsonPath('data.0.has_children', true)
            ->assertJsonPath('data.0.is_terminal_depth', true);
    }

    public function test_invalid_node_key_returns_not_found(): void
    {
        $this->getJson('/surface-tree/nodes/not-a-known-node/children?depth_window=3')
            ->assertNotFound();
    }

    public function test_invalid_depth_window_returns_validation_response(): void
    {
        $response = $this->getJson('/surface-tree/nodes/domain:filesystem/children?depth_window=0')
            ->assertUnprocessable();

        $this->assertArrayHasKey('depth_window', $response->json('errors'));
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
            $table->text('body_preview')->nullable();
            $table->text('normalised_body')->nullable();
            $table->text('human_summary')->nullable();
            $table->text('sensemade_text')->nullable();
            $table->text('why_it_matters')->nullable();
            $table->text('recommended_next_step')->nullable();
            $table->timestampTz('received_at')->nullable();
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

    private function childrenUrl(string $nodeKey, int $depthWindow): string
    {
        return '/surface-tree/nodes/'.rawurlencode($nodeKey).'/children?depth_window='.$depthWindow;
    }
}

