<?php

namespace Database\Factories;

use App\Models\WorkSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkSession>
 */
class WorkSessionFactory extends Factory
{
    protected $model = WorkSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'digit_code' => (string) fake()->unique()->numberBetween(100000, 999999),
            'digit_code_enabled' => true,
            'digit_code_expires_at' => now()->addDays(30),
            'share_enabled' => true,
            'share_token' => Str::random(64),
            'share_expires_at' => now()->addDays(30),
            'allow_invitations' => true,
            'status' => 'active',
            'is_tablo_mode' => false,
        ];
    }

    /**
     * Configure as disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'digit_code_enabled' => false,
        ]);
    }

    /**
     * Configure as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'digit_code_expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Configure as closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
        ]);
    }
}
