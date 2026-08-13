<?php

namespace Database\Factories;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Models\FeeRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeRule>
 */
class FeeRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('FEE-####'),
            'name' => fake()->words(3, true),
            'category' => FeeRuleCategory::Fee,
            'scope' => FeeRuleScope::Application,
            'calculation_type' => FeeRuleCalculationType::Fixed,
            'basis' => 'none',
            'amount_cents' => fake()->numberBetween(1_000, 100_000),
            'effective_from' => now()->startOfYear(),
            'is_active' => true,
            'metadata' => [],
        ];
    }
}
