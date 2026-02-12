<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ResendVerificationRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class VerificationController extends Controller
{
    /**
     * Verify email with signed URL
     */
    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json([
                'message' => 'Érvénytelen verifikációs link.',
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Az email címed már megerősítve van.',
                'already_verified' => true,
            ]);
        }

        $user->markEmailAsVerified();

        \Log::info('[Auth] Email verified', ['user_id' => $user->id]);

        return response()->json([
            'message' => 'Az email címed sikeresen megerősítve. Most már bejelentkezhetsz.',
        ]);
    }

    /**
     * Resend verification email
     */
    public function resendVerification(ResendVerificationRequest $request)
    {
        $user = User::where('email', $request->validated()['email'])->first();

        if (! $user || $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Ha az email cím létezik és még nincs megerősítve, küldtünk egy új linket.',
            ]);
        }

        $this->sendVerificationEmail($user);

        return response()->json([
            'message' => 'Ha az email cím létezik és még nincs megerősítve, küldtünk egy új linket.',
        ]);
    }

    /**
     * Send verification email to user
     */
    private function sendVerificationEmail(User $user): void
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        try {
            Mail::send('emails.verification', [
                'user' => $user,
                'verificationUrl' => $verificationUrl,
            ], function ($message) use ($user) {
                $message->to($user->email, $user->name)
                    ->subject('Email cím megerősítése - Photo Stack');
            });

            \Log::info('[Auth] Verification email sent', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            \Log::error('[Auth] Failed to send verification email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
