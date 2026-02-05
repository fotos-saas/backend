<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Models\TabloContact;
use App\Models\TabloProject;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use App\Helpers\QueryHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Partner Contact Controller - Contact management for partners.
 *
 * Handles: contacts(), allContacts(), storeContact(), createStandaloneContact(),
 *          updateStandaloneContact(), deleteStandaloneContact(),
 *          addContact(), updateContact(), deleteContact()
 */
class PartnerContactController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Get contacts list with project info for partner.
     */
    public function contacts(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $perPage = min((int) $request->input('per_page', 18), 50);
        $search = $request->input('search');

        // Contacts that belong directly to partner
        $query = TabloContact::where('partner_id', $partnerId)
            ->with(['projects.school']);

        // Search using centralized SearchService
        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['name', 'email', 'phone'],
            ]);
        }

        $contacts = $query->orderBy('name')->paginate($perPage);

        $contacts->getCollection()->transform(function ($contact) {
            // Get all projects with their school names
            $projects = $contact->projects;
            $projectIds = $projects->pluck('id')->toArray();
            $projectNames = $projects->map(fn ($p) => $p->display_name)->toArray();
            $schoolNames = $projects->map(fn ($p) => $p->school?->name)->filter()->unique()->values()->toArray();

            // Check if contact is primary for any project
            $isPrimary = $projects->contains(fn ($p) => $p->pivot->is_primary);

            return [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'note' => $contact->note,
                'isPrimary' => $isPrimary,
                // New: multiple projects support
                'projectIds' => $projectIds,
                'projectNames' => $projectNames,
                'schoolNames' => $schoolNames,
                // Backward compatibility: first project
                'projectId' => $projectIds[0] ?? null,
                'projectName' => $projectNames[0] ?? null,
                'schoolName' => $schoolNames[0] ?? null,
                'callCount' => $contact->call_count ?? 0,
                'smsCount' => $contact->sms_count ?? 0,
            ];
        });

        // Get contact limits for this partner (csapattagoknak is működik)
        $partner = auth()->user()->getEffectivePartner();
        $maxContacts = $partner?->getMaxContacts();
        $currentCount = TabloContact::where('partner_id', $partnerId)->count();

        // Add limits to pagination response
        $response = $contacts->toArray();
        $response['limits'] = [
            'current' => $currentCount,
            'max' => $maxContacts,
            'can_create' => $maxContacts === null || $currentCount < $maxContacts,
            'plan_id' => $partner?->plan ?? 'alap',
        ];

        return response()->json($response);
    }

    /**
     * Get all contacts from partner for autocomplete.
     * Returns unique contacts (by email) from the partner.
     */
    public function allContacts(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $search = $request->input('search');

        // Get all contacts that belong to this partner
        $query = TabloContact::where('partner_id', $partnerId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', QueryHelper::safeLikePattern($search))
                    ->orWhere('email', 'ILIKE', QueryHelper::safeLikePattern($search))
                    ->orWhere('phone', 'ILIKE', QueryHelper::safeLikePattern($search));
            });
        }

        // Get unique contacts by email (or name if no email)
        $contacts = $query->orderBy('name')
            ->limit(50)
            ->get()
            ->unique(fn ($contact) => $contact->email ?? $contact->name)
            ->values();

        return response()->json(
            $contacts->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ])
        );
    }

    /**
     * Create a new contact (standalone, will be linked to project on project creation).
     */
    public function storeContact(Request $request): JsonResponse
    {
        $this->getPartnerIdOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ], [
            'name.required' => 'A név megadása kötelező.',
            'name.max' => 'A név maximum 255 karakter lehet.',
            'email.email' => 'Érvénytelen email cím.',
            'email.max' => 'Az email maximum 255 karakter lehet.',
            'phone.max' => 'A telefonszám maximum 50 karakter lehet.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Return contact data without creating (will be created with project)
        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó adatok érvényesek',
            'data' => [
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
            ],
        ]);
    }

    /**
     * Create standalone contact (optionally linked to projects).
     */
    public function createStandaloneContact(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:1000',
            'project_id' => 'nullable|integer|exists:tablo_projects,id',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'integer|exists:tablo_projects,id',
        ], [
            'name.required' => 'A név megadása kötelező.',
            'name.max' => 'A név maximum 255 karakter lehet.',
            'email.email' => 'Érvénytelen email cím.',
            'email.max' => 'Az email maximum 255 karakter lehet.',
            'phone.max' => 'A telefonszám maximum 50 karakter lehet.',
            'note.max' => 'A megjegyzés maximum 1000 karakter lehet.',
            'project_id.exists' => 'A megadott projekt nem található.',
            'project_ids.*.exists' => 'A megadott projekt nem található.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create contact with partner_id
        $contact = TabloContact::create([
            'partner_id' => $partnerId,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'note' => $request->input('note'),
        ]);

        // Get project IDs (support both single and array)
        $projectIds = $request->input('project_ids', []);
        if ($request->filled('project_id') && empty($projectIds)) {
            $projectIds = [$request->input('project_id')];
        }

        // Verify all projects belong to partner and attach
        if (!empty($projectIds)) {
            $projectIds = array_map('intval', $projectIds);
            $validProjects = TabloProject::whereIn('id', $projectIds)
                ->where('partner_id', $partnerId)
                ->pluck('id')
                ->toArray();

            foreach ($validProjects as $projectId) {
                $contact->projects()->attach($projectId, ['is_primary' => false]);
            }
        }

        $contact->load('projects.school');

        // Build response
        $projects = $contact->projects;
        $projectIdsResult = $projects->pluck('id')->toArray();
        $projectNames = $projects->map(fn ($p) => $p->display_name)->toArray();
        $schoolNames = $projects->map(fn ($p) => $p->school?->name)->filter()->unique()->values()->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen létrehozva',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'note' => $contact->note,
                'isPrimary' => false,
                // New: multiple projects
                'projectIds' => $projectIdsResult,
                'projectNames' => $projectNames,
                'schoolNames' => $schoolNames,
                // Backward compatibility
                'projectId' => $projectIdsResult[0] ?? null,
                'projectName' => $projectNames[0] ?? null,
                'schoolName' => $schoolNames[0] ?? null,
                'callCount' => 0,
                'smsCount' => 0,
            ],
        ], 201);
    }

    /**
     * Update standalone contact (can change projects).
     */
    public function updateStandaloneContact(Request $request, int $contactId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        // Find contact that belongs to this partner
        $contact = TabloContact::where('partner_id', $partnerId)
            ->findOrFail($contactId);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:1000',
            'project_id' => 'nullable|integer|exists:tablo_projects,id',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'integer|exists:tablo_projects,id',
        ], [
            'name.max' => 'A név maximum 255 karakter lehet.',
            'email.email' => 'Érvénytelen email cím.',
            'email.max' => 'Az email maximum 255 karakter lehet.',
            'phone.max' => 'A telefonszám maximum 50 karakter lehet.',
            'note.max' => 'A megjegyzés maximum 1000 karakter lehet.',
            'project_id.exists' => 'A megadott projekt nem található.',
            'project_ids.*.exists' => 'A megadott projekt nem található.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update basic fields
        $contact->update([
            'name' => $request->input('name', $contact->name),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'note' => $request->input('note'),
        ]);

        // Handle project sync if provided
        if ($request->has('project_ids') || $request->has('project_id')) {
            $projectIds = $request->input('project_ids', []);
            if ($request->filled('project_id') && empty($projectIds)) {
                $projectIds = [$request->input('project_id')];
            }

            // Verify all projects belong to partner
            $projectIds = array_map('intval', $projectIds);
            $validProjects = TabloProject::whereIn('id', $projectIds)
                ->where('partner_id', $partnerId)
                ->pluck('id')
                ->toArray();

            // Sync projects (this will detach old and attach new)
            // Preserve is_primary flags for existing relationships
            $syncData = [];
            foreach ($validProjects as $projectId) {
                $existingPivot = $contact->projects()->where('tablo_projects.id', $projectId)->first()?->pivot;
                $syncData[$projectId] = ['is_primary' => $existingPivot?->is_primary ?? false];
            }
            $contact->projects()->sync($syncData);
        }

        $contact->load('projects.school');

        // Build response
        $projects = $contact->projects;
        $projectIdsResult = $projects->pluck('id')->toArray();
        $projectNames = $projects->map(fn ($p) => $p->display_name)->toArray();
        $schoolNames = $projects->map(fn ($p) => $p->school?->name)->filter()->unique()->values()->toArray();
        $isPrimary = $projects->contains(fn ($p) => $p->pivot->is_primary);

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen frissítve',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'note' => $contact->note,
                'isPrimary' => $isPrimary,
                // New: multiple projects
                'projectIds' => $projectIdsResult,
                'projectNames' => $projectNames,
                'schoolNames' => $schoolNames,
                // Backward compatibility
                'projectId' => $projectIdsResult[0] ?? null,
                'projectName' => $projectNames[0] ?? null,
                'schoolName' => $schoolNames[0] ?? null,
                'callCount' => $contact->call_count ?? 0,
                'smsCount' => $contact->sms_count ?? 0,
            ],
        ]);
    }

    /**
     * Delete standalone contact.
     */
    public function deleteStandaloneContact(int $contactId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        // Find contact that belongs to this partner
        $contact = TabloContact::where('partner_id', $partnerId)
            ->findOrFail($contactId);

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen törölve',
        ]);
    }

    /**
     * Add contact to project.
     */
    public function addContact(Request $request, int $projectId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $project = $this->getProjectForPartner($projectId);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'isPrimary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        $isPrimary = $request->boolean('isPrimary', false);

        // Ha ez lesz az elsődleges, többi elsődleges flag törlése a pivot-ban
        if ($isPrimary) {
            $project->contacts()->updateExistingPivot(
                $project->contacts()->pluck('tablo_contacts.id')->toArray(),
                ['is_primary' => false]
            );
        }

        // Create contact with partner_id
        $contact = TabloContact::create([
            'partner_id' => $partnerId,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
        ]);

        // Link to project via pivot
        $contact->projects()->attach($projectId, ['is_primary' => $isPrimary]);

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen hozzáadva',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'isPrimary' => $isPrimary,
            ],
        ], 201);
    }

    /**
     * Update contact.
     */
    public function updateContact(Request $request, int $projectId, int $contactId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $project = $this->getProjectForPartner($projectId);

        // Find contact that is linked to this project and belongs to partner
        $contact = $project->contacts()
            ->where('tablo_contacts.id', $contactId)
            ->where('tablo_contacts.partner_id', $partnerId)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'isPrimary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Ha ez lesz az elsődleges, többi elsődleges flag törlése a pivot-ban
        if ($request->boolean('isPrimary')) {
            // Reset all other contacts' is_primary for this project
            $project->contacts()
                ->where('tablo_contacts.id', '!=', $contactId)
                ->each(fn ($c) => $project->contacts()->updateExistingPivot($c->id, ['is_primary' => false]));
        }

        // Update contact basic fields
        $contact->update([
            'name' => $request->input('name', $contact->name),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
        ]);

        // Update is_primary in pivot if provided
        if ($request->has('isPrimary')) {
            $project->contacts()->updateExistingPivot($contactId, [
                'is_primary' => $request->boolean('isPrimary'),
            ]);
        }

        // Get updated pivot data
        $isPrimary = $project->contacts()
            ->where('tablo_contacts.id', $contactId)
            ->first()?->pivot?->is_primary ?? false;

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen frissítve',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'isPrimary' => $isPrimary,
            ],
        ]);
    }

    /**
     * Delete contact from project (detach from pivot).
     * Note: This only removes the project-contact link, not the contact itself.
     * If you want to delete the contact entirely, use deleteStandaloneContact().
     */
    public function deleteContact(int $projectId, int $contactId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $project = $this->getProjectForPartner($projectId);

        // Verify contact exists and belongs to partner
        $contact = $project->contacts()
            ->where('tablo_contacts.id', $contactId)
            ->where('tablo_contacts.partner_id', $partnerId)
            ->firstOrFail();

        // Detach from this project (doesn't delete the contact)
        $project->contacts()->detach($contactId);

        // If contact has no more projects, delete it entirely
        if ($contact->projects()->count() === 0) {
            $contact->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen törölve a projektből',
        ]);
    }
}
