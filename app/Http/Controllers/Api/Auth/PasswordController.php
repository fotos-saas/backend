<?php

namespace App\Http\Controllers\Api\Auth;

use App\Events\PasswordChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\SetPasswordRequest;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Models\EmailEvent;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\EmailService;
use App\Services\EmailVariableService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function __construct(
        private AuthenticationService $authService
    ) {}

    /**
     * Forgot password (send reset email)
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $email = $request->validated()['email'];
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Ha az email cím létezik, küldtünk egy jelszó-visszaállítási linket.',
            ]);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        try {
            Mail::send('emails.password-reset', [
                'token' => $token,
                'email' => $email,
                'user' => $user,
            ], function ($message) use ($email, $user) {
                $message->to($email, $user->name)
                        ->subject('Jelszó visszaállítás - Photo Stack');
            });

            return response()->json([
                'message' => 'Ha az email cím létezik, küldtünk egy jelszó-visszaállítási linket.',
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'message' => 'Ha az email cím létezik, küldtünk egy jelszó-visszaállítási linket.',
            ]);
        }
    }

    /**
     * Reset password with token
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $email = $request->input('email');
        $token = $request->input('token');
        $password = $request->input('password');

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $record || ! Hash::check($token, $record->token)) {
            return response()->json([
                'message' => 'Érvénytelen vagy lejárt visszaállítási link.',
            ], 400);
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return response()->json([
                'message' => 'A visszaállítási link lejárt. Kérj új linket.',
            ], 400);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'Felhasználó nem található.',
            ], 404);
        }

        $user->password = Hash::make($password);
        $user->password_set = true;
        $user->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        $this->authService->clearFailedAttempts($email);
        PasswordChanged::dispatch($user);

        \Log::info('[Auth] Password reset successful', ['user_id' => $user->id]);

        return response()->json([
            'message' => 'A jelszavad sikeresen megváltozott. Most már bejelentkezhetsz.',
        ]);
    }

    /**
     * Set password (for first-time login or password change)
     */
    public function setPassword(SetPasswordRequest $request)
    {
        $validated = $request->validated();

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $user->password = Hash::make($validated['password']);
        $user->password_set = true;
        $user->save();

        PasswordChanged::dispatch($user);

        dispatch(function () use ($user) {
            $emailEvent = EmailEvent::where('event_type', 'password_changed')
                ->where('is_active', true)
                ->first();

            if ($emailEvent && $emailEvent->emailTemplate) {
                $emailService = app(EmailService::class);
                $variableService = app(EmailVariableService::class);

                $variables = $variableService->resolveVariables(user: $user);

                $emailService->sendFromTemplate(
                    template: $emailEvent->emailTemplate,
                    recipientEmail: $user->email,
                    variables: $variables,
                    recipientUser: $user,
                    eventType: 'password_changed'
                );
            }
        })->afterResponse();

        return response()->json([
            'message' => 'Jelszó sikeresen beállítva',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'password_set' => true,
            ],
        ]);
    }

    /**
     * Change password (authenticated user)
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();
        $password = $request->input('password');

        $user->password = Hash::make($password);
        $user->password_set = true;
        $user->save();

        PasswordChanged::dispatch($user);

        \Log::info('[Auth] Password changed', ['user_id' => $user->id]);

        return response()->json([
            'message' => 'A jelszavad sikeresen megváltozott.',
        ]);
    }
}
