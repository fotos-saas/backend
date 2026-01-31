<?php

namespace App\Filament\Resources\NavigationManagement;

use App\Filament\Resources\BaseResource;

use App\Filament\Resources\NavigationManagement\Pages\ManageNavigationManager;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Filament resource for managing role-specific navigation configurations.
 *
 * Provides a dedicated admin panel interface for customizing
 * menu items, groups, and ordering per role.
 */
class NavigationManagerResource extends BaseResource
{
    protected static ?string $model = null;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Menü Elrendezés';

    protected static ?string $modelLabel = 'Menü Elrendezés';

    protected static ?string $pluralModelLabel = 'Menü Elrendezés';

    protected static ?int $navigationSort = 7;

    public static function getNavigationGroup(): ?string
    {
        return 'Platform Beállítások';
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageNavigationManager::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return can_access_permission('navigation.manage');
    }
}

