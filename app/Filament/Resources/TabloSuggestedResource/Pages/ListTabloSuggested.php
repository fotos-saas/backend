<?php

namespace App\Filament\Resources\TabloSuggestedResource\Pages;

use App\Filament\Resources\TabloSuggestedResource;
use App\Models\TabloProject;
use App\Services\TabloProjectScoringService;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;

class ListTabloSuggested extends ListRecords
{
    protected static string $resource = TabloSuggestedResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TabloSuggestedResource\Widgets\PrioritySummaryWidget::class,
        ];
    }

    protected function paginateTableQuery(\Illuminate\Database\Eloquent\Builder $query): Paginator|CursorPaginator
    {
        $paginator = parent::paginateTableQuery($query);

        // Rendezzük a rekordokat PHP-ban prioritás szerint
        $scoringService = app(TabloProjectScoringService::class);

        $priorityOrder = [
            TabloProjectScoringService::PRIORITY_TOP => 1,
            TabloProjectScoringService::PRIORITY_MEDIUM => 2,
            TabloProjectScoringService::PRIORITY_LOW => 3,
        ];

        $items = collect($paginator->items());

        $sorted = $items->sort(function (TabloProject $a, TabloProject $b) use ($scoringService, $priorityOrder) {
            $priorityA = $scoringService->calculateScore($a)['priority'];
            $priorityB = $scoringService->calculateScore($b)['priority'];

            $orderA = $priorityOrder[$priorityA] ?? 99;
            $orderB = $priorityOrder[$priorityB] ?? 99;

            if ($orderA !== $orderB) {
                return $orderA - $orderB;
            }

            // Ha azonos prioritás, iskola név szerint
            return strcmp($a->school?->name ?? '', $b->school?->name ?? '');
        })->values();

        // Cseréljük ki a paginator itemjeit a rendezett listára
        $paginator->setCollection($sorted);

        return $paginator;
    }
}
