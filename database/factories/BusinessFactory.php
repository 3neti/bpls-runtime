<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_owner_id' => BusinessOwner::factory(),
            'name' => fake()->company(),
            'trade_name' => fake()->companySuffix(),
            'registration_number' => fake()->unique()->numerify('BN-#######'),
            'address' => fake()->streetAddress(),
            'barangay' => fake()->randomElement(['Poblacion', 'Taway', 'Veterans Village']),
            'metadata' => [],
        ];
    }
}
