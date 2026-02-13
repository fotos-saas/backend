<?php

namespace App\Actions\Archive;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class GetArchiveChangelogAction
{
    public function execute(Model $entry, int $perPage): LengthAwarePaginator
    {
        $logs = $entry->changeLogs()
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
