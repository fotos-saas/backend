<?php

namespace App\Filament\Resources\EmailVariableResource\Pages;

use App\Filament\Resources\EmailVariableResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmailVariables extends ListRecords
{
    protected static string $resource = EmailVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
