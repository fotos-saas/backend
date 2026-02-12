<?php

namespace App\Actions\Tablo;

use App\Models\TabloProject;
use Illuminate\Support\Facades\DB;

class BatchStorePersonsAction
{
    public function execute(TabloProject $project, array $persons): array
    {
        $created = [];

        DB::transaction(function () use ($project, $persons, &$created) {
            foreach ($persons as $personData) {
                $person = $project->persons()->create([
                    'name' => $personData['name'],
                    'local_id' => $personData['local_id'] ?? null,
                    'note' => $personData['note'] ?? null,
                ]);

                $created[] = [
                    'id' => $person->id,
                    'name' => $person->name,
                    'local_id' => $person->local_id,
                    'note' => $person->note,
                ];
            }
        });

        return $created;
    }
}
