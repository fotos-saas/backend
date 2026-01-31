<?php

namespace App\Filament\Resources\GuestShareTokens\Pages;

use App\Filament\Resources\GuestShareTokens\GuestShareTokenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGuestShareTokens extends ListRecords
{
    protected static string $resource = GuestShareTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
