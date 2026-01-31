<?php

namespace App\Filament\Resources\TabloSampleTemplateCategoryResource\Pages;

use App\Filament\Resources\TabloSampleTemplateCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTabloSampleTemplateCategory extends CreateRecord
{
    protected static string $resource = TabloSampleTemplateCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
