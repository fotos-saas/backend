<?php

namespace App\Filament\Resources\PackagePoints\Pages;

use App\Filament\Resources\PackagePoints\PackagePointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPackagePoint extends EditRecord
{
    protected static string $resource = PackagePointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record->wasRecentlyCreated) {
            session()->put('new_package_point_id', $this->record->getKey());
        }
    }
}
