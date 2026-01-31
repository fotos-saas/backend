<?php

namespace App\Filament\Resources\GuestShareTokens\Pages;

use App\Filament\Resources\GuestShareTokens\GuestShareTokenResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGuestShareToken extends CreateRecord
{
    protected static string $resource = GuestShareTokenResource::class;
}
