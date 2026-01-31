<?php

namespace App\Filament\Resources\TabloSampleTemplateResource\Pages;

use App\Filament\Resources\TabloSampleTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTabloSampleTemplate extends CreateRecord
{
    protected static string $resource = TabloSampleTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
