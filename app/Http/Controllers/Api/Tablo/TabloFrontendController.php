<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Models\TabloContact;
use App\Models\TabloProject;
use App\Models\TabloSampleTemplate;
use App\Services\Tablo\FinalizationSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tablo Frontend Controller
 *
 * Általános végpontok a frontend-tablo Angular alkalmazáshoz.
 * Minden végpont auth:sanctum + CheckTabloProjectStatus middleware mögött van.
 *
 * @see TabloSampleController - minta képek
 * @see TabloOrderViewController - megrendelés megtekintés + PDF
 * @see TabloFinalizationController - megrendelés véglegesítés
 */
class TabloFrontendController extends Controller
{
    /**
     * Get project info (projekt alapadatok).
     */
    public function getProjectInfo(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::with(['school', 'partner.users', 'missingPersons', 'tabloStatus', 'gallery'])->find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $coordinators = $tabloProject->partner?->users
            ->filter(fn ($user) => $user->hasRole('tablo'))
            ->map(fn ($user) => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ])
            ->values() ?? collect();

        $missingPersonsCount = $tabloProject->missingPersons->count();
        $activePollsCount = $tabloProject->polls()->active()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $tabloProject->id,
                'name' => $tabloProject->display_name,
                'schoolName' => $tabloProject->school?->name,
                'className' => $tabloProject->class_name,
                'classYear' => $tabloProject->class_year,
                'status' => $tabloProject->status->value,
                'hasOrderAnalysis' => $tabloProject->hasOrderAnalysis(),
                'samplesCount' => $tabloProject->getMedia('samples')->count(),
                'coordinators' => $coordinators,
                'hasMissingPersons' => $missingPersonsCount > 0,
                'hasTemplateChooser' => TabloSampleTemplate::active()->exists(),
                'activePollsCount' => $activePollsCount,
                'tabloStatus' => $tabloProject->tabloStatus?->toApiResponse(),
                'userStatus' => $tabloProject->tabloStatus?->name ?? $tabloProject->user_status,
                'userStatusColor' => $tabloProject->tabloStatus?->color ?? $tabloProject->user_status_color,
                'workSessionId' => $tabloProject->work_session_id,
                'hasPhotoSelection' => $tabloProject->work_session_id !== null || $tabloProject->tablo_gallery_id !== null,
                'tabloGalleryId' => $tabloProject->tablo_gallery_id,
                'hasGallery' => $tabloProject->gallery !== null,
            ],
        ]);
    }

    /**
     * Get gallery photos for the project.
     */
    public function getGalleryPhotos(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::with('gallery')->find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        if (! $tabloProject->gallery) {
            return response()->json([
                'success' => true,
                'message' => 'Nincs galéria csatolva a projekthez',
                'data' => [],
                'gallery' => null,
            ]);
        }

        $photos = $tabloProject->gallery->getMedia('photos')
            ->map(function ($media) {
                $url = $media->getUrl();
                $thumbUrl = $media->getUrl('thumb');
                $previewUrl = $media->getUrl('preview');

                $urlPath = parse_url($url, PHP_URL_PATH);
                $thumbPath = parse_url($thumbUrl, PHP_URL_PATH);
                $previewPath = parse_url($previewUrl, PHP_URL_PATH);

                return [
                    'id' => $media->id,
                    'url' => $urlPath,
                    'thumbUrl' => $thumbPath,
                    'previewUrl' => $previewPath,
                    'fileName' => $media->file_name,
                    'size' => $media->human_readable_size,
                    'createdAt' => $media->created_at->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $photos,
            'gallery' => [
                'id' => $tabloProject->gallery->id,
                'name' => $tabloProject->gallery->name,
                'photosCount' => $photos->count(),
            ],
        ]);
    }

    /**
     * Update primary contact from home page.
     */
    public function updateContact(Request $request, FinalizationSecurityService $security): JsonResponse
    {
        $request->merge([
            'name' => $security->sanitizeInput($request->input('name')),
            'email' => $security->sanitizeInput($request->input('email')),
            'phone' => $security->sanitizePhone($request->input('phone')),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => ['required', 'string', 'max:50', 'regex:/^[\d\s\+\-\(\)]+$/'],
        ], [
            'name.required' => 'A név megadása kötelező.',
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvénytelen email cím.',
            'phone.required' => 'A telefonszám megadása kötelező.',
            'phone.regex' => 'Érvénytelen telefonszám formátum.',
        ]);

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::with(['contacts'])->find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $contact = $tabloProject->contacts->firstWhere('pivot.is_primary', true)
            ?? $tabloProject->contacts->first();

        if ($contact) {
            $contact->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
            ]);
        } else {
            $contact = TabloContact::create([
                'tablo_project_id' => $projectId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'is_primary' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen frissítve!',
            'data' => [
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ],
        ]);
    }
}
