<?php

namespace App\Filament\Resources\TabloPartnerResource\Pages;

use App\Filament\Resources\TabloPartnerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTabloPartner extends CreateRecord
{
    protected static string $resource = TabloPartnerResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
