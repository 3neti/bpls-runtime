<?php

namespace Database\Factories;

use App\Enums\RevenueCodeProvisionRowStatus;
use App\Models\RevenueCodeProvision;
use App\Models\RevenueCodeProvisionRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevenueCodeProvisionRow>
 */
class RevenueCodeProvisionRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'revenue_code_provision_id' => RevenueCodeProvision::factory(),
            'sequence' => fake()->unique()->numberBetween(1, 500),
            'code' => fake()->unique()->bothify('MRC-ROW-###'),
            'source_basis_text' => 'Less than Php1,000.00',
            'source_value_text' => '22.66',
            'basis_from_cents' => 0,
            'basis_below_cents' => 100_000,
            'amount_cents' => 2_266,
            'is_ceiling' => false,
            'normalization_status' => RevenueCodeProvisionRowStatus::Exact,
            'metadata' => [],
        ];
    }
}
