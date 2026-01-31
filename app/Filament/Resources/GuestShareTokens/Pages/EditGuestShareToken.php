<?php

namespace App\Filament\Resources\GuestShareTokens\Pages;

use App\Filament\Resources\GuestShareTokens\GuestShareTokenResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGuestShareToken extends EditRecord
{
    protected static string $resource = GuestShareTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
