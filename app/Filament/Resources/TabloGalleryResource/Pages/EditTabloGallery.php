<?php

namespace App\Filament\Resources\TabloGalleryResource\Pages;

use App\Filament\Resources\TabloGalleryResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTabloGallery extends EditRecord
{
    protected static string $resource = TabloGalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Mentés után a feltöltött képeket hozzáadjuk a galériához.
     */
    protected function afterSave(): void
    {
        $data = $this->form->getState();
        $photos = $data['photos'] ?? [];

        if (empty($photos)) {
            return;
        }

        $uploadedCount = 0;

        foreach ($photos as $path) {
            // Először próbáljuk a storage/app/ alatt (livewire temp)
            $fullPath = storage_path('app/' . $path);
            if (! file_exists($fullPath)) {
                // Ha nem található, próbáljuk a storage/app/public/ alatt
                $fullPath = storage_path('app/public/' . $path);
            }

            if (file_exists($fullPath)) {
                $this->record->addMedia($fullPath)
                    ->toMediaCollection('photos');
                $uploadedCount++;
            }
        }

        if ($uploadedCount > 0) {
            Notification::make()
                ->title('Képek feltöltve')
                ->body("{$uploadedCount} kép sikeresen hozzáadva a galériához.")
                ->success()
                ->send();
        }
    }
}
