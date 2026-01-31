<?php

namespace App\Filament\Resources\TabloEmailSnippetResource\Pages;

use App\Filament\Resources\TabloEmailSnippetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTabloEmailSnippet extends CreateRecord
{
    protected static string $resource = TabloEmailSnippetResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
