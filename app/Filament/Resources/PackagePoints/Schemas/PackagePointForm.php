<?php

namespace App\Filament\Resources\PackagePoints\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class PackagePointForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('Alapadatok')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('provider')
                                                    ->label('Szolgáltató')
                                                    ->options([
                                                        'foxpost' => 'Foxpost',
                                                        'packeta' => 'Packeta',
                                                    ])
                                                    ->required()
                                                    ->native(false)
                                                    ->columnSpan(1),

                                                TextInput::make('external_id')
                                                    ->label('Külső azonosító')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->helperText('Szolgáltató saját azonosítója')
                                                    ->columnSpan(1),

                                                TextInput::make('name')
                                                    ->label('Név')
                                                    ->placeholder('pl. Foxpost Automata - Budai Skála')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),

                                                Toggle::make('is_active')
                                                    ->label('Aktív')
                                                    ->default(true)
                                                    ->inline(false)
                                                    ->columnSpan(2),

                                                Textarea::make('opening_hours')
                                                    ->label('Nyitvatartás')
                                                    ->placeholder('JSON formátumban vagy szöveges leírás...')
                                                    ->rows(3)
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Cím és Helyszín')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('city')
                                                    ->label('Város')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(1),

                                                TextInput::make('zip')
                                                    ->label('Irányítószám')
                                                    ->required()
                                                    ->maxLength(10)
                                                    ->columnSpan(1),

                                                TextInput::make('address')
                                                    ->label('Utca, házszám')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),

                                                TextInput::make('latitude')
                                                    ->label('Szélesség (latitude)')
                                                    ->required()
                                                    ->numeric()
                                                    ->step(0.0000001)
                                                    ->helperText('GPS koordináta')
                                                    ->columnSpan(1),

                                                TextInput::make('longitude')
                                                    ->label('Hosszúság (longitude)')
                                                    ->required()
                                                    ->numeric()
                                                    ->step(0.0000001)
                                                    ->helperText('GPS koordináta')
                                                    ->columnSpan(1),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Technikai adatok')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        DateTimePicker::make('last_synced_at')
                                            ->label('Utolsó szinkronizálás')
                                            ->disabled()
                                            ->helperText('Az API-ból való utolsó adatfrissítés időpontja'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
