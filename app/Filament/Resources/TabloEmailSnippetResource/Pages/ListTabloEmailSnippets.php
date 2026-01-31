<?php

namespace App\Filament\Resources\TabloEmailSnippetResource\Pages;

use App\Filament\Resources\TabloEmailSnippetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTabloEmailSnippets extends ListRecords
{
    protected static string $resource = TabloEmailSnippetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
