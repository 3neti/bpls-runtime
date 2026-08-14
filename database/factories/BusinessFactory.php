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
            'ownership_type' => 'sole-proprietorship',
            'organization_name' => null,
            'occupancy' => fake()->randomElement(['owned', 'rented']),
            'building_name' => fake()->streetName().' Building',
            'property_index_number' => fake()->optional()->numerify('PIN-####-####'),
            'business_area_square_meters' => fake()->randomFloat(2, 20, 500),
            'male_employee_count' => fake()->numberBetween(1, 10),
            'female_employee_count' => fake()->numberBetween(0, 10),
            'contact_number' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'established_on' => fake()->dateTimeBetween('-20 years', '-1 year'),
            'started_on' => fake()->dateTimeBetween('-10 years', '-1 month'),
            'registered_on' => fake()->dateTimeBetween('-10 years', '-1 month'),
            'metadata' => [],
        ];
    }
}
