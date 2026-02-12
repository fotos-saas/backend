<?php

namespace App\Http\Controllers\Api\Partner;

use App\Enums\QrCodeType;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\GenerateQrCodeRequest;
use App\Services\QrRegistrationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerQrController extends Controller
{
    use PartnerAuthTrait;

    public function __construct(
        private QrRegistrationService $qrService
    ) {}

    /**
     * Get all active QR codes for a project.
     */
    public function getQrCodes(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);
        $codes = $this->qrService->getActiveCodesForProject($project);

        return response()->json([
            'qrCodes' => $codes->map(fn ($code) => $this->formatQrCode($code)),
        ]);
    }

    /**
     * Generate new QR code for a project.
     */
    public function generateQrCode(int $projectId, GenerateQrCodeRequest $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $type = QrCodeType::from($request->input('type'));
        $expiresAt = $request->input('expires_at')
            ? Carbon::parse($request->input('expires_at'))
            : null;

        $qrCode = $this->qrService->generateCode(
            project: $project,
            type: $type,
            maxUsages: $request->input('max_usages'),
            expiresInHours: $expiresAt ? (int) now()->diffInHours($expiresAt) : null,
        );

        // Reload with sessions
        $qrCode->load(['registeredSessions' => fn ($q) => $q->select('id', 'qr_registration_code_id', 'guest_name', 'guest_email', 'created_at')->latest()->limit(5)]);

        return response()->json([
            'success' => true,
            'message' => 'Új QR kód sikeresen létrehozva',
            'qrCode' => $this->formatQrCode($qrCode),
        ], 201);
    }

    /**
     * Deactivate a specific QR code.
     */
    public function deactivateQrCode(int $projectId, int $codeId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        if (! $this->qrService->deactivateCodeForProject($project, $codeId)) {
            return response()->json([
                'success' => false,
                'message' => 'QR kód nem található',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'QR kód sikeresen inaktiválva',
        ]);
    }

    /**
     * Pin a QR code (set as featured).
     */
    public function pinQrCode(int $projectId, int $codeId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        if (! $this->qrService->pinCode($project, $codeId)) {
            return response()->json([
                'success' => false,
                'message' => 'QR kód nem található',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'QR kód rögzítve',
        ]);
    }

    private function formatQrCode($code): array
    {
        return [
            'id' => $code->id,
            'code' => $code->code,
            'type' => $code->type->value,
            'typeLabel' => $code->type->label(),
            'isPinned' => $code->is_pinned,
            'usageCount' => $code->usage_count,
            'maxUsages' => $code->max_usages,
            'expiresAt' => $code->expires_at?->toIso8601String(),
            'isValid' => $code->isValid(),
            'registrationUrl' => $code->getRegistrationUrl(),
            'registeredSessions' => $code->registeredSessions->map(fn ($s) => [
                'id' => $s->id,
                'guestName' => $s->guest_name,
                'guestEmail' => $s->guest_email,
                'createdAt' => $s->created_at->toIso8601String(),
            ])->toArray(),
        ];
    }
}
