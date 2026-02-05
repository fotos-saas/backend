<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Partner\DownloadZipRequest;
use App\Http\Requests\Api\Partner\UploadAlbumPhotosRequest;
use App\Models\PartnerAlbum;
use App\Services\ExcelExportService;
use App\Services\MediaZipService;
use App\Services\PartnerAlbumZipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Partner Order Album Photo Controller
 *
 * Manages photo uploads, deletions, and exports (ZIP, Excel) for partner order albums.
 */
class PartnerOrderAlbumPhotoController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Upload photos to an album.
     * Supports individual images and ZIP archives.
     */
    public function uploadPhotos(UploadAlbumPhotosRequest $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        // Can only upload to draft albums
        if (!$album->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'Csak piszkozat státuszú albumba lehet képet feltölteni.',
            ], 422);
        }

        $uploadedMedia = collect();
        $zipService = app(MediaZipService::class);

        foreach ($request->file('photos') as $file) {
            // Check if it's a ZIP file
            if ($zipService->isZipFile($file)) {
                $extractedMedia = $zipService->extractAndUpload($file, $album, 'photos');
                $uploadedMedia = $uploadedMedia->merge($extractedMedia);
            } else {
                // IPTC title kinyerése
                $iptcTitle = $this->extractIptcTitle($file->getRealPath());

                $media = $album
                    ->addMedia($file)
                    ->preservingOriginal()
                    ->withCustomProperties(['iptc_title' => $iptcTitle])
                    ->toMediaCollection('photos');

                $uploadedMedia->push($media);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$uploadedMedia->count()} kép sikeresen feltöltve",
            'uploadedCount' => $uploadedMedia->count(),
            'photos' => $uploadedMedia->map(fn (Media $media) => [
                'id' => $media->id,
                'name' => $media->file_name,
                'thumbUrl' => $media->getUrl('thumb'),
                'previewUrl' => $media->getUrl('preview'),
            ])->toArray(),
        ]);
    }

    /**
     * Delete a photo from an album.
     */
    public function deletePhoto(int $albumId, int $mediaId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($albumId);

        // Can only delete from draft albums
        if (!$album->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'Csak piszkozat státuszú albumból lehet képet törölni.',
            ], 422);
        }

        $media = $album->getMedia('photos')->firstWhere('id', $mediaId);

        if (!$media) {
            return response()->json([
                'success' => false,
                'message' => 'A kép nem található ebben az albumban.',
            ], 404);
        }

        $media->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kép sikeresen törölve',
        ]);
    }

    /**
     * Download selected photos as ZIP.
     */
    public function downloadZip(DownloadZipRequest $request, int $id): JsonResponse|BinaryFileResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        $photoIds = array_map('intval', $request->input('photo_ids'));

        $zipService = app(PartnerAlbumZipService::class);
        $zipPath = $zipService->generateSelectedPhotosZip($album, $photoIds);

        $filename = "album-{$album->id}-selected-" . now()->format('Y-m-d') . '.zip';

        return response()->download($zipPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Export selected photos filenames to Excel.
     */
    public function exportExcel(Request $request, int $id): JsonResponse|BinaryFileResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->with('progress')->findOrFail($id);

        $photoIds = $request->input('photo_ids', []);

        // Ha nincs megadva, használjuk az összes kiválasztott képet a progress-ből
        if (empty($photoIds) && $album->progress) {
            $photoIds = $album->progress->getClaimedIds();
        }

        if (empty($photoIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs kiválasztott kép az exportáláshoz.',
            ], 422);
        }

        $photoIds = array_map('intval', $photoIds);

        $excelService = app(ExcelExportService::class);
        $excelPath = $excelService->generatePartnerAlbumExcel($album, $photoIds);

        $filename = "album-{$album->id}-export-" . now()->format('Y-m-d') . '.xlsx';

        return response()->download($excelPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Extract IPTC title from an image file.
     *
     * @param string $filePath Path to the image file
     * @return string|null IPTC title or null if not found
     */
    private function extractIptcTitle(string $filePath): ?string
    {
        // Only works with JPEG files
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

        // IPTC title fields (in order of preference):
        // 2#005 = Object Name (Title)
        // 2#120 = Caption/Abstract
        // 2#105 = Headline
        $titleFields = ['2#005', '2#120', '2#105'];

        foreach ($titleFields as $field) {
            if (isset($iptc[$field][0]) && !empty($iptc[$field][0])) {
                // Detect encoding and convert to UTF-8 if needed
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
