<?php

namespace App\Filament\Resources\TabloProjectResource\Widgets;

use App\Enums\TabloProjectStatus;
use App\Models\TabloProject;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class TabloProjectStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $query = TabloProject::query();

        // Tablo szerepkörű felhasználók csak a saját partnerükhöz tartozó projekteket látják
        $user = auth()->user();
        if ($user && $user->hasRole('tablo') && ! $user->hasAnyRole(['super_admin', 'photo_admin'])) {
            $query->where('partner_id', $user->tablo_partner_id);
        }

        $total = (clone $query)->count();

        // Státusz statisztikák összegyűjtése
        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Státuszok HTML badge-ként (kattintható linkek szűréshez) - Alpine.js-sel aktív állapot kezelés
        $baseUrl = route('filament.admin.resources.tablo-projects.index');

        // Alpine.js komponens a kiemeléshez
        $alpineInit = 'x-data="{
            activeFilter: new URLSearchParams(window.location.search).get(\'tableFilters[status][value]\') || \'\',
            init() {
                document.addEventListener(\'livewire:navigated\', () => {
                    this.activeFilter = new URLSearchParams(window.location.search).get(\'tableFilters[status][value]\') || \'\';
                });
            }
        }"';

        // Közös badge stílusok - Alpine :style FELÜLÍRJA a style attribútumot, ezért MINDENBE bele kell tenni!
        $baseBadgeStyle = 'display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; white-space: nowrap; text-decoration: none; cursor: pointer;';

        // "Mind" gomb - aktív és faded (ha más filter aktív) stílusok
        $allActiveStyle = $baseBadgeStyle . ' background-color: #1f2937; color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);';
        $allFadedStyle = $baseBadgeStyle . ' background-color: #f9fafb; color: #9ca3af; border: 1px solid #e5e7eb;';

        $allBadge = '<a href="' . $baseUrl . '" wire:navigate ' .
            ':style="activeFilter === \'\' ? \'' . $allActiveStyle . '\' : \'' . $allFadedStyle . '\'">Mind</a>';

        $statusBadges = collect(TabloProjectStatus::cases())
            ->sortBy(fn ($status) => $status->sortOrder())
            ->filter(fn ($status) => isset($statusCounts[$status->value]) && $statusCounts[$status->value] > 0)
            ->map(function ($status) use ($statusCounts, $baseUrl, $baseBadgeStyle) {
                $count = $statusCounts[$status->value] ?? 0;
                $color = match ($status->color()) {
                    'gray' => '#6b7280',
                    'warning' => '#f59e0b',
                    'info' => '#3b82f6',
                    'success' => '#10b981',
                    'danger' => '#ef4444',
                    'purple' => '#8b5cf6',
                    default => '#6b7280',
                };

                // Világosabb háttérszín az inaktív állapothoz
                $lightBg = match ($status->color()) {
                    'gray' => '#f3f4f6',
                    'warning' => '#fef3c7',
                    'info' => '#dbeafe',
                    'success' => '#d1fae5',
                    'danger' => '#fee2e2',
                    'purple' => '#ede9fe',
                    default => '#f3f4f6',
                };

                $filterUrl = $baseUrl . '?tableFilters[status][value]=' . $status->value;

                // Stílusok - baseBadgeStyle-t BELE KELL tenni mindbe!
                // Aktív: teljes szín, fehér szöveg
                $activeStyle = $baseBadgeStyle . " background-color: {$color}; color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.15);";
                // Nincs filter: normál megjelenés
                $normalStyle = $baseBadgeStyle . " background-color: {$lightBg}; color: {$color}; border: 1px solid {$color}30;";
                // Van filter, de nem ez: enyhén halványabb (de nem inaktív!)
                $fadedStyle = $baseBadgeStyle . " background-color: {$lightBg}; color: {$color}; border: 1px solid {$color}20; opacity: 0.7;";

                // Span stílusok
                $dotBaseStyle = 'display: inline-block; width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;';
                $dotActiveStyle = $dotBaseStyle . ' background-color: white;';
                $dotNormalStyle = $dotBaseStyle . ' background-color: ' . $color . ';';
                $dotFadedStyle = $dotBaseStyle . ' background-color: ' . $color . '; opacity: 0.7;';

                $countBaseStyle = 'display: inline-flex; align-items: center; justify-content: center; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; min-width: 20px;';
                $countActiveStyle = $countBaseStyle . ' background-color: rgba(255,255,255,0.25); color: white;';
                $countNormalStyle = $countBaseStyle . ' background-color: ' . $color . '; color: white;';
                $countFadedStyle = $countBaseStyle . ' background-color: ' . $color . '; color: white; opacity: 0.7;';

                // Ternary: ha ez aktív -> activeStyle, ha nincs filter -> normalStyle, különben fadedStyle
                $styleCondition = "activeFilter === '{$status->value}' ? '{$activeStyle}' : (activeFilter === '' ? '{$normalStyle}' : '{$fadedStyle}')";
                $dotCondition = "activeFilter === '{$status->value}' ? '{$dotActiveStyle}' : (activeFilter === '' ? '{$dotNormalStyle}' : '{$dotFadedStyle}')";
                $countCondition = "activeFilter === '{$status->value}' ? '{$countActiveStyle}' : (activeFilter === '' ? '{$countNormalStyle}' : '{$countFadedStyle}')";

                return '<a href="' . $filterUrl . '" wire:navigate ' .
                    ':style="' . $styleCondition . '">' .
                    '<span :style="' . $dotCondition . '"></span>' .
                    '<span>' . e($status->label()) . '</span>' .
                    '<span :style="' . $countCondition . '">' . $count . '</span>' .
                    '</a>';
            })
            ->join('');

        $statusBadges = '<div ' . $alpineInit . ' style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px;">' . $allBadge . $statusBadges . '</div>';

        return [
            Stat::make('Összesen', $total . ' tabló')
                ->description(new HtmlString($statusBadges))
                ->color('primary'),
        ];
    }

    protected function getColumns(): int
    {
        return 1;
    }
}
