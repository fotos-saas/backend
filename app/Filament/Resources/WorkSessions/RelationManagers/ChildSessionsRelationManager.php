<?php

namespace App\Filament\Resources\WorkSessions\RelationManagers;

use App\Models\Photo;
use App\Models\TabloUserProgress;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChildSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'childSessions';

    protected static ?string $title = 'Almunkamenetek';

    /**
     * Determine if the relation manager can be viewed for the given record
     */
    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        // Only show child sessions tab if this is NOT a child session itself
        return $ownerRecord->parent_work_session_id === null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Munkamenet neve')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Státusz')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'warning' => 'draft',
                        'danger' => 'cancelled',
                        'gray' => 'completed',
                    ])
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Felhasználók')
                    ->counts('users')
                    ->sortable()
                    ->visible(fn () => !$this->getOwnerRecord()->is_tablo_mode),

                TextColumn::make('claimed_photos_count')
                    ->label('Kiválasztott képek')
                    ->getStateUsing(function ($record) {
                        $progressRecords = TabloUserProgress::where('child_work_session_id', $record->id)->get();

                        $totalClaimed = 0;
                        foreach ($progressRecords as $progress) {
                            if ($progress->steps_data && isset($progress->steps_data['claimed_photo_ids'])) {
                                $totalClaimed += count($progress->steps_data['claimed_photo_ids']);
                            }
                        }

                        return $totalClaimed;
                    })
                    ->badge()
                    ->color('info')
                    ->sortable(false)
                    ->default(0),

                TextColumn::make('workflow_status')
                    ->label('Folyamat állapot')
                    ->getStateUsing(function ($record) {
                        $progressRecords = TabloUserProgress::where('child_work_session_id', $record->id)->get();

                        if ($progressRecords->isEmpty()) {
                            return '-';
                        }

                        $totalUsers = $progressRecords->count();
                        $completedUsers = $progressRecords->where('current_step', 'completed')->count();

                        // Ha minden user completed
                        if ($totalUsers === $completedUsers) {
                            return 'completed';
                        }

                        // Egyébként mutassuk a leggyakoribb lépést
                        $stepCounts = $progressRecords->groupBy('current_step')->map->count();
                        $mostCommonStep = $stepCounts->sortDesc()->keys()->first();

                        return "{$completedUsers}/{$totalUsers}|{$mostCommonStep}";
                    })
                    ->formatStateUsing(function ($state) {
                        if ($state === 'completed' || $state === '-') {
                            return $state === 'completed' ? 'Befejezve' : $state;
                        }

                        [$progress, $step] = explode('|', $state);

                        $stepName = match($step) {
                            'claiming' => 'Kiválasztás',
                            'registration' => 'Regisztráció',
                            'retouch' => 'Retusálás',
                            'tablo' => 'Tabló',
                            default => ucfirst($step),
                        };

                        return "{$progress} {$stepName}";
                    })
                    ->badge()
                    ->color(function ($state) {
                        if ($state === 'completed') {
                            return 'success';
                        }
                        if ($state === '-') {
                            return 'gray';
                        }

                        // Extract step from "0/1|claiming" format
                        $step = explode('|', $state)[1] ?? '';

                        return match($step) {
                            'claiming' => 'warning',
                            'registration' => 'info',
                            'retouch' => 'primary',
                            'tablo' => 'success',
                            default => 'gray',
                        };
                    })
                    ->sortable(false),

                TextColumn::make('albums_count')
                    ->label('Albumok')
                    ->counts('albums')
                    ->sortable()
                    ->visible(fn () => !$this->getOwnerRecord()->is_tablo_mode),

                TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // No create/associate actions - child sessions are auto-created
                // Nincs létrehozás/hozzárendelés művelet - az almunkamenetek automatikusan jönnek létre
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Megtekintés/Szerkesztés')
                    ->url(fn ($record) => route('filament.admin.resources.work-sessions.edit', ['record' => $record->id]))
                    ->openUrlInNewTab(false),
            ])
            ->toolbarActions([
                // No bulk actions
            ]);
    }
}
