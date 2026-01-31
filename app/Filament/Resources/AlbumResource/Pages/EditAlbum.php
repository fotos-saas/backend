<?php

namespace App\Filament\Resources\AlbumResource\Pages;

use App\Filament\Resources\AlbumResource;
use App\Filament\Resources\WorkSessions\Schemas\WorkSessionForm;
use App\Filament\Resources\WorkSessions\WorkSessionResource;
use App\Models\Album;
use App\Models\WorkSession;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditAlbum extends EditRecord
{
    protected static string $resource = AlbumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('createWorkSession')
                ->label('Munkamenet létrehozása')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->modalHeading('Új munkamenet létrehozása')
                ->modalDescription('Az album alapján új munkamenet létrehozása')
                ->modalWidth('7xl')
                ->form(fn (Schema $schema) => WorkSessionForm::configure($schema)->getComponents())
                ->fillForm([
                    'name' => $this->record->title,
                    'status' => 'active',
                    'coupon_policy' => 'all',
                ])
                ->action(function (array $data, Album $record): void {
                    // Create the work session
                    // Observer automatically handles digit_code and share_token generation
                    $workSession = WorkSession::create($data);

                    // Attach to the current album
                    $record->workSessions()->attach($workSession->id);

                    // Send success notification
                    Notification::make()
                        ->title('Munkamenet sikeresen létrehozva')
                        ->body('A munkamenet létrejött és hozzá lett rendelve az albumhoz.')
                        ->success()
                        ->send();

                    // Redirect to the work session edit page
                    $this->redirect(WorkSessionResource::getUrl('edit', ['record' => $workSession]));
                }),
        ];
    }
}
