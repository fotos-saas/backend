<?php

namespace App\Filament\Resources\WorkSessions\Pages;

use App\Filament\Resources\WorkSessions\WorkSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListWorkSessions extends ListRecords
{
    protected static string $resource = WorkSessionResource::class;

    /**
     * Get the table query with ID filtering from URL
     */
    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        // Handle ID filter from URL (e.g., ?tableFilters[id][values][]=6)
        $filters = request()->query('tableFilters', []);
        if (! empty($filters['id']['values'])) {
            $ids = is_array($filters['id']['values']) ? $filters['id']['values'] : [$filters['id']['values']];
            $query->whereIn('id', $ids);
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
