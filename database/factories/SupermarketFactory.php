<?php

namespace Database\Factories;

use App\Models\Supermarket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Supermarket test data.
 *
 * @extends Factory<Supermarket>
 */
class SupermarketFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Supermarket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identifier' => fake()->unique()->lexify('???'),
            'name' => fake()->company(),
            'base_url' => fake()->url(),
            'requires_auth' => fake()->boolean(),
            'enabled' => true,
        ];
    }

    /**
     * Indicate that the supermarket requires authentication.
     */
    public function requiresAuth(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_auth' => true,
        ]);
    }

    /**
     * Indicate that the supermarket is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}
