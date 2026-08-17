<?php

namespace Database\Factories;

use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyHistoricalFinancialPreservationPlan;
use App\Models\LegacyHistoricalFinancialPreservationProposal;
use App\Models\LegacyRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyHistoricalFinancialPreservationProposal>
 */
class LegacyHistoricalFinancialPreservationProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_historical_financial_preservation_plan_id' => LegacyHistoricalFinancialPreservationPlan::factory(),
            'legacy_record_id' => LegacyRecord::factory(),
            'legacy_application_id_mapping_id' => null,
            'status' => LegacyMappingProposalStatus::Blocked,
            'projection_hash' => hash('sha256', fake()->uuid()),
            'reasons' => ['accepted_application_mapping_required'],
            'metadata' => ['fixture' => true, 'future_policy_executable' => false],
        ];
    }
}
