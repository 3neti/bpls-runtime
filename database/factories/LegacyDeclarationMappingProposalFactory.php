<?php

namespace Database\Factories;

use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyDeclarationMappingPlan;
use App\Models\LegacyDeclarationMappingProposal;
use App\Models\LegacyRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyDeclarationMappingProposal>
 */
class LegacyDeclarationMappingProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_declaration_mapping_plan_id' => LegacyDeclarationMappingPlan::factory(), 'legacy_record_id' => LegacyRecord::factory(),
            'line_index' => 0, 'legacy_line_of_business_reconciliation_id' => null, 'line_of_business_id' => null,
            'status' => LegacyMappingProposalStatus::Blocked, 'projection_hash' => hash('sha256', fake()->uuid()),
            'reasons' => ['fixture'], 'metadata' => ['fixture' => true],
        ];
    }
}
