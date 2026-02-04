<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailEvent;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\EmailService;
use App\Services\EmailVariableService;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;

class MagicLinkController extends Controller
{
    public function __construct(
        private MagicLinkService $magicLinkService
    ) {}

    /**
     * Request magic link via email
     */
    public function requestMagicLink(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'work_session_code' => ['nullable', 'string', 'size:6'],
            'include_code' => ['nullable', 'boolean'],
        ]);

        $email = $request->input('email');
        $workSessionCode = $request->input('work_session_code');
        $includeCode = $request->input('include_code', false);

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Ha az email cím létezik, küldtünk egy belépési linket.',
                'success' => true,
            ]);
        }

        $workSession = null;
        if ($workSessionCode) {
            $workSession = WorkSession::byDigitCode($workSessionCode)->first();

            if (!$workSession || !$workSession->isDigitCodeValid()) {
                return response()->json([
                    'message' => 'Érvénytelen munkamenet kód.',
                    'success' => false,
                ], 422);
            }
        }

        try {
            if ($workSession) {
                $token = $this->magicLinkService->generateForWorkSession($user->id, $workSession->id);
            } else {
                $token = $this->magicLinkService->generate($user->id);
            }

            $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
            $authData = [
                'magic_link' => $frontendUrl . '/auth/magic/' . $token,
                'include_code' => $includeCode && $workSession && $workSession->digit_code,
                'digit_code' => $workSession?->digit_code ?? '',
            ];

            $emailService = app(EmailService::class);
            $emailVariableService = app(EmailVariableService::class);

            $emailEvent = EmailEvent::where('key', 'user_magic_login')
                ->where('is_active', true)
                ->first();

            if (!$emailEvent || !$emailEvent->emailTemplate) {
                \Log::error('Magic login email event or template not found');
                return response()->json([
                    'message' => 'Email sablon nincs konfigurálva.',
                    'success' => false,
                ], 500);
            }

            $variables = $emailVariableService->resolveVariables(
                user: $user,
                album: $workSession?->album,
                workSession: $workSession,
                authData: $authData
            );

            $emailService->send(
                emailEvent: $emailEvent,
                recipientEmail: $user->email,
                recipientName: $user->name,
                variables: $variables
            );

            return response()->json([
                'message' => 'A belépési linket elküldtük az email címedre.',
                'success' => true,
            ]);

        } catch (\Exception $e) {
            \Log::error('Magic link request failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Hiba történt az email küldése során.',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Validate magic link token (without consuming it)
     */
    public function validateMagicToken(string $token)
    {
        $magicToken = $this->magicLinkService->validateToken($token);

        if (! $magicToken) {
            return response()->json([
                'message' => 'Érvénytelen vagy lejárt magic link',
                'valid' => false,
            ], 401);
        }

        $user = $magicToken->user;
        $workSession = $magicToken->workSession;

        return response()->json([
            'valid' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'first_login' => $user->isFirstLogin(),
                'password_set' => $user->hasSetPassword(),
            ],
            'work_session' => $workSession ? [
                'id' => $workSession->id,
                'name' => $workSession->name,
            ] : null,
        ]);
    }

    /**
     * Send bulk work session invites to multiple email addresses
     */
    public function bulkWorkSessionInvite(Request $request)
    {
        $validated = $request->validate([
            'emails' => ['required', 'array', 'max:30'],
            'emails.*' => ['required', 'email'],
        ]);

        $emails = $validated['emails'];
        $sent = [];
        $failed = [];

        $workSession = $request->user()->workSessions()
            ->where('work_sessions.status', 'active')
            ->latest('work_sessions.created_at')
            ->first();

        if (!$workSession) {
            return response()->json([
                'message' => 'Nincs aktív munkameneted. Először hozz létre egy munkamenetet!',
                'sent' => [],
                'failed' => $emails,
                'total' => count($emails),
            ], 400);
        }

        $emailService = app(EmailService::class);
        $emailVariableService = app(EmailVariableService::class);

        $emailEvent = EmailEvent::where('event_type', 'work_session_invite')
            ->where('is_active', true)
            ->first();

        if (!$emailEvent || !$emailEvent->emailTemplate) {
            return response()->json([
                'message' => 'Email sablon nincs konfigurálva.',
                'sent' => [],
                'failed' => $emails,
                'total' => count($emails),
            ], 500);
        }

        $delay = 0;

        foreach ($emails as $email) {
            $user = User::where('email', $email)
                ->whereNotNull('email')
                ->first();

            if (!$user) {
                try {
                    $user = User::create([
                        'email' => $email,
                        'name' => explode('@', $email)[0],
                        'password' => null,
                        'password_set' => false,
                    ]);

                    $user->assignRole('user');
                } catch (\Exception $e) {
                    logger()->error('Failed to create user for work session invite', [
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                    $failed[] = $email;
                    continue;
                }
            }

            try {
                $variables = $emailVariableService->resolveVariables(
                    user: $user,
                    album: $workSession->album,
                    workSession: $workSession,
                    authData: [
                        'digit_code' => $workSession->digit_code,
                        'work_session_name' => $workSession->album->title ?? 'Munkamenet',
                    ]
                );

                \App\Jobs\SendMagicLinkEmailJob::dispatch(
                    $user,
                    $emailEvent->emailTemplate,
                    $variables,
                    'work_session_invite'
                )->delay(now()->addSeconds($delay));

                $sent[] = $email;
                $delay += 30;
            } catch (\Exception $e) {
                logger()->error('Failed to dispatch work session invite job', [
                    'email' => $email,
                    'work_session_id' => $workSession->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $failed[] = $email;
            }
        }

        return response()->json([
            'message' => 'Meghívók elküldve',
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($emails),
            'work_session' => [
                'id' => $workSession->id,
                'name' => $workSession->album->title ?? 'Munkamenet',
                'digit_code' => $workSession->digit_code,
            ],
        ]);
    }
}
