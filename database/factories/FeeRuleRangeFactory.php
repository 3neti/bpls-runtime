<?php

namespace Database\Factories;

use App\Models\FeeRule;
use App\Models\FeeRuleRange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeRuleRange>
 */
class FeeRuleRangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fee_rule_id' => FeeRule::factory(),
            'min_basis_cents' => 0,
            'max_basis_cents' => 10_000_000,
            'amount_cents' => fake()->numberBetween(1_000, 100_000),
        ];
    }
}
