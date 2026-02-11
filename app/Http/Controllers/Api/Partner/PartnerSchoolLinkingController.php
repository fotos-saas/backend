<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\LinkSchoolsRequest;
use App\Models\TabloPartner;
use App\Models\TabloSchool;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Iskola összekapcsolás kezelése.
 * Partnerenként iskolák csoportosítása linked_group UUID-vel.
 */
class PartnerSchoolLinkingController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Iskolák összekapcsolása — közös linked_group UUID beállítása.
     */
    public function linkSchools(LinkSchoolsRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $schoolIds = array_map('intval', $request->validated('school_ids'));

        // Ellenőrzés: minden school_id a partnerhez tartozik-e
        $partnerSchoolIds = DB::table('partner_schools')
            ->where('partner_id', $partnerId)
            ->whereIn('school_id', $schoolIds)
            ->pluck('school_id')
            ->toArray();

        $missing = array_diff($schoolIds, $partnerSchoolIds);
        if (!empty($missing)) {
            return response()->json([
                'success' => false,
                'message' => 'Egyes iskolák nem tartoznak a partnerhez.',
            ], 422);
        }

        // Ha bármelyik iskola már csoportban van, az összes régi csoportot feloldjuk
        DB::table('partner_schools')
            ->where('partner_id', $partnerId)
            ->whereIn('school_id', $schoolIds)
            ->whereNotNull('linked_group')
            ->update(['linked_group' => null]);

        // Új közös UUID beállítása
        $groupUuid = Str::uuid()->toString();

        DB::table('partner_schools')
            ->where('partner_id', $partnerId)
            ->whereIn('school_id', $schoolIds)
            ->update(['linked_group' => $groupUuid]);

        return response()->json([
            'success' => true,
            'message' => count($schoolIds) . ' iskola sikeresen összekapcsolva.',
            'linkedGroup' => $groupUuid,
        ]);
    }

    /**
     * Iskola leválasztása csoportról — linked_group NULL-ra állítása.
     */
    public function unlinkSchool(int $schoolId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $pivot = DB::table('partner_schools')
            ->where('partner_id', $partnerId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$pivot) {
            return response()->json([
                'success' => false,
                'message' => 'Az iskola nem tartozik a partnerhez.',
            ], 404);
        }

        if (!$pivot->linked_group) {
            return response()->json([
                'success' => false,
                'message' => 'Az iskola nincs csoportban.',
            ], 422);
        }

        // Ha a csoport csak 2 tagú, mindkettőt leválasztjuk
        $groupCount = DB::table('partner_schools')
            ->where('partner_id', $partnerId)
            ->where('linked_group', $pivot->linked_group)
            ->count();

        if ($groupCount <= 2) {
            // Teljes csoport feloszlatása
            DB::table('partner_schools')
                ->where('partner_id', $partnerId)
                ->where('linked_group', $pivot->linked_group)
                ->update(['linked_group' => null]);
        } else {
            // Csak az adott iskola leválasztása
            DB::table('partner_schools')
                ->where('partner_id', $partnerId)
                ->where('school_id', $schoolId)
                ->update(['linked_group' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Iskola sikeresen leválasztva a csoportról.',
        ]);
    }

    /**
     * Partner összes összekapcsolt csoportjának listája.
     */
    public function getLinkedGroups(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $groups = DB::table('partner_schools')
            ->join('tablo_schools', 'tablo_schools.id', '=', 'partner_schools.school_id')
            ->where('partner_schools.partner_id', $partnerId)
            ->whereNotNull('partner_schools.linked_group')
            ->select([
                'partner_schools.linked_group',
                'tablo_schools.id',
                'tablo_schools.name',
                'tablo_schools.city',
            ])
            ->orderBy('partner_schools.linked_group')
            ->orderBy('tablo_schools.name')
            ->get()
            ->groupBy('linked_group')
            ->map(fn ($schools, $groupId) => [
                'linkedGroup' => $groupId,
                'schools' => $schools->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'city' => $s->city,
                ])->values()->toArray(),
            ])
            ->values()
            ->toArray();

        return response()->json(['data' => $groups]);
    }
}
