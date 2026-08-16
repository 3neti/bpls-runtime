<?php

namespace Database\Factories;

use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyPermitEvidencePlan;
use App\Models\LegacyPermitEvidenceProposal;
use App\Models\LegacyRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyPermitEvidenceProposal>
 */
class LegacyPermitEvidenceProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_permit_evidence_plan_id' => LegacyPermitEvidencePlan::factory(),
            'legacy_record_id' => LegacyRecord::factory(),
            'legacy_clearance_type_reconciliation_id' => null,
            'legacy_document_object_reconciliation_id' => null,
            'source_dataset' => 'permit_clearances',
            'kind' => 'clearance',
            'item_key' => 'record',
            'status' => LegacyMappingProposalStatus::Blocked,
            'projection_hash' => hash('sha256', fake()->uuid()),
            'reasons' => ['fixture'],
            'metadata' => ['fixture' => true],
        ];
    }
}
