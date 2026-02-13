<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\StudentAlias;
use App\Models\StudentArchive;
use App\Models\StudentChangeLog;

class CreateStudentAction
{
    public function execute(int $partnerId, array $data): StudentArchive
    {
        $student = StudentArchive::create([
            'partner_id' => $partnerId,
            'school_id' => $data['school_id'],
            'canonical_name' => $data['canonical_name'],
            'class_name' => $data['class_name'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);

        if (!empty($data['aliases'])) {
            foreach ($data['aliases'] as $aliasName) {
                $aliasName = trim($aliasName);
                if ($aliasName !== '') {
                    StudentAlias::create([
                        'student_id' => $student->id,
                        'alias_name' => $aliasName,
                    ]);
                }
            }
        }

        StudentChangeLog::create([
            'student_id' => $student->id,
            'user_id' => auth()->id(),
            'change_type' => 'created',
            'new_value' => $student->canonical_name,
        ]);

        return $student->load('aliases', 'school');
    }
}
