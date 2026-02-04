<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Models\QrRegistrationCode;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Partner QR Controller - QR code management for partners.
 *
 * Handles: getQrCode(), generateQrCode(), deactivateQrCode()
 */
class PartnerQrController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Get QR code for a project.
     */
    public function getQrCode(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $qrCode = $project->qrCodes()->active()->first();

        if (!$qrCode) {
            return response()->json([
                'hasQrCode' => false,
                'message' => 'Nincs aktív QR kód ehhez a projekthez',
            ]);
        }

        return response()->json([
            'hasQrCode' => true,
            'qrCode' => [
                'id' => $qrCode->id,
                'code' => $qrCode->code,
                'usageCount' => $qrCode->usage_count,
                'maxUsages' => $qrCode->max_usages,
                'expiresAt' => $qrCode->expires_at?->toIso8601String(),
                'isValid' => $qrCode->isValid(),
                'registrationUrl' => $qrCode->getRegistrationUrl(),
            ],
        ]);
    }

    /**
     * Generate new QR code for a project.
     */
    public function generateQrCode(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        // Deactivate existing QR codes
        $project->qrCodes()->update(['is_active' => false]);

        // Create new QR code
        $expiresAt = $request->input('expires_at')
            ? Carbon::parse($request->input('expires_at'))
            : now()->addMonths(3);

        $qrCode = QrRegistrationCode::create([
            'tablo_project_id' => $project->id,
            'code' => QrRegistrationCode::generateCode(),
            'is_active' => true,
            'expires_at' => $expiresAt,
            'usage_count' => 0,
            'max_usages' => $request->input('max_usages'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Új QR kód sikeresen létrehozva',
            'qrCode' => [
                'id' => $qrCode->id,
                'code' => $qrCode->code,
                'usageCount' => $qrCode->usage_count,
                'maxUsages' => $qrCode->max_usages,
                'expiresAt' => $qrCode->expires_at?->toIso8601String(),
                'isValid' => $qrCode->isValid(),
                'registrationUrl' => $qrCode->getRegistrationUrl(),
            ],
        ], 201);
    }

    /**
     * Deactivate (invalidate) a QR code.
     */
    public function deactivateQrCode(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        // Deactivate all active QR codes for this project
        $updated = $project->qrCodes()->where('is_active', true)->update(['is_active' => false]);

        if ($updated === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs aktív QR kód ehhez a projekthez',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'QR kód sikeresen inaktiválva',
        ]);
    }
}
