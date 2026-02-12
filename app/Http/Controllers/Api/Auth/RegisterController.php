<?php

namespace App\Http\Controllers\Api\Auth;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QrRegistrationRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use App\Services\QrRegistrationService;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function __construct(
        private QrRegistrationService $qrService
    ) {}

    /**
     * Register new user
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'phone' => $request->input('phone'),
        ]);

        $user->assignRole(User::ROLE_CUSTOMER);

        event(new UserRegistered($user));

        return response()->json([
            'message' => 'Sikeres regisztráció! Most már bejelentkezhetsz.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    /**
     * Validate QR registration code
     */
    public function validateQrCode(string $code)
    {
        $result = $this->qrService->validateCode($code);

        if (! $result['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $result['error'],
            ], 400);
        }

        $project = $result['project'];

        return response()->json([
            'valid' => true,
            'project' => [
                'id' => $project->id,
                'name' => $project->display_name,
                'schoolName' => $project->school?->name,
                'className' => $project->class_name,
                'classYear' => $project->class_year,
            ],
            'type' => $result['type'] ?? 'coordinator',
            'typeLabel' => $result['typeLabel'] ?? 'Kapcsolattartó',
        ]);
    }

    /**
     * Register from QR code
     */
    public function registerFromQr(QrRegistrationRequest $request)
    {
        try {
            $result = $this->qrService->registerFromQr(
                code: $request->input('code'),
                name: $request->input('name'),
                email: $request->input('email'),
                phone: $request->input('phone'),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $user = $result['user'];
            $session = $result['session'];
            $project = $result['project'];

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'type' => 'tablo-guest',
                    'passwordSet' => (bool) $user->password_set,
                ],
                'project' => [
                    'id' => $project->id,
                    'name' => $project->display_name,
                    'schoolName' => $project->school?->name,
                    'className' => $project->class_name,
                    'classYear' => $project->class_year,
                    'samplesCount' => $project->getMedia('samples')->count(),
                    'activePollsCount' => $project->polls()->active()->count(),
                ],
                'token' => $result['token'],
                'tokenType' => 'code',
                'canFinalize' => true,
                'guestSession' => [
                    'sessionToken' => $session->session_token,
                    'guestName' => $session->guest_name,
                    'guestEmail' => $session->guest_email,
                ],
                'registrationType' => $result['registrationType'] ?? 'coordinator',
                'registrationTypeLabel' => $result['registrationTypeLabel'] ?? 'Kapcsolattartó',
            ]);

        } catch (\InvalidArgumentException $e) {
            // Business logic validation error - safe to expose
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'message' => 'Hiba történt a regisztráció során.',
            ], 500);
        }
    }
}
