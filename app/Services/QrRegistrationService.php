<?php

namespace App\Services;

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
        ?int $maxUsages = null,
        ?int $expiresInHours = null
    ): QrRegistrationCode {
        $code = QrRegistrationCode::generateCode();

        $qrCode = QrRegistrationCode::create([
            'tablo_project_id' => $project->id,
            'code' => $code,
            'is_active' => true,
            'expires_at' => $expiresInHours ? now()->addHours($expiresInHours) : null,
            'max_usages' => $maxUsages,
        ]);

        Log::info('[QR] Registration code generated', [
            'project_id' => $project->id,
            'code' => $code,
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
            // Create guest user (like code login)
            $guestEmail = 'tablo-guest-'.$project->id.'-'.Str::random(8).'@internal.local';

            $user = User::create([
                'email' => $guestEmail,
                'name' => $name,
                'password' => null,
            ]);

            // Assign guest role
            $user->assignRole(User::ROLE_GUEST);

            // Create TabloGuestSession with is_coordinator: true
            $guestSession = TabloGuestSession::create([
                'tablo_project_id' => $project->id,
                'session_token' => Str::uuid()->toString(),
                'guest_name' => $name,
                'guest_email' => $email,
                'guest_phone' => $phone,
                'device_identifier' => $userAgent,
                'ip_address' => $ipAddress,
                'is_coordinator' => true,
                'verification_status' => TabloGuestSession::VERIFICATION_VERIFIED,
                'last_activity_at' => now(),
            ]);

            // Create TabloContact and link to project
            // Check if contact with same email already exists for this partner
            $existingContact = TabloContact::where('partner_id', $project->partner_id)
                ->where('email', $email)
                ->first();

            if ($existingContact) {
                // Link existing contact to project if not already linked
                if (! $existingContact->projects()->where('tablo_projects.id', $project->id)->exists()) {
                    $existingContact->projects()->attach($project->id, [
                        'is_primary' => ! $project->contacts()->exists(), // Primary if no other contacts
                    ]);
                }
                $contact = $existingContact;
            } else {
                // Create new contact
                $contact = TabloContact::create([
                    'partner_id' => $project->partner_id,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'note' => 'QR kóddal regisztrált ügyintéző',
                ]);

                // Link to project (primary if no other contacts exist)
                $contact->projects()->attach($project->id, [
                    'is_primary' => ! $project->contacts()->exists(),
                ]);
            }

            // Increment QR code usage and deactivate (single-use)
            $qrCode->incrementUsage();
            $qrCode->deactivate();

            // Create token with metadata
            $token = $this->authService->createTokenWithMetadata(
                user: $user,
                name: 'qr-registration',
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
                    'project_id' => $project->id,
                    'guest_session_id' => $guestSession->id,
                ]
            );

            Log::info('[QR] Customer registered via QR code', [
                'project_id' => $project->id,
                'user_id' => $user->id,
                'guest_session_id' => $guestSession->id,
                'contact_id' => $contact->id,
                'qr_code' => $qrCode->code,
            ]);

            return [
                'user' => $user,
                'session' => $guestSession,
                'token' => $token,
                'project' => $project,
            ];
        });
    }

    /**
     * Get active codes for a project.
     *
     * @return Collection<QrRegistrationCode>
     */
    public function getActiveCodesForProject(TabloProject $project): Collection
    {
        return $project->qrCodes()
            ->valid()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Deactivate a QR code.
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
