<?php

namespace App\Http\Controllers\Api\Partner;

use App\Enums\TabloProjectStatus;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Models\TabloContact;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Partner Project Controller - Project CRUD operations for partners.
 *
 * Handles: storeProject(), updateProject(), deleteProject(), toggleAware(),
 *          projectSamples(), projectMissingPersons()
 */
class PartnerProjectController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Create a new project for the user's partner.
     */
    public function storeProject(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        // Check project limit
        $partner = auth()->user()->partner;
        if ($partner) {
            $maxClasses = $partner->getMaxClasses();
            if ($maxClasses !== null) {
                $currentCount = TabloProject::where('partner_id', $partnerId)->count();
                if ($currentCount >= $maxClasses) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Elérted a csomagodban elérhető maximum projektszámot. Válts magasabb csomagra a korlátozás feloldásához!',
                        'upgrade_required' => true,
                    ], 403);
                }
            }
        }

        $validator = Validator::make($request->all(), [
            'school_id' => 'nullable|exists:tablo_schools,id',
            'class_name' => 'nullable|string|max:255',
            'class_year' => 'nullable|string|max:50',
            'photo_date' => 'nullable|date',
            'deadline' => 'nullable|date',
            'expected_class_size' => 'nullable|integer|min:1|max:500',
            // Contact fields
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
        ], [
            'school_id.exists' => 'A megadott iskola nem található.',
            'class_name.max' => 'Az osztály neve maximum 255 karakter lehet.',
            'class_year.max' => 'Az évfolyam maximum 50 karakter lehet.',
            'photo_date.date' => 'Érvénytelen fotózás dátum.',
            'deadline.date' => 'Érvénytelen határidő dátum.',
            'expected_class_size.integer' => 'A várható létszámnak egész számnak kell lennie.',
            'expected_class_size.min' => 'A várható létszámnak legalább 1-nek kell lennie.',
            'expected_class_size.max' => 'A várható létszám maximum 500 lehet.',
            'contact_email.email' => 'Érvénytelen email cím.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create the project
        $project = TabloProject::create([
            'partner_id' => $partnerId,
            'school_id' => $request->input('school_id'),
            'class_name' => $request->input('class_name'),
            'class_year' => $request->input('class_year'),
            'photo_date' => $request->input('photo_date'),
            'deadline' => $request->input('deadline'),
            'expected_class_size' => $request->input('expected_class_size'),
            'status' => TabloProjectStatus::NotStarted,
        ]);

        // Create contact if provided
        $contact = null;
        if ($request->filled('contact_name')) {
            $contact = TabloContact::create([
                'partner_id' => $partnerId,
                'name' => $request->input('contact_name'),
                'email' => $request->input('contact_email'),
                'phone' => $request->input('contact_phone'),
            ]);
            // Link contact to project via pivot with is_primary = true
            $contact->projects()->attach($project->id, ['is_primary' => true]);
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
    public function updateProject(Request $request, int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $validator = Validator::make($request->all(), [
            'school_id' => 'nullable|exists:tablo_schools,id',
            'class_name' => 'nullable|string|max:255',
            'class_year' => 'nullable|string|max:50',
            'photo_date' => 'nullable|date',
            'deadline' => 'nullable|date',
            'expected_class_size' => 'nullable|integer|min:1|max:500',
        ], [
            'school_id.exists' => 'A megadott iskola nem található.',
            'class_name.max' => 'Az osztály neve maximum 255 karakter lehet.',
            'class_year.max' => 'Az évfolyam maximum 50 karakter lehet.',
            'photo_date.date' => 'Érvénytelen fotózás dátum.',
            'deadline.date' => 'Érvénytelen határidő dátum.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update the project
        $project->update([
            'school_id' => $request->input('school_id'),
            'class_name' => $request->input('class_name'),
            'class_year' => $request->input('class_year'),
            'photo_date' => $request->input('photo_date'),
            'deadline' => $request->input('deadline'),
            'expected_class_size' => $request->input('expected_class_size', $project->expected_class_size),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Projekt sikeresen módosítva',
        ]);
    }

    /**
     * Delete a project.
     */
    public function deleteProject(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $project->delete();

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

        $project->update([
            'is_aware' => !$project->is_aware,
        ]);

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

        $samples = $project->getMedia('samples')->map(fn ($media) => [
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
     * Get project missing persons.
     */
    public function projectMissingPersons(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $withoutPhoto = $request->boolean('without_photo', false);

        $query = $project->missingPersons()->orderBy('position');

        if ($withoutPhoto) {
            $query->whereNull('media_id');
        }

        $missingPersons = $query->with('photo')->get()->map(function ($person) {
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

        return response()->json([
            'data' => $missingPersons,
        ]);
    }
}
