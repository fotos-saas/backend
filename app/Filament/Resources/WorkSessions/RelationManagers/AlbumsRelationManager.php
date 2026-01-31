<?php

namespace App\Filament\Resources\WorkSessions\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AlbumsRelationManager extends RelationManager
{
    protected static string $relationship = 'albums';

    protected static ?string $title = 'Albumok';

    protected static ?string $modelLabel = 'album';

    protected static ?string $pluralModelLabel = 'albumok';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->label('Cím')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('name')
                    ->label('Név')
                    ->maxLength(255),

                Forms\Components\Select::make('status')
                    ->label('Státusz')
                    ->options([
                        'draft' => 'Piszkozat',
                        'active' => 'Aktív',
                        'archived' => 'Archivált',
                    ])
                    ->default('draft')
                    ->required(),

                Forms\Components\DatePicker::make('date')
                    ->label('Dátum')
                    ->default(now()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Cím')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('photos_count')
                    ->label('Képek')
                    ->counts('photos')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Státusz')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'draft' => 'gray',
                        'active' => 'success',
                        'archived' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft' => 'Piszkozat',
                        'active' => 'Aktív',
                        'archived' => 'Archivált',
                        default => ucfirst((string) $state),
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozás dátuma')
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Státusz')
                    ->options([
                        'draft' => 'Piszkozat',
                        'active' => 'Aktív',
                        'archived' => 'Archivált',
                    ]),

                SelectFilter::make('class_id')
                    ->label('Osztály')
                    ->relationship('class', 'label'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Új album')
                    ->visible(fn () => !$this->getOwnerRecord()->parent_work_session_id),
                Action::make('attach_albums')
                    ->label('Albumok hozzáadása')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Albumok hozzáadása')
                    ->visible(fn () => !$this->getOwnerRecord()->parent_work_session_id)
                    ->form([
                        Forms\Components\Select::make('album_ids')
                            ->label('Albumok')
                            ->multiple()
                            ->searchable()
                            ->options(
                                \App\Models\Album::query()
                                    ->select(['id', 'title'])
                                    ->pluck('title', 'id')
                            )
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $workSession = $this->getOwnerRecord();
                        $workSession->albums()->attach($data['album_ids']);

                        \Filament\Notifications\Notification::make()
                            ->title('Albumok hozzáadva')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('view_album')
                        ->label('Albumhoz navigálás')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('info')
                        ->url(fn ($record) => \App\Filament\Resources\AlbumResource::getUrl('edit', ['record' => $record->id]))
                        ->openUrlInNewTab(),
                    EditAction::make()
                        ->label('Szerkesztés'),
                    DetachAction::make()
                        ->label('Eltávolítás'),
                ])
                    ->label('Műveletek')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Kijelöltek eltávolítása'),
                ]),
            ]);
    }
}
