<?php

namespace App\Filament\Resources\TabloProjectResource\Pages;

use App\Filament\Resources\TabloProjectResource;
use App\Models\TabloProject;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTabloProject extends EditRecord
{
    protected static string $resource = TabloProjectResource::class;

    /**
     * Resolve record by ID or external_id.
     * Támogatja: /tablo-projects/6/edit (DB id) és /tablo-projects/94/edit (external_id)
     */
    public function resolveRecord(int|string $key): Model
    {
        // Először próbáljuk meg DB id-val
        $record = TabloProject::find($key);

        // Ha nem találtuk, próbáljuk external_id-val
        if (! $record) {
            $record = TabloProject::where('external_id', (string) $key)->first();
        }

        // Ha még mindig nincs, dobjunk 404-et
        if (! $record) {
            abort(404, 'Projekt nem található');
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openFotocms')
                ->label('FotoCMS')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => "http://fotocms-admin.prod/tablo/project/{$this->record->fotocms_id}")
                ->openUrlInNewTab()
                ->visible(fn (): bool => ! empty($this->record->fotocms_id)),
            DeleteAction::make(),
        ];
    }
}
