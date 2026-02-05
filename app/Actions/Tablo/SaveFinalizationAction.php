<?php

namespace App\Actions\Tablo;

use App\Models\TabloContact;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use App\Services\Tablo\FinalizationSecurityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaveFinalizationAction
{
    public function __construct(
        private FinalizationSecurityService $security,
    ) {}

    /**
     * Save finalization data for a tablo project.
     *
     * @return array{success: bool, message: string, status?: int}
     */
    public function execute(TabloProject $tabloProject, array $validated, string $ip): array
    {
        DB::beginTransaction();
        try {
            $this->saveContact($tabloProject, $validated);
            $this->saveSchool($tabloProject, $validated);

            $tabloProject->class_name = $validated['className'];
            $tabloProject->class_year = $validated['classYear'];

            $existingData = $tabloProject->data ?? [];
            $tabloProject->data = array_merge($existingData, [
                'quote' => $validated['quote'],
                'font_family' => $validated['fontFamily'],
                'color' => $validated['color'],
                'description' => $validated['description'],
                'sort_type' => $validated['sortType'] ?? 'abc',
                'student_description' => $validated['studentDescription'],
                'teacher_description' => $validated['teacherDescription'],
                'finalized_at' => now()->toIso8601String(),
                'finalized_from' => 'frontend-tablo',
            ]);

            $tabloProject->save();

            DB::commit();

            $this->security->logSecurityEvent('finalization_saved', $tabloProject->id, [
                'ip' => $ip,
            ]);

            return [
                'success' => true,
                'message' => 'Megrendelés sikeresen véglegesítve!',
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Finalization save failed', [
                'project_id' => $tabloProject->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Hiba történt a mentés során. Kérjük, próbáld újra később!',
                'status' => 500,
            ];
        }
    }

    private function saveContact(TabloProject $tabloProject, array $validated): void
    {
        $primaryContact = $tabloProject->contacts()->wherePivot('is_primary', true)->first();

        if ($primaryContact) {
            $primaryContact->update([
                'name' => $validated['name'],
                'email' => $validated['contactEmail'],
                'phone' => $validated['contactPhone'],
            ]);
        } else {
            $contact = TabloContact::create([
                'partner_id' => $tabloProject->partner_id,
                'name' => $validated['name'],
                'email' => $validated['contactEmail'],
                'phone' => $validated['contactPhone'],
                'note' => 'Véglegesítéskor hozzáadva',
            ]);
            $tabloProject->contacts()->attach($contact->id, ['is_primary' => true]);
        }
    }

    private function saveSchool(TabloProject $tabloProject, array $validated): void
    {
        if (empty($validated['schoolName'])) {
            return;
        }

        $school = $tabloProject->school;
        if ($school) {
            $school->update([
                'name' => $validated['schoolName'],
                'city' => $validated['schoolCity'] ?? $school->city,
            ]);
        } else {
            $school = TabloSchool::create([
                'name' => $validated['schoolName'],
                'city' => $validated['schoolCity'],
            ]);
            $tabloProject->school_id = $school->id;
        }
    }
}
