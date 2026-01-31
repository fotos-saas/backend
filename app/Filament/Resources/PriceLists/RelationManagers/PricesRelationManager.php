<?php

namespace App\Filament\Resources\PriceLists\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Árak';

    protected static ?string $label = 'Ár';

    protected static ?string $pluralLabel = 'Árak';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('print_size_id')
                    ->label('Nyomtatási méret')
                    ->relationship('printSize', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                TextInput::make('price')
                    ->label('Nettó ár')
                    ->required()
                    ->numeric()
                    ->minValue(0),

                Repeater::make('volume_discounts')
                    ->label('Mennyiségi kedvezmények')
                    ->schema([
                        TextInput::make('minQty')
                            ->label('Min. mennyiség')
                            ->required()
                            ->numeric()
                            ->minValue(1),

                        TextInput::make('percentOff')
                            ->label('Kedvezmény %')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),
                    ])
                    ->defaultItems(0)
                    ->columns(2)
                    ->collapsible()
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('printSize.name')
                    ->label('Méret')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Nettó ár')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', ' ').' Ft')
                    ->sortable(),

                TextColumn::make('volume_discounts')
                    ->label('Mennyiségi kedv.')
                    ->state(function ($record) {
                        if (empty($record->volume_discounts)) {
                            return '—';
                        }
                        $discounts = collect($record->volume_discounts);

                        return $discounts->map(fn ($d) => "{$d['minQty']}+ db: {$d['percentOff']}%")->join(', ');
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('printSize.name');
    }
}
