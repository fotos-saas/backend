<?php

namespace App\Http\Controllers\Api\Partner;

use App\Enums\TabloProjectStatus;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\OverridePersonPhotoRequest;
use App\Http\Requests\Api\Partner\StoreProjectRequest;
use App\Http\Requests\Api\Partner\UpdateProjectRequest;
use App\Actions\Partner\DeleteProjectAction;
use App\Models\TabloPerson;
use App\Repositories\Contracts\TabloContactRepositoryContract;
use App\Repositories\Contracts\TabloProjectRepositoryContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Partner Project Controller - Project CRUD operations for partners.
 *
 * Handles: storeProject(), updateProject(), deleteProject(), toggleAware(),
 *          projectSamples(), projectPersons()
 */
class PartnerProjectController extends Controller
{
    use PartnerAuthTrait;

    public function __construct(
        private readonly TabloProjectRepositoryContract $projectRepository,
        private readonly TabloContactRepositoryContract $contactRepository
    ) {}

    /**
     * Create a new project for the user's partner.
     */
    public function storeProject(StoreProjectRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        // Check project limit (csapattagoknak is működik)
        $partner = auth()->user()->getEffectivePartner();
        if ($partner) {
            $maxClasses = $partner->getMaxClasses();
            if ($maxClasses !== null) {
                $currentCount = $this->projectRepository->countByPartner($partnerId);
                if ($currentCount >= $maxClasses) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Elérted a csomagodban elérhető maximum projektszámot. Válts magasabb csomagra a korlátozás feloldásához!',
                        'upgrade_required' => true,
                    ], 403);
                }
            }
        }

        // Create the project
        $project = $this->projectRepository->create([
            'partner_id' => $partnerId,
            'school_id' => $request->validated('school_id'),
            'class_name' => $request->validated('class_name'),
            'class_year' => $request->validated('class_year'),
            'photo_date' => $request->validated('photo_date'),
            'deadline' => $request->validated('deadline'),
            'expected_class_size' => $request->validated('expected_class_size'),
            'status' => TabloProjectStatus::NotStarted,
        ]);

        // Create contact if provided
        if ($request->filled('contact_name')) {
            $contact = $this->contactRepository->create([
                'partner_id' => $partnerId,
                'name' => $request->validated('contact_name'),
                'email' => $request->validated('contact_email'),
                'phone' => $request->validated('contact_phone'),
            ]);
            // Link contact to project via pivot with is_primary = true
            $this->projectRepository->attachContact($project->id, $contact->id, true);
        }

        // Load relations for response
        $project->load(['school', 'contacts']);

        return response()->json([
            'success' => true,
            'message' => 'Projekt sikeresen létrehozva',
            'data' => [
                'id' => $project->id,
                'name' => $project->display_name,
                'schoolName' => $project->school?->name,
                'schoolCity' => $project->school?->city,
                'className' => $project->class_name,
                'classYear' => $project->class_year,
                'status' => $project->status?->value,
                'statusLabel' => $project->status?->label() ?? 'Ismeretlen',
                'tabloStatus' => null,
                'photoDate' => $project->photo_date?->format('Y-m-d'),
                'deadline' => $project->deadline?->format('Y-m-d'),
                'expectedClassSize' => $project->expected_class_size,
                'guestsCount' => 0,
                'missingCount' => 0,
                'samplesCount' => 0,
                'contact' => null,
                'hasActiveQrCode' => false,
                'createdAt' => $project->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update an existing project.
     */
    public function updateProject(UpdateProjectRequest $request, int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        // Update the project
        $this->projectRepository->update($project, [
            'school_id' => $request->validated('school_id'),
            'class_name' => $request->validated('class_name'),
            'class_year' => $request->validated('class_year'),
            'photo_date' => $request->validated('photo_date'),
            'deadline' => $request->validated('deadline'),
            'expected_class_size' => $request->validated('expected_class_size', $project->expected_class_size),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Projekt sikeresen módosítva',
        ]);
    }

    /**
     * Delete a project.
     */
    public function deleteProject(int $projectId, DeleteProjectAction $action): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $this->authorize('delete', $project);

        $action->execute($project);

        return response()->json([
            'success' => true,
            'message' => 'Projekt sikeresen törölve',
        ]);
    }

    /**
     * Toggle is_aware status for a project.
     */
    public function toggleAware(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $this->projectRepository->update($project, [
            'is_aware' => ! $project->is_aware,
        ]);

        // Refresh to get updated value
        $project->refresh();

        return response()->json([
            'success' => true,
            'message' => $project->is_aware ? 'Tudnak róla' : 'Nem tudnak róla',
            'isAware' => $project->is_aware,
        ]);
    }

    /**
     * Get project samples (images from media library).
     */
    public function projectSamples(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $samples = $this->projectRepository->getSamples($project->id)->map(fn ($media) => [
            'id' => $media->id,
            'url' => $media->getUrl(),
            'thumbnailUrl' => $media->getUrl('thumb'),
            'name' => $media->file_name,
        ]);

        return response()->json([
            'data' => $samples,
        ]);
    }

    /**
     * Get project persons (diákok és tanárok).
     * Fotó resolution: override → archive.active_photo → legacy media_id
     */
    public function projectPersons(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $allPersons = $project->persons()
            ->orderBy('position')
            ->with([
                'photo',
                'overridePhoto',
                'teacherArchive.activePhoto',
                'studentArchive.activePhoto',
            ])
            ->get();

        $withoutPhoto = $request->boolean('without_photo', false);

        $persons = $allPersons->map(fn ($person) => [
            'id' => $person->id,
            'name' => $person->name,
            'type' => $person->type,
            'hasPhoto' => $person->hasEffectivePhoto(),
            'email' => $person->email,
            'photoThumbUrl' => $person->getEffectivePhotoThumbUrl(),
            'photoUrl' => $person->getEffectivePhotoUrl(),
            'archiveId' => $person->archive_id,
            'hasOverride' => $person->override_photo_id !== null,
        ]);

        if ($withoutPhoto) {
            $persons = $persons->filter(fn ($p) => !$p['hasPhoto'])->values();
        }

        return response()->json([
            'data' => $persons,
        ]);
    }

    /**
     * Override: projekt-specifikus fotó beállítása/visszaállítása.
     * PATCH /partner/projects/{projectId}/persons/{personId}/override-photo
     */
    public function overridePersonPhoto(
        OverridePersonPhotoRequest $request,
        int $projectId,
        int $personId,
    ): JsonResponse {
        $project = $this->getProjectForPartner($projectId);

        $person = $project->persons()->find($personId);
        if (!$person) {
            return $this->notFoundResponse('Személy nem található');
        }

        $photoId = $request->input('photo_id');

        if ($photoId !== null) {
            $photoId = (int) $photoId;
            // Ellenőrizzük, hogy a média létezik
            $media = Media::find($photoId);
            if (!$media) {
                return $this->errorResponse('A megadott fotó nem található', 404);
            }
        }

        $person->update(['override_photo_id' => $photoId]);

        // Reload relations
        $person->load(['overridePhoto', 'teacherArchive.activePhoto', 'studentArchive.activePhoto', 'photo']);

        return $this->successResponse([
            'id' => $person->id,
            'hasPhoto' => $person->hasEffectivePhoto(),
            'photoThumbUrl' => $person->getEffectivePhotoThumbUrl(),
            'photoUrl' => $person->getEffectivePhotoUrl(),
            'hasOverride' => $person->override_photo_id !== null,
        ], $photoId ? 'Egyedi fotó beállítva' : 'Visszaállítva az alapértelmezett fotóra');
    }
}
