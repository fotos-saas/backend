<?php

namespace App\Filament\Resources\InvoicingProviderResource\Pages;

use App\Filament\Resources\InvoicingProviderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoicingProvider extends CreateRecord
{
    protected static string $resource = InvoicingProviderResource::class;

    /**
     * Get redirect URL after record creation
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
