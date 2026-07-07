<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SurfaceTreeSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_provider_registers_sidebar_surface_tree_render_hook(): void
    {
        $provider = File::get(app_path('Providers/Filament/OloPanelProvider.php'));

        $this->assertStringContainsString('PanelsRenderHook::SIDEBAR_NAV_END', $provider);
        $this->assertStringContainsString("view('filament.sidebar.surface-tree')", $provider);
        $this->assertStringContainsString("request()->routeIs('filament.olo.resources.database-connections.databases.surface-viewer')", $provider);
    }

    public function test_panel_provider_keeps_surface_tree_navigation_item(): void
    {
        $provider = File::get(app_path('Providers/Filament/OloPanelProvider.php'));

        $this->assertStringContainsString("NavigationItem::make('Surface Tree')", $provider);
        $this->assertStringContainsString("route('filament.olo.resources.database-connections.databases.surface-viewer')", $provider);
    }

    public function test_sidebar_blade_partial_is_a_mount_shell_using_named_routes(): void
    {
        $view = File::get(resource_path('views/filament/sidebar/surface-tree.blade.php'));

        $this->assertStringContainsString('id="surface-tree-sidebar"', $view);
        $this->assertStringContainsString('data-surface-tree-sidebar', $view);
        $this->assertStringContainsString("route('surface-tree.nodes.roots')", $view);
        $this->assertStringContainsString("route('surface-tree.nodes.children', ['nodeKey' => '__NODE_KEY__'])", $view);
        $this->assertStringContainsString("@vite('resources/css/surface-tree-sidebar.css')", $view);
        $this->assertStringNotContainsString('<script', $view);
    }

    public function test_sidebar_component_reuses_node_api_with_separate_cache_prefix(): void
    {
        $component = File::get(resource_path('js/components/surface-tree/SurfaceTreeSidebar.vue'));

        $this->assertStringContainsString("import SurfaceTreeNodeComponent from './SurfaceTreeNode.vue';", $component);
        $this->assertStringContainsString('surface-tree-sidebar:${nodeKey}:depth:${depthWindow}:v1', $component);
        $this->assertStringContainsString('loadChildren(node)', $component);
        $this->assertStringContainsString('localStorage.getItem(cacheKey(node.key))', $component);
        $this->assertStringNotContainsString('ImpressionCard', $component);
        $this->assertStringNotContainsString('SurfaceTreeMainContentHost', $component);
        $this->assertStringNotContainsString("'surface-tree:'", $component);
    }

    public function test_sidebar_component_mounts_through_app_bundle(): void
    {
        $appEntry = File::get(resource_path('js/app.js'));

        $this->assertStringContainsString("import SurfaceTreeSidebar from './components/surface-tree/SurfaceTreeSidebar.vue';", $appEntry);
        $this->assertStringContainsString('mountSurfaceTreeSidebar', $appEntry);
        $this->assertStringContainsString('surface-tree-sidebar', $appEntry);
    }

    public function test_sidebar_css_is_scoped_and_registered_with_vite(): void
    {
        $css = File::get(resource_path('css/surface-tree-sidebar.css'));
        $viteConfig = File::get(base_path('vite.config.js'));

        $this->assertStringContainsString('resources/css/surface-tree-sidebar.css', $viteConfig);
        $this->assertStringContainsString('.surface-tree--sidebar', $css);
        $this->assertStringContainsString('text-ellipsis', $css);
        $this->assertStringNotContainsString('.fi-', $css);
        $this->assertStringContainsString('@apply', $css);
        $this->assertStringContainsString('& .surface-tree__children', $css);
        $this->assertStringContainsString('& .surface-tree__label', $css);
    }

    public function test_sidebar_mount_target_renders_only_on_surface_viewer_page(): void
    {
        config(['app.env' => 'local']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('filament.olo.resources.database-connections.databases.surface-viewer'))
            ->assertOk()
            ->assertSee('surface-tree-sidebar', false)
            ->assertSee('data-surface-tree-sidebar', false);

        $this->actingAs($user)
            ->get(route('filament.olo.pages.dashboard'))
            ->assertOk()
            ->assertDontSee('data-surface-tree-sidebar', false);
    }
}
