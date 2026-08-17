<?php

namespace Database\Factories;

use App\Enums\LegacyMappingPlanStatus;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyHistoricalFinancialPreservationPlan;
use App\Models\LegacyImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyHistoricalFinancialPreservationPlan>
 */
class LegacyHistoricalFinancialPreservationPlanFactory extends Factory
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
            'legacy_financial_mapping_plan_id' => LegacyFinancialMappingPlan::factory(),
            'run_reference' => 'historical-preservation-plan-'.fake()->unique()->uuid(),
            'planner_version' => 'bpls.historical-financial-preservation-plan.v1',
            'dependency_snapshot_hash' => hash('sha256', fake()->uuid()),
            'status' => LegacyMappingPlanStatus::Planned,
            'proposal_count' => 0,
            'ready_count' => 0,
            'blocked_count' => 0,
            'started_at' => now(),
            'completed_at' => now(),
            'metadata' => ['fixture' => true, 'future_policy_executable' => false],
        ];
    }
}
