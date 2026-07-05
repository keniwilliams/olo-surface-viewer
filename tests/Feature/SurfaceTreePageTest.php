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
        $this->assertStringContainsString('surface-tree-browser', $view);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $view);
        $this->assertStringNotContainsString('resources/js/surface-tree.ts', $view);
    }

    public function test_surface_tree_component_fetches_roots_and_lazy_loads_children(): void
    {
        $component = File::get(resource_path('js/components/surface-tree/SurfaceTreeBrowser.vue'));

        $this->assertStringContainsString("const depthWindow = 3;", $component);
        $this->assertStringContainsString("const rootsUrl = '/surface-tree/nodes';", $component);
        $this->assertStringContainsString('onMounted(() => {', $component);
        $this->assertStringContainsString('fetchRoots();', $component);
        $this->assertStringContainsString('loadChildren(node)', $component);
        $this->assertStringContainsString('fetchSurfaceTreeJson(childrenUrl(node.key))', $component);
        $this->assertStringNotContainsString('impression:filesystem:first', $component);
    }

    public function test_surface_tree_keeps_navigation_and_main_content_components_separate(): void
    {
        $treeNode = File::get(resource_path('js/components/surface-tree/SurfaceTreeNode.vue'));
        $mainContentHost = File::get(resource_path('js/components/surface-tree/SurfaceTreeMainContentHost.vue'));
        $types = File::get(resource_path('js/components/surface-tree/types.ts'));

        $this->assertStringNotContainsString('ImpressionCard', $treeNode);
        $this->assertStringNotContainsString('SurfaceTreeMainContentHost', $treeNode);
        $this->assertStringContainsString("import ImpressionCard from './ImpressionCard.vue';", $mainContentHost);
        $this->assertStringContainsString("mode: 'empty' | 'impression_card';", $types);
    }

    public function test_surface_tree_components_include_readability_class_hooks(): void
    {
        $browser = File::get(resource_path('js/components/surface-tree/SurfaceTreeBrowser.vue'));
        $treeNode = File::get(resource_path('js/components/surface-tree/SurfaceTreeNode.vue'));
        $impressionCard = File::get(resource_path('js/components/surface-tree/ImpressionCard.vue'));

        foreach (['surface-tree', 'surface-tree__layout', 'surface-tree__nav', 'surface-tree__main'] as $class) {
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
        ] as $class) {
            $this->assertStringContainsString($class, $impressionCard);
        }
    }

    public function test_surface_tree_component_uses_local_storage_child_cache_and_ttls(): void
    {
        $component = File::get(resource_path('js/components/surface-tree/SurfaceTreeBrowser.vue'));
        $types = File::get(resource_path('js/components/surface-tree/types.ts'));

        $this->assertStringContainsString('CachedSurfaceTreeChildren', $types);
        $this->assertStringContainsString('localStorage.getItem(cacheKey(node.key))', $component);
        $this->assertStringContainsString('localStorage.setItem(cacheKey(node.key), JSON.stringify(payload));', $component);
        $this->assertStringContainsString('new Date(payload.expiresAt) <= new Date()', $component);
        $this->assertStringContainsString('localStorage.removeItem(cacheKey(node.key));', $component);
        $this->assertStringContainsString('surface-tree:${nodeKey}:depth:${depthWindow}:v1', $component);
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

        $this->assertStringContainsString("@import './surface-tree.css';", $appCss);
        $this->assertStringContainsString('.surface-tree__layout', $surfaceTreeCss);
        $this->assertStringContainsString('grid-template-columns: minmax(280px, 420px) 1fr;', $surfaceTreeCss);
        $this->assertStringNotContainsString('linear-gradient', $surfaceTreeCss);
        $this->assertStringNotContainsString('box-shadow', $surfaceTreeCss);
        $this->assertStringNotContainsString('@apply', $surfaceTreeCss);
    }

    public function test_surface_tree_does_not_add_client_database_traversal_or_state_library(): void
    {
        $component = File::get(resource_path('js/components/surface-tree/SurfaceTreeBrowser.vue'));

        $this->assertStringNotContainsString('axios', $component);
        $this->assertStringNotContainsString('pinia', $component);
        $this->assertStringNotContainsString('vuex', $component);
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
