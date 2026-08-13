<?php

namespace Database\Factories;

use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermitApplicationLine>
 */
class PermitApplicationLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'permit_application_id' => PermitApplication::factory(),
            'line_of_business_id' => LineOfBusiness::factory(),
            'declared_gross_sales_cents' => fake()->numberBetween(100_000, 50_000_000),
            'capital_investment_cents' => fake()->numberBetween(100_000, 10_000_000),
            'quantity' => fake()->numberBetween(1, 5),
            'metadata' => [],
        ];
    }
}
