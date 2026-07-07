<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SurfaceTreePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_surface_tree_blade_is_a_filament_mount_shell(): void
    {
        $view = File::get(resource_path('views/surface-tree/index.blade.php'));

        $this->assertStringContainsString('<x-filament-panels::page>', $view);
        $this->assertStringContainsString("@vite('resources/css/surface-tree.css')", $view);
        $this->assertStringContainsString('surface-tree-browser', $view);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $view);
        $this->assertStringNotContainsString('resources/js/surface-tree.ts', $view);
    }

    public function test_surface_tree_sidebar_component_fetches_roots_and_lazy_loads_children(): void
    {
        $component = File::get(resource_path('js/components/surface-tree/SurfaceTreeSidebar.vue'));

        $this->assertStringContainsString("const depthWindow = 3;", $component);
        $this->assertStringContainsString("rootsUrl: '/surface-tree/nodes',", $component);
        $this->assertStringContainsString('onMounted(() => {', $component);
        $this->assertStringContainsString('fetchRoots();', $component);
        $this->assertStringContainsString('loadChildren(node)', $component);
        $this->assertStringContainsString('fetchSurfaceTreeJson(childrenUrl(node.key))', $component);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $component);
        $this->assertStringNotContainsString('impression:filesystem:first', $component);
    }

    public function test_surface_tree_main_panel_is_selection_driven_and_has_no_tree_view(): void
    {
        $browser = File::get(resource_path('js/components/surface-tree/SurfaceTreeBrowser.vue'));
        $sidebar = File::get(resource_path('js/components/surface-tree/SurfaceTreeSidebar.vue'));

        $this->assertStringContainsString("window.addEventListener('olo:surface-tree:select'", $browser);
        $this->assertStringContainsString("window.removeEventListener('olo:surface-tree:select'", $browser);
        $this->assertStringContainsString('SurfaceTreeMainContentHost', $browser);
        $this->assertStringContainsString('surface-tree__toolbar', $browser);
        $this->assertStringContainsString('emailFilterMode', $browser);
        $this->assertStringContainsString('value="non_sensemade"', $browser);
        $this->assertStringContainsString('nodeMatchesEmailFilter', $sidebar);
        $this->assertStringContainsString("mode === 'non_sensemade'", File::get(resource_path('js/components/surface-tree/emailFilters.ts')));
        $this->assertStringContainsString('olo:surface-tree:email-filter', File::get(resource_path('js/components/surface-tree/emailFilters.ts')));
        $this->assertStringNotContainsString('SurfaceTreeNode.vue', $browser);
        $this->assertStringNotContainsString('fetchRoots', $browser);
        $this->assertStringNotContainsString('surface-tree__nav', $browser);

        $this->assertStringContainsString("window.dispatchEvent(new CustomEvent('olo:surface-tree:select'", $sidebar);
    }

    public function test_surface_tree_keeps_navigation_and_main_content_components_separate(): void
    {
        $treeNode = File::get(resource_path('js/components/surface-tree/SurfaceTreeNode.vue'));
        $mainContentHost = File::get(resource_path('js/components/surface-tree/SurfaceTreeMainContentHost.vue'));
        $types = File::get(resource_path('js/components/surface-tree/types.ts'));

        $this->assertStringNotContainsString('ImpressionCard', $treeNode);
        $this->assertStringNotContainsString('SurfaceTreeMainContentHost', $treeNode);
        $this->assertStringContainsString("import EmailRecordCard from './EmailRecordCard.vue';", $mainContentHost);
        $this->assertStringContainsString("import EmailSenderCard from './EmailSenderCard.vue';", $mainContentHost);
        $this->assertStringContainsString("import ImpressionCard from './ImpressionCard.vue';", $mainContentHost);
        $this->assertStringContainsString("mode: 'empty' | 'impression_card' | 'email_sender_card' | 'email_record_card';", $types);
    }

    public function test_surface_tree_components_include_readability_class_hooks(): void
    {
        $browser = File::get(resource_path('js/components/surface-tree/SurfaceTreeBrowser.vue'));
        $treeNode = File::get(resource_path('js/components/surface-tree/SurfaceTreeNode.vue'));
        $impressionCard = File::get(resource_path('js/components/surface-tree/ImpressionCard.vue'));
        $emailRecordCard = File::get(resource_path('js/components/surface-tree/EmailRecordCard.vue'));
        $emailSenderCard = File::get(resource_path('js/components/surface-tree/EmailSenderCard.vue'));

        foreach ([
            'surface-tree',
            'surface-tree__toolbar',
            'surface-tree__filter-control',
            'surface-tree__filter-icon',
            'surface-tree__filter-select',
            'surface-tree__main',
        ] as $class) {
            $this->assertStringContainsString($class, $browser);
        }

        foreach ([
            'surface-tree__node',
            'surface-tree__row',
            'surface-tree__toggle',
            'surface-tree__toggle-placeholder',
            'surface-tree__label',
            'surface-tree__badge',
            'surface-tree__children',
            'surface-tree__muted',
        ] as $class) {
            $this->assertStringContainsString($class, $treeNode);
        }

        foreach ([
            'surface-tree__card',
            'surface-tree__card-title',
            'surface-tree__details',
            'surface-tree__detail-row',
            'surface-tree__detail-label',
            'surface-tree__detail-value',
            'surface-tree__corpus',
            'surface-tree__corpus-title',
            'surface-tree__corpus-body',
        ] as $class) {
            $this->assertStringContainsString($class, $impressionCard);
        }

        foreach ([
            'surface-tree__card',
            'surface-tree__card-title',
            'surface-tree__details',
            'surface-tree__email-sections',
            'surface-tree__email-section',
            'surface-tree__email-body',
        ] as $class) {
            $this->assertStringContainsString($class, $emailRecordCard);
        }

        foreach ([
            'surface-tree__card',
            'surface-tree__card-title',
            'surface-tree__details',
            'surface-tree__email-list',
        ] as $class) {
            $this->assertStringContainsString($class, $emailSenderCard);
        }
    }

    public function test_email_record_card_renders_email_sensemaking_fields(): void
    {
        $browser = File::get(resource_path('js/components/surface-tree/SurfaceTreeBrowser.vue'));
        $mainContentHost = File::get(resource_path('js/components/surface-tree/SurfaceTreeMainContentHost.vue'));
        $emailRecordCard = File::get(resource_path('js/components/surface-tree/EmailRecordCard.vue'));
        $emailSenderCard = File::get(resource_path('js/components/surface-tree/EmailSenderCard.vue'));
        $surfaceTreeCss = File::get(resource_path('css/surface-tree.css'));

        $this->assertStringContainsString("node.type === 'folder' && node.domain === 'email' && node.relation === 'from_sender'", $browser);
        $this->assertStringContainsString("mode: 'email_sender_card'", $browser);
        $this->assertStringContainsString("node.type === 'record' && node.domain === 'email' && node.relation === 'email_listing'", $browser);
        $this->assertStringContainsString("mode: 'email_record_card'", $browser);
        $this->assertStringContainsString("state.mode === 'email_sender_card'", $mainContentHost);
        $this->assertStringContainsString("state.mode === 'email_record_card'", $mainContentHost);
        $this->assertStringContainsString('/surface-tree/nodes/${encodeURIComponent(nodeKey)}/children?depth_window=3', $emailSenderCard);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $emailSenderCard);
        $this->assertStringContainsString('EmailRecordCard', $emailSenderCard);
        $this->assertStringContainsString('Loading emails...', $emailSenderCard);
        $this->assertStringContainsString("import { formatDateTime } from '../../support/dateFormatter';", $emailSenderCard);
        $this->assertStringContainsString('nodeMatchesEmailFilter', $emailSenderCard);
        $this->assertStringContainsString('visibleMessages', $emailSenderCard);
        $this->assertStringContainsString('No sensemade emails available.', $emailSenderCard);
        $this->assertStringContainsString('No non sensemade emails available.', $emailSenderCard);
        $this->assertStringContainsString('formattedLatestReceivedAt', $emailSenderCard);
        $this->assertStringContainsString("import { formatDateTime } from '../../support/dateFormatter';", $emailRecordCard);
        $this->assertStringContainsString('formattedReceivedAt', $emailRecordCard);
        $this->assertStringContainsString('splitParagraphs(emailBody.value)', $emailRecordCard);
        $this->assertStringContainsString('v-for="paragraph in emailBodyParagraphs"', $emailRecordCard);
        $this->assertStringContainsString('email_body', $emailRecordCard);
        $this->assertStringContainsString('normalised_body', $emailRecordCard);
        $this->assertStringContainsString('body_preview', $emailRecordCard);
        $this->assertStringContainsString('human_summary', $emailRecordCard);
        $this->assertStringContainsString('sensemade_text', $emailRecordCard);
        $this->assertStringContainsString('why_it_matters', $emailRecordCard);
        $this->assertStringContainsString('recommended_next_step', $emailRecordCard);
        $this->assertStringContainsString('No email summary available.', $emailRecordCard);
        $this->assertStringNotContainsString('/surface-tree/impressions/', $emailRecordCard);
        $this->assertStringContainsString('.surface-tree .surface-tree__email-sections', $surfaceTreeCss);
        $this->assertStringContainsString('.surface-tree .surface-tree__email-list', $surfaceTreeCss);
        $this->assertStringContainsString('.surface-tree .surface-tree__email-body', $surfaceTreeCss);
        $this->assertStringContainsString('.surface-tree .surface-tree__email-body p', $surfaceTreeCss);
        $this->assertStringContainsString('.surface-tree .surface-tree__toolbar', $surfaceTreeCss);
        $this->assertStringContainsString('.surface-tree .surface-tree__filter-select', $surfaceTreeCss);
    }

    public function test_impression_card_lazy_loads_raw_corpus_as_read_only_markdown(): void
    {
        $impressionCard = File::get(resource_path('js/components/surface-tree/ImpressionCard.vue'));
        $surfaceTreeCss = File::get(resource_path('css/surface-tree.css'));

        $this->assertStringContainsString("import { marked } from 'marked';", $impressionCard);
        $this->assertStringContainsString('marked.parse(rawCorpus.value', $impressionCard);
        $this->assertStringContainsString('v-html="compiledMarkdown"', $impressionCard);
        $this->assertStringContainsString('/surface-tree/impressions/${encodeURIComponent(id)}/corpus', $impressionCard);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $impressionCard);
        $this->assertStringContainsString('Loading corpus...', $impressionCard);
        $this->assertStringNotContainsString('<textarea', $impressionCard);
        $this->assertStringNotContainsString('contenteditable', $impressionCard);
        $this->assertStringContainsString('.surface-tree .surface-tree__corpus-body', $surfaceTreeCss);
    }

    public function test_surface_tree_component_uses_local_storage_child_cache_and_ttls(): void
    {
        $component = File::get(resource_path('js/components/surface-tree/SurfaceTreeSidebar.vue'));
        $types = File::get(resource_path('js/components/surface-tree/types.ts'));

        $this->assertStringContainsString('CachedSurfaceTreeChildren', $types);
        $this->assertStringContainsString('localStorage.getItem(cacheKey(node.key))', $component);
        $this->assertStringContainsString('localStorage.setItem(cacheKey(node.key), JSON.stringify(payload));', $component);
        $this->assertStringContainsString('new Date(payload.expiresAt) <= new Date()', $component);
        $this->assertStringContainsString('localStorage.removeItem(cacheKey(node.key));', $component);
        $this->assertStringContainsString('surface-tree-sidebar:${nodeKey}:depth:${depthWindow}:v1', $component);
        $this->assertStringContainsString('filesystem: 5 * 60 * 1000', $component);
        $this->assertStringContainsString('email: 2 * 60 * 1000', $component);
        $this->assertStringContainsString('dreamstate: 60 * 1000', $component);
        $this->assertStringContainsString('camera_lens: 60 * 1000', $component);
    }

    public function test_surface_tree_node_renders_lazy_loading_error_and_load_deeper_states(): void
    {
        $treeNode = File::get(resource_path('js/components/surface-tree/SurfaceTreeNode.vue'));

        $this->assertStringContainsString('Loading...', $treeNode);
        $this->assertStringContainsString('role="alert"', $treeNode);
        $this->assertStringContainsString('Load deeper', $treeNode);
        $this->assertStringContainsString("emit('load-deeper', node)", $treeNode);
    }

    public function test_surface_tree_css_is_minimal_and_scoped(): void
    {
        $appCss = File::get(resource_path('css/app.css'));
        $surfaceTreeCss = File::get(resource_path('css/surface-tree.css'));
        $viteConfig = File::get(base_path('vite.config.js'));

        $this->assertStringNotContainsString('surface-tree.css', $appCss);
        $this->assertStringContainsString('resources/css/surface-tree.css', $viteConfig);
        $this->assertStringContainsString('.surface-tree .surface-tree__layout', $surfaceTreeCss);
        $this->assertStringContainsString('grid-template-columns: minmax(280px, 420px) 1fr;', $surfaceTreeCss);
        $this->assertStringNotContainsString('linear-gradient', $surfaceTreeCss);
        $this->assertStringNotContainsString('box-shadow', $surfaceTreeCss);
        $this->assertStringNotContainsString('@apply', $surfaceTreeCss);
        $this->assertStringNotContainsString('animation', $surfaceTreeCss);
        $this->assertStringNotContainsString('gradient', $surfaceTreeCss);
        $this->assertStringNotContainsString('shadow', $surfaceTreeCss);
    }

    public function test_surface_tree_css_is_loaded_only_from_surface_tree_blade_wrappers(): void
    {
        $surfaceViewer = File::get(resource_path('views/filament/resources/database-connections/pages/databases/surface-viewer.blade.php'));
        $surfaceTree = File::get(resource_path('views/surface-tree/index.blade.php'));
        $welcome = File::get(resource_path('views/welcome.blade.php'));

        $this->assertStringContainsString("@vite('resources/css/surface-tree.css')", $surfaceViewer);
        $this->assertStringContainsString("@vite('resources/css/surface-tree.css')", $surfaceTree);
        $this->assertStringNotContainsString('resources/css/surface-tree.css', $welcome);
    }

    public function test_surface_tree_does_not_add_client_database_traversal_or_state_library(): void
    {
        foreach (['SurfaceTreeBrowser.vue', 'SurfaceTreeSidebar.vue'] as $file) {
            $component = File::get(resource_path("js/components/surface-tree/{$file}"));

            $this->assertStringNotContainsString('axios', $component);
            $this->assertStringNotContainsString('pinia', $component);
            $this->assertStringNotContainsString('vuex', $component);
        }
    }

    public function test_filament_surface_viewer_page_renders_surface_tree_mount_target(): void
    {
        $view = File::get(resource_path('views/filament/resources/database-connections/pages/databases/surface-viewer.blade.php'));

        $this->assertStringContainsString('surface-tree-browser', $view);
    }

    public function test_surface_tree_mounts_through_filament_app_bundle_only(): void
    {
        $viteConfig = File::get(base_path('vite.config.js'));
        $appEntry = File::get(resource_path('js/app.js'));

        $this->assertStringContainsString("import SurfaceTreeBrowser from './components/surface-tree/SurfaceTreeBrowser.vue';", $appEntry);
        $this->assertStringContainsString('mountSurfaceTreeBrowser', $appEntry);
        $this->assertStringNotContainsString('resources/js/surface-tree.ts', $viteConfig);
        $this->assertFileDoesNotExist(resource_path('js/surface-tree.ts'));
    }

    public function test_authenticated_filament_surface_viewer_route_renders_surface_tree_mount_target(): void
    {
        config(['app.env' => 'local']);

        $this->actingAs(User::factory()->create())
            ->get(route('filament.olo.resources.database-connections.databases.surface-viewer'))
            ->assertOk()
            ->assertSee('surface-tree-browser', false);
    }
}

