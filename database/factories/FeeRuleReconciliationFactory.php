<?php

namespace Database\Factories;

use App\Enums\FeeRuleExecutionStatus;
use App\Models\FeeRule;
use App\Models\FeeRuleReconciliation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeRuleReconciliation>
 */
class FeeRuleReconciliationFactory extends Factory
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
            'version' => 1,
            'legal_authority' => 'Test legal authority',
            'evidence_reference' => 'TEST-EVIDENCE-001',
            'original_text' => 'Exact test ordinance text.',
            'normalized_interpretation' => 'Exact test ordinance text.',
            'decision_authority' => 'Test decision authority',
            'decision_reference' => 'TEST-DECISION-001',
            'effective_from' => now()->startOfYear(),
            'execution_status' => FeeRuleExecutionStatus::Executable,
            'execution_reason' => 'Deterministic test rule accepted for execution.',
            'decided_at' => now(),
        ];
    }
}
