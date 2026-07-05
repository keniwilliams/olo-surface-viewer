<?php

namespace App\Filament\Resources\DatabaseConnections;

use App\Filament\Resources\DatabaseConnections\Schemas\DatabaseConnectionForm;
use App\Filament\Resources\DatabaseConnections\Schemas\DatabaseConnectionInfolist;
use App\Filament\Resources\DatabaseConnections\Tables\DatabaseConnectionsTable;
use App\Models\DatabaseConnection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DatabaseConnectionResource extends Resource
{
    protected static ?string $model = DatabaseConnection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DatabaseConnectionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DatabaseConnectionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DatabaseConnectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDatabaseConnections::route('/'),
            'create' => Pages\CreateDatabaseConnection::route('/create'),

            'databases.cockpit' => Pages\Databases\Cockpit::route('/databases/cockpit'),
            'databases.overview' => Pages\Databases\Overview::route('/databases/overview'),
            'databases.surface-viewer' => Pages\Databases\SurfaceViewer::route('/databases/surface-viewer'),
            'databases.bloodstream' => Pages\Databases\Bloodstream::route('/databases/bloodstream'),
            'databases.subconscious' => Pages\Databases\Subconscious::route('/databases/subconscious'),
            'databases.impressions' => Pages\Databases\Impressions::route('/databases/impressions'),
            'databases.sidecar' => Pages\Databases\Sidecar::route('/databases/sidecar'),

            'view' => Pages\ViewDatabaseConnection::route('/{record}'),
            'edit' => Pages\EditDatabaseConnection::route('/{record}/edit'),
        ];
    }
}
