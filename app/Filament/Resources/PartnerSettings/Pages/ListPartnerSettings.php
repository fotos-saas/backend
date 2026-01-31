<?php

namespace App\Filament\Resources\PartnerSettings\Pages;

use App\Filament\Resources\PartnerSettings\PartnerSettingResource;
use App\Models\PartnerSetting;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPartnerSettings extends ListRecords
{
    protected static string $resource = PartnerSettingResource::class;

    protected function getHeaderActions(): array
    {
        return PartnerSetting::query()->exists()
            ? []
            : [
                CreateAction::make()
                    ->label('Partner létrehozása')
                    ->createAnother(false),
            ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->orderByDesc('updated_at');
    }
}
