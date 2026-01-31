<?php

namespace App\Filament\Resources\EmailEvents\Pages;

use App\Filament\Resources\EmailEvents\EmailEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailEvents extends ListRecords
{
    protected static string $resource = EmailEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
