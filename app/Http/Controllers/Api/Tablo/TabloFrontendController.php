<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tablo\UpdateScheduleRequest;
use App\Http\Requests\Api\Tablo\UpdateTabloContactRequest;
use App\Models\TabloContact;
use App\Models\TabloProject;
use App\Models\TabloSampleTemplate;
use App\Models\TabloUserProgress;
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
     * Validate current tablo project session.
     * Teljes projekt adatok + branding + webshop + photo selection progress.
     */
    public function validateTabloSession(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if (! $token || ! $token->tablo_project_id) {
            return response()->json([
                'valid' => false,
                'message' => 'Nincs érvényes tablo projekt session',
            ], 401);
        }

        $tabloProject = TabloProject::with(['school', 'partner.users', 'contacts', 'persons', 'tabloStatus', 'gallery'])->find($token->tablo_project_id);

        if (! $tabloProject) {
            return response()->json([
                'valid' => false,
                'message' => 'A tablo projekt nem található',
            ], 401);
        }

        // Get ügyintézők (partner users with tablo role)
        $coordinators = $tabloProject->partner?->users
            ->filter(fn ($user) => $user->hasRole('tablo'))
            ->map(fn ($user) => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ])
            ->values() ?? collect();

        // Get elsődleges kapcsolattartó (is_primary=true, vagy a legújabb)
        $primaryContact = $tabloProject->contacts
            ->filter(fn ($c) => $c->is_primary)
            ->first()
            ?? $tabloProject->contacts->sortByDesc('created_at')->first();

        $contacts = $primaryContact ? [[
            'id' => $primaryContact->id,
            'name' => $primaryContact->name,
            'email' => $primaryContact->email,
            'phone' => $primaryContact->phone,
        ]] : [];

        // Get missing persons data
        $missingPersons = $tabloProject->persons
            ->sortBy('position')
            ->map(fn ($person) => [
                'id' => $person->id,
                'name' => $person->name,
                'type' => $person->type,
                'localId' => $person->local_id,
                'hasPhoto' => $person->hasPhoto(),
            ])
            ->values();

        // Calculate missing photos count by type
        $missingWithoutPhoto = $missingPersons->where('hasPhoto', false);
        $studentsWithoutPhoto = $missingWithoutPhoto->where('type', 'student')->count();
        $teachersWithoutPhoto = $missingWithoutPhoto->where('type', 'teacher')->count();

        // Determine token type from token name
        $tokenName = $token->name;
        $tokenType = match($tokenName) {
            'tablo-auth-token' => 'code',
            'qr-registration' => 'code',
            'dev-tablo-token' => 'code',
            'tablo-share-token' => 'share',
            'tablo-preview-token' => 'preview',
            default => 'unknown',
        };
        $isGuest = in_array($tokenType, ['share', 'preview']);
        $canFinalize = $tokenType === 'code';

        // Check if already finalized
        $isFinalized = ! empty($tabloProject->data['finalized_at'] ?? null);

        // Count active polls for voting menu
        $activePollsCount = $tabloProject->polls()->active()->count();

        return response()->json([
            'valid' => true,
            'project' => [
                'id' => $tabloProject->id,
                'name' => $tabloProject->display_name,
                'schoolName' => $tabloProject->school?->name,
                'className' => $tabloProject->class_name,
                'classYear' => $tabloProject->class_year,
                'partnerName' => $tabloProject->partner?->name,
                'partnerEmail' => $tabloProject->partner?->email,
                'partnerPhone' => $tabloProject->partner?->phone,
                'coordinators' => $coordinators,
                'contacts' => $contacts,
                'hasOrderData' => $tabloProject->hasOrderData(),
                'hasOrderAnalysis' => $tabloProject->hasOrderAnalysis(),
                'lastActivityAt' => $tabloProject->lastEmailDate?->toIso8601String(),
                'photoDate' => $tabloProject->photo_date?->format('Y-m-d'),
                'deadline' => $tabloProject->deadline?->format('Y-m-d'),
                'missingPersons' => $missingPersons,
                'missingStats' => [
                    'total' => $missingPersons->count(),
                    'withoutPhoto' => $missingWithoutPhoto->count(),
                    'studentsWithoutPhoto' => $studentsWithoutPhoto,
                    'teachersWithoutPhoto' => $teachersWithoutPhoto,
                ],
                'hasMissingPersons' => $missingPersons->count() > 0,
                'hasTemplateChooser' => TabloSampleTemplate::active()->exists(),
                'samplesCount' => $tabloProject->getMedia('samples')->count(),
                'activePollsCount' => $activePollsCount,
                'expectedClassSize' => $tabloProject->expected_class_size,
                'tabloStatus' => $tabloProject->tabloStatus?->toApiResponse(),
                'userStatus' => $tabloProject->tabloStatus?->name ?? $tabloProject->user_status,
                'userStatusColor' => $tabloProject->tabloStatus?->color ?? $tabloProject->user_status_color,
                'shareUrl' => $tabloProject->hasValidShareToken() ? $tabloProject->getShareUrl() : null,
                'shareEnabled' => $tabloProject->share_token_enabled,
                'isFinalized' => $isFinalized,
                'workSessionId' => $tabloProject->work_session_id,
                'hasPhotoSelection' => $tabloProject->work_session_id !== null || $tabloProject->tablo_gallery_id !== null,
                'billingEnabled' => $tabloProject->partner?->billing_enabled ?? false,
                'tabloGalleryId' => $tabloProject->tablo_gallery_id,
                'hasGallery' => $tabloProject->gallery !== null,
                'photoSelectionCurrentStep' => $tabloProject->tablo_gallery_id
                    ? (TabloUserProgress::where('user_id', $request->user()->id)
                        ->where('tablo_gallery_id', $tabloProject->tablo_gallery_id)
                        ->first()?->current_step ?? 'claiming')
                    : null,
                'photoSelectionFinalized' => $tabloProject->tablo_gallery_id
                    ? (TabloUserProgress::where('user_id', $request->user()->id)
                        ->where('tablo_gallery_id', $tabloProject->tablo_gallery_id)
                        ->first()?->isFinalized() ?? false)
                    : false,
                'photoSelectionProgress' => $tabloProject->tablo_gallery_id
                    ? (function () use ($request, $tabloProject) {
                        $progress = TabloUserProgress::where('user_id', $request->user()->id)
                            ->where('tablo_gallery_id', $tabloProject->tablo_gallery_id)
                            ->first();

                        if (! $progress) {
                            return null;
                        }

                        $stepsData = $progress->steps_data ?? [];

                        return [
                            'claimedCount' => count($stepsData['claimed_media_ids'] ?? []),
                            'retouchCount' => count($stepsData['retouch_media_ids'] ?? []),
                            'hasTabloPhoto' => isset($stepsData['tablo_media_id']),
                        ];
                    })()
                    : null,
                'branding' => $tabloProject->partner?->getActiveBranding(),
                'webshop' => (function () use ($tabloProject) {
                    $partnerId = $tabloProject->tablo_partner_id;
                    $settings = \App\Models\ShopSetting::where('tablo_partner_id', $partnerId)->first();
                    if (!$settings || !$settings->is_enabled) {
                        return null;
                    }
                    $token = $tabloProject->gallery?->webshop_share_token;
                    if (!$token) {
                        return null;
                    }
                    return [
                        'enabled' => true,
                        'shop_url' => '/shop/' . $token,
                    ];
                })(),
            ],
            'tokenType' => $tokenType,
            'isGuest' => $isGuest,
            'canFinalize' => $canFinalize,
            'user' => [
                'passwordSet' => (bool) $request->user()->password_set,
            ],
        ]);
    }

    /**
     * Get project info (projekt alapadatok).
     */
    public function getProjectInfo(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::with(['school', 'partner.users', 'persons', 'tabloStatus', 'gallery'])->find($projectId);

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

        $personsCount = $tabloProject->persons->count();
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
                'hasMissingPersons' => $personsCount > 0,
                'hasTemplateChooser' => TabloSampleTemplate::active()->exists(),
                'activePollsCount' => $activePollsCount,
                'tabloStatus' => $tabloProject->tabloStatus?->toApiResponse(),
                'userStatus' => $tabloProject->tabloStatus?->name ?? $tabloProject->user_status,
                'userStatusColor' => $tabloProject->tabloStatus?->color ?? $tabloProject->user_status_color,
                'workSessionId' => $tabloProject->work_session_id,
                'hasPhotoSelection' => $tabloProject->work_session_id !== null || $tabloProject->tablo_gallery_id !== null,
                'billingEnabled' => $tabloProject->partner?->billing_enabled ?? false,
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
     * Update photo date schedule.
     */
    public function updateSchedule(UpdateScheduleRequest $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if (! $token || ! $token->tablo_project_id) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs érvényes session',
            ], 401);
        }

        $tabloProject = TabloProject::find($token->tablo_project_id);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'A projekt nem található',
            ], 404);
        }

        $validated = $request->validated();

        $tabloProject->update([
            'photo_date' => $validated['photo_date'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fotózás időpontja sikeresen mentve.',
            'photoDate' => $tabloProject->photo_date->format('Y-m-d'),
        ]);
    }

    /**
     * Update primary contact from home page.
     */
    public function updateContact(UpdateTabloContactRequest $request, FinalizationSecurityService $security): JsonResponse
    {
        $request->merge([
            'name' => $security->sanitizeInput($request->input('name')),
            'email' => $security->sanitizeInput($request->input('email')),
            'phone' => $security->sanitizePhone($request->input('phone')),
        ]);

        $validated = $request->validated();

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
