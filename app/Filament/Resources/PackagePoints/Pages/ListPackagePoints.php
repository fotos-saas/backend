<?php

namespace App\Filament\Resources\PackagePoints\Pages;

use App\Filament\Resources\PackagePoints\PackagePointResource;
use App\Services\PackagePointService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;

class ListPackagePoints extends ListRecords
{
    protected static string $resource = PackagePointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Csomagpontok szinkronizálása')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->form([
                    Grid::make(1)
                        ->schema([
                            Checkbox::make('foxpost')
                                ->label('Foxpost csomagpontok')
                                ->default(false),

                            Checkbox::make('packeta')
                                ->label('Packeta csomagpontok')
                                ->default(false),

                        ]),
                ])
                ->action(function (array $data, PackagePointService $service) {
                    $providers = [];
                    if ($data['foxpost'] ?? false) {
                        $providers[] = 'foxpost';
                    }
                    if ($data['packeta'] ?? false) {
                        $providers[] = 'packeta';
                    }

                    if (empty($providers)) {
                        Notification::make()
                            ->title('Nincs kiválasztva szolgáltató')
                            ->warning()
                            ->send();

                        return;
                    }

                    $totalCreated = 0;
                    $totalUpdated = 0;
                    $errors = [];

                    foreach ($providers as $provider) {
                        $result = match ($provider) {
                            'foxpost' => $service->syncFoxpostPoints(),
                            'packeta' => $service->syncPacketaPoints(),
                            default => ['success' => false, 'error' => 'Ismeretlen szolgáltató'],
                        };

                        if ($result['success']) {
                            $totalCreated += $result['created'] ?? 0;
                            $totalUpdated += $result['updated'] ?? 0;
                        } else {
                            $errors[] = ucfirst($provider).': '.$result['error'];
                        }
                    }

                    if (empty($errors)) {
                        Notification::make()
                            ->title('Szinkronizálás sikeres')
                            ->body("Létrehozva: {$totalCreated}, Frissítve: {$totalUpdated}")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Szinkronizálás részben sikertelen')
                            ->body(implode("\n", $errors))
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }
}
