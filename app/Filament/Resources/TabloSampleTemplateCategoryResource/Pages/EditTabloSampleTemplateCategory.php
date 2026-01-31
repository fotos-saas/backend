<?php

namespace App\Filament\Resources\TabloSampleTemplateCategoryResource\Pages;

use App\Filament\Resources\TabloSampleTemplateCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTabloSampleTemplateCategory extends EditRecord
{
    protected static string $resource = TabloSampleTemplateCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Törlés'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
