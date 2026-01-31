<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Support\Filament\Lightbox\LightboxPreviewableAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Hozzárendelt képek';

    protected static ?string $inverseRelationship = 'assignedUser';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('album_id')
                    ->label('Album')
                    ->relationship('album', 'title')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')
                    ->label('Előnézet')
                    ->getStateUsing(function ($record) {
                        $media = $record->getFirstMedia('photo');

                        return $media ? $media->getUrl('thumb') : null;
                    })
                    ->height(40)
                    ->extraImgAttributes([
                        'loading' => 'lazy',
                        'style' => 'object-fit: cover; aspect-ratio: 1/1; cursor: pointer;',
                    ])
                    ->action(LightboxPreviewableAction::make()),

                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('album.title')
                    ->label('Album')
                    ->searchable()
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('notes_count')
                    ->label('Megjegyzések')
                    ->counts('notes')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Feltöltve')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('album_id')
                    ->label('Album')
                    ->relationship('album', 'title'),
            ])
            ->headerActions([
                // No create action - photos are assigned from Album
            ])
            ->actions([
                DetachAction::make()
                    ->label('Hozzárendelés törlése'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Hozzárendelések törlése'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
