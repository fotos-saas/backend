<?php

namespace App\Services\Subscription;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Partner Registration Service
 *
 * Kezeli a partner regisztrációs folyamatot:
 * - Regisztrációs adatok tárolása cache-ben
 * - Partner és User létrehozása
 */
class PartnerRegistrationService
{
    /**
     * Regisztrációs adatok előkészítése és cache-elése
     *
     * @return string Registration token
     */
    public function prepareRegistration(array $validated): string
    {
        $registrationToken = Str::uuid()->toString();

        // SECURITY: A jelszót hash-elve tároljuk a cache-ben is
        $registrationData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'billing' => $validated['billing'],
            'plan' => $validated['plan'],
            'billing_cycle' => $validated['billing_cycle'],
            'created_at' => now()->toIso8601String(),
        ];

        // Store in cache for 1 hour
        cache()->put(
            "registration:{$registrationToken}",
            $registrationData,
            now()->addHour()
        );

        return $registrationToken;
    }

    /**
     * Regisztrációs adatok lekérése cache-ből
     */
    public function getRegistrationData(string $token): ?array
    {
        return cache()->get("registration:{$token}");
    }

    /**
     * Regisztrációs cache törlése
     */
    public function clearRegistrationCache(string $token): void
    {
        cache()->forget("registration:{$token}");
    }

    /**
     * Partner és User létrehozása
     *
     * @return array ['user' => User, 'partner' => Partner]
     */
    public function createPartnerWithUser(array $registrationData, string $customerId, string $subscriptionId): array
    {
        // Calculate subscription end date based on billing cycle
        $subscriptionEndsAt = $registrationData['billing_cycle'] === 'yearly'
            ? now()->addYear()
            : now()->addMonth();

        return DB::transaction(function () use ($registrationData, $customerId, $subscriptionId, $subscriptionEndsAt) {
            // Create user (a jelszó már hash-elve van a cache-ben)
            $user = User::create([
                'name' => $registrationData['name'],
                'email' => $registrationData['email'],
                'password' => $registrationData['password'],
                'phone' => $registrationData['billing']['phone'] ?? null,
                'email_verified_at' => now(),
                'password_set' => true,
            ]);

            // Assign partner role
            $user->assignRole('partner');

            // Create partner profile with subscription info
            $partner = Partner::create([
                'user_id' => $user->id,
                'company_name' => $registrationData['billing']['company_name'],
                'tax_number' => $registrationData['billing']['tax_number'],
                'billing_country' => $registrationData['billing']['country'],
                'billing_postal_code' => $registrationData['billing']['postal_code'],
                'billing_city' => $registrationData['billing']['city'],
                'billing_address' => $registrationData['billing']['address'],
                'phone' => $registrationData['billing']['phone'],
                'plan' => $registrationData['plan'],
                'billing_cycle' => $registrationData['billing_cycle'],
                'stripe_customer_id' => $customerId,
                'stripe_subscription_id' => $subscriptionId,
                'subscription_status' => 'active',
                'subscription_started_at' => now(),
                'subscription_ends_at' => $subscriptionEndsAt,
            ]);

            return [
                'user' => $user,
                'partner' => $partner,
            ];
        });
    }

    /**
     * Ellenőrzi, hogy az email már regisztrálva van-e
     */
    public function isEmailRegistered(string $email): bool
    {
        return User::where('email', $email)->exists();
    }
}
