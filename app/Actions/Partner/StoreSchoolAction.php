<?php

namespace App\Actions\Partner;

use App\Models\TabloPartner;
use App\Models\TabloSchool;
use Illuminate\Http\JsonResponse;

class StoreSchoolAction
{
    /**
     * @return array{success: bool, data?: array, error?: string, status?: int}
     */
    public function execute(int $partnerId, string $name, ?string $city): array
    {
        $tabloPartner = TabloPartner::find($partnerId);

        // Check school limit
        $partner = auth()->user()->getEffectivePartner();
        if ($partner) {
            $maxSchools = $partner->getMaxSchools();
            if ($maxSchools !== null) {
                $currentCount = $tabloPartner?->schools()->count() ?? 0;
                if ($currentCount >= $maxSchools) {
                    return [
                        'success' => false,
                        'error' => 'Elérted a csomagodban elérhető maximum iskolaszámot. Válts magasabb csomagra a korlátozás feloldásához!',
                        'upgrade_required' => true,
                        'status' => 403,
                    ];
                }
            }
        }

        // Check if school already exists
        $school = TabloSchool::where('name', $name)->first();

        if (! $school) {
            $school = TabloSchool::create([
                'name' => $name,
                'city' => $city,
            ]);
        }

        // Link school to partner via pivot table (if not already linked)
        if ($tabloPartner && ! $tabloPartner->schools()->where('school_id', $school->id)->exists()) {
            $tabloPartner->schools()->attach($school->id);
        }

        return [
            'success' => true,
            'data' => [
                'id' => $school->id,
                'name' => $school->name,
                'city' => $school->city,
            ],
        ];
    }
}
