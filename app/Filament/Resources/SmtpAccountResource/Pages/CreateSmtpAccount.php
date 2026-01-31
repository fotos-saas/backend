<?php

namespace App\Filament\Resources\SmtpAccountResource\Pages;

use App\Filament\Resources\SmtpAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSmtpAccount extends CreateRecord
{
    protected static string $resource = SmtpAccountResource::class;

    protected function getRedirectUrl(): string
    {
        session()->put('new_smtp_account_id', $this->record->getKey());

        return $this->getResource()::getUrl('index');
    }
}
