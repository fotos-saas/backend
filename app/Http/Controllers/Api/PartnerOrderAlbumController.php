<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartnerAlbum;
use App\Models\PartnerClient;
use App\Services\ExcelExportService;
use App\Services\MediaZipService;
use App\Services\PartnerAlbumZipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Partner Order Album Controller
 *
 * Manages albums for partner clients (not the existing tablo project albums).
 */
class PartnerOrderAlbumController extends Controller
{
    /**
     * Get the authenticated user's partner ID or fail with 403.
     */
    private function getPartnerIdOrFail(): int
    {
        $partnerId = auth()->user()->tablo_partner_id;

        if (!$partnerId) {
            abort(403, 'Nincs partnerhez rendelve');
        }

        return $partnerId;
    }

    /**
     * List all albums for the partner.
     */
    public function index(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $perPage = min((int) $request->input('per_page', 15), 50);
        $search = $request->input('search');
        $clientId = $request->input('client_id');
        $type = $request->input('type');
        $status = $request->input('status');

        $query = PartnerAlbum::byPartner($partnerId)
            ->with('client');

        if ($clientId) {
            $query->byClient((int) $clientId);
        }

        if ($type) {
            $query->ofType($type);
        }

        if ($status) {
            $query->withStatus($status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('name', 'ILIKE', "%{$search}%");
                    });
            });
        }

        $albums = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $albums->getCollection()->transform(fn ($album) => [
            'id' => $album->id,
            'name' => $album->name,
            'type' => $album->type,
            'status' => $album->status,
            'client' => [
                'id' => $album->client->id,
                'name' => $album->client->name,
            ],
            'photosCount' => $album->photos_count,
            'maxSelections' => $album->max_selections,
            'minSelections' => $album->min_selections,
            'expiresAt' => $album->expires_at?->toIso8601String(),
            'finalizedAt' => $album->finalized_at?->toIso8601String(),
            'allowDownload' => $album->allow_download,
            'createdAt' => $album->created_at->toIso8601String(),
        ]);

        return response()->json($albums);
    }

    /**
     * Get a single album with photos.
     */
    public function show(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)
            ->with(['client', 'progress'])
            ->findOrFail($id);

        return response()->json([
            'id' => $album->id,
            'name' => $album->name,
            'type' => $album->type,
            'status' => $album->status,
            'client' => [
                'id' => $album->client->id,
                'name' => $album->client->name,
                'email' => $album->client->email,
                'phone' => $album->client->phone,
            ],
            'photos' => $album->getPhotosWithUrls(),
            'photosCount' => $album->photos_count,
            'maxSelections' => $album->max_selections,
            'minSelections' => $album->min_selections,
            'maxRetouchPhotos' => $album->max_retouch_photos,
            'settings' => $album->settings,
            'progress' => $album->progress ? [
                'currentStep' => $album->progress->current_step,
                'stepName' => $album->progress->getStepName(),
                'progressPercent' => $album->progress->getProgressPercentage(),
                'claimedIds' => $album->progress->getClaimedIds(),
                'retouchIds' => $album->progress->getRetouchIds(),
                'tabloId' => $album->progress->getTabloId(),
            ] : null,
            'expiresAt' => $album->expires_at?->toIso8601String(),
            'finalizedAt' => $album->finalized_at?->toIso8601String(),
            'allowDownload' => $album->allow_download,
            'createdAt' => $album->created_at->toIso8601String(),
            'updatedAt' => $album->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Create a new album.
     */
    public function store(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $validator = Validator::make($request->all(), [
            'client_id' => 'required|integer|exists:partner_clients,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:selection,tablo',
            'max_selections' => 'nullable|integer|min:1',
            'min_selections' => 'nullable|integer|min:1',
            'max_retouch_photos' => 'nullable|integer|min:1|max:20',
        ], [
            'client_id.required' => 'Az ügyfél kiválasztása kötelező.',
            'client_id.exists' => 'A megadott ügyfél nem található.',
            'name.required' => 'Az album neve kötelező.',
            'name.max' => 'Az album neve maximum 255 karakter lehet.',
            'type.required' => 'Az album típusa kötelező.',
            'type.in' => 'Érvénytelen album típus. Használható: selection, tablo.',
            'max_selections.min' => 'A maximum kiválasztás minimum 1 lehet.',
            'min_selections.min' => 'A minimum kiválasztás minimum 1 lehet.',
            'max_retouch_photos.min' => 'A maximum retusálandó kép minimum 1 lehet.',
            'max_retouch_photos.max' => 'A maximum retusálandó kép maximum 20 lehet.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify client belongs to partner
        $client = PartnerClient::byPartner($partnerId)->find($request->input('client_id'));
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'A megadott ügyfél nem a te partnerekhez tartozik.',
            ], 403);
        }

        $album = PartnerAlbum::create([
            'tablo_partner_id' => $partnerId,
            'partner_client_id' => $client->id,
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'status' => PartnerAlbum::STATUS_DRAFT,
            'max_selections' => $request->input('max_selections'),
            'min_selections' => $request->input('min_selections'),
            'max_retouch_photos' => $request->input('max_retouch_photos', 5),
            'expires_at' => now()->addMonth(), // Automatikus 1 hónap lejárat
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Album sikeresen létrehozva',
            'data' => [
                'id' => $album->id,
                'name' => $album->name,
                'type' => $album->type,
                'status' => $album->status,
                'clientId' => $album->partner_client_id,
                'expiresAt' => $album->expires_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update an album.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        // Can only update draft albums
        if (!$album->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'Csak piszkozat státuszú album módosítható.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'max_selections' => 'nullable|integer|min:1',
            'min_selections' => 'nullable|integer|min:1',
            'max_retouch_photos' => 'nullable|integer|min:1|max:20',
            'status' => 'sometimes|in:draft,claiming',
        ], [
            'name.max' => 'Az album neve maximum 255 karakter lehet.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        $album->update([
            'name' => $request->input('name', $album->name),
            'max_selections' => $request->input('max_selections', $album->max_selections),
            'min_selections' => $request->input('min_selections', $album->min_selections),
            'max_retouch_photos' => $request->input('max_retouch_photos', $album->max_retouch_photos),
            'status' => $request->input('status', $album->status),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Album sikeresen frissítve',
            'data' => [
                'id' => $album->id,
                'name' => $album->name,
                'type' => $album->type,
                'status' => $album->status,
            ],
        ]);
    }

    /**
     * Delete an album.
     */
    public function destroy(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        // Cannot delete completed albums
        if ($album->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Befejezett album nem törölhető.',
            ], 422);
        }

        // Delete all media
        $album->clearMediaCollection('photos');

        $album->delete();

        return response()->json([
            'success' => true,
            'message' => 'Album sikeresen törölve',
        ]);
    }

    /**
     * Upload photos to an album.
     * Supports individual images and ZIP archives.
     */
    public function uploadPhotos(Request $request, int $id): JsonResponse
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

        $validator = Validator::make($request->all(), [
            'photos' => 'required|array|min:1|max:50',
            'photos.*' => 'file|mimes:jpg,jpeg,png,webp,zip|max:51200', // 50MB for ZIP
        ], [
            'photos.required' => 'Legalább egy kép feltöltése kötelező.',
            'photos.max' => 'Maximum 50 fájl tölthető fel egyszerre.',
            'photos.*.mimes' => 'Csak JPG, PNG, WebP képek és ZIP archívumok engedélyezettek.',
            'photos.*.max' => 'Maximum fájlméret: 50MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
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
     * Activate album (change status from draft to claiming).
     */
    public function activate(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        if (!$album->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'Csak piszkozat státuszú album aktiválható.',
            ], 422);
        }

        // Check if album has photos
        if ($album->photos_count === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Az album aktiválásához legalább egy kép feltöltése szükséges.',
            ], 422);
        }

        $album->update(['status' => PartnerAlbum::STATUS_CLAIMING]);

        return response()->json([
            'success' => true,
            'message' => 'Album sikeresen aktiválva',
            'data' => [
                'id' => $album->id,
                'status' => $album->status,
            ],
        ]);
    }

    /**
     * Deactivate album (change status from claiming back to draft).
     */
    public function deactivate(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        // Only claiming status can be deactivated
        if ($album->status !== PartnerAlbum::STATUS_CLAIMING) {
            return response()->json([
                'success' => false,
                'message' => 'Csak kiválasztás státuszú album deaktiválható.',
            ], 422);
        }

        $album->update(['status' => PartnerAlbum::STATUS_DRAFT]);

        return response()->json([
            'success' => true,
            'message' => 'Album sikeresen deaktiválva',
            'data' => [
                'id' => $album->id,
                'status' => $album->status,
            ],
        ]);
    }

    /**
     * Extend album expiry date.
     */
    public function extendExpiry(Request $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'expires_at' => 'required|date|after:now',
        ], [
            'expires_at.required' => 'A lejárati dátum megadása kötelező.',
            'expires_at.date' => 'Érvénytelen dátum formátum.',
            'expires_at.after' => 'A lejárati dátum nem lehet a múltban.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        $album->update([
            'expires_at' => $request->input('expires_at'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lejárat sikeresen módosítva',
            'data' => [
                'expiresAt' => $album->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Reopen album (change status from completed back to claiming).
     */
    public function reopen(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        // Only completed albums can be reopened
        if ($album->status !== PartnerAlbum::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'Csak befejezett album nyitható újra.',
            ], 422);
        }

        $album->update([
            'status' => PartnerAlbum::STATUS_CLAIMING,
            'finalized_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Album sikeresen újranyitva',
            'data' => [
                'id' => $album->id,
                'status' => $album->status,
            ],
        ]);
    }

    /**
     * Toggle download permission for album.
     */
    public function toggleDownload(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        $album->update([
            'allow_download' => !$album->allow_download,
        ]);

        return response()->json([
            'success' => true,
            'message' => $album->allow_download ? 'Letöltés engedélyezve' : 'Letöltés letiltva',
            'data' => [
                'id' => $album->id,
                'allowDownload' => $album->allow_download,
            ],
        ]);
    }

    /**
     * Download selected photos as ZIP.
     */
    public function downloadZip(Request $request, int $id): JsonResponse|BinaryFileResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'photo_ids' => 'required|array|min:1',
            'photo_ids.*' => 'integer',
        ], [
            'photo_ids.required' => 'Legalább egy kép kiválasztása kötelező.',
            'photo_ids.min' => 'Legalább egy képet ki kell választani.',
            'photo_ids.*.integer' => 'Érvénytelen kép azonosító.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

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
