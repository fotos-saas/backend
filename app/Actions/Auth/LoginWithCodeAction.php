<?php

namespace App\Actions\Auth;

use App\Constants\TokenNames;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Support\Str;

class LoginWithCodeAction
{
    /**
     * Login 6 jegyű kóddal (WorkSession alapú).
     *
     * @return array{success: bool, data?: array, message?: string, status?: int}
     */
    public function execute(string $code): array
    {
        $workSession = WorkSession::where('digit_code', $code)->first();

        if (!$workSession) {
            return [
                'success' => false,
                'message' => 'Ez a munkamenet már megszűnt vagy lejárt',
                'status' => 401,
            ];
        }

        if (!$workSession->digit_code_enabled) {
            return [
                'success' => false,
                'message' => 'A belépési kód le van tiltva',
                'status' => 401,
            ];
        }

        if ($workSession->digit_code_expires_at && $workSession->digit_code_expires_at->isPast()) {
            return [
                'success' => false,
                'message' => 'A belépési kód lejárt',
                'status' => 401,
            ];
        }

        if ($workSession->status !== 'active') {
            return [
                'success' => false,
                'message' => 'Ez a munkamenet már nem elérhető',
                'status' => 401,
            ];
        }

        $guestUser = User::create([
            'name' => 'Guest-' . $workSession->id . '-' . Str::random(6),
            'email' => null,
            'password' => null,
        ]);

        $guestUser->assignRole(User::ROLE_GUEST);
        $guestUser->refresh();

        $workSession->users()->attach($guestUser->id);

        $tokenResult = $guestUser->createToken(TokenNames::AUTH);
        $tokenResult->accessToken->work_session_id = $workSession->id;
        $tokenResult->accessToken->save();

        return [
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $guestUser->id,
                    'name' => $guestUser->name,
                    'email' => $guestUser->email,
                    'phone' => $guestUser->phone,
                    'address' => $guestUser->address,
                    'type' => 'guest',
                    'workSessionId' => $workSession->id,
                    'workSessionName' => $workSession->name,
                ],
                'token' => $tokenResult->plainTextToken,
            ],
        ];
    }
}
