<?php

namespace App\Filament\Resources\DatabaseConnections\Pages\Databases;

use App\Filament\Resources\DatabaseConnections\DatabaseConnectionResource;
use Filament\Resources\Pages\Page;

class Cockpit extends Page
{
    protected static string $resource = DatabaseConnectionResource::class;

    protected string $view = 'filament.resources.database-connections.pages.databases.cockpit';

    public function getTitle(): string
    {
        return 'Observation Cockpit';
    }

    public function getHeading(): ?string
    {
        return null;
    }
}
