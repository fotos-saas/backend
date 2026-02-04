<?php

namespace App\Services;

use App\Jobs\GenerateMediaThumbnailJob;
use App\Models\TabloMissingPerson;
use App\Models\TabloProject;
use App\Traits\FileValidation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use ZipArchive;

/**
 * Partner képfeltöltés kezelő service.
 *
 * Kezeli a bulk feltöltést, ZIP kicsomagolást, talon mozgatást
 * és az egyéni kép verziózást.
 */
class PartnerPhotoService
{
    use FileValidation;
    /**
     * Támogatott képformátumok
     */
    private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Maximum ZIP méret (500MB)
     */
    private const MAX_ZIP_SIZE = 524288000;

    /**
     * Hidden fájlok pattern-ek (kihagyandó)
     */
    private const HIDDEN_PATTERNS = ['__MACOSX', '.DS_Store', 'Thumbs.db', 'desktop.ini'];

    public function __construct(
        protected MetadataReaderService $metadataReader
    ) {}

    /**
     * Bulk upload képek a tablo_pending collection-be
     *
     * @param  TabloProject  $project  Projekt
     * @param  array<UploadedFile>  $files  Feltöltött fájlok
     * @param  string  $album  Album típus ('students' | 'teachers')
     * @return Collection<Media> Feltöltött média rekordok
     */
    public function bulkUpload(TabloProject $project, array $files, string $album): Collection
    {
        $uploadedMedia = collect();

        foreach ($files as $file) {
            if (! $this->isValidImageFile($file)) {
                Log::warning('PartnerPhoto: Invalid file skipped', [
                    'filename' => $file->getClientOriginalName(),
                ]);

                continue;
            }

            try {
                $media = $this->uploadSinglePhoto($project, $file, $album);
                $uploadedMedia->push($media);
            } catch (\Exception $e) {
                Log::error('PartnerPhoto: Upload failed', [
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('PartnerPhoto: Bulk upload completed', [
            'project_id' => $project->id,
            'uploaded_count' => $uploadedMedia->count(),
            'total_files' => count($files),
            'album' => $album,
        ]);

        return $uploadedMedia;
    }

    /**
     * ZIP fájl kicsomagolása és feltöltése
     *
     * @param  TabloProject  $project  Projekt
     * @param  UploadedFile  $zipFile  ZIP fájl
     * @param  string  $album  Album típus ('students' | 'teachers')
     * @return Collection<Media> Feltöltött média rekordok
     */
    public function uploadFromZip(TabloProject $project, UploadedFile $zipFile, string $album): Collection
    {
        $zip = new ZipArchive;
        $result = $zip->open($zipFile->getRealPath());

        if ($result !== true) {
            throw new \RuntimeException('Nem sikerült megnyitni a ZIP fájlt. Hibakód: '.$result);
        }

        $uploadedMedia = collect();
        $totalExtractedSize = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $entryName = $stat['name'];

                // Security és szűrés
                if ($this->shouldSkipZipEntry($entryName)) {
                    continue;
                }

                // ZIP bomb védelem
                $totalExtractedSize += $stat['size'];
                if ($totalExtractedSize > self::MAX_ZIP_SIZE) {
                    throw new \RuntimeException('A ZIP fájl túl nagy (max 500MB kicsomagolva)');
                }

                // Kicsomagolás és feltöltés
                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    continue;
                }

                $tempPath = sys_get_temp_dir().'/'.uniqid('zip_').'_'.basename($entryName);
                file_put_contents($tempPath, $content);

                try {
                    $uploadedFile = new UploadedFile(
                        $tempPath,
                        basename($entryName),
                        mime_content_type($tempPath),
                        null,
                        true
                    );

                    if ($this->isValidImageFile($uploadedFile)) {
                        $media = $this->uploadSinglePhoto($project, $uploadedFile, $album);
                        $uploadedMedia->push($media);
                    }
                } finally {
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                }
            }
        } finally {
            $zip->close();
        }

        Log::info('PartnerPhoto: ZIP upload completed', [
            'project_id' => $project->id,
            'uploaded_count' => $uploadedMedia->count(),
            'zip_name' => $zipFile->getClientOriginalName(),
            'album' => $album,
        ]);

        return $uploadedMedia;
    }

    /**
     * Egyetlen kép feltöltése a pending collection-be
     *
     * @param  TabloProject  $project  Projekt
     * @param  UploadedFile  $file  Feltöltött fájl
     * @param  string  $album  Album típus ('students' | 'teachers')
     * @return Media
     */
    protected function uploadSinglePhoto(TabloProject $project, UploadedFile $file, string $album): Media
    {
        // IPTC title kinyerése a feltöltés előtt
        $iptcTitle = $this->metadataReader->extractTitle($file->getRealPath());

        // NFC normalizálás a fájlnévre (macOS NFD → NFC)
        $originalName = $file->getClientOriginalName();
        if (class_exists('Normalizer')) {
            $originalName = \Normalizer::normalize($originalName, \Normalizer::FORM_C);
        }

        $customProperties = [
            'album' => $album,
            'iptc_title' => $iptcTitle,
            'uploaded_at' => now()->toIso8601String(),
            'uploaded_by' => 'partner',
        ];

        $media = $project
            ->addMedia($file)
            ->preservingOriginal()
            ->usingFileName($this->sanitizeFilename($originalName))
            ->withCustomProperties($customProperties)
            ->toMediaCollection('tablo_pending');

        // Thumbnail generálás async (háttérben)
        // A feltöltés azonnal visszatér, a thumbnail később készül
        GenerateMediaThumbnailJob::dispatch($media->id)->onQueue('thumbnails');

        return $media;
    }

    /**
     * Képek áthelyezése a talonba (párosítás nélkül)
     *
     * @param  TabloProject  $project  Projekt
     * @param  array<int>  $mediaIds  Média ID-k
     */
    public function moveToTalon(TabloProject $project, array $mediaIds): int
    {
        $moved = 0;
        // Biztosítjuk, hogy int-ek legyenek
        $mediaIds = array_map('intval', $mediaIds);

        // Collection filter - a whereIn nem működik jól Spatie Media objektumokon
        $media = $project->getMedia('tablo_pending')
            ->filter(fn ($m) => in_array($m->id, $mediaIds, true));

        foreach ($media as $item) {
            // Spatie Media Library: collection változtatás
            $item->move($project, 'talon_photos');
            $item->setCustomProperty('moved_to_talon_at', now()->toIso8601String());
            $item->save();
            $moved++;
        }

        Log::info('PartnerPhoto: Moved to talon', [
            'project_id' => $project->id,
            'moved_count' => $moved,
        ]);

        return $moved;
    }

    /**
     * Személyhez kép hozzárendelése (verziózással)
     *
     * @param  TabloMissingPerson  $person  Személy
     * @param  UploadedFile  $file  Új kép
     * @return Media Az új média rekord
     */
    public function uploadPersonPhoto(TabloMissingPerson $person, UploadedFile $file): Media
    {
        return DB::transaction(function () use ($person, $file) {
            $project = $person->project;

            // Régi képek archiválása (ha vannak)
            $this->archiveOldPhotos($person);

            // Új kép feltöltése a tablo_photos collection-be
            $originalName = $file->getClientOriginalName();
            if (class_exists('Normalizer')) {
                $originalName = \Normalizer::normalize($originalName, \Normalizer::FORM_C);
            }

            // Verziószám meghatározása
            $version = $this->getNextPhotoVersion($person);

            $media = $project
                ->addMedia($file)
                ->preservingOriginal()
                ->usingFileName($this->sanitizeFilename($originalName))
                ->withCustomProperties([
                    'person_id' => $person->id,
                    'person_name' => $person->name,
                    'version' => $version,
                    'is_active' => true,
                    'uploaded_by' => 'partner',
                    'uploaded_at' => now()->toIso8601String(),
                ])
                ->toMediaCollection('tablo_photos');

            // Személy frissítése az új média ID-val
            $person->update(['media_id' => $media->id]);

            Log::info('PartnerPhoto: Person photo uploaded', [
                'person_id' => $person->id,
                'person_name' => $person->name,
                'media_id' => $media->id,
                'version' => $version,
            ]);

            return $media;
        });
    }

    /**
     * Képek hozzárendelése személyekhez (bulk)
     *
     * @param  TabloProject  $project  Projekt
     * @param  array<array{personId: int, mediaId: int}>  $assignments  Párosítások
     * @return int Sikeres párosítások száma
     */
    public function assignPhotos(TabloProject $project, array $assignments): int
    {
        $assigned = 0;

        DB::transaction(function () use ($project, $assignments, &$assigned) {
            foreach ($assignments as $assignment) {
                $personId = (int) $assignment['personId'];
                $mediaId = (int) $assignment['mediaId'];

                $person = $project->missingPersons()->find($personId);

                // Közvetlen DB query - a getMedia() cache problémás
                $media = Media::where('id', $mediaId)
                    ->where('model_id', $project->id)
                    ->where('model_type', TabloProject::class)
                    ->where('collection_name', 'tablo_pending')
                    ->first();

                if (! $person || ! $media) {
                    Log::warning('PartnerPhoto: Assignment skipped - not found', [
                        'person_id' => $personId,
                        'media_id' => $mediaId,
                    ]);

                    continue;
                }

                // Régi képek archiválása
                $this->archiveOldPhotos($person);

                // Média áthelyezése és tulajdonságok frissítése
                // FONTOS: move() új média rekordot hoz létre új ID-val, a régit törli!
                $media = $media->move($project, 'tablo_photos');
                $media->setCustomProperty('person_id', $person->id);
                $media->setCustomProperty('person_name', $person->name);
                $media->setCustomProperty('is_active', true);
                $media->setCustomProperty('assigned_at', now()->toIso8601String());
                $media->save();

                // Személy frissítése
                $person->update(['media_id' => $media->id]);
                $assigned++;
            }
        });

        Log::info('PartnerPhoto: Photos assigned', [
            'project_id' => $project->id,
            'assigned_count' => $assigned,
            'total_assignments' => count($assignments),
        ]);

        return $assigned;
    }

    /**
     * Pending képek listázása
     *
     * @return Collection<array{mediaId: int, filename: string, iptcTitle: string|null, thumbUrl: string, fullUrl: string}>
     */
    public function getPendingPhotos(TabloProject $project): Collection
    {
        return $project->getMedia('tablo_pending')
            ->map(fn (Media $media) => [
                'mediaId' => $media->id,
                'filename' => $media->file_name,
                'iptcTitle' => $media->getCustomProperty('iptc_title'),
                'thumbUrl' => $media->getUrl('thumb'),
                'fullUrl' => $media->getUrl(),
                'uploadedAt' => $media->getCustomProperty('uploaded_at'),
            ]);
    }

    /**
     * Talon képek listázása
     */
    public function getTalonPhotos(TabloProject $project): Collection
    {
        return $project->getMedia('talon_photos')
            ->map(fn (Media $media) => [
                'mediaId' => $media->id,
                'filename' => $media->file_name,
                'iptcTitle' => $media->getCustomProperty('iptc_title'),
                'thumbUrl' => $media->getUrl('thumb'),
                'fullUrl' => $media->getUrl(),
            ]);
    }

    /**
     * Régi képek archiválása - áthelyezés tablo_archived-ba
     */
    protected function archiveOldPhotos(TabloMissingPerson $person): void
    {
        if (! $person->media_id) {
            return;
        }

        $project = $person->project;

        // Régi aktív képek keresése (tablo_photos-ból)
        $oldPhotos = $project->getMedia('tablo_photos')
            ->where('custom_properties.person_id', $person->id)
            ->where('custom_properties.is_active', true);

        foreach ($oldPhotos as $oldPhoto) {
            // Áthelyezés az archív collection-be
            $oldPhoto = $oldPhoto->move($project, 'tablo_archived');
            $oldPhoto->setCustomProperty('is_active', false);
            $oldPhoto->setCustomProperty('archived_at', now()->toIso8601String());
            $oldPhoto->save();
        }
    }

    /**
     * Következő verziószám meghatározása
     */
    protected function getNextPhotoVersion(TabloMissingPerson $person): int
    {
        $project = $person->project;

        // Mindkét collection-ből (aktív + archivált) számoljuk a verziókat
        $activePhotos = $project->getMedia('tablo_photos')
            ->where('custom_properties.person_id', $person->id);

        $archivedPhotos = $project->getMedia('tablo_archived')
            ->where('custom_properties.person_id', $person->id);

        $existingPhotos = $activePhotos->merge($archivedPhotos);

        if ($existingPhotos->isEmpty()) {
            return 1;
        }

        $maxVersion = $existingPhotos
            ->max(fn ($m) => $m->getCustomProperty('version', 0));

        return $maxVersion + 1;
    }

    /**
     * Érvényes képfájl ellenőrzése.
     *
     * Többszintű validáció:
     * 1. Kiterjesztés ellenőrzés
     * 2. MIME type ellenőrzés
     * 3. Valódi kép ellenőrzés (getimagesize) - MIME spoofing ellen
     */
    protected function isValidImageFile(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::SUPPORTED_EXTENSIONS)) {
            return false;
        }

        // MIME type ellenőrzés
        $mimeType = $file->getMimeType();
        $validMimes = ['image/jpeg', 'image/png', 'image/webp'];

        if (! in_array($mimeType, $validMimes)) {
            return false;
        }

        // Valódi kép ellenőrzés - getimagesize() a fájl bináris tartalmát vizsgálja
        // Ez véd a MIME spoofing ellen (pl. PHP fájl .jpg kiterjesztéssel)
        $imageInfo = @getimagesize($file->getRealPath());
        if ($imageInfo === false) {
            Log::warning('PartnerPhoto: File failed getimagesize validation', [
                'filename' => $file->getClientOriginalName(),
                'mime' => $mimeType,
            ]);

            return false;
        }

        // Csak a ténylegesen támogatott image type-ok
        $allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

        if (! in_array($imageInfo[2], $allowedImageTypes, true)) {
            Log::warning('PartnerPhoto: Unsupported image type', [
                'filename' => $file->getClientOriginalName(),
                'detected_type' => $imageInfo[2],
            ]);

            return false;
        }

        return true;
    }

    /**
     * ZIP entry kihagyandó-e
     */
    protected function shouldSkipZipEntry(string $entryName): bool
    {
        // Könyvtárak
        if (str_ends_with($entryName, '/')) {
            return true;
        }

        // Path traversal
        if (str_contains($entryName, '../') || str_starts_with($entryName, '/')) {
            return true;
        }

        // Hidden fájlok
        foreach (self::HIDDEN_PATTERNS as $pattern) {
            if (str_contains($entryName, $pattern)) {
                return true;
            }
        }

        // Nem képfájl
        $extension = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));

        return ! in_array($extension, self::SUPPORTED_EXTENSIONS);
    }

    /**
     * Fájlnév sanitizálás
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Speciális karakterek eltávolítása, de ékezetek megtartása
        $filename = preg_replace('/[^\p{L}\p{N}\s._-]/u', '', $filename);

        // Többszörös szóközök és kötőjelek
        $filename = preg_replace('/[\s]+/', '_', $filename);
        $filename = preg_replace('/[-]+/', '-', $filename);

        return $filename ?: 'unnamed_'.uniqid();
    }

    // isZipFile() és isValidImageFile() a FileValidation trait-ből jön
}
