<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SurfaceTreePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_surface_tree_route_is_registered(): void
    {
        $this->assertTrue(Route::has('surface-tree.index'));
    }

    public function test_surface_tree_page_renders_vue_mount_target(): void
    {
        $this->get('/surface-tree')
            ->assertOk()
            ->assertSee('surface-tree-browser', false);
    }

    public function test_surface_tree_blade_wires_dedicated_vite_entry(): void
    {
        $view = File::get(resource_path('views/surface-tree/index.blade.php'));

        $this->assertStringContainsString('resources/css/app.css', $view);
        $this->assertStringContainsString('resources/js/surface-tree.ts', $view);
    }

    public function test_surface_tree_component_includes_static_impression_children(): void
    {
        $component = File::get(resource_path('js/components/surface-tree/SurfaceTreeBrowser.vue'));

        $this->assertStringContainsString('impression:filesystem:first', $component);
        $this->assertStringContainsString('impression:email:first', $component);
        $this->assertStringContainsString('impression:dreamstate:first', $component);
        $this->assertStringContainsString('impression:camera_lens:first', $component);
    }

    public function test_filament_surface_viewer_page_renders_surface_tree_mount_target(): void
    {
        $view = File::get(resource_path('views/filament/resources/database-connections/pages/databases/surface-viewer.blade.php'));

        $this->assertStringContainsString('surface-tree-browser', $view);
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
