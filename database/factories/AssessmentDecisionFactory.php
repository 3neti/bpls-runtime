<?php

namespace Database\Factories;

use App\Enums\AssessmentDecisionAction;
use App\Models\Assessment;
use App\Models\AssessmentDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentDecision>
 */
class AssessmentDecisionFactory extends Factory
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
            'decided_by_id' => User::factory(),
            'action' => AssessmentDecisionAction::Approved,
            'decided_at' => now(),
            'reason' => null,
            'assessment_snapshot_hash' => fake()->sha256(),
            'total_amount_cents' => fake()->numberBetween(10_000, 1_000_000),
            'source_snapshot' => [],
        ];
    }
}
