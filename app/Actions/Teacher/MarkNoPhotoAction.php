<?php

namespace App\Actions\Teacher;

use App\Models\TeacherArchive;

class MarkNoPhotoAction
{
    private const MARKER = 'Nem találom a képet';

    public function mark(TeacherArchive $teacher, ?int $userId = null): void
    {
        $oldNotes = $teacher->notes;
        $newNotes = $oldNotes ? "{$oldNotes}\n" . self::MARKER : self::MARKER;

        $teacher->update(['notes' => $newNotes]);

        $teacher->changeLogs()->create([
            'user_id' => $userId,
            'change_type' => 'no_photo_marked',
            'old_value' => $oldNotes,
            'new_value' => $newNotes,
            'metadata' => ['action' => 'mark_no_photo'],
            'created_at' => now(),
        ]);
    }

    public function undo(TeacherArchive $teacher, ?int $userId = null): void
    {
        $oldNotes = $teacher->notes;

        if (! $oldNotes || ! str_contains($oldNotes, self::MARKER)) {
            return;
        }

        $lines = array_filter(
            explode("\n", $oldNotes),
            fn (string $line) => trim($line) !== self::MARKER
        );
        $newNotes = implode("\n", $lines) ?: null;

        $teacher->update(['notes' => $newNotes]);

        $teacher->changeLogs()->create([
            'user_id' => $userId,
            'change_type' => 'no_photo_unmarked',
            'old_value' => $oldNotes,
            'new_value' => $newNotes,
            'metadata' => ['action' => 'undo_no_photo'],
            'created_at' => now(),
        ]);
    }
}
