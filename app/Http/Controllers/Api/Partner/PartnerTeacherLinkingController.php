<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\LinkTeachersRequest;
use App\Models\TeacherArchive;
use App\Models\TeacherChangeLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Tanár összekapcsolás kezelése.
 * Partnerenként tanárok csoportosítása linked_group UUID-vel.
 */
class PartnerTeacherLinkingController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Tanárok összekapcsolása — közös linked_group UUID beállítása.
     */
    public function linkTeachers(LinkTeachersRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $teacherIds = array_map('intval', $request->validated('teacher_ids'));

        // Ellenőrzés: minden teacher_id a partnerhez tartozik-e
        $partnerTeacherIds = TeacherArchive::forPartner($partnerId)
            ->whereIn('id', $teacherIds)
            ->pluck('id')
            ->toArray();

        $missing = array_diff($teacherIds, $partnerTeacherIds);
        if (!empty($missing)) {
            return $this->errorResponse('Egyes tanárok nem tartoznak a partnerhez.', 422);
        }

        // Ha bármelyik tanár már csoportban van, a régi csoportot feloldjuk
        TeacherArchive::forPartner($partnerId)
            ->whereIn('id', $teacherIds)
            ->whereNotNull('linked_group')
            ->update(['linked_group' => null]);

        // Új közös UUID beállítása
        $groupUuid = Str::uuid()->toString();

        TeacherArchive::forPartner($partnerId)
            ->whereIn('id', $teacherIds)
            ->update(['linked_group' => $groupUuid]);

        // ChangeLog bejegyzés minden érintett tanárra
        $userId = auth()->id();
        $now = now();
        foreach ($teacherIds as $teacherId) {
            TeacherChangeLog::create([
                'teacher_id' => $teacherId,
                'user_id' => $userId,
                'change_type' => 'linked',
                'old_value' => null,
                'new_value' => $groupUuid,
                'metadata' => ['linked_teacher_ids' => $teacherIds],
                'created_at' => $now,
            ]);
        }

        return $this->successResponse(
            ['linkedGroup' => $groupUuid],
            count($teacherIds) . ' tanár sikeresen összekapcsolva.'
        );
    }

    /**
     * Tanár leválasztása csoportról — linked_group NULL-ra állítása.
     */
    public function unlinkTeacher(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $teacher = TeacherArchive::forPartner($partnerId)->findOrFail($id);

        if (!$teacher->linked_group) {
            return $this->errorResponse('A tanár nincs csoportban.', 422);
        }

        $linkedGroup = $teacher->linked_group;

        // Ha a csoport csak 2 tagú, mindkettőt leválasztjuk
        $groupCount = TeacherArchive::forPartner($partnerId)
            ->where('linked_group', $linkedGroup)
            ->count();

        $userId = auth()->id();
        $now = now();

        if ($groupCount <= 2) {
            // Teljes csoport feloszlatása
            $affectedIds = TeacherArchive::forPartner($partnerId)
                ->where('linked_group', $linkedGroup)
                ->pluck('id')
                ->toArray();

            TeacherArchive::forPartner($partnerId)
                ->where('linked_group', $linkedGroup)
                ->update(['linked_group' => null]);

            foreach ($affectedIds as $teacherId) {
                TeacherChangeLog::create([
                    'teacher_id' => $teacherId,
                    'user_id' => $userId,
                    'change_type' => 'unlinked',
                    'old_value' => $linkedGroup,
                    'new_value' => null,
                    'metadata' => ['reason' => 'group_dissolved'],
                    'created_at' => $now,
                ]);
            }
        } else {
            // Csak az adott tanár leválasztása
            $teacher->update(['linked_group' => null]);

            TeacherChangeLog::create([
                'teacher_id' => $id,
                'user_id' => $userId,
                'change_type' => 'unlinked',
                'old_value' => $linkedGroup,
                'new_value' => null,
                'metadata' => null,
                'created_at' => $now,
            ]);
        }

        return $this->successResponse(null, 'Tanár sikeresen leválasztva a csoportról.');
    }

    /**
     * Partner összes összekapcsolt csoportjának listája.
     */
    public function getLinkedGroups(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $teachers = TeacherArchive::forPartner($partnerId)
            ->whereNotNull('linked_group')
            ->with(['school:id,name', 'activePhoto'])
            ->orderBy('linked_group')
            ->orderBy('canonical_name')
            ->get();

        $groups = $teachers->groupBy('linked_group')
            ->map(fn ($items, $groupId) => [
                'linkedGroup' => $groupId,
                'teachers' => $items->map(fn (TeacherArchive $t) => [
                    'id' => $t->id,
                    'canonicalName' => $t->canonical_name,
                    'titlePrefix' => $t->title_prefix,
                    'fullDisplayName' => $t->full_display_name,
                    'schoolName' => $t->school?->name,
                    'photoThumbUrl' => $t->photo_thumb_url,
                    'photoMiniThumbUrl' => $t->photo_mini_thumb_url,
                ])->values()->toArray(),
            ])
            ->values()
            ->toArray();

        return response()->json(['data' => $groups]);
    }
}
