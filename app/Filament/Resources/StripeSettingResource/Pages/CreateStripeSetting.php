<?php

namespace App\Filament\Resources\StripeSettingResource\Pages;

use App\Filament\Resources\StripeSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStripeSetting extends CreateRecord
{
    protected static string $resource = StripeSettingResource::class;

    protected function getRedirectUrl(): string
    {
        return StripeSettingResource::getUrl('index');
    }
}
