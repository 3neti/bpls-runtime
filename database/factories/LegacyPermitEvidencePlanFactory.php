<?php

namespace Database\Factories;

use App\Enums\LegacyMappingPlanStatus;
use App\Models\LegacyImportBatch;
use App\Models\LegacyPermitEvidencePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyPermitEvidencePlan>
 */
class LegacyPermitEvidencePlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_import_batch_id' => LegacyImportBatch::factory(),
            'run_reference' => 'permit-evidence-'.fake()->unique()->uuid(),
            'planner_version' => 'bpls.permit-evidence-plan.v2',
            'dependency_snapshot_hash' => hash('sha256', fake()->uuid()),
            'status' => LegacyMappingPlanStatus::Planned,
            'proposal_count' => 0,
            'ready_count' => 0,
            'review_count' => 0,
            'blocked_count' => 0,
            'started_at' => now(),
            'completed_at' => now(),
            'metadata' => ['fixture' => true],
        ];
    }
}
