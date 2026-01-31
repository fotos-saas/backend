<?php

namespace App\Filament\Resources\TabloApiKeyResource\Pages;

use App\Filament\Resources\TabloApiKeyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTabloApiKey extends EditRecord
{
    protected static string $resource = TabloApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
