<?php

namespace App\Filament\Resources\PhotoResource\Pages;

use App\Filament\Resources\PhotoResource;
use App\Models\Photo;
use App\Models\Setting;
use App\Services\WatermarkService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class ListPhotos extends ListRecords
{
    protected static string $resource = PhotoResource::class;

    public function mount(): void
    {
        parent::mount();

        $filters = request()->query('tableFilters', request()->query('filters', []));

        if (! empty($filters)) {
            $this->tableFilters = array_merge($this->tableFilters ?? [], $filters);
            $this->normalizeTableFilterValuesFromQueryString($this->tableFilters);
        }
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $filters = $this->tableFilters ?? [];

        if ($albumId = Arr::get($filters, 'album_id.value')) {
            $query->where('album_id', $albumId);
        }

        $assignedUserIds = Arr::wrap(Arr::get($filters, 'assigned_user_id.values', []));
        if (! empty($assignedUserIds)) {
            $query->whereIn('assigned_user_id', $assignedUserIds);
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('watermark_all')
                ->label('Mind vízjelezése')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Összes szűrt kép vízjelezése')
                ->modalDescription(function () {
                    $query = $this->getFilteredTableQuery();
                    $count = $query->count();
                    return "Biztosan vízjelezni szeretnéd az összes szűrt képet? ({$count} db kép). Ez szinkron művelet, várj a befejezésig!";
                })
                ->modalSubmitActionLabel('Mind vízjelezése')
                ->action(function (WatermarkService $watermarkService) {
                    try {
                        // Check watermark settings
                        $watermarkEnabled = Setting::get('watermark_enabled', true);
                        $watermarkText = Setting::get('watermark_text', 'Tablokirály');

                        if (! $watermarkEnabled || ! $watermarkText) {
                            Notification::make()
                                ->title('Vízjel kikapcsolva')
                                ->body('A vízjelezés ki van kapcsolva a beállításokban.')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Get all filtered photos
                        $query = $this->getFilteredTableQuery();
                        $photos = $query->get();

                        $processedCount = 0;
                        $skippedCount = 0;
                        $errorCount = 0;

                        foreach ($photos as $photo) {
                            try {
                                $media = $photo->getFirstMedia('photo');
                                if (! $media) {
                                    $skippedCount++;
                                    continue;
                                }

                                // Check if preview exists
                                if (! $media->hasGeneratedConversion('preview')) {
                                    $skippedCount++;
                                    continue;
                                }

                                $previewPath = $media->getPath('preview');
                                if (! file_exists($previewPath)) {
                                    $skippedCount++;
                                    continue;
                                }

                                // Apply watermark
                                $watermarkService->applyTiledWatermark($previewPath, $watermarkText);

                                // Mark as watermarked
                                $media->setCustomProperty('watermarked', true);
                                $media->save();

                                $processedCount++;
                            } catch (\Exception $e) {
                                $errorCount++;
                                \Log::error('Failed to apply watermark in bulk all action', [
                                    'photo_id' => $photo->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }

                        $message = "Vízjelezve: {$processedCount} kép.";
                        if ($skippedCount > 0) {
                            $message .= " Kihagyva: {$skippedCount}.";
                        }
                        if ($errorCount > 0) {
                            $message .= " Hiba: {$errorCount}.";
                        }

                        Notification::make()
                            ->title('Vízjelezés befejezve')
                            ->body($message)
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Hiba történt')
                            ->body('Nem sikerült a vízjelek alkalmazása: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * Get filtered table query
     */
    public function getFilteredTableQuery(): Builder
    {
        return $this->getTableQuery();
    }
}
