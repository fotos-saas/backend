<?php

namespace App\Filament\Resources\TabloProjectResource\RelationManagers;

use App\Enums\NoteStatus;
use App\Filament\Resources\TabloProjectResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    /**
     * Determine if the relation manager can be viewed for the given record.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return TabloProjectResource::canAccessRelation('notes');
    }

    /**
     * Badge showing breakdown of active notes by status.
     * Format: "1Ú • 2F" (Új, Folyamatban)
     */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $newCount = $ownerRecord->notes()->where('status', NoteStatus::New->value)->count();
        $inProgressCount = $ownerRecord->notes()->where('status', NoteStatus::InProgress->value)->count();

        if ($newCount === 0 && $inProgressCount === 0) {
            return null;
        }

        $parts = [];
        if ($newCount > 0) {
            $parts[] = "{$newCount}Ú";
        }
        if ($inProgressCount > 0) {
            $parts[] = "{$inProgressCount}F";
        }

        return implode(' • ', $parts);
    }

    /**
     * Badge color - warning for active notes.
     */
    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'warning';
    }

    protected static ?string $title = 'Megjegyzések';

    protected static ?string $modelLabel = 'Megjegyzés';

    protected static ?string $pluralModelLabel = 'Megjegyzések';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-chat-bubble-left-right';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('content')
                    ->label('Megjegyzés')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),

                Select::make('status')
                    ->label('Státusz')
                    ->options(NoteStatus::options())
                    ->default(NoteStatus::New->value)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content')
            ->columns([
                TextColumn::make('status')
                    ->label('Státusz')
                    ->badge()
                    ->color(fn (NoteStatus $state): string => $state->color())
                    ->icon(fn (NoteStatus $state): string => $state->icon())
                    ->formatStateUsing(fn (NoteStatus $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('content')
                    ->label('Megjegyzés')
                    ->searchable()
                    ->wrap()
                    ->limit(100),

                TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('resolvedByUser.name')
                    ->label('Elintézte')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('resolved_at')
                    ->label('Elintézve')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Státusz')
                    ->options(NoteStatus::options())
                    ->default(null)
                    ->placeholder('Összes'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Új Megjegyzés'),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('mark_done')
                        ->label('Elintézve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Model $record): void {
                            $record->markAsResolved(NoteStatus::Done);
                            Notification::make()
                                ->title('Megjegyzés elintézve')
                                ->success()
                                ->send();
                        })
                        ->hidden(fn (Model $record): bool => $record->status === NoteStatus::Done),

                    Action::make('mark_in_progress')
                        ->label('Folyamatban')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->action(function (Model $record): void {
                            $record->update(['status' => NoteStatus::InProgress]);
                            Notification::make()
                                ->title('Státusz frissítve')
                                ->success()
                                ->send();
                        })
                        ->hidden(fn (Model $record): bool => $record->status === NoteStatus::InProgress),

                    Action::make('mark_not_relevant')
                        ->label('Nem releváns')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->action(function (Model $record): void {
                            $record->markAsResolved(NoteStatus::NotRelevant);
                            Notification::make()
                                ->title('Megjelölve nem relevánsként')
                                ->success()
                                ->send();
                        })
                        ->hidden(fn (Model $record): bool => $record->status === NoteStatus::NotRelevant),

                    EditAction::make(),
                    DeleteAction::make(),
                ])->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
