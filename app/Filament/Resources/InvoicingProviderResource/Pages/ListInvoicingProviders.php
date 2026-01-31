<?php

namespace App\Filament\Resources\InvoicingProviderResource\Pages;

use App\Filament\Resources\InvoicingProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvoicingProviders extends ListRecords
{
    protected static string $resource = InvoicingProviderResource::class;

    /**
     * Get header actions
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Új számlázó rendszer'),
        ];
    }
}
