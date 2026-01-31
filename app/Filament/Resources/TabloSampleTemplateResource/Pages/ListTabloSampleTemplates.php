<?php

namespace App\Filament\Resources\TabloSampleTemplateResource\Pages;

use App\Filament\Resources\TabloSampleTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTabloSampleTemplates extends ListRecords
{
    protected static string $resource = TabloSampleTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Új minta'),
        ];
    }
}
