<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TeacherArchive;
use App\Models\TeacherChangeLog;
use Illuminate\Support\Facades\DB;

class BulkImportTeacherExecuteAction
{
    public function __construct(
        private CreateTeacherAction $createAction,
        private UpdateTeacherAction $updateAction,
    ) {}

    /**
     * Feloldott tömeges import végrehajtása.
     *
     * @param  array  $items  [{input_name, action, teacher_id}]
     * @return array{created: int, updated: int, skipped: int}
     */
    public function execute(int $partnerId, int $schoolId, array $items): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($partnerId, $schoolId, $items, &$created, &$updated, &$skipped) {
            foreach ($items as $item) {
                $action = $item['action'];
                $inputName = trim($item['input_name']);
                $teacherId = $item['teacher_id'] ?? null;

                match ($action) {
                    'create' => $this->handleCreate($partnerId, $schoolId, $inputName, $created),
                    'update' => $this->handleUpdate($partnerId, $teacherId, $inputName, $updated),
                    'skip' => $skipped++,
                };
            }
        });

        return compact('created', 'updated', 'skipped');
    }

    private function handleCreate(int $partnerId, int $schoolId, string $inputName, int &$counter): void
    {
        $parsed = $this->parseNameWithTitle($inputName);

        $this->createAction->execute($partnerId, [
            'school_id' => $schoolId,
            'canonical_name' => $parsed['name'],
            'title_prefix' => $parsed['title'],
        ]);

        $counter++;
    }

    private function handleUpdate(int $partnerId, ?int $teacherId, string $inputName, int &$counter): void
    {
        if (!$teacherId) return;

        $teacher = TeacherArchive::forPartner($partnerId)->find($teacherId);
        if (!$teacher) return;

        $parsed = $this->parseNameWithTitle($inputName);

        $this->updateAction->execute($teacher, [
            'canonical_name' => $parsed['name'],
            'title_prefix' => $parsed['title'] ?? $teacher->title_prefix,
        ]);

        $counter++;
    }

    /**
     * Név + titulus szétbontása (pl. "Dr. Nagy János" → title: "Dr.", name: "Nagy János").
     */
    private function parseNameWithTitle(string $fullName): array
    {
        $titlePrefixes = ['dr.', 'dr', 'phd', 'prof.', 'prof', 'id.', 'ifj.', 'özv.'];
        $parts = preg_split('/\s+/', $fullName, 2);

        if (count($parts) === 2 && in_array(mb_strtolower($parts[0]), $titlePrefixes, true)) {
            return [
                'title' => $parts[0],
                'name' => $parts[1],
            ];
        }

        return [
            'title' => null,
            'name' => $fullName,
        ];
    }
}
