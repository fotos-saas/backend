<?php

namespace App\Filament\Resources;

use App\Enums\TabloProjectStatus;
use App\Filament\Resources\TabloSuggestedResource\Pages;
use App\Filament\Resources\TabloSuggestedResource\Widgets;
use App\Models\TabloProject;
use App\Services\TabloProjectScoringService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TabloSuggestedResource extends BaseResource
{

    protected static ?string $model = TabloProject::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static ?string $navigationLabel = 'Javaslatok';

    protected static ?string $modelLabel = 'Javasolt tabló';

    protected static ?string $pluralModelLabel = 'Javasolt tablók';

    protected static string|UnitEnum|null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'tablo/suggestions';

    /**
     * Badge a navigation-ben a TOP prioritású projektek számával.
     */
    public static function getNavigationBadge(): ?string
    {
        $scoringService = app(TabloProjectScoringService::class);
        $user = Auth::user();

        $partnerId = null;
        if ($user && $user->hasRole('tablo') && !$user->hasAnyRole(['super_admin', 'photo_admin'])) {
            $partnerId = $user->tablo_partner_id;
        }

        $summary = $scoringService->getPrioritySummary($partnerId);
        $topCount = $summary[TabloProjectScoringService::PRIORITY_TOP] ?? 0;

        return $topCount > 0 ? (string) $topCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->whereNotIn('status', [
                TabloProjectStatus::Done->value,
                TabloProjectStatus::InPrint->value,
            ])
            ->with(['school', 'partner', 'contacts', 'persons', 'emails']);

        // Tablo szerepkörű felhasználók csak a saját partnerükhöz tartozó projekteket látják
        $user = Auth::user();
        if ($user && $user->hasRole('tablo') && !$user->hasAnyRole(['super_admin', 'photo_admin'])) {
            $query->where('partner_id', $user->tablo_partner_id);
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        $scoringService = app(TabloProjectScoringService::class);

        return $table
            ->columns([
                // Prioritás oszlop
                Tables\Columns\TextColumn::make('calculated_priority')
                    ->label('Prioritás')
                    ->state(function (TabloProject $record) use ($scoringService): string {
                        $scoreData = $scoringService->calculateScore($record);

                        return match ($scoreData['priority']) {
                            TabloProjectScoringService::PRIORITY_TOP => 'TOP',
                            TabloProjectScoringService::PRIORITY_MEDIUM => 'Közepes',
                            TabloProjectScoringService::PRIORITY_LOW => 'Elvan',
                            default => '?',
                        };
                    })
                    ->badge()
                    ->color(function (TabloProject $record) use ($scoringService): string {
                        $scoreData = $scoringService->calculateScore($record);

                        return $scoringService->getPriorityColor($scoreData['priority']);
                    }),

                // Iskola + osztály
                Tables\Columns\TextColumn::make('school.name')
                    ->label('Iskola')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap()
                    ->description(fn (TabloProject $record) => trim(
                        ($record->class_name ?? '') . ' ' . ($record->class_year ?? '')
                    ) ?: null),

                // Státusz
                Tables\Columns\TextColumn::make('status')
                    ->label('Státusz')
                    ->badge()
                    ->formatStateUsing(fn (TabloProjectStatus $state): string => $state->label())
                    ->color(fn (TabloProjectStatus $state): string => $state->color()),

                // Kapcsolattartó
                Tables\Columns\TextColumn::make('first_contact_name')
                    ->label('Kapcsolattartó')
                    ->state(fn (TabloProject $record): string => $record->contacts->first()?->name ?? '-')
                    ->icon('heroicon-m-user')
                    ->color('primary')
                    ->action(
                        Action::make('viewContacts')
                            ->modalHeading(fn (TabloProject $record): string => 'Kapcsolattartók - ' . ($record->school?->name ?? 'Ismeretlen'))
                            ->modalWidth('lg')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Bezárás')
                            ->modalContent(fn (TabloProject $record): \Illuminate\Contracts\View\View => view('filament.resources.tablo-outreach.contacts-modal-wrapper', [
                                'projectId' => $record->id,
                            ]))
                    ),

                // Hiányzók összesítés
                Tables\Columns\TextColumn::make('missing_summary')
                    ->label('Hiányzók')
                    ->state(function (TabloProject $record): string {
                        $total = $record->persons->count();
                        if ($total === 0) {
                            return '-';
                        }

                        $withPhoto = $record->persons->whereNotNull('media_id')->count();

                        return "{$withPhoto}/{$total}";
                    })
                    ->badge()
                    ->color(function (TabloProject $record): string {
                        $total = $record->persons->count();
                        if ($total === 0) {
                            return 'gray';
                        }

                        $withPhoto = $record->persons->whereNotNull('media_id')->count();
                        if ($withPhoto === $total) {
                            return 'success';
                        }

                        return 'danger';
                    })
                    ->tooltip(fn (TabloProject $record): string => 'Képpel rendelkező / Összes hiányzó'),

                // Utolsó aktivitás (utolsó email alapján)
                Tables\Columns\TextColumn::make('last_email_activity')
                    ->label('Utolsó aktivitás')
                    ->state(function (TabloProject $record): ?string {
                        $lastEmailDate = $record->last_email_date;

                        if (!$lastEmailDate) {
                            return null;
                        }

                        return $lastEmailDate->diffForHumans();
                    })
                    ->icon('heroicon-m-envelope')
                    ->color(function (TabloProject $record): string {
                        $lastEmailDate = $record->last_email_date;

                        if (!$lastEmailDate) {
                            return 'gray';
                        }

                        $days = $lastEmailDate->diffInDays(now());

                        if ($days <= 7) {
                            return 'success';  // Friss - 1 héten belül
                        }
                        if ($days <= 14) {
                            return 'warning';  // Közepes - 2 héten belül
                        }

                        return 'danger';  // Régi - 2 hétnél régebbi
                    })
                    ->placeholder('-'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query)
            ->modifyQueryUsing(function (Builder $query): Builder {
                // Iskola szerinti rendezés
                return $query
                    ->orderByRaw('(SELECT name FROM tablo_schools WHERE tablo_schools.id = tablo_projects.school_id)');
            })
            ->groups([
                Tables\Grouping\Group::make('php_priority')
                    ->label('Prioritás')
                    ->getTitleFromRecordUsing(function (TabloProject $record) use ($scoringService): string {
                        $scoreData = $scoringService->calculateScore($record);

                        return match ($scoreData['priority']) {
                            TabloProjectScoringService::PRIORITY_TOP => '🔴 TOP - Azonnal!',
                            TabloProjectScoringService::PRIORITY_MEDIUM => '🟡 Közepes',
                            TabloProjectScoringService::PRIORITY_LOW => '🟢 Elvan',
                            default => 'Ismeretlen',
                        };
                    })
                    ->getKeyFromRecordUsing(function (TabloProject $record) use ($scoringService): string {
                        $scoreData = $scoringService->calculateScore($record);

                        return $scoreData['priority'];
                    })
                    ->orderQueryUsing(function (Builder $query, string $direction) use ($scoringService): Builder {
                        // A rendezés PHP-ban történik a getRecords után
                        return $query;
                    })
                    ->collapsible(),

                Tables\Grouping\Group::make('school.name')
                    ->label('Iskola')
                    ->getTitleFromRecordUsing(fn (TabloProject $record) => $record->school?->name ?? 'Ismeretlen iskola')
                    ->collapsible(),
            ])
            ->defaultGroup('php_priority')
            ->filters([
                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioritás')
                    ->options([
                        TabloProjectScoringService::PRIORITY_TOP => 'TOP - Azonnal!',
                        TabloProjectScoringService::PRIORITY_MEDIUM => 'Közepes',
                        TabloProjectScoringService::PRIORITY_LOW => 'Elvan',
                    ])
                    ->query(function (Builder $query, array $data) use ($scoringService): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        // Sajnos a filter-t PHP szinten kell alkalmazni
                        // Ez nem hatékony nagy adatmennyiségnél
                        $priority = $data['value'];
                        $projectIds = TabloProject::query()
                            ->whereNotIn('status', [
                                TabloProjectStatus::Done->value,
                                TabloProjectStatus::InPrint->value,
                            ])
                            ->get()
                            ->filter(function (TabloProject $project) use ($scoringService, $priority) {
                                $scoreData = $scoringService->calculateScore($project);

                                return $scoreData['priority'] === $priority;
                            })
                            ->pluck('id');

                        return $query->whereIn('id', $projectIds);
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Státusz')
                    ->options(TabloProjectStatus::options()),

                Tables\Filters\SelectFilter::make('school_id')
                    ->label('Iskola')
                    ->relationship('school', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('partner_id')
                    ->label('Partner')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(function () {
                        $user = Auth::user();

                        return !($user && $user->hasRole('tablo') && !$user->hasAnyRole(['super_admin', 'photo_admin']));
                    }),
            ])
            ->actions([
                // FotoCMS link gomb
                Action::make('openFotoCms')
                    ->label('')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->tooltip('Megnyitás FotoCMS-ben')
                    ->url(fn (TabloProject $record): ?string => $record->external_id
                        ? "http://fotocms-admin.prod/tablo/project/{$record->external_id}"
                        : null)
                    ->openUrlInNewTab()
                    ->hidden(fn (TabloProject $record): bool => !$record->external_id),

                // Szerkesztés gomb (TabloProjectResource-ra)
                Action::make('editProject')
                    ->label('')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->tooltip('Szerkesztés')
                    ->url(fn (TabloProject $record): string => TabloProjectResource::getUrl('edit', ['record' => $record])),

                // Tudnak róla toggle
                Action::make('toggleAware')
                    ->label('')
                    ->icon(fn (TabloProject $record) => $record->is_aware ? 'heroicon-s-check-circle' : 'heroicon-o-check-circle')
                    ->color(fn (TabloProject $record) => $record->is_aware ? 'success' : 'gray')
                    ->tooltip(fn (TabloProject $record) => $record->is_aware ? 'Tudnak róla' : 'Nem tudnak róla')
                    ->action(function (TabloProject $record): void {
                        $record->update(['is_aware' => !$record->is_aware]);
                        Notification::make()
                            ->title($record->is_aware ? 'Tudnak róla' : 'Nem tudnak róla')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Nincs javasolt tabló')
            ->emptyStateDescription('Minden aktív tablónak alacsony a prioritása.')
            ->emptyStateIcon('heroicon-o-light-bulb')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\PrioritySummaryWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTabloSuggested::route('/'),
        ];
    }
}
