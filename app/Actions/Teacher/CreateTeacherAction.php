<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TeacherAlias;
use App\Models\TeacherArchive;
use App\Models\TeacherChangeLog;

class CreateTeacherAction
{
    public function execute(int $partnerId, array $data): TeacherArchive
    {
        $teacher = TeacherArchive::create([
            'partner_id' => $partnerId,
            'school_id' => $data['school_id'],
            'canonical_name' => $data['canonical_name'],
            'title_prefix' => $data['title_prefix'] ?? null,
            'position' => $data['position'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);

        // Aliasok létrehozása
        if (!empty($data['aliases'])) {
            foreach ($data['aliases'] as $aliasName) {
                $aliasName = trim($aliasName);
                if ($aliasName !== '') {
                    TeacherAlias::create([
                        'teacher_id' => $teacher->id,
                        'alias_name' => $aliasName,
                    ]);
                }
            }
        }

        // Changelog bejegyzés
        TeacherChangeLog::create([
            'teacher_id' => $teacher->id,
            'user_id' => auth()->id(),
            'change_type' => 'created',
            'new_value' => $teacher->full_display_name,
        ]);

        return $teacher->load('aliases', 'school');
    }
}
