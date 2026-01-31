<?php

namespace App\Filament\Resources\PhotoResource\Pages;

use App\Filament\Resources\PhotoResource;
use App\Services\PhotoVersionService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPhoto extends EditRecord
{
    protected static string $resource = PhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Mutate form data before saving
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['photo']) && $data['photo']) {
            // Get file path
            $file = $data['photo'];
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $temporaryPath = $file->getRealPath();
            } else {
                $temporaryPath = \Illuminate\Support\Facades\Storage::path($file);
            }
            
            $newHash = hash_file('sha256', $temporaryPath);
            
            // Check for duplicate (excluding current photo)
            $existingPhoto = \App\Models\Photo::where('album_id', $this->record->album_id)
                ->where('hash', $newHash)
                ->where('id', '!=', $this->record->id)
                ->first();
            
            if ($existingPhoto) {
                \Filament\Notifications\Notification::make()
                    ->title('Duplikált kép')
                    ->body('Ez a kép már létezik ebben az albumban. A teljesen azonos képek nem tölthetők fel többször ugyanabba az albumba.')
                    ->warning()
                    ->duration(60000) // 1 perc
                    ->actions([
                        \Filament\Actions\Action::make('navigate_to_photo')
                            ->label('Képhez navigálás')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->color('primary')
                            ->url("javascript:window.dispatchEvent(new CustomEvent('apply-photo-filter', { detail: { photoId: {$existingPhoto->id} } }))")
                    ])
                    ->send();
                
                unset($data['photo']);
                unset($data['version_reason']);
                return $data;
            }
            
            // New photo uploaded - create version from current photo
            app(PhotoVersionService::class)->createVersion(
                $this->record,
                $data['version_reason'] ?? null,
                auth()->user()
            );
        }

        unset($data['version_reason']);

        return $data;
    }

    /**
     * Get Livewire listeners
     */
    protected function getListeners(): array
    {
        return array_merge(parent::getListeners(), [
            'restore-version' => 'restoreVersion',
        ]);
    }

    /**
     * Restore a photo version
     */
    public function restoreVersion(int $versionId): void
    {
        $version = \App\Models\PhotoVersion::findOrFail($versionId);

        if ($version->photo_id !== $this->record->id) {
            $this->notify('danger', 'Hibás verzió!');

            return;
        }

        try {
            app(PhotoVersionService::class)->restoreVersion(
                $this->record,
                $version,
                auth()->user()
            );

            $this->notify('success', 'Verzió sikeresen visszaállítva!');

            // Refresh the form
            $this->fillForm();
        } catch (\Exception $e) {
            $this->notify('danger', 'Hiba történt a verzió visszaállítása során: '.$e->getMessage());
        }
    }

    /**
     * Get additional view data
     */
    protected function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'lightboxScript' => true,
        ]);
    }

    /**
     * Get additional scripts
     */
    protected function getFooterWidgets(): array
    {
        return array_merge(parent::getFooterWidgets(), [
            \Filament\Widgets\Widget::make('lightbox-script')
                ->view('filament.widgets.lightbox-script'),
        ]);
    }
}
