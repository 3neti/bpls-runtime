<?php

namespace Database\Factories;

use App\Enums\LegacyMappingPlanStatus;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LegacyMappingPlan> */
class LegacyMappingPlanFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'legacy_import_batch_id' => LegacyImportBatch::factory(),
            'run_reference' => 'registry-plan-'.fake()->unique()->uuid(),
            'planner_version' => 'bpls.registry-mapping-plan.v1',
            'registry_snapshot_hash' => hash('sha256', fake()->uuid()),
            'status' => LegacyMappingPlanStatus::Planned,
            'owner_proposal_count' => 0,
            'business_proposal_count' => 0,
            'ready_count' => 0,
            'review_count' => 0,
            'blocked_count' => 0,
            'exact_link_count' => 0,
            'started_at' => now(),
            'completed_at' => now(),
            'metadata' => ['fixture' => true],
        ];
    }
}
