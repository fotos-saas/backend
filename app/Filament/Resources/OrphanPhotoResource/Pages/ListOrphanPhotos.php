<?php

namespace App\Filament\Resources\OrphanPhotoResource\Pages;

use App\Filament\Resources\OrphanPhotoResource;
use Filament\Resources\Pages\ListRecords;

class ListOrphanPhotos extends ListRecords
{
    protected static string $resource = OrphanPhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
