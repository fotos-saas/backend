<?php

namespace App\Filament\Resources\TabloProjectResource\RelationManagers;

use App\Filament\Resources\TabloProjectResource;
use App\Models\TabloMissingPerson;
use App\Models\TabloProject;
use App\Services\NameMatcherService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use ZipArchive;

class MissingPersonsRelationManager extends RelationManager
{
    protected static string $relationship = 'missingPersons';

    /**
     * When super admin views this relation, mark photos as seen.
     */
    public function mount(): void
    {
        parent::mount();

        $user = auth()->user();
        $project = $this->getOwnerRecord();

        // Ha super admin vagy photo admin nézi, töröljük a flag-et
        if ($user && $project && $user->hasAnyRole(['super_admin', 'photo_admin'])) {
            if ($project->has_new_tablo_photos) {
                $project->update(['has_new_tablo_photos' => false]);
            }
        }
    }

    /**
     * Slugify filename while preserving extension
     */
    private static function slugifyFilename(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Slug: lowercase, ASCII only, hyphens
        $slug = Str::slug($name);

        // Ha üres lett (pl. csak speciális karakterek voltak), generálunk egy random nevet
        if (empty($slug)) {
            $slug = 'photo-' . uniqid();
        }

        return $slug . '.' . strtolower($extension);
    }

    /**
     * Determine if the relation manager can be viewed for the given record.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return TabloProjectResource::canAccessRelation('missing-persons');
    }

    /**
     * Get badge showing count of students and teachers without photos.
     */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var \App\Models\TabloProject $ownerRecord */
        $missingPersons = $ownerRecord->missingPersons;

        if ($missingPersons->isEmpty()) {
            return null;
        }

        // Count persons without photos (media_id is null)
        $studentsWithoutPhoto = $missingPersons
            ->where('type', 'student')
            ->filter(fn ($p) => $p->media_id === null)
            ->count();

        $teachersWithoutPhoto = $missingPersons
            ->where('type', 'teacher')
            ->filter(fn ($p) => $p->media_id === null)
            ->count();

        // If all have photos, no badge needed
        if ($studentsWithoutPhoto === 0 && $teachersWithoutPhoto === 0) {
            return null;
        }

        // Build badge text: "5D • 2T" format
        $parts = [];
        if ($studentsWithoutPhoto > 0) {
            $parts[] = "{$studentsWithoutPhoto}D";
        }
        if ($teachersWithoutPhoto > 0) {
            $parts[] = "{$teachersWithoutPhoto}T";
        }

        return implode(' • ', $parts);
    }

    /**
     * Badge color - warning if there are missing photos.
     */
    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'warning';
    }

    protected static ?string $title = 'Hiányzó Emberek';

    protected static ?string $modelLabel = 'Hiányzó Személy';

    protected static ?string $pluralModelLabel = 'Hiányzó Személyek';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-user-minus';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Kép + név egy sorban (inline HTML)
                Forms\Components\Placeholder::make('photo_preview')
                    ->label('')
                    ->content(function ($record) {
                        $name = e($record?->name ?? '');
                        $hasPhoto = $record?->photo;

                        if (! $hasPhoto) {
                            return new HtmlString("
                                <div style=\"display: flex; align-items: center; gap: 16px; padding: 8px 0;\">
                                    <div style=\"width: 70px; height: 85px; background: #f3f4f6; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #9ca3af; flex-shrink: 0;\">
                                        <svg xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"1.5\" stroke=\"currentColor\" style=\"width: 28px; height: 28px;\">
                                            <path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z\" />
                                        </svg>
                                    </div>
                                    <div style=\"font-weight: 600; font-size: 15px;\">{$name}</div>
                                </div>
                            ");
                        }

                        $thumbUrl = e($record->photo_thumb_url ?? $record->photo->getUrl('thumb'));
                        $fullUrl = e($record->photo->getUrl());

                        return new HtmlString("
                            <div style=\"display: flex; align-items: center; gap: 16px; padding: 8px 0;\">
                                <img src=\"{$thumbUrl}\"
                                     onclick=\"window.open('{$fullUrl}', '_blank', 'width=1200,height=900')\"
                                     style=\"width: 70px; height: 85px; object-fit: cover; object-position: top; border-radius: 8px; cursor: zoom-in; box-shadow: 0 2px 8px rgba(0,0,0,0.12); flex-shrink: 0;\" />
                                <div style=\"font-weight: 600; font-size: 15px;\">{$name}</div>
                            </div>
                        ");
                    })
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('name')
                    ->label('Név')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('type')
                    ->label('Típus')
                    ->options([
                        'student' => 'Diák',
                        'teacher' => 'Tanár',
                    ])
                    ->default('student')
                    ->required(),

                Forms\Components\TextInput::make('local_id')
                    ->label('Helyi ID')
                    ->maxLength(255)
                    ->placeholder('Opcionális'),

                Forms\Components\FileUpload::make('upload_photo')
                    ->label('Kép feltöltése')
                    ->image()
                    ->imageEditor(false)
                    ->storeFiles(false)
                    ->maxSize(10240) // 10MB
                    ->columnSpanFull(),

                Forms\Components\Checkbox::make('remove_photo')
                    ->label('Kép törlése')
                    ->dehydrated(false)
                    ->visible(fn ($record) => $record?->photo)
                    ->reactive(),

                Forms\Components\Textarea::make('note')
                    ->label('Megjegyzés')
                    ->rows(2)
                    ->placeholder('Opcionális megjegyzés...')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo_thumb_url')
                    ->label('')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl(asset('images/placeholder-person.svg'))
                    ->width(48)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('position')
                    ->label('#')
                    ->sortable()
                    ->width(50),

                Tables\Columns\TextColumn::make('local_id')
                    ->label('Helyi ID')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('note')
                    ->label('Megjegyzés')
                    ->limit(80)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Hozzáadva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Tables\Grouping\Group::make('type')
                    ->label('Típus')
                    ->getTitleFromRecordUsing(fn ($record): HtmlString => match ($record->type) {
                        'teacher' => new HtmlString('<span style="display: inline-flex; align-items: center; gap: 8px;"><span style="width: 10px; height: 10px; background: #f59e0b; border-radius: 50%; display: inline-block;"></span> Tanárok</span>'),
                        'student' => new HtmlString('<span style="display: inline-flex; align-items: center; gap: 8px;"><span style="width: 10px; height: 10px; background: #3b82f6; border-radius: 50%; display: inline-block;"></span> Diákok</span>'),
                        default => new HtmlString($record->type),
                    })
                    ->collapsible(),
            ])
            ->defaultGroup('type')
            ->defaultSort('name', 'asc')
            ->headerActions([
                Action::make('uploadPhotos')
                    ->label('Képek feltöltése')
                    ->icon('heroicon-o-photo')
                    ->color('primary')
                    ->form([
                        Forms\Components\Radio::make('type')
                            ->label('Típus')
                            ->options([
                                'student' => 'Diák',
                                'teacher' => 'Tanár',
                            ])
                            ->default('student')
                            ->required()
                            ->inline(),

                        Forms\Components\FileUpload::make('photos')
                            ->label('Képek vagy ZIP fájl')
                            ->multiple()
                            ->acceptedFileTypes(['image/*', 'application/zip', 'application/x-zip-compressed'])
                            ->maxSize(102400) // 100MB
                            ->storeFiles(false)
                            ->imageEditor(false)
                            ->imagePreviewHeight('60')
                            ->extraAttributes(['style' => 'max-height: 400px; overflow-y: auto;'])
                            ->helperText('Képeket vagy egy ZIP fájlt tölthetsz fel'),
                    ])
                    ->action(function (array $data) {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();
                        $uploadedCount = 0;
                        $type = $data['type'] ?? 'student';

                        foreach ($data['photos'] ?? [] as $file) {
                            // Ha TemporaryUploadedFile objektum, használjuk közvetlenül
                            if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                $originalName = str_replace(' ', '_', $file->getClientOriginalName());

                                if (str_ends_with(strtolower($originalName), '.zip')) {
                                    // ZIP kezelés - másoljuk át temp helyre
                                    $tempPath = sys_get_temp_dir() . '/' . $originalName;
                                    copy($file->getRealPath(), $tempPath);

                                    $zip = new ZipArchive();
                                    if ($zip->open($tempPath) === true) {
                                        $tempDir = sys_get_temp_dir() . '/tablo_photos_' . uniqid();
                                        mkdir($tempDir, 0755, true);
                                        $zip->extractTo($tempDir);
                                        $zip->close();

                                        $iterator = new \RecursiveIteratorIterator(
                                            new \RecursiveDirectoryIterator($tempDir)
                                        );
                                        foreach ($iterator as $fileInfo) {
                                            if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $fileInfo->getFilename())) {
                                                if (str_starts_with($fileInfo->getFilename(), '.') || str_contains($fileInfo->getPathname(), '__MACOSX')) {
                                                    continue;
                                                }
                                                $project->addMedia($fileInfo->getPathname())
                                                    ->usingFileName(self::slugifyFilename($fileInfo->getFilename()))
                                                    ->preservingOriginal()
                                                    ->withCustomProperties(['type' => $type])
                                                    ->toMediaCollection('tablo_photos');
                                                $uploadedCount++;
                                            }
                                        }
                                        // Cleanup
                                        array_map('unlink', glob("$tempDir/*.*"));
                                        @rmdir($tempDir);
                                    }
                                    @unlink($tempPath);
                                } else {
                                    // Sima kép
                                    $project->addMediaFromStream($file->readStream())
                                        ->usingFileName(self::slugifyFilename($originalName))
                                        ->withCustomProperties(['type' => $type])
                                        ->toMediaCollection('tablo_photos');
                                    $uploadedCount++;
                                }
                                continue;
                            }

                            // Ellenőrizzük, létezik-e a fájl (string path esetén)
                            if (!Storage::disk('public')->exists($file)) {
                                Log::warning("Missing photos upload: file not found - {$file}");
                                continue;
                            }

                            $filePath = Storage::disk('public')->path($file);

                            // ZIP fájl kezelése
                            if (str_ends_with(strtolower($file), '.zip')) {
                                $zip = new ZipArchive();
                                if ($zip->open($filePath) === true) {
                                    $tempDir = sys_get_temp_dir() . '/tablo_photos_' . uniqid();
                                    mkdir($tempDir, 0755, true);
                                    $zip->extractTo($tempDir);
                                    $zip->close();

                                    // Képek feldolgozása a kicsomagolt mappából
                                    $iterator = new \RecursiveIteratorIterator(
                                        new \RecursiveDirectoryIterator($tempDir)
                                    );
                                    foreach ($iterator as $fileInfo) {
                                        if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $fileInfo->getFilename())) {
                                            // Rejtett fájlok kihagyása
                                            if (str_starts_with($fileInfo->getFilename(), '.') || str_starts_with($fileInfo->getFilename(), '__MACOSX')) {
                                                continue;
                                            }
                                            $project->addMedia($fileInfo->getPathname())
                                                ->usingFileName(self::slugifyFilename($fileInfo->getFilename()))
                                                ->preservingOriginal()
                                                ->withCustomProperties(['type' => $type])
                                                ->toMediaCollection('tablo_photos');
                                            $uploadedCount++;
                                        }
                                    }

                                    // Cleanup
                                    array_map('unlink', glob("$tempDir/*.*"));
                                    @rmdir($tempDir);
                                }
                                Storage::disk('public')->delete($file);
                            } else {
                                // Sima kép - preservingOriginal() hogy ne törölje azonnal
                                $project->addMedia($filePath)
                                    ->usingFileName(self::slugifyFilename(basename($filePath)))
                                    ->preservingOriginal()
                                    ->withCustomProperties(['type' => $type])
                                    ->toMediaCollection('tablo_photos');
                                $uploadedCount++;
                                // Töröljük a temp fájlt
                                Storage::disk('public')->delete($file);
                            }
                        }

                        // Ha volt feltöltés, jelöljük meg a projektet
                        if ($uploadedCount > 0) {
                            $project->update(['has_new_tablo_photos' => true]);
                        }

                        $typeLabel = $type === 'teacher' ? 'tanár' : 'diák';
                        Notification::make()
                            ->title("{$uploadedCount} {$typeLabel} kép feltöltve")
                            ->success()
                            ->send();
                    }),

                ActionGroup::make([
                    Action::make('viewPhotos')
                    ->label(function () {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();
                        $count = $project->getMedia('tablo_photos')->count();
                        return $count > 0 ? "Képek ({$count})" : 'Képek';
                    })
                    ->icon('heroicon-o-photo')
                    ->color('gray')
                    ->modalHeading('Feltöltött képek')
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Bezárás')
                    ->infolist(function () {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();
                        $photos = $project->getMedia('tablo_photos');

                        if ($photos->isEmpty()) {
                            return [
                                \Filament\Infolists\Components\TextEntry::make('empty')
                                    ->label('')
                                    ->state('Nincsenek feltöltött képek'),
                            ];
                        }

                        // URL encode helper for Hungarian characters
                        $encodeUrl = function ($url) {
                            $parts = parse_url($url);
                            if (isset($parts['path'])) {
                                $pathSegments = explode('/', $parts['path']);
                                $encodedSegments = array_map(fn($s) => rawurlencode($s), $pathSegments);
                                $parts['path'] = implode('/', $encodedSegments);
                            }
                            return (isset($parts['scheme']) ? $parts['scheme'] . '://' : '')
                                . ($parts['host'] ?? '')
                                . (isset($parts['port']) ? ':' . $parts['port'] : '')
                                . ($parts['path'] ?? '');
                        };

                        // Csoportosítás típus szerint
                        $teacherPhotos = $photos->filter(fn ($p) => $p->getCustomProperty('type') === 'teacher');
                        $studentPhotos = $photos->filter(fn ($p) => ($p->getCustomProperty('type') ?? 'student') === 'student');

                        $html = '';

                        // Tanárok section
                        if ($teacherPhotos->isNotEmpty()) {
                            $html .= '<div style="margin-bottom: 24px;">';
                            $html .= '<h3 style="font-weight: 600; font-size: 16px; margin-bottom: 12px; color: #374151; display: flex; align-items: center; gap: 8px;"><span style="width: 12px; height: 12px; background: #f59e0b; border-radius: 50%; display: inline-block;"></span> Tanárok (' . $teacherPhotos->count() . ')</h3>';
                            $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px;">';
                            foreach ($teacherPhotos as $photo) {
                                $fullUrl = $encodeUrl($photo->getUrl());
                                $thumbUrl = $encodeUrl($photo->getUrl('thumb'));
                                $name = e($photo->file_name);
                                $html .= "<div style='text-align: center; position: relative;'>"
                                    . "<span style='position: absolute; top: 6px; left: 6px; width: 14px; height: 14px; background: #f59e0b; border-radius: 50%; border: 2px solid white; box-shadow: 0 1px 3px rgba(0,0,0,0.3); z-index: 1;'></span>"
                                    . "<img src='{$thumbUrl}' onclick=\"event.stopPropagation(); event.preventDefault(); window.open('{$fullUrl}', '_blank', 'width=1200,height=900'); return false;\" style='width: 140px; height: 170px; object-fit: cover; object-position: top; border-radius: 8px; cursor: zoom-in; box-shadow: 0 2px 8px rgba(0,0,0,0.1);' title='Kattints a nagyításhoz' />"
                                    . "<div style='font-size: 10px; color: #666; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 140px;'>{$name}</div>"
                                    . "</div>";
                            }
                            $html .= '</div></div>';
                        }

                        // Diákok section
                        if ($studentPhotos->isNotEmpty()) {
                            $html .= '<div>';
                            $html .= '<h3 style="font-weight: 600; font-size: 16px; margin-bottom: 12px; color: #374151; display: flex; align-items: center; gap: 8px;"><span style="width: 12px; height: 12px; background: #3b82f6; border-radius: 50%; display: inline-block;"></span> Diákok (' . $studentPhotos->count() . ')</h3>';
                            $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px;">';
                            foreach ($studentPhotos as $photo) {
                                $fullUrl = $encodeUrl($photo->getUrl());
                                $thumbUrl = $encodeUrl($photo->getUrl('thumb'));
                                $name = e($photo->file_name);
                                $html .= "<div style='text-align: center; position: relative;'>"
                                    . "<span style='position: absolute; top: 6px; left: 6px; width: 14px; height: 14px; background: #3b82f6; border-radius: 50%; border: 2px solid white; box-shadow: 0 1px 3px rgba(0,0,0,0.3); z-index: 1;'></span>"
                                    . "<img src='{$thumbUrl}' onclick=\"event.stopPropagation(); event.preventDefault(); window.open('{$fullUrl}', '_blank', 'width=1200,height=900'); return false;\" style='width: 140px; height: 170px; object-fit: cover; object-position: top; border-radius: 8px; cursor: zoom-in; box-shadow: 0 2px 8px rgba(0,0,0,0.1);' title='Kattints a nagyításhoz' />"
                                    . "<div style='font-size: 10px; color: #666; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 140px;'>{$name}</div>"
                                    . "</div>";
                            }
                            $html .= '</div></div>';
                        }

                        return [
                            \Filament\Infolists\Components\TextEntry::make('gallery')
                                ->label('')
                                ->state(new HtmlString($html))
                                ->html(),
                        ];
                    }),

                Action::make('clearPhotos')
                    ->label('Képek törlése')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Összes kép törlése')
                    ->modalDescription('Biztosan törölni szeretnéd az összes feltöltött képet? Ez a művelet nem vonható vissza!')
                    ->visible(function () {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();

                        return $project->getMedia('tablo_photos')->count() > 0;
                    })
                    ->action(function () {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();
                        $count = $project->getMedia('tablo_photos')->count();
                        $project->clearMediaCollection('tablo_photos');

                        Notification::make()
                            ->title("{$count} kép törölve")
                            ->success()
                            ->send();
                    }),

                Action::make('autoMatch')
                    ->label('Automatikus párosítás')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Automatikus párosítás')
                    ->modalDescription('Az AI megpróbálja párosítani a feltöltött képeket a hiányzó személyekkel a fájlnevek alapján. A párosítás típus szerint történik (diák képek → diákokhoz, tanár képek → tanárokhoz).')
                    ->visible(function () {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();
                        $hasPhotos = $project->getMedia('tablo_photos')->count() > 0;
                        $hasUnmatchedPersons = $project->missingPersons()->whereNull('media_id')->exists();

                        return $hasPhotos && $hasUnmatchedPersons;
                    })
                    ->action(function () {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();

                        $totalMatchedCount = 0;
                        $allSummaries = [];

                        // Párosítatlan képek (még nem használt media_id-k)
                        $usedMediaIds = $project->missingPersons()
                            ->whereNotNull('media_id')
                            ->pluck('media_id')
                            ->toArray();

                        $allPhotos = $project->getMedia('tablo_photos')
                            ->filter(fn ($media) => ! in_array($media->id, $usedMediaIds));

                        // Típusonként csoportosítjuk és párosítjuk
                        foreach (['student', 'teacher'] as $type) {
                            $typeLabel = $type === 'teacher' ? 'Tanár' : 'Diák';

                            // Adott típusú párosítatlan személyek
                            $unmatchedPersons = $project->missingPersons()
                                ->whereNull('media_id')
                                ->where('type', $type)
                                ->get();

                            if ($unmatchedPersons->isEmpty()) {
                                continue;
                            }

                            $names = $unmatchedPersons->pluck('name')->toArray();

                            // Adott típusú képek (custom property alapján)
                            $availablePhotos = $allPhotos->filter(fn ($media) =>
                                ($media->getCustomProperty('type') ?? 'student') === $type
                            );

                            if ($availablePhotos->isEmpty()) {
                                continue;
                            }

                            $files = $availablePhotos->map(fn ($media) => [
                                'filename' => $media->file_name,
                                'mediaId' => $media->id,
                            ])->values()->toArray();

                            try {
                                $nameMatcher = app(NameMatcherService::class);
                                $result = $nameMatcher->match($names, $files);

                                $matchedCount = 0;

                                // Sikeres párosítások feldolgozása
                                foreach ($result->matches as $match) {
                                    Log::info("AutoMatch [{$type}]: Processing match", [
                                        'match_name' => $match['name'],
                                        'match_mediaId' => $match['mediaId'] ?? 'NULL',
                                        'match_filename' => $match['filename'] ?? 'N/A',
                                    ]);

                                    $person = $unmatchedPersons->firstWhere('name', $match['name']);

                                    if (!$person) {
                                        // Próbáljuk case-insensitive kereséssel
                                        $person = $unmatchedPersons->first(fn($p) =>
                                            mb_strtolower(trim($p->name)) === mb_strtolower(trim($match['name']))
                                        );
                                    }

                                    if ($person && ! empty($match['mediaId'])) {
                                        $person->update(['media_id' => $match['mediaId']]);
                                        $matchedCount++;
                                        $totalMatchedCount++;
                                    }
                                }

                                if ($matchedCount > 0 || count($result->unmatchedNames) > 0) {
                                    $allSummaries[] = "{$typeLabel}: {$matchedCount} párosítva";
                                }

                            } catch (\Exception $e) {
                                Log::error("Auto-match failed for {$type}", ['error' => $e->getMessage()]);
                                $allSummaries[] = "{$typeLabel}: hiba - " . $e->getMessage();
                            }
                        }

                        $notification = Notification::make()
                            ->title('Párosítás kész');

                        if ($totalMatchedCount > 0) {
                            $notification->body(implode("\n", $allSummaries))->success();
                        } elseif (!empty($allSummaries)) {
                            $notification->body(implode("\n", $allSummaries))->warning();
                        } else {
                            $notification->body('Nem találtunk párosítható képeket és személyeket.')->info();
                        }

                        $notification->send();
                    }),

                Action::make('manualMatch')
                    ->label('Kézi párosítás')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->modalHeading('Kézi párosítás')
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Bezárás')
                    ->visible(function () {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();

                        return $project->getMedia('tablo_photos')->count() > 0;
                    })
                    ->infolist(function () {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();

                        // Párosítatlan személyek
                        $unmatchedPersons = $project->missingPersons()
                            ->whereNull('media_id')
                            ->orderBy('name')
                            ->get();

                        // Párosítatlan képek
                        $usedMediaIds = $project->missingPersons()
                            ->whereNotNull('media_id')
                            ->pluck('media_id')
                            ->toArray();

                        $availablePhotos = $project->getMedia('tablo_photos')
                            ->filter(fn ($media) => ! in_array($media->id, $usedMediaIds));

                        // HTML felépítése
                        $html = '<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px; min-height: 400px;">';

                        // Bal oldal: Párosítatlan nevek
                        $html .= '<div style="border-right: 1px solid #e5e7eb; padding-right: 24px;">';
                        $html .= '<h3 style="font-weight: 600; margin-bottom: 12px;">Párosítatlan személyek ('.$unmatchedPersons->count().')</h3>';
                        $html .= '<div style="display: flex; flex-direction: column; gap: 8px; max-height: 500px; overflow-y: auto;">';

                        foreach ($unmatchedPersons as $person) {
                            $typeLabel = $person->type === 'teacher' ? 'T' : 'D';
                            $html .= '<div class="manual-match-person" data-person-id="'.$person->id.'" style="padding: 8px 12px; background: #f3f4f6; border-radius: 6px; cursor: pointer; transition: all 0.2s;" onclick="selectPerson(this, '.$person->id.')">';
                            $html .= '<span style="font-size: 11px; color: #6b7280; margin-right: 4px;">['.$typeLabel.']</span>';
                            $html .= '<span>'.e($person->name).'</span>';
                            $html .= '</div>';
                        }

                        $html .= '</div></div>';

                        // Jobb oldal: Képek grid
                        $html .= '<div>';
                        $html .= '<h3 style="font-weight: 600; margin-bottom: 12px;">Elérhető képek ('.$availablePhotos->count().')</h3>';
                        $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px; max-height: 500px; overflow-y: auto;">';

                        foreach ($availablePhotos as $photo) {
                            $thumbUrl = $photo->getUrl('thumb');
                            $filename = e($photo->file_name);
                            $html .= '<div class="manual-match-photo" data-media-id="'.$photo->id.'" style="text-align: center; cursor: pointer;" onclick="selectPhoto(this, '.$photo->id.')">';
                            $html .= '<img src="'.$thumbUrl.'" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 3px solid transparent; transition: all 0.2s;" />';
                            $html .= '<div style="font-size: 10px; color: #6b7280; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100px;">'.$filename.'</div>';
                            $html .= '</div>';
                        }

                        $html .= '</div></div>';
                        $html .= '</div>';

                        // JavaScript a párosításhoz
                        $html .= '<script>
                            let selectedPersonId = null;
                            let selectedMediaId = null;

                            function selectPerson(el, id) {
                                document.querySelectorAll(".manual-match-person").forEach(e => e.style.background = "#f3f4f6");
                                el.style.background = "#dbeafe";
                                selectedPersonId = id;
                                tryMatch();
                            }

                            function selectPhoto(el, id) {
                                document.querySelectorAll(".manual-match-photo img").forEach(e => e.style.borderColor = "transparent");
                                el.querySelector("img").style.borderColor = "#3b82f6";
                                selectedMediaId = id;
                                tryMatch();
                            }

                            function tryMatch() {
                                if (selectedPersonId && selectedMediaId) {
                                    fetch("/admin/api/missing-person-match", {
                                        method: "POST",
                                        headers: {
                                            "Content-Type": "application/json",
                                            "X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]")?.content || ""
                                        },
                                        body: JSON.stringify({ person_id: selectedPersonId, media_id: selectedMediaId })
                                    })
                                    .then(r => r.json())
                                    .then(data => {
                                        if (data.success) {
                                            // Eltüntetjük a párosított elemeket
                                            document.querySelector("[data-person-id=\"" + selectedPersonId + "\"]")?.remove();
                                            document.querySelector("[data-media-id=\"" + selectedMediaId + "\"]")?.remove();
                                            selectedPersonId = null;
                                            selectedMediaId = null;
                                        }
                                    });
                                }
                            }
                        </script>';

                        $html .= '<style>
                            .manual-match-person:hover { background: #e5e7eb !important; }
                            .manual-match-photo:hover img { border-color: #93c5fd !important; }
                        </style>';

                        return [
                            \Filament\Infolists\Components\TextEntry::make('manual_match')
                                ->label('')
                                ->state(new HtmlString($html))
                                ->html(),
                        ];
                    }),
                ])
                    ->label('Képek kezelése')
                    ->icon('heroicon-o-photo')
                    ->color('gray')
                    ->button(),

                Action::make('importList')
                    ->label('Importálás listából')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Típus')
                            ->options([
                                'student' => 'Diák',
                                'teacher' => 'Tanár',
                            ])
                            ->default('student')
                            ->required(),

                        Forms\Components\Textarea::make('names')
                            ->label('Nevek (soronként egy)')
                            ->placeholder("Kovács János\nNagy Péter\nSzabó Anna")
                            ->required()
                            ->rows(10)
                            ->helperText('Másold be a neveket, soronként egyet'),
                    ])
                    ->action(function (array $data) {
                        $project = $this->getOwnerRecord();
                        $names = array_filter(array_map('trim', explode("\n", $data['names'])));

                        if (empty($names)) {
                            Notification::make()
                                ->title('Nincs importálható név')
                                ->warning()
                                ->send();
                            return;
                        }

                        $maxPosition = $project->missingPersons()->max('position') ?? 0;
                        $imported = 0;

                        foreach ($names as $name) {
                            if (empty($name)) continue;

                            // Ellenőrizzük, hogy már létezik-e
                            $exists = $project->missingPersons()
                                ->where('name', $name)
                                ->where('type', $data['type'])
                                ->exists();

                            if (!$exists) {
                                $maxPosition++;
                                TabloMissingPerson::create([
                                    'tablo_project_id' => $project->id,
                                    'name' => $name,
                                    'type' => $data['type'],
                                    'position' => $maxPosition,
                                ]);
                                $imported++;
                            }
                        }

                        Notification::make()
                            ->title("{$imported} személy importálva")
                            ->body(count($names) - $imported > 0
                                ? (count($names) - $imported) . ' már létezett'
                                : null)
                            ->success()
                            ->send();
                    }),

                CreateAction::make()
                    ->label('Új Hiányzó Személy'),
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth('xl')
                    ->using(function (TabloMissingPerson $record, array $data) {
                        // Handle photo removal
                        if (! empty($data['remove_photo'])) {
                            $record->update(['media_id' => null]);
                        }

                        // Handle photo upload (TemporaryUploadedFile from storeFiles(false))
                        if (! empty($data['upload_photo'])) {
                            $file = $data['upload_photo'];

                            if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                $project = $record->project;
                                $originalName = $file->getClientOriginalName();

                                $media = $project->addMediaFromStream($file->readStream())
                                    ->usingFileName(self::slugifyFilename($originalName))
                                    ->withCustomProperties(['type' => $data['type'] ?? 'student'])
                                    ->toMediaCollection('tablo_photos');

                                $record->update(['media_id' => $media->id]);

                                // Jelöljük meg a projektet, hogy új kép érkezett
                                $project->update(['has_new_tablo_photos' => true]);
                            }
                        }

                        // Update other fields
                        $record->update([
                            'name' => $data['name'],
                            'type' => $data['type'],
                            'local_id' => $data['local_id'] ?? null,
                            'note' => $data['note'] ?? null,
                        ]);

                        return $record;
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
