<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TeacherAlias;
use App\Models\TeacherArchive;
use App\Models\TeacherChangeLog;

class UpdateTeacherAction
{
    public function execute(TeacherArchive $teacher, array $data): TeacherArchive
    {
        $changes = [];

        // Név változás tracking
        if (isset($data['canonical_name']) && $data['canonical_name'] !== $teacher->canonical_name) {
            $changes[] = [
                'change_type' => 'name_changed',
                'old_value' => $teacher->canonical_name,
                'new_value' => $data['canonical_name'],
            ];
        }

        // Title prefix változás tracking
        if (array_key_exists('title_prefix', $data) && $data['title_prefix'] !== $teacher->title_prefix) {
            $changes[] = [
                'change_type' => 'title_changed',
                'old_value' => $teacher->title_prefix,
                'new_value' => $data['title_prefix'],
            ];
        }

        // Iskola változás tracking
        if (isset($data['school_id']) && (int) $data['school_id'] !== $teacher->school_id) {
            $changes[] = [
                'change_type' => 'school_changed',
                'old_value' => (string) $teacher->school_id,
                'new_value' => (string) $data['school_id'],
            ];
        }

        // Pozíció változás tracking
        if (array_key_exists('position', $data) && $data['position'] !== $teacher->position) {
            $changes[] = [
                'change_type' => 'position_changed',
                'old_value' => $teacher->position,
                'new_value' => $data['position'],
            ];
        }

        // Tanár frissítés
        $teacher->update(array_filter([
            'canonical_name' => $data['canonical_name'] ?? null,
            'title_prefix' => array_key_exists('title_prefix', $data) ? $data['title_prefix'] : null,
            'position' => array_key_exists('position', $data) ? $data['position'] : null,
            'school_id' => $data['school_id'] ?? null,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : null,
            'is_active' => $data['is_active'] ?? null,
        ], fn ($v) => $v !== null));

        // Aliasok szinkronizálás (ha küldtek)
        if (array_key_exists('aliases', $data)) {
            $this->syncAliases($teacher, $data['aliases'] ?? []);
        }

        // Changelog bejegyzések
        foreach ($changes as $change) {
            TeacherChangeLog::create([
                'teacher_id' => $teacher->id,
                'user_id' => auth()->id(),
                ...$change,
            ]);
        }

        return $teacher->load('aliases', 'school', 'photos.media');
    }

    private function syncAliases(TeacherArchive $teacher, array $aliases): void
    {
        // Meglévő aliasok törlése és újak létrehozása
        $teacher->aliases()->delete();

        foreach ($aliases as $aliasName) {
            $aliasName = trim($aliasName);
            if ($aliasName !== '') {
                TeacherAlias::create([
                    'teacher_id' => $teacher->id,
                    'alias_name' => $aliasName,
                ]);
            }
        }
    }
}
