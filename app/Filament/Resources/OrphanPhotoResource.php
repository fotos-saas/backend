<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\OrphanPhotoResource\Pages\ListOrphanPhotos;
use App\Models\OrphanPhoto;
use App\Models\TabloPerson;
use App\Models\TabloProject;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class OrphanPhotoResource extends BaseResource
{
    protected static ?string $model = OrphanPhoto::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Talon képek';

    protected static string|UnitEnum|null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 35;

    protected static ?string $modelLabel = 'Talon kép';

    protected static ?string $pluralModelLabel = 'Talon képek';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumb_url')
                    ->label('')
                    ->circular()
                    ->size(50)
                    ->width(60)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('suggested_name')
                    ->label('Javasolt név')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Nincs név'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Típus')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'teacher' => 'Tanár',
                        'student' => 'Diák',
                        default => 'Ismeretlen',
                    })
                    ->colors([
                        'warning' => 'teacher',
                        'primary' => 'student',
                        'gray' => 'unknown',
                    ]),

                Tables\Columns\TextColumn::make('original_filename')
                    ->label('Eredeti fájlnév')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Feltöltve')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Típus')
                    ->options([
                        'teacher' => 'Tanár',
                        'student' => 'Diák',
                        'unknown' => 'Ismeretlen',
                    ]),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Megtekintés')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Kép előnézet')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Bezárás')
                    ->infolist(function (OrphanPhoto $record) {
                        $url = $record->full_url ?? $record->media?->getUrl();
                        if (! $url) {
                            return [
                                \Filament\Infolists\Components\TextEntry::make('error')
                                    ->label('')
                                    ->state('A kép nem található'),
                            ];
                        }

                        return [
                            \Filament\Infolists\Components\TextEntry::make('image')
                                ->label('')
                                ->state(new HtmlString(
                                    "<img src=\"{$url}\" style=\"max-width: 100%; max-height: 70vh; border-radius: 8px;\" />"
                                ))
                                ->html(),
                        ];
                    }),

                Action::make('assignToProject')
                    ->label('Hozzárendelés')
                    ->icon('heroicon-o-link')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('project_id')
                            ->label('Projekt')
                            ->options(fn () => TabloProject::with('school')
                                ->get()
                                ->mapWithKeys(fn ($p) => [
                                    $p->id => ($p->school?->name ?? 'Ismeretlen') . ' - ' . $p->class_name . ' (' . $p->class_year . ')',
                                ])
                            )
                            ->searchable()
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('person_id')
                            ->label('Személy')
                            ->options(function (Forms\Get $get) {
                                $projectId = $get('project_id');
                                if (! $projectId) {
                                    return [];
                                }

                                return TabloPerson::where('tablo_project_id', $projectId)
                                    ->whereNull('media_id')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn ($p) => [
                                        $p->id => $p->name . ' (' . ($p->type === 'teacher' ? 'Tanár' : 'Diák') . ')',
                                    ]);
                            })
                            ->searchable()
                            ->required()
                            ->helperText('Csak azok a személyek jelennek meg, akiknek még nincs képük'),
                    ])
                    ->action(function (OrphanPhoto $record, array $data) {
                        $person = TabloPerson::find($data['person_id']);
                        if (! $person) {
                            Notification::make()
                                ->title('Személy nem található')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Kép hozzárendelése
                        $person->update(['media_id' => $record->media_id]);

                        // Orphan rekord törlése
                        $record->delete();

                        Notification::make()
                            ->title('Kép hozzárendelve')
                            ->body("{$person->name} személyhez sikeresen hozzárendelve")
                            ->success()
                            ->send();
                    }),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nincsenek talon képek')
            ->emptyStateDescription('A párosítatlan képek itt jelennek meg.')
            ->emptyStateIcon('heroicon-o-photo');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrphanPhotos::route('/'),
        ];
    }
}
