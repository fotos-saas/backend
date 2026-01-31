<?php

namespace App\Filament\Resources\TabloApiKeyResource\Pages;

use App\Filament\Resources\TabloApiKeyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTabloApiKeys extends ListRecords
{
    protected static string $resource = TabloApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Új API Kulcs'),
        ];
    }
}
