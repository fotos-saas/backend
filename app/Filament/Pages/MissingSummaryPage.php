<?php

namespace App\Filament\Pages;

use App\Enums\TabloProjectStatus;
use App\Filament\Resources\TabloProjectResource;
use App\Models\TabloProject;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Hiányzó képek összesítő - Projektek szerinti áttekintés
 *
 * Megjeleníti a projekteket a hiányzó tanár és diák képek számával.
 * Csak a nem kész státuszú projekteket mutatja.
 */
class MissingSummaryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Hiányzó képek összesítő';

    protected static string|UnitEnum|null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Hiányzó képek összesítő';

    protected string $view = 'filament.pages.missing-summary-page';

    /**
     * Determine if the user can access this page.
     */
    public static function canAccess(): bool
    {
        return can_access_permission('missing-photos.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('school.name')
                    ->label('Iskola')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('class_name')
                    ->label('Osztály')
                    ->sortable(),

                TextColumn::make('class_year')
                    ->label('Évfolyam')
                    ->sortable(),

                TextColumn::make('missing_teachers_count')
                    ->label('Tanár')
                    ->badge()
                    ->color('warning')
                    ->alignCenter()
                    ->default(0),

                TextColumn::make('missing_students_count')
                    ->label('Diák')
                    ->badge()
                    ->color('primary')
                    ->alignCenter()
                    ->default(0),

                TextColumn::make('status')
                    ->label('Státusz')
                    ->badge()
                    ->formatStateUsing(fn (TabloProjectStatus $state): string => $state->label())
                    ->color(fn (TabloProjectStatus $state): string => $state->color())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('school.name')
            ->striped()
            ->recordUrl(fn (TabloProject $record) => TabloProjectResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('Nincs hiányzó kép')
            ->emptyStateDescription('Minden aktív projektben megvannak a képek.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    protected function getTableQuery(): Builder
    {
        $doneStatuses = [TabloProjectStatus::Done->value, TabloProjectStatus::InPrint->value];

        return TabloProject::query()
            ->with('school')
            ->whereNotIn('status', $doneStatuses)
            ->whereHas('persons', fn ($q) => $q->whereNull('media_id'))
            ->withCount([
                'persons as missing_teachers_count' => fn ($q) => $q->where('type', 'teacher')->whereNull('media_id'),
                'persons as missing_students_count' => fn ($q) => $q->where('type', 'student')->whereNull('media_id'),
            ]);
    }

    public function getTotalProjects(): int
    {
        $doneStatuses = [TabloProjectStatus::Done->value, TabloProjectStatus::InPrint->value];

        return TabloProject::query()
            ->whereNotIn('status', $doneStatuses)
            ->whereHas('persons', fn ($q) => $q->whereNull('media_id'))
            ->count();
    }

    public function getTotalMissingTeachers(): int
    {
        $doneStatuses = [TabloProjectStatus::Done->value, TabloProjectStatus::InPrint->value];

        return TabloProject::query()
            ->whereNotIn('status', $doneStatuses)
            ->withCount(['persons as count' => fn ($q) => $q->where('type', 'teacher')->whereNull('media_id')])
            ->get()
            ->sum('count');
    }

    public function getTotalMissingStudents(): int
    {
        $doneStatuses = [TabloProjectStatus::Done->value, TabloProjectStatus::InPrint->value];

        return TabloProject::query()
            ->whereNotIn('status', $doneStatuses)
            ->withCount(['persons as count' => fn ($q) => $q->where('type', 'student')->whereNull('media_id')])
            ->get()
            ->sum('count');
    }
}
