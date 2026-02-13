<?php

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Archive\GetArchiveChangelogAction;
use App\Actions\Archive\MarkNoPhotoAction;
use App\Actions\Student\BulkImportStudentExecuteAction;
use App\Actions\Student\BulkImportStudentPreviewAction;
use App\Actions\Student\CreateStudentAction;
use App\Actions\Student\ExportStudentArchiveCsvAction;
use App\Actions\Student\GetStudentsByProjectAction;
use App\Actions\Student\ListStudentArchiveAction;
use App\Actions\Student\UpdateStudentAction;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\BulkImportStudentExecuteRequest;
use App\Http\Requests\Api\Partner\BulkImportStudentPreviewRequest;
use App\Http\Requests\Api\Partner\StoreStudentRequest;
use App\Http\Requests\Api\Partner\UpdateStudentRequest;
use App\Models\StudentArchive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartnerStudentController extends Controller
{
    use PartnerAuthTrait;

    public function index(Request $request, ListStudentArchiveAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $students = $action->execute(
            $partnerId,
            $request->input('search'),
            $request->input('school_id') ? (int) $request->input('school_id') : null,
            $request->input('class_name'),
            min((int) $request->input('per_page', 18), 50),
        );

        return response()->json($students);
    }

    public function byProject(Request $request, GetStudentsByProjectAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $result = $action->execute(
            $partnerId,
            $request->input('class_year'),
            $request->input('school_id') ? (int) $request->input('school_id') : null,
            (bool) $request->input('missing_only', false),
        );

        return response()->json($result);
    }

    public function classYears(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $years = DB::table('tablo_projects')
            ->where('partner_id', $partnerId)
            ->whereNotNull('class_year')
            ->where('class_year', '!=', '')
            ->selectRaw("DISTINCT regexp_replace(class_year, '.*?(\\d{4})\\s*$', '\\1') as grad_year")
            ->orderByDesc('grad_year')
            ->pluck('grad_year')
            ->filter(fn ($y) => preg_match('/^\d{4}$/', $y))
            ->values();

        return response()->json($years);
    }

    public function show(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $student = StudentArchive::forPartner($partnerId)
            ->with(['school', 'aliases', 'photos.media', 'activePhoto'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatStudentDetail($student),
        ]);
    }

    public function store(StoreStudentRequest $request, CreateStudentAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $student = $action->execute($partnerId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Diák sikeresen létrehozva',
            'data' => $this->formatStudentDetail($student),
        ], 201);
    }

    public function update(UpdateStudentRequest $request, int $id, UpdateStudentAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $student = StudentArchive::forPartner($partnerId)->findOrFail($id);
        $student = $action->execute($student, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Diák sikeresen frissítve',
            'data' => $this->formatStudentDetail($student),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $student = StudentArchive::forPartner($partnerId)->findOrFail($id);
        $student->delete();

        return response()->json([
            'success' => true,
            'message' => 'Diák sikeresen törölve',
        ]);
    }

    public function markNoPhoto(int $id, MarkNoPhotoAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $student = StudentArchive::forPartner($partnerId)->findOrFail($id);

        $action->mark($student, auth()->id());

        return $this->successResponse(null, 'Megjegyzés mentve');
    }

    public function undoNoPhoto(int $id, MarkNoPhotoAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $student = StudentArchive::forPartner($partnerId)->findOrFail($id);

        $action->undo($student, auth()->id());

        return $this->successResponse(null, 'Jelölés visszavonva');
    }

    public function getChangelog(int $id, Request $request, GetArchiveChangelogAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $student = StudentArchive::forPartner($partnerId)->findOrFail($id);

        $logs = $action->execute($student, min((int) $request->input('per_page', 20), 50));

        return response()->json($logs);
    }

    public function bulkImportPreview(BulkImportStudentPreviewRequest $request, BulkImportStudentPreviewAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $validated = $request->validated();

        $results = $action->execute(
            $partnerId,
            (int) $validated['school_id'],
            $validated['names'] ?? null,
            $request->file('file'),
        );

        return response()->json(['success' => true, 'data' => $results]);
    }

    public function bulkImportExecute(BulkImportStudentExecuteRequest $request, BulkImportStudentExecuteAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $validated = $request->validated();

        $result = $action->execute(
            $partnerId,
            (int) $validated['school_id'],
            $validated['items'],
        );

        return response()->json([
            'success' => true,
            'message' => "Import kész: {$result['created']} létrehozva, {$result['updated']} frissítve, {$result['skipped']} kihagyva.",
            'data' => $result,
        ]);
    }

    public function exportCsv(ExportStudentArchiveCsvAction $action): StreamedResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        return $action->execute($partnerId);
    }

    private function formatStudentDetail(StudentArchive $student): array
    {
        return [
            'id' => $student->id,
            'canonicalName' => $student->canonical_name,
            'className' => $student->class_name,
            'schoolId' => $student->school_id,
            'schoolName' => $student->school?->name,
            'isActive' => $student->is_active,
            'notes' => $student->notes,
            'photoThumbUrl' => $student->photo_thumb_url,
            'photoUrl' => $student->photo_url,
            'aliases' => $student->aliases->map(fn ($a) => [
                'id' => $a->id,
                'aliasName' => $a->alias_name,
            ])->toArray(),
            'photos' => $student->photos->map(fn ($p) => [
                'id' => $p->id,
                'mediaId' => $p->media_id,
                'year' => $p->year,
                'isActive' => $p->is_active,
                'url' => $p->media?->getUrl(),
                'thumbUrl' => $p->media?->getUrl('thumb'),
                'fileName' => $p->media?->file_name,
            ])->toArray(),
            'createdAt' => $student->created_at->toIso8601String(),
            'updatedAt' => $student->updated_at->toIso8601String(),
        ];
    }
}
