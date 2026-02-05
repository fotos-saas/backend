<?php

namespace App\Actions\Tablo;

use App\Models\TabloProject;
use Illuminate\Support\Facades\Log;

class SaveDraftAction
{
    /**
     * Save draft finalization data (partial, without validation strictness).
     *
     * @return array{success: bool, message?: string, status?: int}
     */
    public function execute(TabloProject $tabloProject, array $validated): array
    {
        try {
            $existingData = $tabloProject->data ?? [];

            $draftFields = [
                'quote' => 'quote',
                'fontFamily' => 'font_family',
                'color' => 'color',
                'description' => 'description',
                'sortType' => 'sort_type',
                'studentDescription' => 'student_description',
                'teacherDescription' => 'teacher_description',
            ];

            foreach ($draftFields as $inputKey => $dataKey) {
                if (isset($validated[$inputKey])) {
                    $existingData[$dataKey] = $validated[$inputKey];
                }
            }

            $existingData['draft_saved_at'] = now()->toIso8601String();
            $tabloProject->data = $existingData;

            if (! empty($validated['className'])) {
                $tabloProject->class_name = $validated['className'];
            }
            if (! empty($validated['classYear'])) {
                $tabloProject->class_year = $validated['classYear'];
            }

            $tabloProject->save();

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Draft save failed', [
                'project_id' => $tabloProject->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Hiba történt a mentés során.',
                'status' => 500,
            ];
        }
    }
}
