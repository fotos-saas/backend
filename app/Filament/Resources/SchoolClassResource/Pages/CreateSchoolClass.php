<?php

namespace App\Filament\Resources\SchoolClassResource\Pages;

use App\Filament\Resources\SchoolClassResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSchoolClass extends CreateRecord
{
    protected static string $resource = SchoolClassResource::class;

    protected function getRedirectUrl(): string
    {
        // Session-based új rekord jelzés a lista oldalon való kiemeléshez
        session()->put('new_school_class_id', $this->record->getKey());

        return $this->getResource()::getUrl('index');
    }
}
