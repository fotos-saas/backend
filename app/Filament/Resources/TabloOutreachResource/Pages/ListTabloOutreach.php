<?php

namespace App\Filament\Resources\TabloOutreachResource\Pages;

use App\Filament\Resources\TabloOutreachResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTabloOutreach extends ListRecords
{
    protected static string $resource = TabloOutreachResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'not_aware' => Tab::make('Nem tudnak róla')
                ->icon('heroicon-o-x-circle')
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_aware', false))
                ->badge(fn () => TabloOutreachResource::getEloquentQuery()->where('is_aware', false)->count()),

            'aware' => Tab::make('Tudnak róla')
                ->icon('heroicon-o-check-circle')
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_aware', true))
                ->badge(fn () => TabloOutreachResource::getEloquentQuery()->where('is_aware', true)->count()),

            'all' => Tab::make('Mind')
                ->icon('heroicon-o-list-bullet')
                ->badge(fn () => TabloOutreachResource::getEloquentQuery()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'not_aware';
    }
}
