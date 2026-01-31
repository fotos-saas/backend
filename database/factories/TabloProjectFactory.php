<?php

namespace Database\Factories;

use App\Enums\TabloProjectStatus;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TabloProject>
 */
class TabloProjectFactory extends Factory
{
    protected $model = TabloProject::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_id' => TabloPartner::factory(),
            'school_id' => null,
            'name' => fake()->company().' - '.fake()->randomElement(['1.a', '2.b', '3.c', '4.d']),
            'class_name' => fake()->randomElement(['1.a', '2.b', '3.c', '4.d', '5.e']),
            'class_year' => (string) fake()->year(),
            'status' => TabloProjectStatus::NotStarted,
            'access_code' => strtoupper(fake()->bothify('???###')), // 6 karakter (3 betű + 3 szám)
            'access_code_enabled' => true,
            'access_code_expires_at' => now()->addMonths(6),
            'share_token_enabled' => true,
            'share_token_expires_at' => now()->addMonths(6),
            'photo_date' => fake()->dateTimeBetween('now', '+3 months'),
            'deadline' => fake()->dateTimeBetween('+3 months', '+6 months'),
            'data' => [],
        ];
    }

    /**
     * Indicate that the project has expired access code
     */
    public function expiredAccessCode(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_code_expires_at' => now()->subDays(1),
        ]);
    }

    /**
     * Indicate that the project has disabled access code
     */
    public function disabledAccessCode(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_code_enabled' => false,
        ]);
    }

    /**
     * Indicate that the project is waiting for response
     */
    public function waitingForResponse(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TabloProjectStatus::WaitingForResponse,
        ]);
    }

    /**
     * Indicate that the project is done
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TabloProjectStatus::Done,
        ]);
    }
}
