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

    public function test_email_children_endpoint_returns_sender_and_impression_nodes(): void
    {
        $senderChildren = $this->getJson('/surface-tree/nodes/domain:email/children?depth_window=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'sender@example.com')
            ->assertJsonPath('data.0.type', 'folder')
            ->assertJsonPath('data.0.domain', 'email')
            ->assertJsonPath('data.0.relation', 'from_sender')
            ->json('data');

        $this->getJson($this->childrenUrl($senderChildren[0]['key'], 3))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'impression:email-uuid')
            ->assertJsonPath('data.0.type', 'impression')
            ->assertJsonPath('data.0.domain', 'email')
            ->assertJsonPath('data.0.impression_id', 'email-uuid')
            ->assertJsonPath('data.0.href', '/impressions/email-uuid')
            ->assertJsonPath('data.0.meta.source_ref', 'message-1')
            ->assertJsonPath('data.0.meta.sender', 'sender@example.com')
            ->assertJsonPath('data.0.meta.thread_id', 'thread-1');
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

    private function childrenUrl(string $nodeKey, int $depthWindow): string
    {
        return '/surface-tree/nodes/'.rawurlencode($nodeKey).'/children?depth_window='.$depthWindow;
    }
}
