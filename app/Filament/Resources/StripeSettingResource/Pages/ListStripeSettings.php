<?php

namespace App\Filament\Resources\StripeSettingResource\Pages;

use App\Filament\Resources\StripeSettingResource;
use App\Models\StripeSetting;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStripeSettings extends ListRecords
{
    protected static string $resource = StripeSettingResource::class;

    protected function getHeaderActions(): array
    {
        return StripeSetting::query()->exists()
            ? []
            : [
                CreateAction::make()
                    ->label('Stripe beállítás létrehozása')
                    ->createAnother(false),
            ];
    }
}
