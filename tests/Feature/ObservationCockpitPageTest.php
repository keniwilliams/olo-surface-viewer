<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ObservationCockpitPageTest extends TestCase
{
    public function test_filament_cockpit_route_is_registered(): void
    {
        $this->assertTrue(Route::has('filament.olo.resources.database-connections.databases.cockpit'));
    }

    public function test_observation_cockpit_is_registered_in_sidebar_navigation(): void
    {
        $items = collect(Filament::getPanel('olo')->getNavigation())
            ->flatMap(fn ($group) => $group->getItems());

        $item = $items->first(fn ($item) => $item->getLabel() === 'Observation Cockpit');

        $this->assertNotNull($item);
        $this->assertSame(
            route('filament.olo.resources.database-connections.databases.cockpit'),
            $item->getUrl(),
        );
    }

    public function test_cockpit_blade_holder_renders_vue_mount_target(): void
    {
        $view = File::get(resource_path('views/filament/resources/database-connections/pages/databases/cockpit.blade.php'));

        $this->assertStringContainsString('olo-observation-cockpit', $view);
        $this->assertStringContainsString('/api/organs/state', $view);
        $this->assertStringContainsString('/api/activity/recent', $view);
    }
}
