<?php

namespace App\Http\Controllers\Api;

use App\Constants\TokenNames;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveShareSelectionRequest;
use App\Http\Requests\Api\SendShareLinkRequest;
use App\Models\Album;
use App\Models\GuestSelection;
use App\Models\GuestShareToken;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShareController extends Controller
{
    /**
     * Send share link (create token)
     */
    public function sendLink(SendShareLinkRequest $request)
    {
        $validated = $request->validated();

        $album = Album::findOrFail($validated['albumId']);

        // Check if album allows guest sharing
        $flags = $album->flags ?? [];
        if (! ($flags['allowGuestShare'] ?? true)) {
            return response()->json([
                'message' => 'Ez az album nem engedélyezi a vendég megosztást',
            ], 403);
        }

        // Generate token
        $token = GuestShareToken::generateToken();

        // Create token record
        $guestToken = GuestShareToken::create([
            'token' => $token,
            'album_id' => $validated['albumId'],
            'email' => $validated['email'],
            'expires_at' => now()->addDays(7),
            'usage_count' => 0,
            'max_usage' => 999,
        ]);

        // TODO: Send email using EmailService
        // $this->emailService->send('guest-share-link', $validated['email'], [
        //     'shareUrl' => $guestToken->share_url,
        // ]);

        return response()->json([
            'success' => true,
            'token' => $guestToken->token,
            'message' => "Meghívó elküldve a(z) {$validated['email']} címre",
            'shareUrl' => $guestToken->share_url,
        ]);
    }

    /**
     * Validate guest token (album or work session)
     */
    public function validateToken(string $token)
    {
        // First, check if it's a WorkSession share token
        $workSession = WorkSession::with(['albums.photos', 'users'])
            ->byShareToken($token)
            ->first();

        if ($workSession) {
            // Create or find guest user by token
            $guestUser = User::firstOrCreate(
                ['guest_token' => $token],
                [
                    'name' => 'Vendég #'.substr(md5($token), 0, 6), // e.g., "Vendég #7f3a2k"
                    'email' => 'guest-'.$token.'@internal.local',
                    'password' => bcrypt(Str::random(32)),
                    'guest_token' => $token,  // IMPORTANT: Must set guest_token in creation data too!
                ]
            );

            // Assign ROLE_GUEST if not already assigned
            if (! $guestUser->hasRole(User::ROLE_GUEST)) {
                $guestUser->assignRole(User::ROLE_GUEST);
            }

            // Generate Sanctum token for authentication
            $sanctumToken = $guestUser->createToken(TokenNames::GUEST_ACCESS)->plainTextToken;

            return response()->json([
                'type' => 'work_session',
                'user' => [
                    'id' => $guestUser->id,
                    'name' => $guestUser->name,
                    'email' => $guestUser->email,
                    'type' => 'guest',
                ],
                'token' => $sanctumToken, // Sanctum token for API authentication
                'workSession' => [
                    'id' => $workSession->id,
                    'name' => $workSession->name,
                    'description' => $workSession->description,
                    'expiresAt' => $workSession->share_expires_at?->toISOString(),
                    'albums' => $workSession->albums->map(fn ($album) => [
                        'id' => $album->id,
                        'title' => $album->title,
                        'photosCount' => $album->photos->count(),
                    ]),
                ],
            ]);
        }

        // Fall back to GuestShareToken (album-based)
        $guestToken = GuestShareToken::with('album')
            ->where('token', $token)
            ->first();

        if (! $guestToken) {
            return response()->json([
                'message' => 'Érvénytelen vagy lejárt token',
            ], 404);
        }

        // Check if token is valid
        if (! $guestToken->isValid()) {
            return response()->json([
                'message' => 'A megosztás link lejárt vagy elérte a maximális használat számot',
            ], 410);
        }

        // Increment usage count
        $guestToken->incrementUsage();

        return response()->json([
            'type' => 'album',
            'id' => $guestToken->id,
            'token' => $guestToken->token,
            'albumId' => $guestToken->album_id,
            'email' => $guestToken->email,
            'expiresAt' => $guestToken->expires_at->toISOString(),
            'usageCount' => $guestToken->usage_count,
            'maxUsage' => $guestToken->max_usage,
            'createdAt' => $guestToken->created_at->toISOString(),
        ]);
    }

    /**
     * Save guest selection
     */
    public function saveSelection(SaveShareSelectionRequest $request, string $token)
    {
        $guestToken = GuestShareToken::where('token', $token)->firstOrFail();

        if (! $guestToken->isValid()) {
            return response()->json([
                'message' => 'A megosztás link lejárt',
            ], 410);
        }

        $validated = $request->validated();

        // Save or update selections
        foreach ($validated['selections'] as $selection) {
            GuestSelection::updateOrCreate(
                [
                    'guest_token_id' => $guestToken->id,
                    'photo_id' => $selection['photoId'],
                ],
                [
                    'selected' => $selection['selected'],
                    'quantity' => $selection['quantity'],
                    'notes' => $selection['notes'] ?? null,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Választás sikeresen mentve',
        ]);
    }
}
