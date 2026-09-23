<?php

namespace Database\Factories;

use App\Models\PricingChargeGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PricingChargeGroup> */
class PricingChargeGroupFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('charge-########'), 'name' => 'Test charge'];
    }
}
