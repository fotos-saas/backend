<?php

namespace App\Actions\Archive;

use Illuminate\Database\Eloquent\Model;

class MarkNoPhotoAction
{
    private const MARKER = 'Nem találom a képet';

    public function mark(Model $entry, ?int $userId = null): void
    {
        $oldNotes = $entry->notes;
        $newNotes = $oldNotes ? "{$oldNotes}\n" . self::MARKER : self::MARKER;

        $entry->update(['notes' => $newNotes]);

        $entry->changeLogs()->create([
            'user_id' => $userId,
            'change_type' => 'no_photo_marked',
            'old_value' => $oldNotes,
            'new_value' => $newNotes,
            'metadata' => ['action' => 'mark_no_photo'],
            'created_at' => now(),
        ]);
    }

    public function undo(Model $entry, ?int $userId = null): void
    {
        $oldNotes = $entry->notes;

        if (! $oldNotes || ! str_contains($oldNotes, self::MARKER)) {
            return;
        }

        $lines = array_filter(
            explode("\n", $oldNotes),
            fn (string $line) => trim($line) !== self::MARKER
        );
        $newNotes = implode("\n", $lines) ?: null;

        $entry->update(['notes' => $newNotes]);

        $entry->changeLogs()->create([
            'user_id' => $userId,
            'change_type' => 'no_photo_unmarked',
            'old_value' => $oldNotes,
            'new_value' => $newNotes,
            'metadata' => ['action' => 'undo_no_photo'],
            'created_at' => now(),
        ]);
    }
}
