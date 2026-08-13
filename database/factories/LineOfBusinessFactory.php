<?php

namespace Database\Factories;

use App\Models\LineOfBusiness;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LineOfBusiness>
 */
class LineOfBusinessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('LOB-####'),
            'name' => fake()->words(3, true),
            'major_category' => fake()->randomElement(['Retail', 'Services', 'Manufacturing']),
            'is_active' => true,
            'metadata' => [],
        ];
    }
}
