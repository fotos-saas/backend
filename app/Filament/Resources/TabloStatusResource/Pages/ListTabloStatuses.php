<?php

namespace App\Filament\Resources\TabloStatusResource\Pages;

use App\Filament\Resources\TabloStatusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTabloStatuses extends ListRecords
{
    protected static string $resource = TabloStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Új státusz'),
        ];
    }
}
