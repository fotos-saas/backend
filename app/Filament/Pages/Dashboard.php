<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * Determine if the user can access the dashboard.
     */
    public static function canAccess(): bool
    {
        return can_access_permission('dashboard.view');
    }
}
