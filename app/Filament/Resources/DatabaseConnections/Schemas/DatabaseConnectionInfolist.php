<?php

namespace App\Filament\Resources\DatabaseConnections\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DatabaseConnectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Name'),

                TextEntry::make('connection_key')
                    ->label('Connection')
                    ->badge(),

                TextEntry::make('host')
                    ->label('Host'),

                TextEntry::make('port')
                    ->label('Port'),

                TextEntry::make('database')
                    ->label('Database'),

                TextEntry::make('username')
                    ->label('Username'),

                IconEntry::make('is_enabled')
                    ->label('Enabled')
                    ->boolean(),

                TextEntry::make('description')
                    ->label('Description')
                    ->columnSpanFull(),

                TextEntry::make('created_at')
                    ->label('Created')
                    ->dateTime(),

                TextEntry::make('updated_at')
                    ->label('Updated')
                    ->dateTime(),
            ]);
    }
}
