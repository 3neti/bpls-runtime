<?php

namespace Database\Factories;

use App\Models\PricingChargeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PricingChargeItem> */
class PricingChargeItemFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('charge-########'), 'name' => 'Test charge'];
    }
}
