<?php

namespace Database\Factories;

use App\Models\NormalizedCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for generating NormalizedCategory test data.
 *
 * @extends Factory<NormalizedCategory>
 */
class NormalizedCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = NormalizedCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'parent_id' => null,
            'description' => fake()->sentence(),
            'keywords' => null,
        ];
    }
}
