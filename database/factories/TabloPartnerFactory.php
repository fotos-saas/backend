<?php

namespace Database\Factories;

use App\Models\TabloPartner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TabloPartner>
 */
class TabloPartnerFactory extends Factory
{
    protected $model = TabloPartner::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . fake()->unique()->numerify('###'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+36' . fake()->numerify('##########'),
            'features' => [],
        ];
    }

    /**
     * Configure with client_orders feature enabled.
     */
    public function withClientOrders(): static
    {
        return $this->state(fn (array $attributes) => [
            'features' => ['client_orders' => true],
        ]);
    }
}
