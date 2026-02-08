<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Partner\GetGalleryMonitoringAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use Illuminate\Http\JsonResponse;

/**
 * Partner Gallery Monitoring Controller
 *
 * Galéria monitoring: diákok haladásának nyomon követése.
 */
class PartnerGalleryMonitoringController extends Controller
{
    use PartnerAuthTrait;

    public function __construct(
        private readonly GetGalleryMonitoringAction $monitoringAction,
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
}
