<?php

namespace Database\Factories;

use App\Enums\LegacyMappingProposalAction;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyApplicationMappingProposal;
use App\Models\LegacyRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyApplicationMappingProposal>
 */
class LegacyApplicationMappingProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_application_mapping_plan_id' => LegacyApplicationMappingPlan::factory(),
            'legacy_record_id' => LegacyRecord::factory(),
            'owner_mapping_id' => null,
            'business_mapping_id' => null,
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
