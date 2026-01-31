<?php

namespace App\Filament\Resources\StripeSettingResource\Pages;

use App\Filament\Resources\StripeSettingResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStripeSetting extends EditRecord
{
    protected static string $resource = StripeSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Vissza a listához')
                ->url(fn () => StripeSettingResource::getUrl('index'))
                ->color('gray'),

            DeleteAction::make(),
        ];
    }
}
