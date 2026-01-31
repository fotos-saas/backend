<?php

namespace App\Filament\Resources\TabloGalleryResource\Pages;

use App\Filament\Resources\TabloGalleryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTabloGallery extends CreateRecord
{
    protected static string $resource = TabloGalleryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
