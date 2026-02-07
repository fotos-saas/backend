<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Services\Partner\PartnerDashboardService;
use App\Services\Partner\PartnerOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Partner Dashboard Controller - Statisztikák és projekt listák.
 *
 * Vékony controller: validáció + service hívás + response.
 * Üzleti logika: PartnerDashboardService, PartnerOrderService
 */
class PartnerDashboardController extends Controller
{
    use PartnerAuthTrait;

    public function __construct(
        private readonly PartnerDashboardService $dashboardService,
        private readonly PartnerOrderService $orderService,
    ) {}

    /**
     * Dashboard statisztikák.
     */
    public function stats(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        return response()->json(
            $this->dashboardService->getStats($partnerId)
        );
    }

    /**
     * Projektek listázása szűréssel és lapozással.
     */
    public function projects(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        return response()->json(
            $this->dashboardService->getProjectsList($partnerId, $request)
        );
    }

    /**
     * Projekt részletei.
     */
    public function projectDetails(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        return response()->json(
            $this->dashboardService->getProjectDetailsData($project)
        );
    }

    /**
     * Projektek autocomplete keresése.
     */
    public function projectsAutocomplete(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $search = $request->input('search');

        return response()->json(
            $this->dashboardService->getAutocompleteResults($partnerId, $search)
        );
    }

    /**
     * Megrendelés adatok lekérése egy projekthez.
     */
    public function getProjectOrderData(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);
        $result = $this->orderService->getOrderData($project);

        if (! $result['hasData']) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Nincs még leadott megrendelés',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ]);
    }

    /**
     * Megrendelőlap PDF generálása és megtekintése.
     */
    public function viewProjectOrderPdf(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);
        $result = $this->orderService->generateOrderPdf($project);

        $statusCode = $result['success'] ? 200 : ($result['message'] === 'Nincs leadott megrendelés ehhez a projekthez' ? 404 : 500);

        return response()->json($result, $statusCode);
    }
}
