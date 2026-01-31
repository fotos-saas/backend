<?php

namespace App\Filament\Resources\TabloEmailSnippetResource\Pages;

use App\Filament\Resources\TabloEmailSnippetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTabloEmailSnippet extends EditRecord
{
    protected static string $resource = TabloEmailSnippetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
