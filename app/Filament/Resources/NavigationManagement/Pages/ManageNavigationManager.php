<?php

namespace App\Filament\Resources\NavigationManagement\Pages;

use App\Filament\Resources\NavigationManagement\NavigationManagerResource;
use Filament\Resources\Pages\Page;

/**
 * Filament page for managing navigation configurations.
 *
 * Displays the NavigationManager Livewire component
 * for role-specific menu customization.
 */
class ManageNavigationManager extends Page
{
    protected static string $resource = NavigationManagerResource::class;

    protected string $view = 'filament.resources.navigation-management.pages.manage-navigation-manager';

    public function getTitle(): string
    {
        return 'Menü Elrendezés Kezelése';
    }

    public function getHeading(): string
    {
        return 'Menü Elrendezés Kezelése';
    }

    public function getSubheading(): ?string
    {
        return 'Szerepkör-specifikus menüpontok testreszabása címkék, csoportok és sorrend szerint';
    }
}

