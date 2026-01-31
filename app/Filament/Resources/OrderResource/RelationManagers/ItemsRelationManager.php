<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('size')
                    ->label('Méret')
                    ->required()
                    ->maxLength(255),

                TextInput::make('quantity')
                    ->label('Mennyiség')
                    ->required()
                    ->numeric()
                    ->default(1),

                TextInput::make('unit_price_huf')
                    ->label('Egységár (Ft)')
                    ->required()
                    ->numeric()
                    ->suffix('Ft'),

                TextInput::make('total_price_huf')
                    ->label('Összesen (Ft)')
                    ->required()
                    ->numeric()
                    ->suffix('Ft'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('size')
            ->columns([
                TextColumn::make('photo.id')
                    ->label('Fotó #')
                    ->default('-')
                    ->sortable(),

                TextColumn::make('size')
                    ->label('Méret')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Mennyiség')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('unit_price_huf')
                    ->label('Egységár')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', ' ').' Ft')
                    ->sortable(),

                TextColumn::make('total_price_huf')
                    ->label('Összesen')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', ' ').' Ft')
                    ->sortable()
                    ->weight('bold'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
