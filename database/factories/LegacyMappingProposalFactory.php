<?php

namespace Database\Factories;

use App\Enums\LegacyMappingProposalAction;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMappingProposal;
use App\Models\LegacyRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LegacyMappingProposal> */
class LegacyMappingProposalFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'legacy_mapping_plan_id' => LegacyMappingPlan::factory(),
            'legacy_record_id' => LegacyRecord::factory(),
            'parent_legacy_record_id' => null,
            'dataset_key' => 'business_owners',
            'entity_type' => 'business_owner',
            'target_type' => 'business_owner',
            'target_id' => null,
            'proposed_action' => LegacyMappingProposalAction::Create,
            'status' => LegacyMappingProposalStatus::Ready,
            'identity_fingerprint' => hash('sha256', fake()->uuid()),
            'projection_hash' => hash('sha256', fake()->uuid()),
            'collision_fingerprints' => [],
            'reasons' => [],
            'metadata' => ['fixture' => true],
        ];
    }
}
