<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Előnézet')
                ->url(fn () => EmailTemplateResource::getUrl('preview', ['record' => $this->record]))
                ->icon('heroicon-o-eye')
                ->color('info'),
            DeleteAction::make(),
        ];
    }
}
