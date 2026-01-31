<?php

namespace App\Filament\Resources\TabloApiKeyResource\Pages;

use App\Filament\Resources\TabloApiKeyResource;
use App\Models\TabloApiKey;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTabloApiKey extends CreateRecord
{
    protected static string $resource = TabloApiKeyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate API key automatically
        $data['key'] = TabloApiKey::generateKey();

        return $data;
    }

    protected function afterCreate(): void
    {
        // Show the generated key to the user
        Notification::make()
            ->title('API Kulcs létrehozva!')
            ->body('API Kulcs: '.$this->record->key)
            ->success()
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
