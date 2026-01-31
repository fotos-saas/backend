<?php

namespace App\Filament\Resources\TabloMissingPeople\Pages;

use App\Filament\Resources\TabloMissingPeople\TabloMissingPersonResource;
use App\Filament\Resources\TabloProjectResource;
use App\Models\TabloMissingPerson;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EditTabloMissingPerson extends EditRecord
{
    protected static string $resource = TabloMissingPersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Vissza a listához')
                ->url(fn () => TabloMissingPersonResource::getUrl('index'))
                ->color('gray'),

            $this->getGoToProjectAction(),

            DeleteAction::make(),
        ];
    }

    /**
     * "Ugrás a projekthez" action - tanároknál modal ha több projektben szerepel.
     */
    protected function getGoToProjectAction(): Action
    {
        $record = $this->record;

        // Diáknál vagy ha csak 1 projektben szerepel: egyszerű navigáció
        if ($record->type === 'student') {
            return Action::make('goToProject')
                ->label('Ugrás a projekthez')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('info')
                ->url(fn () => TabloProjectResource::getUrl('edit', ['record' => $record->tablo_project_id]));
        }

        // Tanár: keressük meg az összes projektet ahol ugyanezzel a névvel szerepel
        $sameNameEntries = TabloMissingPerson::where('name', $record->name)
            ->where('type', 'teacher')
            ->with('project.school')
            ->get();

        // Ha csak 1 projektben szerepel: egyszerű navigáció
        if ($sameNameEntries->count() <= 1) {
            return Action::make('goToProject')
                ->label('Ugrás a projekthez')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('info')
                ->url(fn () => TabloProjectResource::getUrl('edit', ['record' => $record->tablo_project_id]));
        }

        // Több projektben is szerepel: modal a választáshoz
        $options = $sameNameEntries->mapWithKeys(function ($entry) {
            $projectName = $entry->project?->school?->name . ' - ' . $entry->project?->class_name;

            return [$entry->tablo_project_id => $projectName];
        })->toArray();

        return Action::make('goToProject')
            ->label('Ugrás a projekthez')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->color('info')
            ->form([
                Select::make('project_id')
                    ->label('Válaszd ki a projektet')
                    ->options($options)
                    ->default($record->tablo_project_id)
                    ->required()
                    ->helperText('Ez a tanár több osztálynál is szerepel hiányzó képpel.'),
            ])
            ->action(function (array $data) {
                return redirect(TabloProjectResource::getUrl('edit', ['record' => $data['project_id']]));
            });
    }

    /**
     * Handle photo upload/removal before saving.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->record;

        // Kép törlése
        if (!empty($data['remove_photo'])) {
            $record->update(['media_id' => null]);
        }

        // Kép feltöltése
        if (!empty($data['upload_photo'])) {
            $file = $data['upload_photo'];

            if ($file instanceof TemporaryUploadedFile) {
                $project = $record->project;
                $originalName = $file->getClientOriginalName();

                $media = $project->addMediaFromStream($file->readStream())
                    ->usingFileName($this->slugifyFilename($originalName))
                    ->withCustomProperties(['type' => $record->type])
                    ->toMediaCollection('tablo_photos');

                $record->update(['media_id' => $media->id]);

                // Jelöljük meg a projektet, hogy új kép érkezett
                $project->update(['has_new_missing_photos' => true]);
            }
        }

        // Távolítsuk el ezeket a mezőket a mentés előtt
        unset($data['upload_photo'], $data['remove_photo']);

        return $data;
    }

    /**
     * Mentés után frissítjük az oldalt, hogy a Placeholder-ben megjelenjen az új kép.
     */
    protected function afterSave(): void
    {
        // Refresh the record to get the updated photo relation
        $this->record->refresh();

        // Reset the form to clear the upload field and update the placeholder
        $this->fillForm();
    }

    /**
     * Slugify filename while preserving extension.
     */
    private function slugifyFilename(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $slug = Str::slug($name);

        if (empty($slug)) {
            $slug = 'photo-' . uniqid();
        }

        return $slug . '.' . strtolower($extension);
    }
}
