<?php

namespace Database\Factories;

use App\Models\Price;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Price test data.
 *
 * @extends Factory<Price>
 */
class PriceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Price::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $priceCents = fake()->numberBetween(100, 1000);

        return [
            'product_id' => 'prod-'.fake()->numberBetween(1000, 9999),
            'supermarket' => 'ah',
            'price_cents' => $priceCents,
            'promo_price_cents' => 0,
            'available' => true,
            'badge' => null,
            'unit_price' => fake()->randomElement(['€2.99/kg', '€1.50/L', null]),
            'scraped_at' => now(),
        ];
    }

    /**
     * Indicate that the price has a promotion.
     */
    public function withPromotion(): static
    {
        return $this->state(function (array $attributes) {
            $promoPriceCents = (int) ($attributes['price_cents'] * 0.8);

            return [
                'promo_price_cents' => $promoPriceCents,
                'badge' => fake()->randomElement(['2+1 gratis', '25% korting', 'Bonus']),
            ];
        });
    }

    /**
     * Indicate that the product is unavailable.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'available' => false,
        ]);
    }

    /**
     * Indicate that the price is from Jumbo.
     */
    public function jumbo(): static
    {
        return $this->state(fn (array $attributes) => [
            'supermarket' => 'jumbo',
        ]);
    }
}
