<?php

namespace Database\Factories;

use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyFinancialMappingProposal;
use App\Models\LegacyRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyFinancialMappingProposal>
 */
class LegacyFinancialMappingProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_financial_mapping_plan_id' => LegacyFinancialMappingPlan::factory(), 'legacy_record_id' => LegacyRecord::factory(),
            'source_dataset' => 'payment_schedules', 'kind' => 'payment_schedule', 'item_key' => 'record',
            'legacy_fee_rule_reconciliation_id' => null, 'fee_rule_id' => null, 'status' => LegacyMappingProposalStatus::Blocked,
            'projection_hash' => hash('sha256', fake()->uuid()), 'reasons' => ['fixture'], 'metadata' => ['fixture' => true],
        ];
    }
}
