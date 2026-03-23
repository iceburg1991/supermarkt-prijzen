<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Category test data.
 *
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supermarket' => 'ah',
            'category_id' => fake()->unique()->numerify('cat-####'),
            'name' => fake()->words(2, true),
            'parent_id' => null,
            'last_scraped_at' => null,
        ];
    }

    /**
     * Indicate that the category is from Jumbo.
     */
    public function jumbo(): static
    {
        return $this->state(fn (array $attributes) => [
            'supermarket' => 'jumbo',
        ]);
    }

    /**
     * Indicate that the category was recently scraped.
     */
    public function recentlyScraped(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_scraped_at' => now(),
        ]);
    }
}
