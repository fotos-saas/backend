<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloGallery;
use App\Services\MediaZipService;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Képek feltöltése galériába (egyedi fájlok és ZIP archívumok).
 */
class UploadGalleryPhotosAction
{
    public function __construct(
        private readonly MediaZipService $zipService,
    ) {}

    /**
     * Képek feltöltése a galériába.
     *
     * @param TabloGallery $gallery
     * @param array<UploadedFile> $files
     * @return array{success: bool, message: string, uploadedCount: int, photos: array}
     */
    public function execute(TabloGallery $gallery, array $files): array
    {
        error_log("[GALLERY_UPLOAD] Gallery={$gallery->id}, Files=" . count($files));

        $uploadedMedia = collect();

        foreach ($files as $index => $file) {
            error_log("[GALLERY_UPLOAD] Processing file {$index}: {$file->getClientOriginalName()} ({$file->getSize()} bytes)");

            if ($this->zipService->isZipFile($file)) {
                $extractedMedia = $this->zipService->extractAndUpload($file, $gallery, 'photos');
                $uploadedMedia = $uploadedMedia->merge($extractedMedia);
            } else {
                $iptcTitle = $this->extractIptcTitle($file->getRealPath());

                $media = $gallery
                    ->addMedia($file)
                    ->preservingOriginal()
                    ->withCustomProperties(['iptc_title' => $iptcTitle])
                    ->toMediaCollection('photos');

                error_log("[GALLERY_UPLOAD] Uploaded media id={$media->id} file={$media->file_name}");
                $uploadedMedia->push($media);
            }
        }

        error_log("[GALLERY_UPLOAD] SUCCESS count={$uploadedMedia->count()}");

        return [
            'success' => true,
            'message' => "{$uploadedMedia->count()} kép sikeresen feltöltve",
            'uploadedCount' => $uploadedMedia->count(),
            'photos' => $uploadedMedia->map(fn (Media $media) => [
                'id' => $media->id,
                'name' => $media->file_name,
                'title' => $media->getCustomProperty('iptc_title', ''),
                'thumb_url' => $media->getUrl('thumb'),
                'preview_url' => $media->getUrl('preview'),
                'original_url' => $media->getUrl(),
                'size' => $media->size,
                'createdAt' => $media->created_at->toIso8601String(),
            ])->values()->toArray(),
        ];
    }

    /**
     * IPTC cím kinyerése képfájlból.
     */
    private function extractIptcTitle(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $size = @getimagesize($filePath, $info);

        if ($size === false || !isset($info['APP13'])) {
            return null;
        }

        $iptc = @iptcparse($info['APP13']);

        if ($iptc === false) {
            return null;
        }

        // IPTC mező prioritások: Object Name, Caption, Headline
        $titleFields = ['2#005', '2#120', '2#105'];

        foreach ($titleFields as $field) {
            if (isset($iptc[$field][0]) && !empty($iptc[$field][0])) {
                $title = $iptc[$field][0];
                $encoding = mb_detect_encoding($title, ['UTF-8', 'ISO-8859-1', 'ISO-8859-2', 'Windows-1252'], true);

                if ($encoding && $encoding !== 'UTF-8') {
                    $title = mb_convert_encoding($title, 'UTF-8', $encoding);
                }

                return trim($title) ?: null;
            }
        }

        return null;
    }
}
