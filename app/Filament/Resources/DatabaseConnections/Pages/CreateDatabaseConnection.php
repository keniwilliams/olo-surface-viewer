<?php

namespace App\Filament\Resources\DatabaseConnections\Pages;

use App\Filament\Resources\DatabaseConnections\DatabaseConnectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDatabaseConnection extends CreateRecord
{
    protected static string $resource = DatabaseConnectionResource::class;
}
