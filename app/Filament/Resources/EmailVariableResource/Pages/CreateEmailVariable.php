<?php

namespace App\Filament\Resources\EmailVariableResource\Pages;

use App\Filament\Resources\EmailVariableResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailVariable extends CreateRecord
{
    protected static string $resource = EmailVariableResource::class;

    protected function getRedirectUrl(): string
    {
        session()->put('new_email_variable_id', $this->record->getKey());

        return $this->getResource()::getUrl('index');
    }
}
