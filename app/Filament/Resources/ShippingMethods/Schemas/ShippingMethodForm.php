<?php

namespace App\Filament\Resources\ShippingMethods\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class ShippingMethodForm
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
                                                TextInput::make('name')
                                                    ->label('Megnevezés')
                                                    ->placeholder('pl. MPL futárszolgálat')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(1),

                                                Select::make('type')
                                                    ->label('Típus')
                                                    ->options([
                                                        'courier' => 'Futár',
                                                        'parcel_locker' => 'Csomagautomata',
                                                        'letter' => 'Levél',
                                                        'pickup' => 'Személyes átvétel',
                                                    ])
                                                    ->required()
                                                    ->native(false)
                                                    ->columnSpan(1),

                                                Select::make('provider')
                                                    ->label('Szolgáltató')
                                                    ->options([
                                                        'foxpost' => 'Foxpost',
                                                        'packeta' => 'Packeta',
                                                        'mpl' => 'Magyar Posta',
                                                        'other' => 'Egyéb',
                                                    ])
                                                    ->native(false)
                                                    ->columnSpan(1),

                                                Toggle::make('is_active')
                                                    ->label('Aktív')
                                                    ->default(true)
                                                    ->inline(false)
                                                    ->columnSpan(1),

                                                Toggle::make('is_default')
                                                    ->label('Alapértelmezett szállítási mód')
                                                    ->helperText('Csak egy szállítási mód lehet alapértelmezett')
                                                    ->default(false)
                                                    ->inline(false)
                                                    ->columnSpan(1),

                                                TextInput::make('sort_order')
                                                    ->label('Sorrend')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->helperText('Kisebb szám = előrébb jelenik meg')
                                                    ->columnSpan(2),

                                                Textarea::make('description')
                                                    ->label('Leírás')
                                                    ->placeholder('Rövid leírás a szállítási módról...')
                                                    ->rows(3)
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Korlátozások')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Toggle::make('requires_address')
                                                    ->label('Szállítási cím szükséges')
                                                    ->helperText('Be kell-e kérni a vásárló címét')
                                                    ->default(false)
                                                    ->inline(false)
                                                    ->columnSpan(1),

                                                Toggle::make('requires_parcel_point')
                                                    ->label('Csomagpont választás szükséges')
                                                    ->helperText('Kell-e csomagpontot választani')
                                                    ->default(false)
                                                    ->inline(false)
                                                    ->columnSpan(1),

                                                TextInput::make('min_weight_grams')
                                                    ->label('Minimum súly (gramm)')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->suffix('g')
                                                    ->helperText('Minimum csomag súly')
                                                    ->columnSpan(1),

                                                TextInput::make('max_weight_grams')
                                                    ->label('Maximum súly (gramm)')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->suffix('g')
                                                    ->helperText('Maximum csomag súly')
                                                    ->columnSpan(1),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Árképzés')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Toggle::make('supports_cod')
                                                    ->label('Utánvétes fizetés támogatott')
                                                    ->default(false)
                                                    ->inline(false)
                                                    ->live()
                                                    ->columnSpan(2),

                                                TextInput::make('cod_fee_huf')
                                                    ->label('Utánvét díj')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->suffix('Ft')
                                                    ->mask(RawJs::make('$money($input, \' \', \'.\', 0)'))
                                                    ->helperText('Az érték automatikusan formázva jelenik meg')
                                                    ->visible(fn ($get) => $get('supports_cod'))
                                                    ->columnSpan(2),
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
