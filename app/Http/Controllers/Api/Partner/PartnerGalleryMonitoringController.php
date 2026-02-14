<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Partner\ExportGalleryMonitoringExcelAction;
use App\Actions\Partner\GenerateGalleryZipAction;
use App\Actions\Partner\GetGalleryMonitoringAction;
use App\Actions\Partner\GetPersonSelectionsAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Gallery\DownloadGalleryZipRequest;
use App\Http\Requests\Gallery\ExportGalleryExcelRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Partner Gallery Monitoring Controller
 *
 * Galéria monitoring: diákok haladásának nyomon követése + export.
 */
class PartnerGalleryMonitoringController extends Controller
{
    use PartnerAuthTrait;

    public function __construct(
        private readonly GetGalleryMonitoringAction $monitoringAction,
        private readonly GetPersonSelectionsAction $selectionsAction,
        private readonly ExportGalleryMonitoringExcelAction $excelAction,
        private readonly GenerateGalleryZipAction $zipAction,
    ) {}

    /**
     * Monitoring adatok lekérdezése (személyek + haladás + összesítő).
     */
    public function getMonitoring(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        if (!$project->tablo_gallery_id) {
            return response()->json([
                'persons' => [],
                'summary' => [
                    'totalPersons' => 0,
                    'opened' => 0,
                    'notOpened' => 0,
                    'finalized' => 0,
                    'inProgress' => 0,
                    'staleCount' => 0,
                ],
            ]);
        }

        $result = $this->monitoringAction->execute($project->id, $project->tablo_gallery_id);

        return response()->json($result);
    }

    /**
     * Excel export a monitoring adatokból.
     */
    public function exportExcel(ExportGalleryExcelRequest $request, int $projectId): BinaryFileResponse|JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        if (!$project->tablo_gallery_id) {
            return response()->json(['message' => 'A projektnek nincs galéria hozzárendelve.'], 422);
        }

        $filter = $request->input('filter', 'all');
        $excelPath = $this->excelAction->execute($project->id, $project->tablo_gallery_id, $filter);

        $filename = "monitoring-{$project->id}-" . now()->format('Y-m-d') . '.xlsx';

        return response()->download($excelPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Egy személy kiválasztásainak részletezése (claimed, retouch, tablo) thumbnail URL-ekkel.
     */
    public function getPersonSelections(int $projectId, int $personId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        if (!$project->tablo_gallery_id) {
            return response()->json([
                'claimed' => [],
                'retouch' => [],
                'tablo' => null,
                'workflowStatus' => null,
                'currentStep' => null,
            ]);
        }

        $result = $this->selectionsAction->execute($project->id, $project->tablo_gallery_id, $personId);

        return response()->json($result);
    }

    /**
     * ZIP letöltés a kiválasztott képekkel.
     */
    public function downloadZip(DownloadGalleryZipRequest $request, int $projectId): BinaryFileResponse|JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        if (!$project->tablo_gallery_id) {
            return response()->json(['message' => 'A projektnek nincs galéria hozzárendelve.'], 422);
        }

        $personIds = $request->has('person_ids')
            ? array_map('intval', $request->input('person_ids'))
            : null;

        $zipContent = $request->input('zip_content', 'all');
        $fileNaming = $request->input('file_naming', 'original');
        $includeExcel = (bool) $request->input('include_excel', false);
        $personType = $request->input('person_type');

        // Ha Excel mellékelés kell, először generáljuk azt
        $excelPath = null;
        if ($includeExcel) {
            $excelPath = $this->excelAction->execute($project->id, $project->tablo_gallery_id);
        }

        $zipPath = $this->zipAction->execute(
            $project,
            $project->tablo_gallery_id,
            $personIds,
            $zipContent,
            $fileNaming,
            $excelPath,
            $personType,
        );

        // Excel temp fájl takarítás
        if ($excelPath && file_exists($excelPath)) {
            unlink($excelPath);
        }

        $filename = "gallery-{$project->id}-" . now()->format('Y-m-d') . '.zip';

        return response()->download($zipPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }
}
