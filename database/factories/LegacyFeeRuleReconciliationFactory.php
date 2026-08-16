<?php

namespace Database\Factories;

use App\Enums\LegacyFeeRuleReconciliationStatus;
use App\Models\FeeRule;
use App\Models\LegacyFeeRuleReconciliation;
use App\Models\LegacySource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyFeeRuleReconciliation>
 */
class LegacyFeeRuleReconciliationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_source_id' => LegacySource::factory(), 'source_dataset' => 'fees', 'source_legacy_id' => fake()->unique()->uuid(),
            'fee_rule_id' => FeeRule::factory(), 'status' => LegacyFeeRuleReconciliationStatus::Accepted,
            'decision_authority' => 'Test authority', 'evidence_reference' => 'TEST-FEE-EVIDENCE', 'decided_at' => now(), 'metadata' => ['fixture' => true],
        ];
    }
}
