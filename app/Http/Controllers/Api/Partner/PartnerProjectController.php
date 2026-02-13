<?php

namespace App\Http\Controllers\Api\Partner;

use App\Enums\TabloProjectStatus;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\StoreProjectRequest;
use App\Http\Requests\Api\Partner\UpdateProjectRequest;
use App\Actions\Partner\DeleteProjectAction;
use App\Models\TeacherArchive;
use App\Repositories\Contracts\TabloContactRepositoryContract;
use App\Repositories\Contracts\TabloProjectRepositoryContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     *
     * Diákok: TabloPerson.media_id (direkt FK)
     * Tanárok: TeacherArchive.active_photo_id (canonical_name matching)
     */
    public function projectPersons(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $allPersons = $project->persons()->orderBy('position')->with('photo')->get();

        // Tanári fotók kikeresése TeacherArchive-ból
        $teacherArchiveMap = collect();
        $teachers = $allPersons->where('type', 'teacher');
        if ($teachers->isNotEmpty() && $project->partner_id) {
            $teacherNames = $teachers->pluck('name')->filter()->unique()->values()->toArray();
            $archiveQuery = TeacherArchive::where('partner_id', $project->partner_id)
                ->whereNotNull('active_photo_id')
                ->whereIn('canonical_name', $teacherNames)
                ->with('activePhoto:id,disk,file_name,conversions_disk');

            if ($project->school_id) {
                $archiveQuery->where('school_id', $project->school_id);
            }

            $teacherArchiveMap = $archiveQuery->get()->keyBy('canonical_name');
        }

        $withoutPhoto = $request->boolean('without_photo', false);

        $persons = $allPersons->map(function ($person) use ($teacherArchiveMap) {
            if ($person->type === 'teacher') {
                $archive = $teacherArchiveMap->get($person->name);
                $thumbUrl = null;
                $fullUrl = null;
                if ($archive?->activePhoto) {
                    try {
                        $thumbUrl = $archive->activePhoto->getUrl('thumb');
                    } catch (\Throwable) {
                        $thumbUrl = $archive->activePhoto->getUrl();
                    }
                    $fullUrl = $archive->activePhoto->getUrl();
                }
                return [
                    'id' => $person->id,
                    'name' => $person->name,
                    'type' => $person->type,
                    'hasPhoto' => $archive !== null,
                    'email' => $person->email,
                    'photoThumbUrl' => $thumbUrl,
                    'photoUrl' => $fullUrl,
                ];
            }

            return [
                'id' => $person->id,
                'name' => $person->name,
                'type' => $person->type,
                'hasPhoto' => $person->hasPhoto(),
                'email' => $person->email,
                'photoThumbUrl' => $person->photo_thumb_url,
                'photoUrl' => $person->photo_url,
            ];
        });

        if ($withoutPhoto) {
            $persons = $persons->filter(fn ($p) => !$p['hasPhoto'])->values();
        }

        return response()->json([
            'data' => $persons,
        ]);
    }

}
