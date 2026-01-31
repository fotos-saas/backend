<?php

namespace App\Filament\Resources\TabloStatusResource\Pages;

use App\Filament\Resources\TabloStatusResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTabloStatus extends CreateRecord
{
    protected static string $resource = TabloStatusResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
