<?php

namespace App\Filament\Resources\DatabaseConnections\Pages\Databases;

use App\Filament\Resources\DatabaseConnections\DatabaseConnectionResource;
use App\Services\Bloodstream\BloodstreamObserverPanelState;
use Filament\Resources\Pages\Page;

class Bloodstream extends Page
{
    protected static string $resource = DatabaseConnectionResource::class;

    protected string $view = 'filament.resources.database-connections.pages.databases.bloodstream';

    public function getTitle(): string
    {
        return 'Bloodstream';
    }

    public function getViewData(): array
    {
        $panelState = app(BloodstreamObserverPanelState::class);
        $observer = $panelState->snapshot();

        if ($observer['status'] === 'unknown') {
            $observer = $panelState->refresh();
        }

        return [
            'observer' => $observer,
        ];
    }
}
