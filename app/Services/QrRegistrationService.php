<?php

namespace App\Services;

use App\Constants\TokenNames;
use App\Enums\QrCodeType;
use App\Models\QrRegistrationCode;
use App\Models\TabloContact;
use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * QR Registration Service
 *
 * Handles QR code-based guest registration for tablo projects.
 * Allows customers to self-register by scanning a QR code.
 */
class QrRegistrationService
{
    public function __construct(
        private AuthenticationService $authService
    ) {}

    /**
     * Generate a new QR registration code for a project.
     *
     * @param  int|null  $maxUsages  Maximum number of times code can be used (null = unlimited)
     * @param  int|null  $expiresInHours  Hours until expiration (null = never)
     */
    public function generateCode(
        TabloProject $project,
        QrCodeType $type = QrCodeType::Coordinator,
        ?int $maxUsages = null,
        ?int $expiresInHours = null
    ): QrRegistrationCode {
        // Deactivate only active codes of the SAME type
        $project->qrCodes()
            ->where('is_active', true)
            ->where('type', $type->value)
            ->update(['is_active' => false]);

        $code = QrRegistrationCode::generateCode();

        // Auto-pin if no other pinned code exists for this project
        $hasPinned = $project->qrCodes()->where('is_pinned', true)->where('is_active', true)->exists();

        // Multi-use types default to unlimited usages
        if ($type->isMultiUse() && $maxUsages === null) {
            $maxUsages = null; // unlimited
        }

        $qrCode = QrRegistrationCode::create([
            'tablo_project_id' => $project->id,
            'code' => $code,
            'type' => $type,
            'is_active' => true,
            'expires_at' => $expiresInHours ? now()->addHours($expiresInHours) : null,
            'max_usages' => $maxUsages,
            'is_pinned' => ! $hasPinned,
        ]);

        Log::info('[QR] Registration code generated', [
            'project_id' => $project->id,
            'code' => $code,
            'type' => $type->value,
            'max_usages' => $maxUsages,
            'expires_at' => $qrCode->expires_at,
        ]);

        return $qrCode;
    }

    /**
     * Validate a QR registration code.
     *
     * @return array{valid: bool, project: ?TabloProject, error: ?string}
     */
    public function validateCode(string $code): array
    {
        $qrCode = QrRegistrationCode::findValidCode($code);

        if (! $qrCode) {
            return [
                'valid' => false,
                'project' => null,
                'error' => 'Érvénytelen vagy lejárt regisztrációs kód',
            ];
        }

        $project = $qrCode->project;

        if (! $project) {
            return [
                'valid' => false,
                'project' => null,
                'error' => 'A projekt nem található',
            ];
        }

        return [
            'valid' => true,
            'project' => $project,
            'qr_code' => $qrCode,
            'type' => $qrCode->type->value,
            'typeLabel' => $qrCode->type->label(),
            'error' => null,
        ];
    }

    /**
     * Register a customer from QR code.
     * Creates a guest user and TabloGuestSession with is_coordinator: true.
     *
     * @return array{user: User, session: TabloGuestSession, token: string}
     */
    public function registerFromQr(
        string $code,
        string $name,
        string $email,
        ?string $phone,
        string $ipAddress,
        ?string $userAgent = null
    ): array {
        $validation = $this->validateCode($code);

        if (! $validation['valid']) {
            throw new \InvalidArgumentException($validation['error']);
        }

        $project = $validation['project'];
        $qrCode = $validation['qr_code'];

        // Check if email already exists for this project
        $existingSession = TabloGuestSession::where('tablo_project_id', $project->id)
            ->where('guest_email', $email)
            ->first();

        if ($existingSession) {
            throw new \InvalidArgumentException(
                'Ez az email cím már regisztrálva van erre a tablóra. Kérlek jelentkezz be a meglévő fiókoddal.'
            );
        }

        return DB::transaction(function () use ($project, $qrCode, $name, $email, $phone, $ipAddress, $userAgent) {
            $type = $qrCode->type;

            // Create guest user (like code login)
            $guestEmail = 'tablo-guest-'.$project->id.'-'.Str::random(8).'@internal.local';

            $user = User::create([
                'email' => $guestEmail,
                'name' => $name,
                'password' => null,
            ]);

            // Assign guest role
            $user->assignRole(User::ROLE_GUEST);

            // Create TabloGuestSession - flags based on QR code type
            $guestSession = TabloGuestSession::create([
                'tablo_project_id' => $project->id,
                'session_token' => Str::uuid()->toString(),
                'guest_name' => $name,
                'guest_email' => $email,
                'guest_phone' => $phone,
                'device_identifier' => $userAgent,
                'ip_address' => $ipAddress,
                'is_coordinator' => $type->isCoordinator(),
                'is_extra' => $type->isExtra(),
                'qr_registration_code_id' => $qrCode->id,
                'registration_type' => $type->value,
                'verification_status' => TabloGuestSession::VERIFICATION_VERIFIED,
                'last_activity_at' => now(),
            ]);

            // Create TabloContact only if the type requires it
            $contact = null;
            if ($type->shouldCreateContact()) {
                $existingContact = TabloContact::where('partner_id', $project->partner_id)
                    ->where('email', $email)
                    ->first();

                if ($existingContact) {
                    if (! $existingContact->projects()->where('tablo_projects.id', $project->id)->exists()) {
                        $existingContact->projects()->attach($project->id, [
                            'is_primary' => ! $project->contacts()->exists(),
                        ]);
                    }
                    $contact = $existingContact;
                } else {
                    $contact = TabloContact::create([
                        'partner_id' => $project->partner_id,
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'note' => $type->contactNote(),
                    ]);

                    $contact->projects()->attach($project->id, [
                        'is_primary' => ! $project->contacts()->exists(),
                    ]);
                }
            }

            // Increment usage count
            $qrCode->incrementUsage();

            // Single-use types: deactivate after use
            if (! $type->isMultiUse()) {
                $qrCode->deactivate();
            }

            // Create token with metadata
            $token = $this->authService->createTokenWithMetadata(
                user: $user,
                name: TokenNames::QR_REGISTRATION,
                loginMethod: 'qr_registration',
                ipAddress: $ipAddress,
                deviceName: $this->parseDeviceName($userAgent),
                tabloProjectId: $project->id
            );

            // Log the registration
            $this->authService->logLoginAttempt(
                email: $email,
                method: 'qr_registration',
                success: true,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                user: $user,
                metadata: [
                    'qr_code' => $qrCode->code,
                    'qr_type' => $type->value,
                    'project_id' => $project->id,
                    'guest_session_id' => $guestSession->id,
                ]
            );

            Log::info('[QR] Customer registered via QR code', [
                'project_id' => $project->id,
                'user_id' => $user->id,
                'guest_session_id' => $guestSession->id,
                'contact_id' => $contact?->id,
                'qr_code' => $qrCode->code,
                'qr_type' => $type->value,
            ]);

            return [
                'user' => $user,
                'session' => $guestSession,
                'token' => $token,
                'project' => $project,
                'registrationType' => $type->value,
                'registrationTypeLabel' => $type->label(),
            ];
        });
    }

    /**
     * Get all active codes for a project (with registered sessions).
     *
     * @return Collection<QrRegistrationCode>
     */
    public function getActiveCodesForProject(TabloProject $project): Collection
    {
        return $project->qrCodes()
            ->where('is_active', true)
            ->with(['registeredSessions' => fn ($q) => $q->select('id', 'qr_registration_code_id', 'guest_name', 'guest_email', 'created_at')->latest()->limit(5)])
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Deactivate a QR code by ID (for a specific project).
     */
    public function deactivateCodeForProject(TabloProject $project, int $codeId): bool
    {
        $qrCode = $project->qrCodes()->find($codeId);

        if (! $qrCode) {
            return false;
        }

        $qrCode->deactivate();

        Log::info('[QR] Registration code deactivated', [
            'id' => $codeId,
            'code' => $qrCode->code,
            'project_id' => $project->id,
        ]);

        return true;
    }

    /**
     * Pin a QR code (unpin all others for the project).
     */
    public function pinCode(TabloProject $project, int $codeId): bool
    {
        $qrCode = $project->qrCodes()->where('is_active', true)->find($codeId);

        if (! $qrCode) {
            return false;
        }

        // Unpin all others
        $project->qrCodes()->where('id', '!=', $codeId)->update(['is_pinned' => false]);

        // Pin this one
        $qrCode->update(['is_pinned' => true]);

        return true;
    }

    /**
     * Deactivate a QR code by code string.
     */
    public function deactivateCode(string $code): bool
    {
        $qrCode = QrRegistrationCode::where('code', strtoupper($code))->first();

        if (! $qrCode) {
            return false;
        }

        $qrCode->deactivate();

        Log::info('[QR] Registration code deactivated', [
            'code' => $code,
            'project_id' => $qrCode->tablo_project_id,
        ]);

        return true;
    }

    /**
     * Deactivate a QR code by ID.
     */
    public function deactivateCodeById(int $id): bool
    {
        $qrCode = QrRegistrationCode::find($id);

        if (! $qrCode) {
            return false;
        }

        $qrCode->deactivate();

        Log::info('[QR] Registration code deactivated', [
            'id' => $id,
            'code' => $qrCode->code,
            'project_id' => $qrCode->tablo_project_id,
        ]);

        return true;
    }

    /**
     * Parse device name from user agent.
     */
    private function parseDeviceName(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        // Simple parsing - extract browser and OS
        $browser = 'Unknown';
        $os = 'Unknown';

        if (str_contains($userAgent, 'Chrome')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Safari')) {
            $browser = 'Safari';
        } elseif (str_contains($userAgent, 'Edge')) {
            $browser = 'Edge';
        }

        if (str_contains($userAgent, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($userAgent, 'Mac')) {
            $os = 'Mac';
        } elseif (str_contains($userAgent, 'Linux')) {
            $os = 'Linux';
        } elseif (str_contains($userAgent, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            $os = 'iOS';
        }

        return "{$browser} on {$os}";
    }
}
