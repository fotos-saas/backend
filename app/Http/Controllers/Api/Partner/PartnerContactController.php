<?php

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Partner\ExportContactsExcelAction;
use App\Actions\Partner\ExportContactsVcardAction;
use App\Actions\Partner\ImportContactsFromExcelAction;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\CreateStandaloneContactRequest;
use App\Http\Requests\Api\Partner\ImportContactsRequest;
use App\Http\Requests\Api\Partner\StoreContactRequest;
use App\Http\Requests\Api\Partner\UpdateStandaloneContactRequest;
use App\Models\TabloContact;
use App\Models\TabloProject;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use App\Helpers\QueryHelper;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Partner Contact Controller - Standalone contact management for partners.
 *
 * Project-specific contacts: @see PartnerProjectContactController
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

        $query = TabloContact::where('partner_id', $partnerId)
            ->with(['projects.school']);

        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['name', 'email', 'phone'],
            ]);
        }

        $contacts = $query->orderBy('name')->paginate($perPage);

        $contacts->getCollection()->transform(function ($contact) {
            return $this->formatContactResponse($contact);
        });

        $partner = auth()->user()->getEffectivePartner();
        $maxContacts = $partner?->getMaxContacts();
        $currentCount = TabloContact::where('partner_id', $partnerId)->count();

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
     */
    public function allContacts(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $search = $request->input('search');

        $query = TabloContact::where('partner_id', $partnerId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', QueryHelper::safeLikePattern($search))
                    ->orWhere('email', 'ILIKE', QueryHelper::safeLikePattern($search))
                    ->orWhere('phone', 'ILIKE', QueryHelper::safeLikePattern($search));
            });
        }

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
     * Validate contact data (used before project creation).
     */
    public function storeContact(StoreContactRequest $request): JsonResponse
    {
        $this->getPartnerIdOrFail();

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
    public function createStandaloneContact(CreateStandaloneContactRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $contact = TabloContact::create([
            'partner_id' => $partnerId,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'note' => $request->input('note'),
        ]);

        $this->syncProjects($contact, $request, $partnerId);

        $contact->load('projects.school');

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen létrehozva',
            'data' => $this->formatContactResponse($contact),
        ], 201);
    }

    /**
     * Update standalone contact (can change projects).
     */
    public function updateStandaloneContact(UpdateStandaloneContactRequest $request, int $contactId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $contact = TabloContact::where('partner_id', $partnerId)
            ->findOrFail($contactId);

        $contact->update([
            'name' => $request->input('name', $contact->name),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'note' => $request->input('note'),
        ]);

        if ($request->has('project_ids') || $request->has('project_id')) {
            $this->syncProjectsForUpdate($contact, $request, $partnerId);
        }

        $contact->load('projects.school');

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen frissítve',
            'data' => $this->formatContactResponse($contact),
        ]);
    }

    /**
     * Delete standalone contact.
     */
    public function deleteStandaloneContact(int $contactId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $contact = TabloContact::where('partner_id', $partnerId)
            ->findOrFail($contactId);

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen törölve',
        ]);
    }

    private function formatContactResponse(TabloContact $contact): array
    {
        $projects = $contact->projects;
        $projectIds = $projects->pluck('id')->toArray();
        $projectNames = $projects->map(fn ($p) => $p->display_name)->toArray();
        $schoolNames = $projects->map(fn ($p) => $p->school?->name)->filter()->unique()->values()->toArray();
        $isPrimary = $projects->contains(fn ($p) => $p->pivot->is_primary);

        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'note' => $contact->note,
            'isPrimary' => $isPrimary,
            'projectIds' => $projectIds,
            'projectNames' => $projectNames,
            'schoolNames' => $schoolNames,
            'projectId' => $projectIds[0] ?? null,
            'projectName' => $projectNames[0] ?? null,
            'schoolName' => $schoolNames[0] ?? null,
            'callCount' => $contact->call_count ?? 0,
            'smsCount' => $contact->sms_count ?? 0,
        ];
    }

    private function syncProjects(TabloContact $contact, Request $request, int $partnerId): void
    {
        $projectIds = $request->input('project_ids', []);
        if ($request->filled('project_id') && empty($projectIds)) {
            $projectIds = [$request->input('project_id')];
        }

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
    }

    /**
     * Export contacts to Excel.
     */
    public function exportExcel(Request $request, ExportContactsExcelAction $action): BinaryFileResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $search = $request->input('search');

        $filePath = $action->execute($partnerId, $search);

        return response()->download($filePath, 'kapcsolattartok.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * Export contacts to vCard (.vcf).
     */
    public function exportVcard(Request $request, ExportContactsVcardAction $action): \Illuminate\Http\Response
    {
        $partnerId = $this->getPartnerIdOrFail();
        $search = $request->input('search');

        $vcardContent = $action->execute($partnerId, $search);

        return response($vcardContent, 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="kapcsolattartok.vcf"',
        ]);
    }

    /**
     * Import contacts from Excel file.
     */
    public function importExcel(ImportContactsRequest $request, ImportContactsFromExcelAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $file = $request->file('file');

        $result = $action->execute($partnerId, $file->getRealPath());

        return $this->successResponse($result, 'Importálás befejezve');
    }

    private function syncProjectsForUpdate(TabloContact $contact, Request $request, int $partnerId): void
    {
        $projectIds = $request->input('project_ids', []);
        if ($request->filled('project_id') && empty($projectIds)) {
            $projectIds = [$request->input('project_id')];
        }

        $projectIds = array_map('intval', $projectIds);
        $validProjects = TabloProject::whereIn('id', $projectIds)
            ->where('partner_id', $partnerId)
            ->pluck('id')
            ->toArray();

        $syncData = [];
        foreach ($validProjects as $projectId) {
            $existingPivot = $contact->projects()->where('tablo_projects.id', $projectId)->first()?->pivot;
            $syncData[$projectId] = ['is_primary' => $existingPivot?->is_primary ?? false];
        }
        $contact->projects()->sync($syncData);
    }
}
