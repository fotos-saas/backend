<?php

namespace App\Filament\Resources\TabloOrderAnalysisResource\Pages;

use App\Filament\Resources\TabloOrderAnalysisResource;
use App\Models\TabloOrderAnalysis;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTabloOrderAnalyses extends ListRecords
{
    protected static string $resource = TabloOrderAnalysisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('autoLink')
                ->label('Automatikus kapcsolás')
                ->icon('heroicon-o-link')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Automatikus projekt kapcsolás')
                ->modalDescription('Az összes nem kapcsolt, kész elemzést automatikusan megpróbáljuk összekapcsolni a megfelelő projektekkel iskola név és osztály alapján.')
                ->action(function () {
                    $results = TabloOrderAnalysis::autoLinkAllUnlinked();

                    $body = "Sikeresen kapcsolva: {$results['linked']} db\n";
                    if ($results['linked'] > 0) {
                        $body .= "- Email alapján: {$results['by_email']} db\n";
                        $body .= "- Telefon alapján: {$results['by_phone']} db\n";
                        $body .= "- Iskola+osztály alapján: {$results['by_school']} db\n";
                    }
                    $body .= "Nem sikerült: {$results['failed']} db";

                    Notification::make()
                        ->title('Automatikus kapcsolás kész')
                        ->body($body)
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Mind')
                ->icon('heroicon-o-list-bullet')
                ->badge(fn () => $this->getFilteredTableQuery()->count()),

            'pending' => Tab::make('Várakozik')
                ->icon('heroicon-o-clock')
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['pending', 'processing']))
                ->badge(fn () => $this->getFilteredTableQuery()
                    ->whereIn('status', ['pending', 'processing'])
                    ->count()),

            'completed' => Tab::make('Kész')
                ->icon('heroicon-o-check-circle')
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed'))
                ->badge(fn () => $this->getFilteredTableQuery()
                    ->where('status', 'completed')
                    ->count()),

            'failed' => Tab::make('Hibás')
                ->icon('heroicon-o-x-circle')
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'failed'))
                ->badge(fn () => $this->getFilteredTableQuery()
                    ->where('status', 'failed')
                    ->count()),

            'unlinked' => Tab::make('Nincs projekt')
                ->icon('heroicon-o-link-slash')
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('tablo_project_id'))
                ->badge(fn () => $this->getFilteredTableQuery()
                    ->whereNull('tablo_project_id')
                    ->count()),
        ];
    }
}
