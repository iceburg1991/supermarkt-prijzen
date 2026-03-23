<?php

namespace Database\Factories;

use App\Models\ScrapeRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating ScrapeRun test data.
 *
 * @extends Factory<ScrapeRun>
 */
class ScrapeRunFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ScrapeRun::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supermarket' => 'ah',
            'started_at' => now(),
            'completed_at' => null,
            'product_count' => 0,
            'status' => 'running',
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the scrape run is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
            'product_count' => fake()->numberBetween(50, 500),
        ]);
    }

    /**
     * Indicate that the scrape run failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the scrape run is from Jumbo.
     */
    public function jumbo(): static
    {
        return $this->state(fn (array $attributes) => [
            'supermarket' => 'jumbo',
        ]);
    }
}
