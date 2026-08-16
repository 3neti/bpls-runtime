<?php

namespace Database\Factories;

use App\Enums\LegacyMappingPlanStatus;
use App\Models\LegacyDeclarationMappingPlan;
use App\Models\LegacyImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyDeclarationMappingPlan>
 */
class LegacyDeclarationMappingPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_import_batch_id' => LegacyImportBatch::factory(), 'run_reference' => 'declaration-plan-'.fake()->unique()->uuid(),
            'planner_version' => 'bpls.declaration-mapping-plan.v1', 'dependency_snapshot_hash' => hash('sha256', fake()->uuid()),
            'status' => LegacyMappingPlanStatus::Planned, 'proposal_count' => 0, 'ready_count' => 0, 'review_count' => 0,
            'blocked_count' => 0, 'started_at' => now(), 'completed_at' => now(), 'metadata' => ['fixture' => true],
        ];
    }
}
