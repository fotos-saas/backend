<?php

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Teacher\BulkImportTeacherExecuteAction;
use App\Actions\Teacher\BulkImportTeacherPreviewAction;
use App\Actions\Teacher\CreateTeacherAction;
use App\Actions\Teacher\GetTeachersByProjectAction;
use App\Actions\Teacher\MarkNoPhotoAction;
use App\Actions\Teacher\PreviewTeacherSyncAction;
use App\Actions\Teacher\SyncSingleTeacherCrossSchoolAction;
use App\Actions\Teacher\SyncTeacherPhotosAction;
use App\Actions\Teacher\UpdateTeacherAction;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\BulkImportTeacherExecuteRequest;
use App\Http\Requests\Api\Partner\BulkImportTeacherPreviewRequest;
use App\Http\Requests\Api\Partner\StoreTeacherRequest;
use App\Http\Requests\Api\Partner\SyncTeacherPhotosRequest;
use App\Http\Requests\Api\Partner\UpdateTeacherRequest;
use App\Helpers\QueryHelper;
use App\Models\TeacherArchive;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerTeacherController extends Controller
{
    use PartnerAuthTrait;

    public function index(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $perPage = min((int) $request->input('per_page', 18), 50);
        $search = $request->input('search');
        $schoolId = $request->input('school_id');
        $classYear = $request->input('class_year');

        $query = TeacherArchive::forPartner($partnerId)
            ->with('school', 'activePhoto')
            ->withCount('aliases', 'photos');

        if ($schoolId) {
            $query->forSchool((int) $schoolId);
        }

        // Évfolyam szűrő: tablo_persons + tablo_projects.class_year alapján
        if ($classYear) {
            $query->whereIn('id', function ($sub) use ($partnerId, $classYear) {
                $sub->select('ta.id')
                    ->from('teacher_archive as ta')
                    ->join('tablo_persons as tp', function ($join) {
                        $join->on('tp.name', '=', 'ta.canonical_name')
                            ->where('tp.type', 'teacher');
                    })
                    ->join('tablo_projects as tpr', function ($join) use ($partnerId) {
                        $join->on('tpr.id', '=', 'tp.tablo_project_id')
                            ->where('tpr.partner_id', $partnerId);
                    })
                    ->where('tpr.class_year', 'ILIKE', QueryHelper::safeLikePattern($classYear));
            });
        }

        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['canonical_name', 'title_prefix', 'position'],
                'relations' => [
                    'aliases' => ['alias_name'],
                    'school' => ['name'],
                ],
            ]);
        }

        $teachers = $query->orderBy('canonical_name')->paginate($perPage);

        $teachers->getCollection()->transform(fn (TeacherArchive $t) => [
            'id' => $t->id,
            'canonicalName' => $t->canonical_name,
            'titlePrefix' => $t->title_prefix,
            'position' => $t->position,
            'fullDisplayName' => $t->full_display_name,
            'schoolId' => $t->school_id,
            'schoolName' => $t->school?->name,
            'isActive' => $t->is_active,
            'photoThumbUrl' => $t->photo_thumb_url,
            'photoUrl' => $t->photo_url,
            'aliasesCount' => $t->aliases_count ?? 0,
            'photosCount' => $t->photos_count ?? 0,
        ]);

        return response()->json($teachers);
    }

    public function byProject(Request $request, GetTeachersByProjectAction $action): JsonResponse
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

    public function allTeachers(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $search = $request->input('search');
        $schoolId = $request->input('school_id');

        $query = TeacherArchive::forPartner($partnerId)->active()->with('activePhoto');

        if ($schoolId) {
            $query->forSchool((int) $schoolId);
        }

        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['canonical_name'],
                'relations' => ['aliases' => ['alias_name']],
            ]);
        }

        $teachers = $query->orderBy('canonical_name')->limit(50)->get();

        return response()->json(
            $teachers->map(fn (TeacherArchive $t) => [
                'id' => $t->id,
                'canonicalName' => $t->canonical_name,
                'titlePrefix' => $t->title_prefix,
                'fullDisplayName' => $t->full_display_name,
                'schoolId' => $t->school_id,
                'photoThumbUrl' => $t->photo_thumb_url,
            ])
        );
    }

    public function classYears(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        // Végzési évek kinyerése a class_year mezőből (utolsó 4 számjegy)
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

        $teacher = TeacherArchive::forPartner($partnerId)
            ->with(['school', 'aliases', 'photos.media', 'activePhoto'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatTeacherDetail($teacher),
        ]);
    }

    public function store(StoreTeacherRequest $request, CreateTeacherAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $teacher = $action->execute($partnerId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tanár sikeresen létrehozva',
            'data' => $this->formatTeacherDetail($teacher),
        ], 201);
    }

    public function update(UpdateTeacherRequest $request, int $id, UpdateTeacherAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $teacher = TeacherArchive::forPartner($partnerId)->findOrFail($id);
        $teacher = $action->execute($teacher, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tanár sikeresen frissítve',
            'data' => $this->formatTeacherDetail($teacher),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $teacher = TeacherArchive::forPartner($partnerId)->findOrFail($id);
        $teacher->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tanár sikeresen törölve',
        ]);
    }

    public function markNoPhoto(int $id, MarkNoPhotoAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $teacher = TeacherArchive::forPartner($partnerId)->findOrFail($id);

        $action->mark($teacher, auth()->id());

        return $this->successResponse(null, 'Megjegyzés mentve');
    }

    public function undoNoPhoto(int $id, MarkNoPhotoAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $teacher = TeacherArchive::forPartner($partnerId)->findOrFail($id);

        $action->undo($teacher, auth()->id());

        return $this->successResponse(null, 'Jelölés visszavonva');
    }

    public function syncCrossSchool(int $id, SyncSingleTeacherCrossSchoolAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $teacher = TeacherArchive::forPartner($partnerId)->findOrFail($id);

        $result = $action->execute($teacher);

        if (! $result['success']) {
            return $this->errorResponse($result['message'], 422);
        }

        return $this->successResponse($result, $result['message']);
    }

    public function getChangelog(int $id, Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $teacher = TeacherArchive::forPartner($partnerId)->findOrFail($id);

        $perPage = min((int) $request->input('per_page', 20), 50);

        $logs = $teacher->changeLogs()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $logs->getCollection()->transform(fn ($log) => [
            'id' => $log->id,
            'changeType' => $log->change_type,
            'oldValue' => $log->old_value,
            'newValue' => $log->new_value,
            'metadata' => $log->metadata,
            'userName' => $log->user?->name,
            'createdAt' => $log->created_at->toIso8601String(),
        ]);

        return response()->json($logs);
    }

    public function bulkImportPreview(BulkImportTeacherPreviewRequest $request, BulkImportTeacherPreviewAction $action): JsonResponse
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

    public function bulkImportExecute(BulkImportTeacherExecuteRequest $request, BulkImportTeacherExecuteAction $action): JsonResponse
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

    public function previewSync(SyncTeacherPhotosRequest $request, PreviewTeacherSyncAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $validated = $request->validated();

        $result = $action->execute(
            (int) $validated['school_id'],
            $partnerId,
            $validated['class_year'] ?? null,
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function executeSync(SyncTeacherPhotosRequest $request, SyncTeacherPhotosAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $validated = $request->validated();

        $personIds = isset($validated['person_ids'])
            ? array_map('intval', $validated['person_ids'])
            : null;

        $result = $action->execute(
            (int) $validated['school_id'],
            $partnerId,
            $validated['class_year'] ?? null,
            $personIds,
        );

        return response()->json([
            'success' => true,
            'message' => "Szinkronizálás kész: {$result['synced']} tanár fotója frissítve.",
            'data' => $result,
        ]);
    }

    private function formatTeacherDetail(TeacherArchive $teacher): array
    {
        return [
            'id' => $teacher->id,
            'canonicalName' => $teacher->canonical_name,
            'titlePrefix' => $teacher->title_prefix,
            'position' => $teacher->position,
            'fullDisplayName' => $teacher->full_display_name,
            'schoolId' => $teacher->school_id,
            'schoolName' => $teacher->school?->name,
            'isActive' => $teacher->is_active,
            'notes' => $teacher->notes,
            'photoThumbUrl' => $teacher->photo_thumb_url,
            'photoUrl' => $teacher->photo_url,
            'aliases' => $teacher->aliases->map(fn ($a) => [
                'id' => $a->id,
                'aliasName' => $a->alias_name,
            ])->toArray(),
            'photos' => $teacher->photos->map(fn ($p) => [
                'id' => $p->id,
                'mediaId' => $p->media_id,
                'year' => $p->year,
                'isActive' => $p->is_active,
                'url' => $p->media?->getUrl(),
                'thumbUrl' => $p->media?->getUrl('thumb'),
                'fileName' => $p->media?->file_name,
            ])->toArray(),
            'createdAt' => $teacher->created_at->toIso8601String(),
            'updatedAt' => $teacher->updated_at->toIso8601String(),
        ];
    }
}
