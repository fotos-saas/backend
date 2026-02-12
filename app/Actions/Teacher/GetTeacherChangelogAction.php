<?php

namespace App\Actions\Teacher;

use App\Models\TeacherArchive;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetTeacherChangelogAction
{
    public function execute(TeacherArchive $teacher, int $perPage): LengthAwarePaginator
    {
        $logs = $teacher->changeLogs()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $logs->getCollection()->transform(fn ($log) => [
            'id' => $log->id,
            'changeType' => $log->change_type,
            'oldValue' => $log->old_value,
            'newValue' => $log->new_value,
            'metadata' => $log->metadata,
            'userName' => $log->user?->name,
            'createdAt' => $log->created_at->toIso8601String(),
        ]);

        return $logs;
    }
}
