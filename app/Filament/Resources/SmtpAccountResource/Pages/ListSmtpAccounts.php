<?php

namespace App\Filament\Resources\SmtpAccountResource\Pages;

use App\Filament\Resources\SmtpAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSmtpAccounts extends ListRecords
{
    protected static string $resource = SmtpAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
