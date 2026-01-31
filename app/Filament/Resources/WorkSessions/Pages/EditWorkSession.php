<?php

namespace App\Filament\Resources\WorkSessions\Pages;

use App\Filament\Resources\WorkSessions\WorkSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\HtmlString;

class EditWorkSession extends EditRecord
{
    protected static string $resource = WorkSessionResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        if ($record->parent_work_session_id) {
            return 'Almunkamenet szerkesztése';
        }

        return parent::getHeading();
    }

    public function getSubheading(): string|HtmlString|null
    {
        $record = $this->getRecord();

        if ($record->parent_work_session_id && $record->parentWorkSession) {
            $url = WorkSessionResource::getUrl('edit', ['record' => $record->parentWorkSession->id]);
            return new HtmlString("Szülő munkamenet: <a href=\"{$url}\" target=\"_blank\" class=\"text-primary-600 underline hover:text-primary-700\">{$record->parentWorkSession->name}</a>");
        }

        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
