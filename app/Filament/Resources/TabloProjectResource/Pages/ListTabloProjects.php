<?php

namespace App\Filament\Resources\TabloProjectResource\Pages;

use App\Filament\Resources\TabloProjectResource;
use App\Services\TabloProjectSyncService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;

class ListTabloProjects extends ListRecords
{
    protected static string $resource = TabloProjectResource::class;

    public int $newProjectsCount = 0;

    /**
     * Táblázat szűrők URL query string-ből
     * @var array<string, mixed>|null
     */
    #[Url]
    public ?array $tableFilters = null;

    public function mount(): void
    {
        parent::mount();

        // Ellenőrizzük hány új projekt érhető el
        $this->checkNewProjects();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label(fn () => $this->newProjectsCount > 0
                    ? "Szinkronizálás ({$this->newProjectsCount} új)"
                    : 'Szinkronizálás')
                ->icon('heroicon-o-arrow-path')
                ->color(fn () => $this->newProjectsCount > 0 ? 'success' : 'gray')
                ->badge(fn () => $this->newProjectsCount > 0 ? $this->newProjectsCount : null)
                ->badgeColor('success')
                ->action(function () {
                    $this->syncProjects();
                })
                ->requiresConfirmation()
                ->modalHeading('Projektek szinkronizálása')
                ->modalDescription(fn () => $this->newProjectsCount > 0
                    ? "{$this->newProjectsCount} új projekt lesz importálva az API-ból. A meglévő projektek nem változnak."
                    : 'Ellenőrizzük az új projekteket az API-ban.')
                ->modalSubmitActionLabel('Szinkronizálás')
                ->visible(function (): bool {
                    $user = auth()->user();
                    // Tabló partnerek NEM látják a szinkronizálás gombot
                    return ! ($user && $user->hasRole('tablo') && ! $user->hasAnyRole(['super_admin', 'photo_admin']));
                }),

            CreateAction::make()
                ->label('Új Tabló'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TabloProjectResource\Widgets\TabloProjectStatsOverview::class,
        ];
    }

    /**
     * Ellenőrzi az új projektek számát
     */
    private function checkNewProjects(): void
    {
        try {
            $service = new TabloProjectSyncService;
            $this->newProjectsCount = $service->checkNewProjectsCount();
        } catch (\Exception $e) {
            $this->newProjectsCount = 0;
        }
    }

    /**
     * Szinkronizálja az új projekteket
     */
    private function syncProjects(): void
    {
        $service = new TabloProjectSyncService;
        $result = $service->syncNewProjects();

        if ($result['created'] > 0) {
            Notification::make()
                ->title('Sikeres szinkronizálás')
                ->body("{$result['created']} új projekt importálva.")
                ->success()
                ->send();
        } elseif (empty($result['errors'])) {
            Notification::make()
                ->title('Nincs új projekt')
                ->body('Minden projekt már szinkronizálva van.')
                ->info()
                ->send();
        }

        if (! empty($result['errors'])) {
            Notification::make()
                ->title('Hibák történtek')
                ->body(implode("\n", array_slice($result['errors'], 0, 3)))
                ->danger()
                ->send();
        }

        // Frissítjük a számlálót
        $this->newProjectsCount = 0;
    }
}
