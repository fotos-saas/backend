<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TabloPartner;
use App\Services\Teacher\TeacherMatchingService;

class MatchTeacherNamesAction
{
    public function __construct(
        private TeacherMatchingService $matchingService,
    ) {}

    /**
     * @param  string[]  $teacherNames  Bemeneti tanárnevek
     * @param  int  $partnerId  Partner ID
     * @param  int  $schoolId  Iskola ID
     * @return array Párosítási eredmények
     */
    public function execute(array $teacherNames, int $partnerId, int $schoolId): array
    {
        // Üres nevek kiszűrése, max 20 név
        $names = array_values(array_filter(
            array_map('trim', $teacherNames),
            fn ($n) => $n !== '',
        ));

        if (empty($names)) {
            return ['matches' => []];
        }

        $names = array_slice($names, 0, 20);

        // Összekapcsolt iskolák feloldása
        $tabloPartner = TabloPartner::find($partnerId);
        $linkedSchoolIds = $tabloPartner ? $tabloPartner->getLinkedSchoolIds($schoolId) : [$schoolId];

        $matches = $this->matchingService->matchNames($names, $partnerId, $linkedSchoolIds);

        return ['matches' => $matches];
    }
}
