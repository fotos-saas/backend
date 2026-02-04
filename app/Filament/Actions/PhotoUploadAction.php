<?php

namespace App\Filament\Actions;

use App\DTOs\PhotoMatchResult;
use App\Models\OrphanPhoto;
use App\Models\TabloPerson;
use App\Models\TabloProject;
use App\Services\PhotoMatcherService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use ZipArchive;

/**
 * Fotós képfeltöltő action.
 * AI alapú párosítás a hiányzó személyekhez.
 */
class PhotoUploadAction
{
    /**
     * Maximum ZIP fájl méret (500MB)
     */
    private const MAX_ZIP_SIZE = 524288000;

    /**
     * Maximum kicsomagolt méret (2GB) - ZIP bomb védelem
     */
    private const MAX_EXTRACTED_SIZE = 2147483648;

    /**
     * Maximum fájlszám egy ZIP-ben
     */
    private const MAX_FILES_IN_ZIP = 1000;

    /**
     * Támogatott képformátumok
     */
    private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public static function make(): Action
    {
        return Action::make('photoUpload')
            ->label('Fotós képfeltöltés')
            ->icon('heroicon-o-camera')
            ->color('primary')
            ->modalHeading('Képek feltöltése és párosítása')
            ->modalWidth('6xl')
            ->modalSubmitActionLabel('Párosítás indítása')
            ->form([
                Forms\Components\Radio::make('type')
                    ->label('Típus')
                    ->options([
                        'student' => 'Diák',
                        'teacher' => 'Tanár',
                    ])
                    ->default('student')
                    ->required()
                    ->inline()
                    ->columnSpanFull(),

                Forms\Components\Select::make('project_id')
                    ->label('Projekt (opcionális)')
                    ->placeholder('AI automatikusan detektálja')
                    ->options(fn () => TabloProject::with('school')
                        ->whereHas('persons', fn ($q) => $q->whereNull('media_id'))
                        ->get()
                        ->mapWithKeys(fn ($p) => [
                            $p->id => ($p->school?->name ?? 'Ismeretlen iskola') . ' - ' . $p->class_name . ' (' . $p->class_year . ')',
                        ])
                    )
                    ->searchable()
                    ->helperText('Ha üresen hagyod, az AI a fájlnevekből próbálja kitalálni a projektet')
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('photos')
                    ->label('Képek')
                    ->multiple()
                    ->acceptedFileTypes(['image/*', 'application/zip', 'application/x-zip-compressed'])
                    ->maxSize(102400) // 100MB
                    ->storeFiles(false)
                    ->imageEditor(false)
                    ->imagePreviewHeight('80')
                    ->panelLayout('grid')
                    ->helperText('Húzd ide a képeket vagy egy ZIP fájlt. Maximum 100MB.')
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) {
                $result = static::processUpload($data);

                if ($result->totalCount() === 0) {
                    Notification::make()
                        ->title('Nincs feldolgozandó kép')
                        ->warning()
                        ->send();

                    return;
                }

                // Sikeres párosítások mentése
                $savedMatches = 0;
                foreach ($result->matches as $match) {
                    if (! empty($match['person_id']) && ! empty($match['media_id'])) {
                        TabloPerson::where('id', $match['person_id'])
                            ->update(['media_id' => $match['media_id']]);
                        $savedMatches++;

                        // Tanár szinkronizálás más projektekhez
                        if (($data['type'] ?? 'student') === 'teacher') {
                            static::syncTeacherToOtherProjects(
                                $match['person_name'],
                                $match['media_id'],
                                $match['project_id']
                            );
                        }
                    }
                }

                // Orphan képek mentése
                $savedOrphans = 0;
                foreach ($result->orphans as $orphan) {
                    if (! empty($orphan['media_id'])) {
                        OrphanPhoto::create([
                            'suggested_name' => $orphan['suggested_name'] ?? null,
                            'type' => $data['type'] ?? 'unknown',
                            'media_id' => $orphan['media_id'],
                            'original_filename' => $orphan['filename'],
                            'source_info' => [
                                'uploaded_at' => now()->toIso8601String(),
                                'uploaded_by' => auth()->id(),
                                'reason' => $orphan['reason'] ?? null,
                            ],
                        ]);
                        $savedOrphans++;
                    }
                }

                // Projekt flag frissítése
                if ($savedMatches > 0 && ! empty($data['project_id'])) {
                    TabloProject::where('id', $data['project_id'])
                        ->update(['has_new_missing_photos' => true]);
                }

                // Notification
                $notification = Notification::make()->title('Párosítás kész');

                if ($savedMatches > 0) {
                    $notification->body($result->getSummary())->success();
                } elseif ($savedOrphans > 0) {
                    $notification->body($result->getSummary() . "\n\nA talon képek a 'Talon képek' menüpontban találhatók.")->warning();
                } else {
                    $notification->body('Nem sikerült párosítani a képeket.')->danger();
                }

                $notification->send();
            });
    }

    /**
     * Képfeltöltés feldolgozása.
     */
    protected static function processUpload(array $data): PhotoMatchResult
    {
        $type = $data['type'] ?? 'student';
        $projectId = $data['project_id'] ?? null;
        $photos = $data['photos'] ?? [];

        if (empty($photos)) {
            return new PhotoMatchResult();
        }

        // Képek feldolgozása (ZIP kicsomagolás, media létrehozás)
        $processedFiles = static::processFiles($photos, $type, $projectId);

        if (empty($processedFiles)) {
            return new PhotoMatchResult();
        }

        // Ha van project_id, ahhoz párosítunk
        if ($projectId) {
            $project = TabloProject::find($projectId);
            if ($project) {
                $matcher = app(PhotoMatcherService::class);

                return $matcher->matchToProject($project, $processedFiles, $type);
            }
        }

        // AI alapú párosítás (projekt detektálással)
        $matcher = app(PhotoMatcherService::class);

        return $matcher->matchPhotos($processedFiles, $type, $projectId);
    }

    /**
     * Fájlok feldolgozása és média létrehozása.
     *
     * @return array<int, array{filename: string, mediaId: int}>
     */
    protected static function processFiles(array $photos, string $type, ?int $projectId): array
    {
        $files = [];

        // Ha van projekt, ahhoz rendeljük a média-t
        $mediaOwner = $projectId ? TabloProject::find($projectId) : null;

        foreach ($photos as $photo) {
            if ($photo instanceof TemporaryUploadedFile) {
                $originalName = $photo->getClientOriginalName();

                if (str_ends_with(strtolower($originalName), '.zip')) {
                    // ZIP feldolgozás
                    $files = array_merge($files, static::processZip($photo, $type, $mediaOwner));
                } else {
                    // Sima kép
                    $media = static::saveMediaFromUpload($photo, $type, $mediaOwner);
                    if ($media) {
                        $files[] = [
                            'filename' => $originalName,
                            'mediaId' => $media->id,
                        ];
                    }
                }
            }
        }

        return $files;
    }

    /**
     * ZIP fájl feldolgozása biztonsági ellenőrzésekkel.
     *
     * @return array<int, array{filename: string, mediaId: int}>
     *
     * @throws \RuntimeException Ha a ZIP fájl nem biztonságos
     */
    protected static function processZip(TemporaryUploadedFile $zipFile, string $type, ?TabloProject $mediaOwner): array
    {
        $files = [];
        $tempPath = sys_get_temp_dir() . '/' . uniqid('zip_') . '.zip';
        copy($zipFile->getRealPath(), $tempPath);

        // ZIP méret ellenőrzés
        $zipSize = filesize($tempPath);
        if ($zipSize > self::MAX_ZIP_SIZE) {
            @unlink($tempPath);
            Log::warning('PhotoUploadAction: ZIP file too large', ['size' => $zipSize]);
            throw new \RuntimeException('A ZIP fájl túl nagy (max 500MB)');
        }

        $zip = new ZipArchive();
        if ($zip->open($tempPath) !== true) {
            @unlink($tempPath);
            throw new \RuntimeException('Nem sikerült megnyitni a ZIP fájlt');
        }

        // Fájlszám limit ellenőrzés
        if ($zip->numFiles > self::MAX_FILES_IN_ZIP) {
            $zip->close();
            @unlink($tempPath);
            Log::warning('PhotoUploadAction: Too many files in ZIP', ['count' => $zip->numFiles]);
            throw new \RuntimeException('A ZIP fájl túl sok fájlt tartalmaz (max ' . self::MAX_FILES_IN_ZIP . ')');
        }

        // ZIP bomb védelem - előzetes méret ellenőrzés
        $totalExtractedSize = 0;
        $validEntries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $entryName = $stat['name'];

            // Path traversal védelem
            if (str_contains($entryName, '../') || str_starts_with($entryName, '/') || str_contains($entryName, '..\\')) {
                Log::warning('PhotoUploadAction: Path traversal attempt', ['entry' => $entryName]);
                continue;
            }

            // Könyvtárak kihagyása
            if (str_ends_with($entryName, '/')) {
                continue;
            }

            // Rejtett fájlok és rendszerfájlok kihagyása
            $basename = basename($entryName);
            if (str_starts_with($basename, '.') || str_contains($entryName, '__MACOSX') || str_contains($entryName, 'Thumbs.db') || str_contains($entryName, 'desktop.ini')) {
                continue;
            }

            // Kiterjesztés ellenőrzés
            $extension = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
            if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                continue;
            }

            // ZIP bomb védelem - kumulatív méret
            $totalExtractedSize += $stat['size'];
            if ($totalExtractedSize > self::MAX_EXTRACTED_SIZE) {
                $zip->close();
                @unlink($tempPath);
                Log::warning('PhotoUploadAction: ZIP bomb detected', [
                    'extracted_size' => $totalExtractedSize,
                    'limit' => self::MAX_EXTRACTED_SIZE,
                ]);
                throw new \RuntimeException('A ZIP fájl kicsomagolt mérete túl nagy (ZIP bomb védelem)');
            }

            $validEntries[] = ['index' => $i, 'name' => $entryName];
        }

        // Biztonságos kicsomagolás - csak az ellenőrzött fájlok
        $tempDir = sys_get_temp_dir() . '/photo_upload_' . uniqid();
        mkdir($tempDir, 0755, true);

        foreach ($validEntries as $entry) {
            $content = $zip->getFromIndex($entry['index']);
            if ($content === false) {
                continue;
            }

            $targetPath = $tempDir . '/' . basename($entry['name']);

            // Valódi kép ellenőrzés a tartalom alapján
            file_put_contents($targetPath, $content);

            $imageInfo = @getimagesize($targetPath);
            if ($imageInfo === false) {
                Log::warning('PhotoUploadAction: Invalid image in ZIP', ['entry' => $entry['name']]);
                @unlink($targetPath);
                continue;
            }

            $allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
            if (! in_array($imageInfo[2], $allowedImageTypes, true)) {
                Log::warning('PhotoUploadAction: Unsupported image type in ZIP', [
                    'entry' => $entry['name'],
                    'type' => $imageInfo[2],
                ]);
                @unlink($targetPath);
                continue;
            }

            $media = static::saveMediaFromPath($targetPath, basename($entry['name']), $type, $mediaOwner);
            if ($media) {
                $files[] = [
                    'filename' => basename($entry['name']),
                    'mediaId' => $media->id,
                ];
            }

            @unlink($targetPath);
        }

        $zip->close();

        // Cleanup
        static::deleteDirectory($tempDir);
        @unlink($tempPath);

        return $files;
    }

    /**
     * Média mentése feltöltött fájlból.
     */
    protected static function saveMediaFromUpload(TemporaryUploadedFile $file, string $type, ?TabloProject $owner): ?Media
    {
        try {
            // Valódi kép ellenőrzés
            $imageInfo = @getimagesize($file->getRealPath());
            if ($imageInfo === false) {
                Log::warning('PhotoUploadAction: Invalid image file', [
                    'filename' => $file->getClientOriginalName(),
                ]);

                return null;
            }

            $allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
            if (! in_array($imageInfo[2], $allowedImageTypes, true)) {
                Log::warning('PhotoUploadAction: Unsupported image type', [
                    'filename' => $file->getClientOriginalName(),
                    'type' => $imageInfo[2],
                ]);

                return null;
            }

            $originalName = $file->getClientOriginalName();
            $slugName = static::slugifyFilename($originalName);

            if ($owner) {
                return $owner->addMediaFromStream($file->readStream())
                    ->usingFileName($slugName)
                    ->withCustomProperties([
                        'type' => $type,
                        'original_filename' => $originalName,
                    ])
                    ->toMediaCollection('tablo_photos');
            }

            // Nincs owner → globális orphan kollekció
            // Használjuk az első elérhető projektet mint owner (workaround)
            $tempOwner = TabloProject::first();
            if ($tempOwner) {
                return $tempOwner->addMediaFromStream($file->readStream())
                    ->usingFileName($slugName)
                    ->withCustomProperties([
                        'type' => $type,
                        'original_filename' => $originalName,
                        'is_orphan' => true,
                    ])
                    ->toMediaCollection('orphan_photos');
            }

            Log::warning('PhotoUploadAction: No project available for orphan media');

            return null;
        } catch (\Exception $e) {
            Log::error('PhotoUploadAction: Failed to save media from upload', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Média mentése fájl útvonalból.
     */
    protected static function saveMediaFromPath(string $path, string $originalName, string $type, ?TabloProject $owner): ?Media
    {
        try {
            $slugName = static::slugifyFilename($originalName);

            if ($owner) {
                return $owner->addMedia($path)
                    ->usingFileName($slugName)
                    ->preservingOriginal()
                    ->withCustomProperties([
                        'type' => $type,
                        'original_filename' => $originalName,
                    ])
                    ->toMediaCollection('tablo_photos');
            }

            // Nincs owner → globális orphan kollekció
            $tempOwner = TabloProject::first();
            if ($tempOwner) {
                return $tempOwner->addMedia($path)
                    ->usingFileName($slugName)
                    ->preservingOriginal()
                    ->withCustomProperties([
                        'type' => $type,
                        'original_filename' => $originalName,
                        'is_orphan' => true,
                    ])
                    ->toMediaCollection('orphan_photos');
            }

            Log::warning('PhotoUploadAction: No project available for orphan media');

            return null;
        } catch (\Exception $e) {
            Log::error('PhotoUploadAction: Failed to save media from path', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);

            return null;
        }
    }

    /**
     * Tanár kép szinkronizálása más projektekhez.
     */
    protected static function syncTeacherToOtherProjects(string $teacherName, int $mediaId, int $excludeProjectId): void
    {
        $otherPersons = TabloPerson::where('name', $teacherName)
            ->where('type', 'teacher')
            ->where('tablo_project_id', '!=', $excludeProjectId)
            ->whereNull('media_id')
            ->get();

        foreach ($otherPersons as $person) {
            $person->update(['media_id' => $mediaId]);

            Log::info('PhotoUploadAction: Teacher photo synced to other project', [
                'teacher_name' => $teacherName,
                'person_id' => $person->id,
                'project_id' => $person->tablo_project_id,
            ]);
        }
    }

    /**
     * Fájlnév slugify (ékezetek, speciális karakterek kezelése).
     */
    protected static function slugifyFilename(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        $slug = Str::slug($name);

        if (empty($slug)) {
            $slug = 'photo-' . uniqid();
        }

        return $slug . '.' . strtolower($extension);
    }

    /**
     * Könyvtár rekurzív törlése.
     */
    protected static function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? static::deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
