<?php

namespace App\Filament\Resources\AlbumResource\Pages;

use App\Events\AlbumCreated;
use App\Filament\Resources\AlbumResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAlbum extends CreateRecord
{
    protected static string $resource = AlbumResource::class;

    protected function getRedirectUrl(): string
    {
        session()->put('new_album_id', $this->record->getKey());

        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        event(new AlbumCreated($this->record));
    }
}
