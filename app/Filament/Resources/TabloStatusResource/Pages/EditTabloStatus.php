<?php

namespace App\Filament\Resources\TabloStatusResource\Pages;

use App\Filament\Resources\TabloStatusResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTabloStatus extends EditRecord
{
    protected static string $resource = TabloStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Törlés')
                ->requiresConfirmation()
                ->modalHeading('Státusz törlése')
                ->modalDescription('Biztosan törölni szeretnéd? A hozzárendelt projektek státusza null lesz.'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
