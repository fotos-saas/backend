<?php

namespace App\Filament\Pages;

use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use App\Services\Tablo\GuestSessionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Pending Verifications Page
 *
 * Megjeleníti a jóváhagyásra váró vendég session-öket.
 * Ütközés esetén (ugyanaz a személy többször kiválasztva)
 * az ügyintéző eldöntheti, melyik session-t hagyja jóvá.
 */
class PendingVerificationsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Jóváhagyásra vár';

    protected static string|UnitEnum|null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Jóváhagyásra váró vendégek';

    protected string $view = 'filament.pages.pending-verifications-page';

    /**
     * Determine if the user can access this page.
     */
    public static function canAccess(): bool
    {
        return can_access_permission('guest-sessions.manage');
    }

    /**
     * Navigation badge showing pending count.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = TabloGuestSession::pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Navigation badge color.
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('project.school.name')
                    ->label('Iskola')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (TabloGuestSession $record) => $record->project?->class_name),

                TextColumn::make('guest_name')
                    ->label('Becenév')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('person.name')
                    ->label('Tablón szereplő név')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(function ($state, TabloGuestSession $record) {
                        if (! $state) {
                            return new HtmlString('<span style="color: #6b7280;">Nem azonosított</span>');
                        }

                        $typeLabel = $record->person?->type_label ?? '';

                        return new HtmlString(
                            e($state) .
                            ' <span style="font-size: 11px; color: #6b7280;">(' . e($typeLabel) . ')</span>'
                        );
                    }),

                TextColumn::make('existing_owner')
                    ->label('Jelenlegi tulajdonos')
                    ->state(function (TabloGuestSession $record): string {
                        if (! $record->tablo_person_id) {
                            return '-';
                        }

                        // Keressük meg a verified session-t ugyanahhoz a személyhez
                        $existingSession = TabloGuestSession::query()
                            ->where('tablo_person_id', $record->tablo_person_id)
                            ->where('verification_status', TabloGuestSession::VERIFICATION_VERIFIED)
                            ->where('id', '!=', $record->id)
                            ->first();

                        return $existingSession?->guest_name ?? '-';
                    })
                    ->color('danger')
                    ->weight(fn ($state) => $state !== '-' ? 'bold' : 'normal'),

                TextColumn::make('guest_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ip_address')
                    ->label('IP cím')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Regisztrált')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->description(fn (TabloGuestSession $record) => $record->created_at->diffForHumans()),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('tablo_project_id')
                    ->label('Projekt')
                    ->relationship('project', 'id')
                    ->getOptionLabelFromRecordUsing(fn (TabloProject $record) => $record->school?->name . ' - ' . $record->class_name)
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Jóváhagyás')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Vendég jóváhagyása')
                    ->modalDescription(function (TabloGuestSession $record): string {
                        $existingSession = null;
                        if ($record->tablo_person_id) {
                            $existingSession = TabloGuestSession::query()
                                ->where('tablo_person_id', $record->tablo_person_id)
                                ->where('verification_status', TabloGuestSession::VERIFICATION_VERIFIED)
                                ->where('id', '!=', $record->id)
                                ->first();
                        }

                        if ($existingSession) {
                            return "Biztosan jóváhagyod \"{$record->guest_name}\" vendéget? " .
                                "A jelenlegi tulajdonos (\"{$existingSession->guest_name}\") elveszíti a párosítást " .
                                "és nem lesz bökhető a \"{$record->person->name}\" személyként.";
                        }

                        return "Biztosan jóváhagyod \"{$record->guest_name}\" vendéget?";
                    })
                    ->modalSubmitActionLabel('Jóváhagyás')
                    ->action(function (TabloGuestSession $record) {
                        $service = app(GuestSessionService::class);
                        $result = $service->resolveConflict($record->id, 'approve');

                        if ($result['success']) {
                            Notification::make()
                                ->title('Vendég jóváhagyva')
                                ->body("\"{$record->guest_name}\" sikeresen jóváhagyva.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Hiba')
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('reject')
                    ->label('Elutasítás')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Vendég elutasítása')
                    ->modalDescription(fn (TabloGuestSession $record): string => "Biztosan elutasítod \"{$record->guest_name}\" vendéget? " .
                        "A vendég visszairányításra kerül a regisztrációs oldalra.")
                    ->modalSubmitActionLabel('Elutasítás')
                    ->action(function (TabloGuestSession $record) {
                        $service = app(GuestSessionService::class);
                        $result = $service->resolveConflict($record->id, 'reject');

                        if ($result['success']) {
                            Notification::make()
                                ->title('Vendég elutasítva')
                                ->body("\"{$record->guest_name}\" elutasítva.")
                                ->warning()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Hiba')
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('viewDetails')
                    ->label('Részletek')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (TabloGuestSession $record) => $record->guest_name . ' - Részletek')
                    ->modalWidth('md')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Bezárás')
                    ->modalContent(function (TabloGuestSession $record) {
                        $html = '<div style="padding: 16px;">';

                        // Alapadatok
                        $html .= '<div style="margin-bottom: 16px;">';
                        $html .= '<h3 style="font-weight: 600; margin-bottom: 8px; color: #374151;">Vendég adatai</h3>';
                        $html .= '<div style="background: #f3f4f6; padding: 12px; border-radius: 8px;">';
                        $html .= '<div style="margin-bottom: 4px;"><strong>Becenév:</strong> ' . e($record->guest_name) . '</div>';
                        $html .= '<div style="margin-bottom: 4px;"><strong>Email:</strong> ' . e($record->guest_email ?: '-') . '</div>';
                        $html .= '<div style="margin-bottom: 4px;"><strong>IP cím:</strong> ' . e($record->ip_address ?: '-') . '</div>';
                        $html .= '<div><strong>Regisztrált:</strong> ' . $record->created_at->format('Y-m-d H:i:s') . '</div>';
                        $html .= '</div>';
                        $html .= '</div>';

                        // Párosítás
                        if ($record->person) {
                            $html .= '<div style="margin-bottom: 16px;">';
                            $html .= '<h3 style="font-weight: 600; margin-bottom: 8px; color: #374151;">Tablón szereplő személy</h3>';
                            $html .= '<div style="background: #fef3c7; padding: 12px; border-radius: 8px;">';
                            $html .= '<div style="margin-bottom: 4px;"><strong>Név:</strong> ' . e($record->person->name) . '</div>';
                            $html .= '<div style="margin-bottom: 4px;"><strong>Típus:</strong> ' . e($record->person->type_label) . '</div>';
                            $html .= '<div><strong>Fotó:</strong> ' . ($record->person->media_id ? 'Van' : 'Nincs') . '</div>';
                            $html .= '</div>';
                            $html .= '</div>';
                        }

                        // Ütközés
                        if ($record->tablo_person_id) {
                            $existingSession = TabloGuestSession::query()
                                ->where('tablo_person_id', $record->tablo_person_id)
                                ->where('verification_status', TabloGuestSession::VERIFICATION_VERIFIED)
                                ->where('id', '!=', $record->id)
                                ->first();

                            if ($existingSession) {
                                $html .= '<div>';
                                $html .= '<h3 style="font-weight: 600; margin-bottom: 8px; color: #dc2626;">Ütközés</h3>';
                                $html .= '<div style="background: #fef2f2; padding: 12px; border-radius: 8px; border: 1px solid #fecaca;">';
                                $html .= '<div style="margin-bottom: 4px;"><strong>Jelenlegi tulajdonos:</strong> ' . e($existingSession->guest_name) . '</div>';
                                $html .= '<div style="margin-bottom: 4px;"><strong>Email:</strong> ' . e($existingSession->guest_email ?: '-') . '</div>';
                                $html .= '<div style="margin-bottom: 4px;"><strong>IP cím:</strong> ' . e($existingSession->ip_address ?: '-') . '</div>';
                                $html .= '<div><strong>Regisztrált:</strong> ' . $existingSession->created_at->format('Y-m-d H:i:s') . '</div>';
                                $html .= '</div>';
                                $html .= '</div>';
                            }
                        }

                        $html .= '</div>';

                        return new HtmlString($html);
                    }),
            ])
            ->emptyStateHeading('Nincs jóváhagyásra váró vendég')
            ->emptyStateDescription('Jelenleg minden vendég regisztráció jóvá van hagyva.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    protected function getTableQuery(): Builder
    {
        return TabloGuestSession::query()
            ->with(['project.school', 'person'])
            ->pending();
    }

    public function getTotalPending(): int
    {
        return TabloGuestSession::pending()->count();
    }

    public function getTotalWithConflict(): int
    {
        // Pending session-ök, ahol van másik verified session ugyanahhoz a személyhez
        return TabloGuestSession::query()
            ->pending()
            ->whereNotNull('tablo_person_id')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('tablo_guest_sessions as existing')
                    ->whereColumn('existing.tablo_person_id', 'tablo_guest_sessions.tablo_person_id')
                    ->where('existing.verification_status', TabloGuestSession::VERIFICATION_VERIFIED)
                    ->whereColumn('existing.id', '!=', 'tablo_guest_sessions.id');
            })
            ->count();
    }

    public function getAffectedProjects(): int
    {
        return TabloGuestSession::query()
            ->pending()
            ->distinct('tablo_project_id')
            ->count('tablo_project_id');
    }
}
