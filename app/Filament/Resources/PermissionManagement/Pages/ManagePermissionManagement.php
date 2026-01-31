<?php

namespace App\Filament\Resources\PermissionManagement\Pages;

use App\Filament\Resources\PermissionManagement\PermissionManagementResource;
use Filament\Resources\Pages\Page;

class ManagePermissionManagement extends Page
{
    protected static string $resource = PermissionManagementResource::class;

    protected string $view = 'filament.resources.permission-management.pages.manage-permission-management';

    public function getTitle(): string
    {
        return 'Jogosultság Kezelés';
    }

    public function getHeading(): string
    {
        return 'Jogosultság Kezelés';
    }

    public function getSubheading(): ?string
    {
        return 'Szerepkörök és jogosultságok kezelése';
    }
}
