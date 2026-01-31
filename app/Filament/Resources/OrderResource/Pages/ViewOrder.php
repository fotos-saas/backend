<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Rendelés törlése')
                ->modalDescription('Biztosan törölni szeretnéd ezt a rendelést? Ez a művelet nem vonható vissza!')
                ->modalSubmitActionLabel('Igen, törlöm'),
        ];
    }
}
