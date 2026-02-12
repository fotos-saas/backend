<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Subscription;

class AccountController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('stripe.secret_key'));
    }

    /**
     * Delete account (soft delete with 30-day retention)
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $partner = Partner::where('user_id', $user->id)->first();

        if (!$partner) {
            return response()->json([
                'message' => 'Partner profil nem található.',
            ], 404);
        }

        // Check if already scheduled for deletion
        if ($partner->deletion_scheduled_at) {
            return response()->json([
                'message' => 'A fiók törlése már ütemezve van.',
                'deletion_date' => $partner->deletion_scheduled_at->toDateString(),
            ], 400);
        }

        try {
            DB::transaction(function () use ($user, $partner) {
                // Cancel Stripe subscription immediately if exists
                if ($partner->stripe_subscription_id) {
                    try {
                        $subscription = Subscription::retrieve($partner->stripe_subscription_id);

                        // Only cancel if not already canceled
                        if ($subscription->status !== 'canceled') {
                            Subscription::cancel($partner->stripe_subscription_id);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to cancel Stripe subscription during account deletion', [
                            'partner_id' => $partner->id,
                            'subscription_id' => $partner->stripe_subscription_id,
                            'error' => 'Hiba történt a művelet során.',
                        ]);
                    }
                }

                // Schedule deletion for 30 days from now
                $deletionDate = now()->addDays(30);

                // Update partner status
                $partner->update([
                    'subscription_status' => 'canceled',
                    'deletion_scheduled_at' => $deletionDate,
                ]);

                // Soft delete partner and user
                $partner->delete();
                $user->delete();

                Log::info('Account deletion scheduled', [
                    'user_id' => $user->id,
                    'partner_id' => $partner->id,
                    'deletion_date' => $deletionDate->toDateString(),
                ]);
            });

            return response()->json([
                'message' => 'Fiókod törlése ütemezve. 30 napon belül véglegesen törlődik.',
                'deletion_date' => now()->addDays(30)->toDateString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete account', [
                'user_id' => $user->id,
                'error' => 'Hiba történt a művelet során.',
            ]);

            return response()->json([
                'message' => 'Hiba történt a fiók törlésekor.',
            ], 500);
        }
    }

    /**
     * Cancel scheduled deletion (restore account)
     */
    public function cancelDeletion(Request $request): JsonResponse
    {
        // Get user with trashed to find soft-deleted users
        $userId = $request->user()->id;
        $user = User::withTrashed()->find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található.',
            ], 404);
        }

        $partner = Partner::withTrashed()->where('user_id', $user->id)->first();

        if (!$partner) {
            return response()->json([
                'message' => 'Partner profil nem található.',
            ], 404);
        }

        // Check if deletion is actually scheduled
        if (!$partner->deletion_scheduled_at) {
            return response()->json([
                'message' => 'A fiók nem volt törölve.',
            ], 400);
        }

        try {
            DB::transaction(function () use ($user, $partner) {
                // Restore user and partner
                $user->restore();
                $partner->restore();

                // Clear deletion schedule
                $partner->update([
                    'deletion_scheduled_at' => null,
                    'subscription_status' => 'canceled', // Keep as canceled, they need to resubscribe
                ]);

                Log::info('Account deletion canceled', [
                    'user_id' => $user->id,
                    'partner_id' => $partner->id,
                ]);
            });

            return response()->json([
                'message' => 'Törlés visszavonva. A fiókod újra aktív.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel account deletion', [
                'user_id' => $user->id,
                'error' => 'Hiba történt a művelet során.',
            ]);

            return response()->json([
                'message' => 'Hiba történt a törlés visszavonásakor.',
            ], 500);
        }
    }

    /**
     * Get account status (for checking deletion status)
     */
    public function getStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $partner = Partner::withTrashed()->where('user_id', $user->id)->first();

        if (!$partner) {
            return response()->json([
                'message' => 'Partner profil nem található.',
            ], 404);
        }

        return response()->json([
            'is_deleted' => $partner->trashed(),
            'deletion_scheduled_at' => $partner->deletion_scheduled_at?->toDateString(),
            'days_until_permanent_deletion' => $partner->deletion_scheduled_at
                ? max(0, now()->diffInDays($partner->deletion_scheduled_at, false))
                : null,
        ]);
    }
}
