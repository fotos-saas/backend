<?php

namespace App\Filament\Resources\TabloSuggestedResource\Widgets;

use App\Services\TabloProjectScoringService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class PrioritySummaryWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $scoringService = app(TabloProjectScoringService::class);

        $user = Auth::user();
        $partnerId = null;
        if ($user && $user->hasRole('tablo') && !$user->hasAnyRole(['super_admin', 'photo_admin'])) {
            $partnerId = $user->tablo_partner_id;
        }

        $summary = $scoringService->getPrioritySummary($partnerId);

        return [
            Stat::make('TOP Prioritás', $summary[TabloProjectScoringService::PRIORITY_TOP])
                ->description('Azonnal foglalkozz velük!')
                ->descriptionIcon('heroicon-m-fire')
                ->color('danger'),

            Stat::make('Közepes', $summary[TabloProjectScoringService::PRIORITY_MEDIUM])
                ->description('Érdemes odafigyelni')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Elvan', $summary[TabloProjectScoringService::PRIORITY_LOW])
                ->description('Nyugodtan várhat')
                ->descriptionIcon('heroicon-m-check')
                ->color('success'),

            Stat::make('Összesen', $summary['total'])
                ->description('Aktív projektek')
                ->descriptionIcon('heroicon-m-rectangle-group')
                ->color('gray'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
