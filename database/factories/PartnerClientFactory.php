<?php

namespace Database\Factories;

use App\Models\PartnerClient;
use App\Models\TabloPartner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PartnerClient>
 */
class PartnerClientFactory extends Factory
{
    protected $model = PartnerClient::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tablo_partner_id' => TabloPartner::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'access_code' => strtoupper(Str::random(8)),
            'access_code_enabled' => true,
            'access_code_expires_at' => now()->addDays(30),
            'is_registered' => false,
            'wants_notifications' => true,
            'allow_registration' => true,
        ];
    }

    /**
     * Configure as registered (requires password login).
     */
    public function registered(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_registered' => true,
            'registered_at' => now(),
            'password' => bcrypt('password'),
        ]);
    }

    /**
     * Configure with disabled access code.
     */
    public function codeDisabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_code_enabled' => false,
        ]);
    }

    /**
     * Configure with expired access code.
     */
    public function codeExpired(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_code_expires_at' => now()->subDay(),
        ]);
    }
}
