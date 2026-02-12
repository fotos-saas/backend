<?php

namespace App\Actions\Partner;

use App\Models\SchoolChangeLog;
use App\Models\TabloSchool;

class UpdateSchoolAction
{
    public function execute(int $partnerId, int $schoolId, string $newName, ?string $newCity): array
    {
        $school = TabloSchool::whereHas('partners', fn ($q) => $q->where('partner_schools.partner_id', $partnerId))
            ->findOrFail($schoolId);

        $userId = auth()->id();

        // Log changes before update
        if ($newName !== $school->name) {
            SchoolChangeLog::create([
                'school_id' => $schoolId,
                'user_id' => $userId,
                'change_type' => 'name_changed',
                'old_value' => $school->name,
                'new_value' => $newName,
            ]);
        }

        if ($newCity !== $school->city) {
            SchoolChangeLog::create([
                'school_id' => $schoolId,
                'user_id' => $userId,
                'change_type' => 'city_changed',
                'old_value' => $school->city,
                'new_value' => $newCity,
            ]);
        }

        $school->update([
            'name' => $newName,
            'city' => $newCity,
        ]);

        return [
            'id' => $school->id,
            'name' => $school->name,
            'city' => $school->city,
        ];
    }
}
