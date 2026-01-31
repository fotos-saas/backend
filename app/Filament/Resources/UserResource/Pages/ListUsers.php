<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\ActionGroups\UserImportActionGroup;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            UserImportActionGroup::make(),

            Actions\Action::make('deleteGuestUsers')
                ->label('Vendég felhasználók törlése')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Vendég felhasználók törlése')
                ->modalDescription('Biztosan törölni szeretnéd az összes vendég felhasználót? Ez a művelet nem vonható vissza!')
                ->modalSubmitActionLabel('Igen, törlés')
                ->action(function () {
                    $guestUsers = User::whereHas('roles', function ($query) {
                        $query->where('name', User::ROLE_GUEST);
                    })->get();

                    $count = $guestUsers->count();

                    if ($count === 0) {
                        Notification::make()
                            ->title('Nincs vendég felhasználó')
                            ->body('Nem található törlendő vendég felhasználó.')
                            ->warning()
                            ->send();

                        return;
                    }

                    // Delete all guest users
                    foreach ($guestUsers as $user) {
                        $user->delete();
                    }

                    Notification::make()
                        ->title('Sikeresen törölve')
                        ->body("{$count} vendég felhasználó törölve.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
