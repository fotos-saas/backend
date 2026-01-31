<?php

namespace App\Filament\Resources\TabloPartnerResource\Pages;

use App\Filament\Resources\TabloPartnerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTabloPartners extends ListRecords
{
    protected static string $resource = TabloPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Új Partner'),
        ];
    }
}
