<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class PaymentMethodForm
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
                                                    ->placeholder('pl. Bankkártyás fizetés')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(1),

                                                Select::make('type')
                                                    ->label('Típus')
                                                    ->options([
                                                        'card' => 'Bankkártya',
                                                        'transfer' => 'Átutalás',
                                                        'cash' => 'Készpénz',
                                                    ])
                                                    ->required()
                                                    ->native(false)
                                                    ->live()
                                                    ->columnSpan(1),

                                                Toggle::make('is_active')
                                                    ->label('Aktív')
                                                    ->default(true)
                                                    ->inline(false)
                                                    ->columnSpan(1),

                                                TextInput::make('sort_order')
                                                    ->label('Sorrend')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->helperText('Kisebb szám = előrébb jelenik meg')
                                                    ->columnSpan(1),

                                                Textarea::make('description')
                                                    ->label('Leírás')
                                                    ->placeholder('Rövid leírás a fizetési módról...')
                                                    ->rows(3)
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Megjelenítés')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextInput::make('icon')
                                            ->label('Ikon')
                                            ->placeholder('pl. heroicon-o-credit-card')
                                            ->helperText('Heroicon osztálynév vagy SVG path')
                                            ->maxLength(255),
                                    ]),
                            ]),

                        Tabs\Tab::make('Banki átutalás adatok')
                            ->schema([
                                Section::make()
                                    ->description('Banki átutaláshoz szükséges adatok, amelyeket a vásárlók látni fognak.')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('bank_account_number')
                                                    ->label('Bankszámlaszám')
                                                    ->placeholder('pl. 12345678-12345678-12345678')
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                TextInput::make('account_holder_name')
                                                    ->label('Kedvezményezett neve')
                                                    ->placeholder('pl. Példa Kft.')
                                                    ->maxLength(255)
                                                    ->columnSpan(1),

                                                TextInput::make('bank_name')
                                                    ->label('Bank neve')
                                                    ->placeholder('pl. OTP Bank')
                                                    ->maxLength(255)
                                                    ->columnSpan(1),
                                            ]),
                                    ]),
                            ])
                            ->visible(fn ($get): bool => $get('type') === 'transfer'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
