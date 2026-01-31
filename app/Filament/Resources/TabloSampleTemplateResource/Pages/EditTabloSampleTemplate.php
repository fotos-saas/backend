<?php

namespace App\Filament\Resources\TabloSampleTemplateResource\Pages;

use App\Filament\Resources\TabloSampleTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTabloSampleTemplate extends EditRecord
{
    protected static string $resource = TabloSampleTemplateResource::class;

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
