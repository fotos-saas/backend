<?php

namespace App\Http\Controllers\Api;

use App\Events\UserCreatedWithCredentials;
use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreClaimRequest;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClaimController extends Controller
{
    public function store(StoreClaimRequest $request)
    {
        $validated = $request->validated();

        // Find or create user
        $wasRecentlyCreated = false;
        $generatedPassword = Str::random(32);
        $user = User::firstOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'],
                'password' => Hash::make($generatedPassword),
                'role' => User::ROLE_CUSTOMER,
            ]
        );

        if ($user->wasRecentlyCreated) {
            $wasRecentlyCreated = true;
        }

        // Assign photos to user
        Photo::whereIn('id', $validated['photoIds'])
            ->whereNull('assigned_user_id')
            ->update(['assigned_user_id' => $user->id]);

        // Send welcome/magic link email to user
        if ($wasRecentlyCreated) {
            // Send credentials email with password
            event(new UserCreatedWithCredentials($user, $generatedPassword));
        }

        return response()->json([
            'message' => 'Photos claimed successfully. Check your email for the magic link.',
            'claimed_count' => count($validated['photoIds']),
        ], 200);
    }
}
