<?php

namespace App\Filament\Resources\DatabaseConnections\Pages;

use App\Filament\Resources\DatabaseConnections\DatabaseConnectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDatabaseConnections extends ListRecords
{
    protected static string $resource = DatabaseConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
