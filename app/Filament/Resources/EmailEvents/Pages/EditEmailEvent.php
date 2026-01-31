<?php

namespace App\Filament\Resources\EmailEvents\Pages;

use App\Filament\Resources\EmailEvents\EmailEventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmailEvent extends EditRecord
{
    protected static string $resource = EmailEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
