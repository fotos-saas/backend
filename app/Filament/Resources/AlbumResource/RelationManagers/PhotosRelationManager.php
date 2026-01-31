<?php

namespace App\Filament\Resources\AlbumResource\RelationManagers;

use App\Models\Setting;
use App\Services\WatermarkService;
use App\Support\Filament\Lightbox\LightboxPreviewableAction;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Tabs;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Képek';

    /**
     * Get table heading with comment count badge
     */
    public function getTableHeading(): string
    {
        $commentsCount = $this->getOwnerRecord()->photos()
            ->whereNotNull('user_comment')
            ->where('user_comment', '!=', '')
            ->count();

        if ($commentsCount > 0) {
            return 'Képek ('.$commentsCount.' megjegyzés)';
        }

        return 'Képek';
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

        try {
            app(\App\Services\PhotoVersionService::class)->restoreVersion(
                $version->photo,
                $version,
                auth()->user()
            );

            Notification::make()
                ->title('Verzió sikeresen visszaállítva!')
                ->success()
                ->send();

            // Refresh the table
            $this->resetTable();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Hiba történt a verzió visszaállítása során')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('path')
                    ->label('Fájl elérési út')
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\Select::make('assigned_user_id')
                    ->label('Hozzárendelt felhasználó')
                    ->relationship('assignedUser', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')
                    ->label('Előnézet')
                    ->getStateUsing(function ($record) {
                        $media = $record->getFirstMedia('photo');

                        return $media ? $media->getUrl('thumb') : null;
                    })
                    ->height(40)
                    ->extraImgAttributes([
                        'loading' => 'lazy',
                        'style' => 'object-fit: cover; aspect-ratio: 1/1; cursor: pointer;',
                    ])
                    ->action(LightboxPreviewableAction::make()),

                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('original_filename')
                    ->label('Eredeti fájlnév')
                    ->getStateUsing(function ($record) {
                        $media = $record->getFirstMedia('photo');

                        return $media ? $media->getCustomProperty('original_filename', 'N/A') : 'N/A';
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Hozzárendelve')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->assigned_user_id ? 'success' : 'warning')
                    ->default('Jelöletlen'),

                Tables\Columns\TextColumn::make('face_groups')
                    ->label('Arccsoport')
                    ->getStateUsing(function ($record) {
                        $groups = $record->faceGroups;
                        if ($groups->isEmpty()) {
                            return 'Nincs csoportosítva';
                        }

                        return $groups->pluck('name')->join(', ');
                    })
                    ->badge()
                    ->color(fn ($record) => $record->faceGroups->isNotEmpty() ? 'success' : 'gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('gender')
                    ->label('Nem')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'male' => 'blue',
                        'female' => 'pink',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'male' => 'Fiú',
                        'female' => 'Lány',
                        default => 'Ismeretlen',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('face_direction')
                    ->label('Tekintet')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'left' => 'warning',
                        'center' => 'success',
                        'right' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'left' => 'Balra',
                        'center' => 'Középre',
                        'right' => 'Jobbra',
                        default => 'Ismeretlen',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('face_subject')
                    ->label('Arc azonosító')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => $state ? substr($state, 0, 20).'...' : 'Nincs')
                    ->tooltip(fn ($record) => $record->face_subject ?? 'Nincs arcazonosítás')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user_comment')
                    ->label('Megjegyzés')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->user_comment)
                    ->icon(fn ($record) => $record->user_comment ? 'heroicon-o-chat-bubble-left-right' : null)
                    ->color(fn ($record) => $record->user_comment ? 'warning' : 'gray')
                    ->placeholder('Nincs')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('notes_count')
                    ->label('Jegyzetek')
                    ->counts('notes')
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Feltöltve')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('comment_status')
                    ->label('Megjegyzés státusz')
                    ->options([
                        'all' => 'Összes',
                        'with_comment' => 'Van megjegyzés',
                        'without_comment' => 'Nincs megjegyzés',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! isset($data['value'])) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'with_comment' => $query->whereNotNull('user_comment')->where('user_comment', '!=', ''),
                            'without_comment' => $query->where(function ($q) {
                                $q->whereNull('user_comment')->orWhere('user_comment', '=', '');
                            }),
                            default => $query,
                        };
                    })
                    ->default('all'),
                Tables\Filters\SelectFilter::make('assigned_user_id')
                    ->label('Hozzárendelés')
                    ->relationship('assignedUser', 'name')
                    ->multiple(),

                Tables\Filters\Filter::make('unassigned')
                    ->label('Csak jelöletlenek')
                    ->query(fn (Builder $query) => $query->whereNull('assigned_user_id')),

                Tables\Filters\SelectFilter::make('face_group_id')
                    ->label('Arccsoport')
                    ->relationship('faceGroups', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('gender')
                    ->label('Nem')
                    ->options([
                        'male' => 'Fiú',
                        'female' => 'Lány',
                        'unknown' => 'Ismeretlen',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('face_direction')
                    ->label('Tekintet')
                    ->options([
                        'left' => 'Balra',
                        'center' => 'Középre',
                        'right' => 'Jobbra',
                        'unknown' => 'Ismeretlen',
                    ])
                    ->multiple(),
            ])
            ->headerActions([
                Action::make('upload_photos')
                    ->label('Képek feltöltése')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        Forms\Components\FileUpload::make('photos')
                            ->label('Képek')
                            ->image()
                            ->multiple()
                            ->maxSize(4194304)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                            ->imagePreviewHeight('150')
                            ->preserveFilenames()
                            ->helperText('Maximum 4GB képenként. Eredeti minőségben mentve, thumb/preview/watermarked konverziók automatikusan generálva.')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('assigned_user_id')
                            ->label('Hozzárendelt felhasználó (opcionális)')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Ha választasz, minden kép ehhez a felhasználóhoz lesz rendelve')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data, \App\Services\PhotoUploadService $uploadService) {
                        $album = $this->getOwnerRecord();

                        try {
                        $photos = $uploadService->uploadMultiple(
                            files: $data['photos'] ?? [],
                            albumId: $album->id,
                            assignedUserId: $data['assigned_user_id'] ?? null,
                            originalFilenames: []
                        );

                        // Store last photo ID for highlighting
                        if ($lastPhoto = end($photos)) {
                            session()->put('new_photo_id', $lastPhoto->id);
                        }

                            Notification::make()
                            ->title('Képek feltöltve')
                            ->body(count($photos).' kép sikeresen feltöltve és feldolgozva (thumb, preview, watermarked konverziók generálva).')
                            ->success()
                            ->send();
                        } catch (\Exception $e) {
                            // Check if it's a duplicate photo error
                            if (str_contains($e->getMessage(), 'már létezik ebben az albumban')) {
                                // Extract photo ID from error message if possible
                                $existingPhotoId = null;
                                if (preg_match('/ID:\s*(\d+)/', $e->getMessage(), $matches)) {
                                    $existingPhotoId = $matches[1];
                                }
                                
                                $notification = Notification::make()
                                    ->title('Duplikált kép')
                                    ->body($e->getMessage())
                                    ->warning()
                                    ->duration(60000); // 1 perc
                                
                                // Add navigation action if we have the photo ID
                                if ($existingPhotoId) {
                                $notification->actions([
                                    \Filament\Actions\Action::make('navigate_to_photo')
                                        ->label('Képhez navigálás')
                                        ->icon('heroicon-o-arrow-top-right-on-square')
                                        ->color('primary')
                                        ->url("javascript:window.dispatchEvent(new CustomEvent('apply-photo-filter', { detail: { photoId: {$existingPhotoId} } }))")
                                ]);
                                }
                                
                                $notification->send();
                            } else {
                                Notification::make()
                                    ->title('Hiba a képek feltöltése során')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }
                    })
                    ->modalHeading('Képek feltöltése')
                    ->modalWidth('7xl'),

                Action::make('uploadZip')
                    ->label('ZIP Feltöltése')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->schema([
                        Forms\Components\Radio::make('upload_method')
                            ->label('Feltöltési mód')
                            ->options([
                                'file' => 'Fájl feltöltése',
                                'google_drive' => 'Google Drive link',
                            ])
                            ->default('file')
                            ->live()
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('zip_file')
                            ->label('ZIP Fájl')
                            ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                            ->maxSize(4194304)
                            ->helperText('Maximum 4GB ZIP fájl. Minden kép a ZIP-ből kicsomagolásra és feltöltésre kerül.')
                            ->required()
                            ->visible(fn ($get) => $get('upload_method') === 'file')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('google_drive_url')
                            ->label('Google Drive megosztási link')
                            ->url()
                            ->placeholder('https://drive.google.com/file/d/...')
                            ->helperText('Győződj meg róla, hogy a fájl "Bárki, akinek megvan a link" jogosultsággal megosztva van')
                            ->required()
                            ->visible(fn ($get) => $get('upload_method') === 'google_drive')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('assigned_user_id')
                            ->label('Hozzárendelt felhasználó (opcionális)')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Ha választasz, minden kép ehhez a felhasználóhoz lesz rendelve')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        $album = $this->getOwnerRecord();

                        // Album status beállítása
                        $album->update([
                            'zip_processing_status' => 'pending',
                            'zip_total_images' => null,
                            'zip_processed_images' => 0,
                        ]);

                        if ($data['upload_method'] === 'file') {
                            // ZIP áthelyezése a zip-uploads könyvtárba
                            // A Livewire már feltöltötte, $data['zip_file'] egy string (path)
                            $tempPath = $data['zip_file'];
                            $newFilename = uniqid().'.zip';
                            $zipPath = 'zip-uploads/'.$newFilename;

                            Storage::disk('local')->move($tempPath, $zipPath);

                            \App\Jobs\ProcessZipUpload::dispatchSync(
                                $album->id,
                                $zipPath,
                                $data['assigned_user_id'] ?? null,
                                'local'
                            );
                        } else {
                            // Google Drive link
                            \App\Jobs\ProcessZipUpload::dispatchSync(
                                $album->id,
                                $data['google_drive_url'],
                                $data['assigned_user_id'] ?? null,
                                'google_drive'
                            );
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('ZIP feltöltve')
                            ->body('A ZIP feldolgozása háttérben folyik. Az albumok táblázatban követheted a feldolgozás állapotát.')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('ZIP Feltöltése')
                    ->modalWidth('7xl'),

                Action::make('deleteAll')
                    ->label('Összes kép törlése')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Összes kép törlése az albumból')
                    ->modalDescription('Biztosan törölni szeretnéd az összes képet ebből az albumból? Ez a művelet nem vonható vissza!')
                    ->modalSubmitActionLabel('Igen, törlöm mind')
                    ->action(function () {
                        $album = $this->getOwnerRecord();
                        $count = $album->photos()->count();

                        // Delete photo files from storage
                        foreach ($album->photos as $photo) {
                            Storage::disk('public')->delete($photo->path);
                        }

                        // Delete photo records
                        $album->photos()->delete();

                        \Filament\Notifications\Notification::make()
                            ->title('Képek törölve')
                            ->body("{$count} kép sikeresen törölve az albumból.")
                            ->success()
                            ->send();
                    }),

                Action::make('watermark_all')
                    ->label('Mind vízjelezése')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Összes vízjelezetlen kép vízjelezése')
                    ->modalDescription(function () {
                        $album = $this->getOwnerRecord();
                        $photos = $album->photos;

                        // Count photos without watermark
                        $unwatermarkedCount = $photos->filter(function ($photo) {
                            $media = $photo->getFirstMedia('photo');
                            return $media && !$media->getCustomProperty('watermarked', false);
                        })->count();

                        return "Biztosan vízjelezni szeretnéd az összes vízjelezetlen képet? ({$unwatermarkedCount} db kép). Ez szinkron művelet, várj a befejezésig!";
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

                            // Get all photos for this album
                            $album = $this->getOwnerRecord();
                            $photos = $album->photos;

                            $processedCount = 0;
                            $skippedCount = 0;
                            $alreadyWatermarkedCount = 0;
                            $errorCount = 0;

                            foreach ($photos as $photo) {
                                try {
                                    $media = $photo->getFirstMedia('photo');
                                    if (! $media) {
                                        $skippedCount++;
                                        continue;
                                    }

                                    // Check if already watermarked
                                    if ($media->getCustomProperty('watermarked', false)) {
                                        $alreadyWatermarkedCount++;
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
                                    $watermarkService->addCircularWatermark($previewPath, $watermarkText);

                                    // Mark as watermarked
                                    $media->setCustomProperty('watermarked', true);
                                    $media->save();

                                    $processedCount++;
                                } catch (\Exception $e) {
                                    $errorCount++;
                                    \Log::error('Failed to apply watermark in album relation manager', [
                                        'photo_id' => $photo->id,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }

                            $message = "Vízjelezve: {$processedCount} kép.";
                            if ($alreadyWatermarkedCount > 0) {
                                $message .= " Már vízjeles: {$alreadyWatermarkedCount}.";
                            }
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
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Kép szerkesztése')
                    ->modalWidth('3xl')
                    ->schema([
                        Forms\Components\Select::make('assigned_user_id')
                            ->label('Hozzárendelt felhasználó')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Textarea::make('user_comment')
                            ->label('Felhasználói megjegyzés')
                            ->placeholder('pl. Kérem zöld legyen a hajam...')
                            ->rows(3)
                            ->nullable()
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('history')
                            ->label('Képcsere előzmények')
                            ->content(function ($record) {
                                $activities = $record->activities()
                                    ->where('description', 'photo_replaced')
                                    ->latest()
                                    ->get();

                                if ($activities->isEmpty()) {
                                    return 'Nincs előzmény';
                                }

                                return new HtmlString(
                                    $activities->map(function ($activity) {
                                        $date = $activity->created_at->format('Y-m-d H:i');
                                        $oldUrl = $activity->getExtraProperty('old_media_url');

                                        return "<div class='mb-2 text-sm'>
                                            <strong>{$date}</strong>: Kép lecserélve
                                            ".($oldUrl ? "<br><a href='{$oldUrl}' target='_blank' class='text-primary-600 hover:text-primary-700 underline'>📷 Régi kép megtekintése</a>" : '').'
                                        </div>';
                                    })->join('')
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Action::make('apply_watermark')
                    ->label('Vízjel alkalmazása')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Vízjel alkalmazása')
                    ->modalDescription('A képre vízjel kerül alkalmazásra. Ha már vízjelezett, újra vízjelezve lesz.')
                    ->modalSubmitActionLabel('Vízjelezés')
                    ->action(function ($record, WatermarkService $watermarkService) {
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

                            $media = $record->getFirstMedia('photo');
                            if (! $media) {
                                Notification::make()
                                    ->title('Hiba')
                                    ->body('A képhez nem tartozik media fájl.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // Check if preview exists
                            if (! $media->hasGeneratedConversion('preview')) {
                                Notification::make()
                                    ->title('Hiba')
                                    ->body('A preview conversion még nem készült el. Próbáld újra később.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $previewPath = $media->getPath('preview');
                            if (! file_exists($previewPath)) {
                                Notification::make()
                                    ->title('Hiba')
                                    ->body('A preview fájl nem található.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // Apply watermark
                            $watermarkService->addCircularWatermark($previewPath, $watermarkText);

                            // Mark as watermarked
                            $media->setCustomProperty('watermarked', true);
                            $media->save();

                            Notification::make()
                                ->title('Vízjel alkalmazva')
                                ->body('A képre sikeresen alkalmazva a vízjel.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Hiba történt')
                                ->body('Nem sikerült a vízjel alkalmazása: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('replaceImage')
                    ->label('Kép cseréje')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Kép cseréje')
                    ->modalDescription('A régi kép előzményekbe kerül, az új kép váltja fel.')
                    ->form([
                        Tabs::make('Tabs')
                            ->tabs([
                                Tabs\Tab::make('Új kép')
                                    ->schema([
                        Forms\Components\FileUpload::make('new_image')
                            ->label('Új kép')
                            ->image()
                            ->required()
                            ->maxSize(4194304)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                            ->preserveFilenames()
                            ->helperText('Maximum 4GB képfájl'),

                                        Forms\Components\Textarea::make('reason')
                                            ->label('Csere indoklása (opcionális)')
                                            ->helperText('Miért cseréled le a képet? (pl. rossz kép, régi kép, hibás)')
                                            ->rows(2),
                                    ]),

                                Tabs\Tab::make('Előzmények')
                                    ->badge(fn ($record) => $record?->versions()->count() ?? 0)
                                    ->schema([
                                        Forms\Components\Placeholder::make('version_list')
                                            ->label('')
                                            ->content(function ($record) {
                                                $versions = $record?->versions()->with(['replacedBy'])->get() ?? collect();
                                                
                                                // Get the latest restored version
                                                $latestRestored = $record?->versions()
                                                    ->where('is_restored', true)
                                                    ->latest()
                                                    ->first();
                                                
                                                if ($versions->isEmpty()) {
                                                    return new \Illuminate\Support\HtmlString('
                                                        <div class="text-center py-8">
                                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                                                            </svg>
                                                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nincs még előzmény</p>
                                                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Az első képcsere után itt fognak megjelenni a verziók</p>
                                                        </div>
                                                    ');
                                                }
                                                
                                                // Lightbox script
                                                $lightboxScript = <<<'JS'
                                                <script>
                                                window.openLightbox = function(imageUrl) {
                                                    let modal = document.getElementById("lightbox-modal");
                                                    if (!modal) {
                                                        modal = document.createElement("div");
                                                        modal.id = "lightbox-modal";
                                                        modal.className = "fixed inset-0 z-[9999] overflow-y-auto";
                                                        modal.innerHTML = `
                                                            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                                                                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" onclick="document.getElementById('lightbox-modal').remove(); document.body.style.overflow='auto';"></div>
                                                                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                                                                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                                                                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                                                        <div class="flex justify-end mb-2">
                                                                            <button type="button" onclick="document.getElementById('lightbox-modal').remove(); document.body.style.overflow='auto';" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                                                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                                </svg>
                                                                            </button>
                                                                        </div>
                                                                        <img src="${imageUrl}" alt="Preview" class="w-full h-auto" />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        `;
                                                        document.body.appendChild(modal);
                                                        document.body.style.overflow = "hidden";
                                                    } else {
                                                        modal.querySelector("img").src = imageUrl;
                                                        modal.classList.remove("hidden");
                                                        document.body.style.overflow = "hidden";
                                                    }
                                                };
                                                </script>
                                                JS;
                                                
                                                $html = $lightboxScript . '<div class="space-y-4">';
                                                foreach ($versions as $version) {
                                                    $versionMedia = $version->media();
                                                    $html .= '
                                                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
                                                            <div class="flex flex-row gap-4 items-center">
                                                                <div class="flex-shrink-0">';
                                                    
                                                    if ($versionMedia) {
                                                        $html .= '<img src="'.$versionMedia->getUrl('thumb').'" alt="'.$version->original_filename.'" class="w-32 h-auto sm:h-32 object-contain sm:object-cover sm:object-top rounded-md border border-gray-300 dark:border-gray-600 cursor-pointer hover:opacity-75 transition" onclick="openLightbox(\''.$versionMedia->getUrl('preview').'\')" />';
                                                    } else {
                                                        $html .= '<div class="w-32 h-32 bg-gray-100 dark:bg-gray-700 rounded-md flex items-center justify-center">
                                                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                        </div>';
                                                    }
                                                    
                                                    $html .= '</div>
                                                                <div class="flex-1 space-y-2">
                                                                    <div>
                                                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Eredeti fájlnév:</span>
                                                                        <div class="text-sm text-gray-900 dark:text-gray-100 break-all">'.($version->original_filename ?? 'N/A').'</div>
                                                                    </div>
                                                                    <div>
                                                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Cserélve:</span>
                                                                        <span class="text-sm text-gray-900 dark:text-gray-100">'.$version->created_at->format('Y-m-d H:i').'</span>';
                                                    
                                                    if ($version->replacedBy) {
                                                        $html .= '<span class="text-sm text-gray-500 dark:text-gray-400"> by '.$version->replacedBy->name.'</span>';
                                                    }
                                                    
                                                    $html .= '</div>';
                                                    
                                                    if ($version->reason) {
                                                        $html .= '<div>
                                                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Indoklás:</span>
                                                                            <p class="text-sm text-gray-900 dark:text-gray-100 mt-1">'.$version->reason.'</p>
                                                                        </div>';
                                                    } else {
                                                        $html .= '<div>
                                                                            <span class="text-sm text-gray-500 dark:text-gray-400 italic">Nincs indoklás</span>
                                                                        </div>';
                                                    }
                                                    
                                                    if ($version->is_restored && $latestRestored?->id === $version->id) {
                                                        $html .= '<div>
                                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                                                </svg>
                                                                                Visszaállítva
                                                                            </span>
                                                                        </div>';
                                                    }
                                                    
                                                    if ($version->width && $version->height) {
                                                        $html .= '<div>
                                                                            <span class="text-sm text-gray-500 dark:text-gray-400">'.$version->width.' × '.$version->height.' px</span>
                                                                        </div>';
                                                    }
                                                    
                                                    $html .= '</div>
                                                                <div class="flex-shrink-0">
                                                    
                                                    <button type="button" wire:click="$dispatch(\'restore-version\', { versionId: '.$version->id.' })" onclick="if(!confirm(\'Biztosan visszaállítod ezt a verziót? A jelenlegi kép előzményekbe kerül.\')) return false;" class="inline-flex items-center px-2 py-1 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                                                        </svg>
                                                                        Visszaállítás
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>';
                                                }
                                                $html .= '</div>';
                                                
                                                return new \Illuminate\Support\HtmlString($html);
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->action(function ($record, array $data, \App\Services\PhotoVersionService $versionService) {
                        // Prepare file for upload (same logic as PhotoUploadService)
                        $file = $data['new_image'];

                        // Get real path and original filename
                        if ($file instanceof UploadedFile) {
                            $temporaryPath = $file->getRealPath();
                            $originalName = $file->getClientOriginalName();
                        } else {
                            // Livewire temporary path (string)
                            $temporaryPath = Storage::path($file);
                            $originalName = basename($file);
                        }

                        // Calculate hash and check for duplicates
                        $newHash = hash_file('sha256', $temporaryPath);
                        
                        // Check for duplicate (excluding current photo)
                        $existingPhoto = \App\Models\Photo::where('album_id', $record->album_id)
                            ->where('hash', $newHash)
                            ->where('id', '!=', $record->id)
                            ->first();
                        
                        if ($existingPhoto) {
                            Notification::make()
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
                            return;
                        }

                        // Create version from current photo
                        $versionService->createVersion(
                            $record,
                            $data['reason'] ?? null,
                            auth()->user()
                        );

                        // Delete old media
                        $record->clearMediaCollection('photo');

                        // Generate ULID-based unique filename
                        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                        $uniqueFilename = strtoupper(Str::ulid()->toString()).'.'.$extension;

                        // Upload new image with proper filename handling
                        $media = $record->addMedia($temporaryPath)
                            ->usingFileName($uniqueFilename)
                            ->withCustomProperties(['original_filename' => $originalName])
                            ->toMediaCollection('photo', 'public');

                        // Update path and hash
                        $record->update([
                            'path' => $media->getPath(),
                            'hash' => $newHash,
                        ]);

                        Notification::make()
                            ->title('Kép lecserélve')
                            ->body('Az új kép sikeresen feltöltve, a régi előzményekben megtalálható.')
                            ->success()
                            ->send();
                    })
                            ->modalWidth('4xl')
                    ->extraModalFooterActions([
                        \Filament\Actions\Action::make('lightbox-script')
                            ->label('')
                            ->view('filament.widgets.lightbox-script')
                            ->extraAttributes(['style' => 'display: none;']),
                    ]),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('apply_watermark')
                        ->label('Vízjel alkalmazása')
                        ->icon('heroicon-o-sparkles')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Vízjel alkalmazása')
                        ->modalDescription('A kijelölt képekre vízjel kerül alkalmazásra. Ez szinkron művelet, várj a befejezésig!')
                        ->modalSubmitActionLabel('Vízjelezés')
                        ->action(function ($records, WatermarkService $watermarkService) {
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

                                $processedCount = 0;
                                $skippedCount = 0;
                                $errorCount = 0;

                                foreach ($records as $photo) {
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
                                        $watermarkService->addCircularWatermark($previewPath, $watermarkText);

                                        // Mark as watermarked
                                        $media->setCustomProperty('watermarked', true);
                                        $media->save();

                                        $processedCount++;
                                    } catch (\Exception $e) {
                                        $errorCount++;
                                        \Log::error('Failed to apply watermark in bulk action', [
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
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('assign')
                        ->label('Hozzárendelés felhasználóhoz')
                        ->icon('heroicon-o-user-plus')
                        ->schema([
                            Forms\Components\Select::make('user_id')
                                ->label('Felhasználó')
                                ->relationship('assignedUser', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->action(function (array $data, $records) {
                            foreach ($records as $record) {
                                $record->update(['assigned_user_id' => $data['user_id']]);
                            }
                        })
                        ->successNotificationTitle('Képek sikeresen hozzárendelve!')
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('moveToFaceGroup')
                        ->label('Áthelyezés csoportba')
                        ->icon('heroicon-o-arrows-right-left')
                        ->schema([
                            Forms\Components\Select::make('face_group_id')
                                ->label('Cél arccsoport')
                                ->options(function () {
                                    $album = $this->getOwnerRecord();

                                    return $album->faceGroups()->pluck('name', 'id');
                                })
                                ->searchable()
                                ->required()
                                ->helperText('Válaszd ki, melyik csoportba kerüljenek a kijelölt fotók'),
                        ])
                        ->action(function (array $data, $records) {
                            foreach ($records as $record) {
                                // Detach from all groups
                                $record->faceGroups()->detach();
                                // Attach to new group
                                $record->faceGroups()->attach($data['face_group_id'], [
                                    'confidence' => 1.0, // manuális, 100%
                                ]);
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Fotók áthelyezve')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordClasses(function ($record) {
                $classes = [];

                // Check if record was created within the last 10 seconds
                $createdAt = $record->created_at;
                $tenSecondsAgo = now()->subSeconds(10);

                if ($createdAt && $createdAt->isAfter($tenSecondsAgo)) {
                    $classes[] = 'fi-ta-row-new';
                }

                // Highlight photos with comments
                if ($record->user_comment) {
                    $classes[] = 'bg-warning-50 dark:bg-warning-900/10';
                }

                return implode(' ', $classes) ?: null;
            })
            ->modifyQueryUsing(function ($query) {
                // Apply new photo sorting
                $newPhotoId = session('new_photo_id');

                if ($newPhotoId) {
                    return $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$newPhotoId])
                        ->orderBy('created_at', 'desc');
                }

                return $query->orderBy('created_at', 'desc');
            })
            ->defaultSort('created_at', 'desc')
            ->poll('10s');
    }
}
