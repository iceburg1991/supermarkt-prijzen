<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Product test data.
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => fake()->unique()->numerify('prod-####'),
            'supermarket' => 'ah',
            'name' => fake()->words(3, true),
            'quantity' => fake()->randomElement(['500g', '1kg', '250ml', '1L', '6 stuks']),
            'image_url' => fake()->imageUrl(),
            'product_url' => fake()->url(),
        ];
    }

    /**
     * Indicate that the product is from Jumbo.
     */
    public function jumbo(): static
    {
        return $this->state(fn (array $attributes) => [
            'supermarket' => 'jumbo',
        ]);
    }
}
