<?php

declare(strict_types=1);

namespace App\Actions\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

trait BulkUploadsArchivePhotos
{
    /**
     * @param  array<string, int>  $assignments  filename => person_id
     * @param  array<string, UploadedFile>  $fileMap  filename => UploadedFile
     * @return array{uploaded: int, skipped: int, failed: int, results: array}
     */
    protected function executeBulkUpload(
        array $assignments,
        array $fileMap,
        int $year,
        bool $setActive,
        string $modelClass,
        string $entityLabel,
        string $idField,
    ): array {
        $uploaded = 0;
        $skipped = 0;
        $failed = 0;
        $results = [];

        // N+1 fix: összes person betöltése egyetlen query-vel
        $persons = $modelClass::whereIn('id', array_values($assignments))->get()->keyBy('id');

        foreach ($assignments as $filename => $personId) {
            if (!isset($fileMap[$filename])) {
                $skipped++;
                $results[] = ['filename' => $filename, 'status' => 'skipped', 'reason' => 'Fájl nem található'];
                continue;
            }

            $person = $persons->get($personId);
            if (!$person) {
                $skipped++;
                $results[] = ['filename' => $filename, 'status' => 'skipped', 'reason' => "$entityLabel nem található"];
                continue;
            }

            try {
                $this->getUploadAction()->execute($person, $fileMap[$filename], $year, $setActive);
                $uploaded++;
                $results[] = ['filename' => $filename, 'status' => 'success', $idField => $personId];
            } catch (\Throwable $e) {
                $failed++;
                Log::error("Bulk photo upload hiba ($entityLabel)", [
                    'filename' => $filename,
                    $idField => $personId,
                    'error' => $e->getMessage(),
                ]);
                $results[] = ['filename' => $filename, 'status' => 'failed', 'reason' => 'Feltöltési hiba'];
            }
        }

        return compact('uploaded', 'skipped', 'failed', 'results');
    }

    abstract protected function getUploadAction(): object;
}
