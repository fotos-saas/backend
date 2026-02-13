<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\StudentArchive;
use Illuminate\Support\Facades\DB;

class BulkImportStudentExecuteAction
{
    public function __construct(
        private CreateStudentAction $createAction,
        private UpdateStudentAction $updateAction,
    ) {}

    public function execute(int $partnerId, int $schoolId, array $items): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($partnerId, $schoolId, $items, &$created, &$updated, &$skipped) {
            foreach ($items as $item) {
                $action = $item['action'];
                $inputName = trim($item['input_name']);
                $studentId = $item['student_id'] ?? null;

                match ($action) {
                    'create' => $this->handleCreate($partnerId, $schoolId, $inputName, $created),
                    'update' => $this->handleUpdate($partnerId, $studentId, $inputName, $updated),
                    'skip' => $skipped++,
                };
            }
        });

        return compact('created', 'updated', 'skipped');
    }

    private function handleCreate(int $partnerId, int $schoolId, string $inputName, int &$counter): void
    {
        $this->createAction->execute($partnerId, [
            'school_id' => $schoolId,
            'canonical_name' => $inputName,
        ]);

        $counter++;
    }

    private function handleUpdate(int $partnerId, ?int $studentId, string $inputName, int &$counter): void
    {
        if (!$studentId) return;

        $student = StudentArchive::forPartner($partnerId)->find($studentId);
        if (!$student) return;

        $this->updateAction->execute($student, [
            'canonical_name' => $inputName,
        ]);

        $counter++;
    }
}
