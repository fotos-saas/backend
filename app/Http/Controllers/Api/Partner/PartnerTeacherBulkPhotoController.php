<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Teacher\BulkUploadTeacherPhotosAction;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\BulkPhotoMatchRequest;
use App\Http\Requests\Api\Partner\BulkPhotoUploadRequest;
use App\Models\TeacherArchive;
use App\Services\FileNameMatcherService;
use Illuminate\Http\JsonResponse;

class PartnerTeacherBulkPhotoController extends Controller
{
    use PartnerAuthTrait;

    public function match(BulkPhotoMatchRequest $request, FileNameMatcherService $matcher): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $schoolId = (int) $request->validated('school_id');

        $teachers = TeacherArchive::forPartner($partnerId)
            ->where('school_id', $schoolId)
            ->select('id', 'canonical_name')
            ->get();

        $nameMap = $teachers->pluck('canonical_name', 'id')->toArray();
        $matches = $matcher->matchFilenames($request->validated('filenames'), $nameMap);

        return $this->successResponse($matches, 'Párosítás kész');
    }

    public function upload(BulkPhotoUploadRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $year = (int) $request->validated('year');
        $setActive = (bool) $request->input('set_active', false);
        $assignments = $request->getAssignments();

        // Fájl map: filename => UploadedFile
        $fileMap = [];
        foreach ($request->file('photos', []) as $file) {
            $fileMap[$file->getClientOriginalName()] = $file;
        }

        // Szűrés: csak az adott partner tanárai
        $validTeacherIds = TeacherArchive::forPartner($partnerId)
            ->whereIn('id', array_values($assignments))
            ->pluck('id')
            ->toArray();

        $filteredAssignments = array_filter(
            $assignments,
            fn ($teacherId) => in_array($teacherId, $validTeacherIds)
        );

        $action = new BulkUploadTeacherPhotosAction();
        $result = $action->execute($filteredAssignments, $fileMap, $year, $setActive);

        return $this->successResponse([
            'summary' => [
                'uploaded' => $result['uploaded'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
            ],
            'results' => $result['results'],
        ], 'Tömeges fotó feltöltés kész');
    }
}
