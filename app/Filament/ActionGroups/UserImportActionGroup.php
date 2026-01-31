<?php

namespace App\Filament\ActionGroups;

use App\Filament\Actions\ImportUsersFromCsvAction;
use App\Filament\Actions\ImportUsersFromListAction;
use Filament\Actions\ActionGroup;

class UserImportActionGroup
{
    /**
     * Build the "Tömeges import" action group with reusable configuration.
     */
    public static function make(bool $allowClassSelection = true, ?int $fixedClassId = null, ?int $albumId = null): ActionGroup
    {
        return ActionGroup::make([
            ImportUsersFromListAction::make(
                $fixedClassId,
                $allowClassSelection,
                $albumId,
            ),
            ImportUsersFromCsvAction::make($fixedClassId, $albumId),
        ])
            ->label('Tömeges import')
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->button();
    }
}
