<?php

namespace App\Filament\Resources\TabloGalleryResource\Pages;

use App\Filament\Resources\TabloGalleryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTabloGalleries extends ListRecords
{
    protected static string $resource = TabloGalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
