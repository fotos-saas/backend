<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailTemplate extends CreateRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        session()->put('new_email_template_id', $this->record->getKey());

        return $this->getResource()::getUrl('index');
    }
}
