<?php

namespace App\Filament\Resources\TabloProjectResource\Pages;

use App\Filament\Resources\TabloProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTabloProject extends CreateRecord
{
    protected static string $resource = TabloProjectResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
