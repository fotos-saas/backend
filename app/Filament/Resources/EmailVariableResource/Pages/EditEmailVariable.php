<?php

namespace App\Filament\Resources\EmailVariableResource\Pages;

use App\Filament\Resources\EmailVariableResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmailVariable extends EditRecord
{
    protected static string $resource = EmailVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
