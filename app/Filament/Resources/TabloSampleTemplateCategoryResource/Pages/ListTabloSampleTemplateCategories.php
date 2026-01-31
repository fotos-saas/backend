<?php

namespace App\Filament\Resources\TabloSampleTemplateCategoryResource\Pages;

use App\Filament\Resources\TabloSampleTemplateCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTabloSampleTemplateCategories extends ListRecords
{
    protected static string $resource = TabloSampleTemplateCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Új kategória'),
        ];
    }
}
