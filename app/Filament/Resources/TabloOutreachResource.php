<?php

namespace App\Filament\Resources;

use App\Enums\TabloProjectStatus;
use App\Filament\Concerns\HasUnaccentSearch;
use App\Filament\Resources\TabloOutreachResource\Pages;
use App\Helpers\SmsHelper;
use App\Models\TabloContact;
use App\Models\TabloProject;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TabloOutreachResource extends BaseResource
{
    use HasUnaccentSearch;

    /**
     * Kereshető oszlopok ékezet- és kis/nagybetű-független kereséssel.
     */
    protected static array $unaccentSearchColumns = [
        'school.name',        // Iskola neve
        'class_name',         // Osztály neve
        'contacts.name',      // Kapcsolattartó neve
        'contacts.phone',     // Telefonszám
        'contacts.email',     // Email
    ];

    protected static ?string $model = TabloProject::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $navigationLabel = 'Megkeresések';

    protected static ?string $modelLabel = 'Megkeresés';

    protected static ?string $pluralModelLabel = 'Megkeresések';

    protected static string|UnitEnum|null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'tablo-megkeresek';

    /**
     * Badge a navigation-ben a nem tudnak róla tablók számával.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = TabloProject::query()
            ->where('is_aware', false)
            ->where('status', TabloProjectStatus::NotStarted)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', TabloProjectStatus::NotStarted)
            ->with(['school', 'contacts']);
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
        return $table
            ->searchable()
            ->searchUsing(static::getUnaccentSearchCallback())
            ->columns([
                Tables\Columns\TextColumn::make('school.name')
                    ->label('Iskola / Osztály')
                    ->sortable()
                    ->weight('bold')
                    ->wrap()
                    ->description(fn (TabloProject $record) => trim(
                        ($record->class_name ?? '') . ' ' . ($record->class_year ?? '')
                    ) ?: null)
                    ->url(fn (TabloProject $record): string => TabloProjectResource::getUrl('edit', ['record' => $record]))
                    ->color('primary'),

                Tables\Columns\TextColumn::make('first_contact_name')
                    ->label('Kapcsolattartó')
                    ->state(fn (TabloProject $record): string => $record->contacts->first()?->name ?? '-')
                    ->icon('heroicon-m-user')
                    ->color('primary')
                    ->action(
                        Action::make('viewContacts')
                            ->modalHeading(fn (TabloProject $record): string =>
                                'Kapcsolattartók - ' . ($record->school?->name ?? 'Ismeretlen')
                            )
                            ->modalWidth('lg')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Bezárás')
                            ->modalContent(fn (TabloProject $record): \Illuminate\Contracts\View\View =>
                                view('filament.resources.tablo-outreach.contacts-modal-wrapper', [
                                    'projectId' => $record->id,
                                ])
                            )
                    ),

                Tables\Columns\TextColumn::make('first_contact_phone')
                    ->label('Telefon')
                    ->state(fn (TabloProject $record): string => $record->contacts->first()?->phone ?? '-')
                    ->icon('heroicon-m-phone')
                    ->copyable()
                    ->copyMessage('Telefonszám másolva'),

                // Aktivitás oszlop - hívások és SMS-ek száma, utolsó kontakt zárójelben
                Tables\Columns\TextColumn::make('contact_activity')
                    ->label('Aktivitás')
                    ->state(function (TabloProject $record): string {
                        $contact = $record->contacts->first();
                        if (! $contact) {
                            return '-';
                        }
                        $calls = $contact->call_count ?? 0;
                        $sms = $contact->sms_count ?? 0;
                        if ($calls === 0 && $sms === 0) {
                            return '-';
                        }
                        $parts = [];
                        if ($calls > 0) {
                            $parts[] = "{$calls}× hívás";
                        }
                        if ($sms > 0) {
                            $parts[] = "{$sms}× SMS";
                        }

                        return implode(', ', $parts);
                    })
                    ->description(fn (TabloProject $record): ?string =>
                        $record->contacts->first()?->last_contacted_at
                            ? '(' . $record->contacts->first()->last_contacted_at->diffForHumans() . ')'
                            : null
                    )
                    ->color(fn (TabloProject $record): ?string =>
                        ($record->contacts->first()?->call_count ?? 0) + ($record->contacts->first()?->sms_count ?? 0) > 0
                            ? 'success'
                            : null
                    ),
            ])
            ->defaultSort('school.name', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('school_id')
                    ->label('Iskola')
                    ->relationship('school', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                // Hívás gomb - regisztrálja a hívást és megnyitja a tel: linket
                Action::make('call')
                    ->label('')
                    ->icon('heroicon-o-phone')
                    ->color('success')
                    ->tooltip(fn (TabloProject $record): string =>
                        'Hívás (' . ($record->contacts->first()?->call_count ?? 0) . 'x): ' . ($record->contacts->first()?->phone ?? '-')
                    )
                    ->hidden(fn (TabloProject $record): bool => empty($record->contacts->first()?->phone))
                    ->action(function (TabloProject $record, Action $action): void {
                        $contact = $record->contacts->first();
                        if ($contact) {
                            $contact->registerCall();

                            $phone = preg_replace('/[^\d+]/', '', $contact->phone ?? '');

                            Notification::make()
                                ->title('Hívás regisztrálva')
                                ->body($contact->name . ' - ' . $contact->call_count . '. hívás')
                                ->success()
                                ->send();

                            // tel: link megnyitása JavaScript-tel
                            $action->getLivewire()->js("window.location.href = 'tel:{$phone}'");
                        }
                    })
                    ->extraAttributes(['class' => 'outreach-action-btn']),

                // SMS gomb - regisztrálja az SMS-t és megnyitja az sms: linket
                Action::make('sms')
                    ->label('')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('info')
                    ->tooltip(fn (TabloProject $record): string =>
                        'SMS (' . ($record->contacts->first()?->sms_count ?? 0) . 'x)'
                    )
                    ->hidden(fn (TabloProject $record): bool => empty($record->contacts->first()?->phone))
                    ->action(function (TabloProject $record, Action $action): void {
                        $contact = $record->contacts->first();
                        if ($contact) {
                            $contact->registerSms();

                            $smsUrl = self::getSmsUrl($record);

                            Notification::make()
                                ->title('SMS regisztrálva')
                                ->body($contact->name . ' - ' . $contact->sms_count . '. SMS')
                                ->success()
                                ->send();

                            // sms: link megnyitása JavaScript-tel
                            $action->getLivewire()->js("window.location.href = '{$smsUrl}'");
                        }
                    })
                    ->extraAttributes(['class' => 'outreach-action-btn']),

                // Tudnak róla toggle
                Action::make('markAware')
                    ->label('')
                    ->icon(fn (TabloProject $record) => $record->is_aware ? 'heroicon-s-check-circle' : 'heroicon-o-check-circle')
                    ->color(fn (TabloProject $record) => $record->is_aware ? 'success' : 'gray')
                    ->tooltip(fn (TabloProject $record) => $record->is_aware ? 'Tudnak róla ✓' : 'Nem tudnak róla')
                    ->requiresConfirmation()
                    ->modalHeading(fn (TabloProject $record) => $record->is_aware ? 'Már nem tudnak róla?' : 'Tudnak róla')
                    ->modalDescription(fn (TabloProject $record) => $record->is_aware
                        ? 'Biztos, hogy visszaállítod "nem tudnak róla" státuszra?'
                        : 'Biztos, hogy a kapcsolattartó már tud a tablóról?')
                    ->modalSubmitActionLabel(fn (TabloProject $record) => $record->is_aware ? 'Igen, visszaállítom' : 'Igen, tudnak róla')
                    ->modalIcon('heroicon-o-check-circle')
                    ->action(function (TabloProject $record): void {
                        $newState = !$record->is_aware;
                        $record->update(['is_aware' => $newState]);
                        Notification::make()
                            ->title('Megjelölve')
                            ->body($record->school?->name . ' - ' . ($newState ? 'tudnak róla' : 'nem tudnak róla'))
                            ->success()
                            ->send();
                    })
                    ->extraAttributes(['class' => 'outreach-action-btn']),

                // Megjegyzés gomb
                Action::make('addNote')
                    ->label('')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->color('warning')
                    ->tooltip('Megjegyzés hozzáadása')
                    ->modalHeading('Megjegyzés a kapcsolattartóhoz')
                    ->modalDescription(fn (TabloProject $record): string =>
                        ($record->contacts->first()?->name ?? 'Kapcsolattartó') . ' - ' . $record->school?->name
                    )
                    ->modalWidth('md')
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Megjegyzés')
                            ->rows(4)
                            ->default(fn (TabloProject $record): string => $record->contacts->first()?->note ?? '')
                            ->placeholder('Írd ide a megjegyzést...'),
                    ])
                    ->modalSubmitActionLabel('Mentés')
                    ->hidden(fn (TabloProject $record): bool => $record->contacts->isEmpty())
                    ->action(function (TabloProject $record, array $data): void {
                        $contact = $record->contacts->first();
                        if ($contact) {
                            $contact->update(['note' => $data['note']]);
                            Notification::make()
                                ->title('Megjegyzés mentve')
                                ->success()
                                ->send();
                        }
                    })
                    ->extraAttributes(['class' => 'outreach-action-btn']),
            ])
            ->bulkActions([
                \Filament\Actions\BulkAction::make('exportContacts')
                    ->label('Kontaktok exportálása')
                    ->icon('heroicon-o-phone')
                    ->color('success')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (\Illuminate\Support\Collection $records): \Symfony\Component\HttpFoundation\StreamedResponse {
                        // Használjuk a TabloProjectResource meglévő export logikáját
                        return TabloProjectResource::exportContactsToVCard($records);
                    }),
            ])
            ->emptyStateHeading('Nincs megkeresendő tabló')
            ->emptyStateDescription('Minden tabló esetén tudnak már róla.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    /**
     * SMS URL generálása előre megírt üzenettel.
     */
    protected static function getSmsUrl(TabloProject $record): string
    {
        $phone = $record->contacts->first()?->phone ?? '';
        $fullName = $record->contacts->first()?->name ?? '';

        return SmsHelper::generateSmsLinkFromName($phone, $fullName);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTabloOutreach::route('/'),
        ];
    }
}
