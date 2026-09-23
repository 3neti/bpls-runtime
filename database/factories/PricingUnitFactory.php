<?php

namespace Database\Factories;

use App\Models\PricingUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PricingUnit> */
class PricingUnitFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('unit-########'), 'name' => 'Test unit', 'dimension' => 'count', 'decimal_places' => 0];
    }
}
