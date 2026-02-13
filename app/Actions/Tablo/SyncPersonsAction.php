<?php

namespace App\Actions\Tablo;

use App\Models\TabloProject;
use App\Services\ArchiveLinkingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncPersonsAction
{
    public function __construct(
        private readonly ArchiveLinkingService $archiveLinking,
    ) {}

    public function execute(TabloProject $project, Collection $incomingPersons): array
    {
        $added = 0;
        $removed = 0;
        $updated = 0;

        DB::transaction(function () use ($project, $incomingPersons, &$added, &$removed, &$updated) {
            // Get current persons indexed by local_id
            $currentPersons = $project->persons()->get()->keyBy('local_id');

            // Get incoming local_ids
            $incomingLocalIds = $incomingPersons->pluck('local_id')->toArray();

            // Remove persons not in incoming list
            foreach ($currentPersons as $localId => $current) {
                if (! in_array($localId, $incomingLocalIds)) {
                    $current->delete();
                    $removed++;
                }
            }

            // Add or update persons
            foreach ($incomingPersons as $index => $person) {
                $localId = $person['local_id'];
                $position = $person['position'] ?? $index;
                $type = $person['type'] ?? 'student';
                $existing = $currentPersons->get($localId);

                if ($existing) {
                    // Update if data changed
                    $needsUpdate = $existing->name !== $person['name']
                        || $existing->note !== ($person['note'] ?? null)
                        || $existing->position !== $position
                        || $existing->type !== $type;

                    if ($needsUpdate) {
                        $existing->update([
                            'name' => $person['name'],
                            'note' => $person['note'] ?? null,
                            'position' => $position,
                            'type' => $type,
                        ]);
                        $updated++;
                    }
                } else {
                    // Add new
                    $newPerson = $project->persons()->create([
                        'name' => $person['name'],
                        'local_id' => $localId,
                        'note' => $person['note'] ?? null,
                        'position' => $position,
                        'type' => $type,
                    ]);
                    // Archive link
                    $this->archiveLinking->linkPerson($newPerson, autoCreate: true);
                    $added++;
                }
            }
        });

        // Get updated list ordered by position
        $updatedPersons = $project->persons()->orderBy('position')->get();

        return [
            'project_id' => $project->id,
            'external_id' => $project->external_id,
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
            'total' => $updatedPersons->count(),
            'persons' => $updatedPersons->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'type' => $m->type,
                'local_id' => $m->local_id,
                'note' => $m->note,
                'position' => $m->position,
            ])->toArray(),
        ];
    }
}
