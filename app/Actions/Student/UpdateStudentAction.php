<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\StudentAlias;
use App\Models\StudentArchive;
use App\Models\StudentChangeLog;

class UpdateStudentAction
{
    public function execute(StudentArchive $student, array $data): StudentArchive
    {
        $changes = [];

        if (isset($data['canonical_name']) && $data['canonical_name'] !== $student->canonical_name) {
            $changes[] = [
                'change_type' => 'name_changed',
                'old_value' => $student->canonical_name,
                'new_value' => $data['canonical_name'],
            ];
        }

        if (array_key_exists('class_name', $data) && $data['class_name'] !== $student->class_name) {
            $changes[] = [
                'change_type' => 'class_name_changed',
                'old_value' => $student->class_name,
                'new_value' => $data['class_name'],
            ];
        }

        if (isset($data['school_id']) && (int) $data['school_id'] !== $student->school_id) {
            $changes[] = [
                'change_type' => 'school_changed',
                'old_value' => (string) $student->school_id,
                'new_value' => (string) $data['school_id'],
            ];
        }

        $student->update(array_filter([
            'canonical_name' => $data['canonical_name'] ?? null,
            'class_name' => array_key_exists('class_name', $data) ? $data['class_name'] : null,
            'school_id' => $data['school_id'] ?? null,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : null,
            'is_active' => $data['is_active'] ?? null,
        ], fn ($v) => $v !== null));

        if (array_key_exists('aliases', $data)) {
            $this->syncAliases($student, $data['aliases'] ?? []);
        }

        foreach ($changes as $change) {
            StudentChangeLog::create([
                'student_id' => $student->id,
                'user_id' => auth()->id(),
                ...$change,
            ]);
        }

        return $student->load('aliases', 'school', 'photos.media');
    }

    private function syncAliases(StudentArchive $student, array $aliases): void
    {
        $student->aliases()->delete();

        foreach ($aliases as $aliasName) {
            $aliasName = trim($aliasName);
            if ($aliasName !== '') {
                StudentAlias::create([
                    'student_id' => $student->id,
                    'alias_name' => $aliasName,
                ]);
            }
        }
    }
}
