<?php

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Teacher\CreateTeacherAction;
use App\Actions\Teacher\UpdateTeacherAction;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\StoreTeacherRequest;
use App\Http\Requests\Api\Partner\UpdateTeacherRequest;
use App\Models\TeacherArchive;
use App\Helpers\QueryHelper;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerTeacherController extends Controller
{
    use PartnerAuthTrait;

    public function index(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $perPage = min((int) $request->input('per_page', 18), 50);
        $search = $request->input('search');
        $schoolId = $request->input('school_id');

        $query = TeacherArchive::forPartner($partnerId)
            ->with('school', 'activePhoto')
            ->withCount('aliases', 'photos');

        if ($schoolId) {
            $query->forSchool((int) $schoolId);
        }

        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['canonical_name'],
                'relations' => ['aliases' => ['alias_name']],
            ]);
        }

        $teachers = $query->orderBy('canonical_name')->paginate($perPage);

        $teachers->getCollection()->transform(fn (TeacherArchive $t) => [
            'id' => $t->id,
            'canonicalName' => $t->canonical_name,
            'titlePrefix' => $t->title_prefix,
            'fullDisplayName' => $t->full_display_name,
            'schoolId' => $t->school_id,
            'schoolName' => $t->school?->name,
            'isActive' => $t->is_active,
            'photoThumbUrl' => $t->photo_thumb_url,
            'aliasesCount' => $t->aliases_count ?? 0,
            'photosCount' => $t->photos_count ?? 0,
        ]);

        return response()->json($teachers);
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
            $query->where(function ($q) use ($search) {
                $q->where('canonical_name', 'ILIKE', QueryHelper::safeLikePattern($search))
                    ->orWhereHas('aliases', function ($aq) use ($search) {
                        $aq->where('alias_name', 'ILIKE', QueryHelper::safeLikePattern($search));
                    });
            });
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
