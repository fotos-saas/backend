<?php

namespace App\Filament\Resources\SmtpAccountResource\Pages;

use App\Filament\Resources\SmtpAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSmtpAccount extends EditRecord
{
    protected static string $resource = SmtpAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
