<?php

namespace App\Filament\Resources\PrintSizes\Pages;

use App\Filament\Resources\PrintSizes\PrintSizeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrintSizes extends ListRecords
{
    protected static string $resource = PrintSizeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
