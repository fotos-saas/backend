<?php

namespace App\Filament\Resources\PermissionManagement;

use App\Filament\Resources\BaseResource;

use App\Filament\Resources\PermissionManagement\Pages\ManagePermissionManagement;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class PermissionManagementResource extends BaseResource
{
    protected static ?string $model = null;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Jogosultság Kezelés';

    protected static ?string $modelLabel = 'Jogosultság';

    protected static ?string $pluralModelLabel = 'Jogosultságok';

    protected static ?int $navigationSort = 6;

    public static function getNavigationGroup(): ?string
    {
        return 'Platform Beállítások';
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePermissionManagement::route('/'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return can_access_permission('roles.edit');
    }

    public static function canViewAny(): bool
    {
        return can_access_permission('roles.edit');
    }
}
