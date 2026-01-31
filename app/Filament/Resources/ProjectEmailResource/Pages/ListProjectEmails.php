<?php

namespace App\Filament\Resources\ProjectEmailResource\Pages;

use App\Filament\Resources\ProjectEmailResource;
use App\Models\ProjectEmail;
use App\Models\TabloContact;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ListProjectEmails extends ListRecords
{
    protected static string $resource = ProjectEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncEmails')
                ->label('IMAP szinkronizálás')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\TextInput::make('days')
                        ->label('Napok száma')
                        ->numeric()
                        ->default(7)
                        ->minValue(1)
                        ->maxValue(365)
                        ->helperText('Hány napra visszamenőleg szinkronizáljon'),

                    \Filament\Forms\Components\Toggle::make('include_sent')
                        ->label('Elküldött emailek is')
                        ->default(false)
                        ->helperText('Az elküldött mappát is szinkronizálja'),
                ])
                ->action(function (array $data) {
                    try {
                        $options = [
                            '--days' => $data['days'] ?? 7,
                        ];

                        if ($data['include_sent'] ?? false) {
                            $options['--sent'] = true;
                        }

                        Artisan::call('emails:sync', $options);

                        Notification::make()
                            ->title('Szinkronizálás sikeres')
                            ->body('Az emailek szinkronizálása befejeződött.')
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Szinkronizálási hiba')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('linkToProjects')
                ->label('Projektekhez rendelés')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Emailek hozzárendelése projektekhez')
                ->modalDescription(function () {
                    $unassigned = ProjectEmail::whereNull('tablo_project_id')->count();

                    return "A rendszer megkeresi az összes hozzárendeletlen emailt ({$unassigned} db) és a kapcsolattartók email címe vagy neve alapján hozzárendeli őket a megfelelő projektekhez.";
                })
                ->modalSubmitActionLabel('Hozzárendelés')
                ->action(function () {
                    $linkedCount = 0;

                    // Email cím alapján keresés - kapcsolattartók email címei
                    $contacts = TabloContact::whereNotNull('email')->get();

                    foreach ($contacts as $contact) {
                        $email = strtolower($contact->email);

                        $count = ProjectEmail::whereNull('tablo_project_id')
                            ->where(function ($query) use ($email) {
                                $query->where(DB::raw('LOWER(from_email)'), $email)
                                    ->orWhere(DB::raw('LOWER(to_email)'), $email);
                            })
                            ->update(['tablo_project_id' => $contact->tablo_project_id]);

                        $linkedCount += $count;
                    }

                    // Név alapján keresés - kapcsolattartók nevei
                    $contactsWithNames = TabloContact::whereNotNull('name')
                        ->where('name', '!=', '')
                        ->get();

                    foreach ($contactsWithNames as $contact) {
                        $name = $contact->name;

                        $count = ProjectEmail::whereNull('tablo_project_id')
                            ->where(function ($query) use ($name) {
                                $query->where('from_name', 'ILIKE', "%{$name}%")
                                    ->orWhere('to_name', 'ILIKE', "%{$name}%");
                            })
                            ->update(['tablo_project_id' => $contact->tablo_project_id]);

                        $linkedCount += $count;
                    }

                    if ($linkedCount > 0) {
                        Notification::make()
                            ->title('Hozzárendelés sikeres')
                            ->body("{$linkedCount} email hozzárendelve projektekhez.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Nincs új hozzárendelés')
                            ->body('Nem találtam hozzárendeletlen emailt a kapcsolattartókhoz.')
                            ->info()
                            ->send();
                    }
                }),
        ];
    }
}
