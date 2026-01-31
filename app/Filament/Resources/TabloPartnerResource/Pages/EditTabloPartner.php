<?php

namespace App\Filament\Resources\TabloPartnerResource\Pages;

use App\Filament\Resources\TabloPartnerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTabloPartner extends EditRecord
{
    protected static string $resource = TabloPartnerResource::class;

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
