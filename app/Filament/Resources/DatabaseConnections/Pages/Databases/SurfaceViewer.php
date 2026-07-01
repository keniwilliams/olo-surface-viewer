<?php

namespace App\Filament\Resources\DatabaseConnections\Pages\Databases;

use App\Filament\Resources\DatabaseConnections\DatabaseConnectionResource;
use Filament\Resources\Pages\Page;

class SurfaceViewer extends Page
{
    protected static string $resource = DatabaseConnectionResource::class;

    protected string $view = 'filament.resources.database-connections.pages.databases.surface-viewer';
}
