<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner\Traits;

use App\Http\Requests\Api\Partner\BulkPhotoMatchRequest;
use App\Http\Requests\Api\Partner\BulkPhotoUploadRequest;
use App\Services\FileNameMatcherService;
use Illuminate\Http\JsonResponse;

trait BulkPhotoTrait
{
    abstract protected function getArchiveModelClass(): string;

    abstract protected function getBulkUploadAction(): object;

    public function match(BulkPhotoMatchRequest $request, FileNameMatcherService $matcher): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $schoolId = (int) $request->validated('school_id');

        $this->validateSchoolBelongsToPartner($partnerId, $schoolId);

        $modelClass = $this->getArchiveModelClass();
        $persons = $modelClass::forPartner($partnerId)
            ->where('school_id', $schoolId)
            ->select('id', 'canonical_name')
            ->get();

        $nameMap = $persons->pluck('canonical_name', 'id')->toArray();
        $matches = $matcher->matchFilenames($request->validated('filenames'), $nameMap);

        return $this->successResponse($matches, 'Párosítás kész');
    }

    public function upload(BulkPhotoUploadRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $schoolId = (int) $request->validated('school_id');
        $year = (int) $request->validated('year');
        $setActive = filter_var($request->validated('set_active'), FILTER_VALIDATE_BOOLEAN);
        $assignments = $this->parseAndValidateAssignments($request->getAssignments());

        $this->validateSchoolBelongsToPartner($partnerId, $schoolId);

        // Fájl map: filename => UploadedFile (basename a path traversal ellen)
        $fileMap = [];
        foreach ($request->file('photos', []) as $file) {
            $fileMap[basename($file->getClientOriginalName())] = $file;
        }

        // Szűrés: csak az adott partner + iskola személyei
        $modelClass = $this->getArchiveModelClass();
        $validIds = $modelClass::forPartner($partnerId)
            ->where('school_id', $schoolId)
            ->whereIn('id', array_values($assignments))
            ->pluck('id')
            ->toArray();

        $filteredAssignments = array_filter(
            $assignments,
            fn ($id) => in_array($id, $validIds, true)
        );

        $result = $this->getBulkUploadAction()->execute($filteredAssignments, $fileMap, $year, $setActive);

        return $this->successResponse([
            'summary' => [
                'uploaded' => $result['uploaded'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
            ],
            'results' => $result['results'],
        ], 'Tömeges fotó feltöltés kész');
    }

    private function validateSchoolBelongsToPartner(int $partnerId, int $schoolId): void
    {
        $exists = \App\Models\TabloSchool::where('id', $schoolId)
            ->where('partner_id', $partnerId)
            ->exists();

        if (!$exists) {
            abort(403, 'Az iskola nem tartozik a partnerhez.');
        }
    }

    /**
     * JSON assignments validálása és intval cast.
     * @return array<string, int>
     */
    private function parseAndValidateAssignments(array $raw): array
    {
        $clean = [];
        foreach ($raw as $filename => $personId) {
            if (!is_string($filename) || !is_numeric($personId)) {
                continue;
            }
            $clean[basename($filename)] = intval($personId);
        }
        return $clean;
    }
}
