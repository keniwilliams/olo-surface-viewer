<?php

namespace App\Filament\Resources\DatabaseConnections\Pages\Databases;

use App\Filament\Resources\DatabaseConnections\DatabaseConnectionResource;
use App\Support\DatabaseConnectionStatus;
use Filament\Resources\Pages\Page;

class Overview extends Page
{
    protected static string $resource = DatabaseConnectionResource::class;

    protected string $view = 'filament.resources.database-connections.pages.databases.overview';

    public function getTitle(): string
    {
        return 'Database Overview';
    }

    public function getHeading(): ?string
    {
        return null;
    }

    public function getViewData(): array
    {
        return [
            'databases' => DatabaseConnectionStatus::all(),
        ];
    }
}
