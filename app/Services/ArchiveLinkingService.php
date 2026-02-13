<?php

namespace App\Services;

use App\Models\StudentArchive;
use App\Models\TabloPerson;
use App\Models\TabloProject;
use App\Models\TeacherArchive;
use Illuminate\Support\Facades\Log;

class ArchiveLinkingService
{
    /**
     * Egy személy linkelése az archive-hoz.
     *
     * @return bool True ha sikerült linkelni
     */
    public function linkPerson(TabloPerson $person, bool $autoCreate = true): bool
    {
        if ($person->archive_id) {
            return true;
        }

        $archive = $this->findMatchingArchive($person);

        if (!$archive && $autoCreate) {
            $archive = $this->createArchiveFromPerson($person);
        }

        if (!$archive) {
            return false;
        }

        $person->update(['archive_id' => $archive->id]);

        return true;
    }

    /**
     * Matching archive keresése: exact name + school_id + partner_id
     */
    public function findMatchingArchive(TabloPerson $person): TeacherArchive|StudentArchive|null
    {
        $project = $person->project;
        if (!$project) {
            return null;
        }

        $partnerId = $project->partner_id;
        if (!$partnerId) {
            return null;
        }

        $name = trim($person->name);
        if (!$name) {
            return null;
        }

        if ($person->type === 'teacher') {
            $query = TeacherArchive::where('partner_id', $partnerId)
                ->where('canonical_name', $name);

            if ($project->school_id) {
                $query->where('school_id', $project->school_id);
            }

            return $query->first();
        }

        $query = StudentArchive::where('partner_id', $partnerId)
            ->where('canonical_name', $name);

        if ($project->school_id) {
            $query->where('school_id', $project->school_id);
        }

        return $query->first();
    }

    /**
     * Új archive rekord létrehozása a person adataiból.
     */
    public function createArchiveFromPerson(TabloPerson $person): TeacherArchive|StudentArchive|null
    {
        $project = $person->project;
        if (!$project) {
            return null;
        }

        $partnerId = $project->partner_id;
        if (!$partnerId) {
            return null;
        }

        $name = trim($person->name);
        if (!$name) {
            return null;
        }

        $data = [
            'partner_id' => $partnerId,
            'school_id' => $project->school_id,
            'canonical_name' => $name,
            'is_active' => true,
        ];

        if ($person->type === 'teacher') {
            $archive = TeacherArchive::create($data);
        } else {
            $data['class_name'] = $project->class_name;
            $archive = StudentArchive::create($data);
        }

        Log::info('ArchiveLinking: Auto-created archive', [
            'type' => $person->type,
            'archive_id' => $archive->id,
            'person_id' => $person->id,
            'name' => $name,
        ]);

        return $archive;
    }

    /**
     * Bulk link: egy partner összes személyét linkelni az archive-okhoz.
     *
     * @return array{linked: int, created: int, skipped: int, already_linked: int}
     */
    public function linkAllForPartner(int $partnerId, bool $autoCreate = true, bool $dryRun = false): array
    {
        $stats = ['linked' => 0, 'created' => 0, 'skipped' => 0, 'already_linked' => 0];

        $persons = TabloPerson::whereHas('project', fn ($q) => $q->where('partner_id', $partnerId))
            ->with('project')
            ->get();

        foreach ($persons as $person) {
            if ($person->archive_id) {
                $stats['already_linked']++;
                continue;
            }

            $archive = $this->findMatchingArchive($person);

            if (!$archive && $autoCreate) {
                if ($dryRun) {
                    $stats['created']++;
                    $stats['linked']++;
                    continue;
                }
                $archive = $this->createArchiveFromPerson($person);
                if ($archive) {
                    $stats['created']++;
                }
            }

            if ($archive) {
                if (!$dryRun) {
                    $person->update(['archive_id' => $archive->id]);
                }
                $stats['linked']++;
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    /**
     * Egy projekt összes személyét linkelni.
     *
     * @return array{linked: int, created: int, skipped: int, already_linked: int}
     */
    public function linkAllForProject(TabloProject $project, bool $autoCreate = true): array
    {
        $stats = ['linked' => 0, 'created' => 0, 'skipped' => 0, 'already_linked' => 0];

        $persons = $project->persons()->get();

        foreach ($persons as $person) {
            if ($person->archive_id) {
                $stats['already_linked']++;
                continue;
            }

            $person->setRelation('project', $project);

            $archive = $this->findMatchingArchive($person);

            if (!$archive && $autoCreate) {
                $archive = $this->createArchiveFromPerson($person);
                if ($archive) {
                    $stats['created']++;
                }
            }

            if ($archive) {
                $person->update(['archive_id' => $archive->id]);
                $stats['linked']++;
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }
}
