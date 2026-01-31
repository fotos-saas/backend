<?php

namespace App\Filament\Resources\InvoicingProviderResource\Pages;

use App\Filament\Resources\InvoicingProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoicingProvider extends EditRecord
{
    protected static string $resource = InvoicingProviderResource::class;

    /**
     * Get header actions
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Törlés'),
        ];
    }

    /**
     * Get redirect URL after record update
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
