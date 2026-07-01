<?php

namespace App\Filament\Resources\DatabaseConnections\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DatabaseConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('connection_key')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('host')
                    ->required()
                    ->maxLength(255)
                    ->default('127.0.0.1'),

                TextInput::make('port')
                    ->required()
                    ->numeric(),

                TextInput::make('database')
                    ->required()
                    ->maxLength(255),

                TextInput::make('username')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->columnSpanFull(),

                Toggle::make('is_enabled')
                    ->default(true),
            ]);
    }
}
