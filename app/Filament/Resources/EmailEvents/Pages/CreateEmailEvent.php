<?php

namespace App\Filament\Resources\EmailEvents\Pages;

use App\Filament\Resources\EmailEvents\EmailEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailEvent extends CreateRecord
{
    protected static string $resource = EmailEventResource::class;

    protected function getRedirectUrl(): string
    {
        session()->put('new_email_event_id', $this->record->getKey());

        return $this->getResource()::getUrl('index');
    }
}
