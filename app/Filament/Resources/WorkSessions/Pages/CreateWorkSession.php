<?php

namespace App\Filament\Resources\WorkSessions\Pages;

use App\Filament\Resources\WorkSessions\WorkSessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkSession extends CreateRecord
{
    protected static string $resource = WorkSessionResource::class;

    protected function getRedirectUrl(): string
    {
        session()->put('new_work_session_id', $this->record->getKey());

        return $this->getResource()::getUrl('index');
    }
}
