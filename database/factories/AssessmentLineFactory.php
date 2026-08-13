<?php

namespace Database\Factories;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentLine>
 */
class AssessmentLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'code' => fake()->unique()->bothify('LINE-####'),
            'name' => fake()->words(3, true),
            'category' => FeeRuleCategory::Fee,
            'calculation_type' => FeeRuleCalculationType::Fixed,
            'basis' => 'none',
            'basis_amount_cents' => 0,
            'amount_cents' => fake()->numberBetween(1_000, 100_000),
            'rule_snapshot' => [],
        ];
    }
}
