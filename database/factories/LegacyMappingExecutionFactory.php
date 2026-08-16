<?php

namespace Database\Factories;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\LegacyMappingExecution;
use App\Models\LegacyMappingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyMappingExecution>
 */
class LegacyMappingExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_mapping_plan_id' => LegacyMappingPlan::factory(),
            'run_reference' => 'registry-execution-'.fake()->unique()->uuid(),
            'selection_hash' => hash('sha256', fake()->uuid()),
            'status' => LegacyMappingExecutionStatus::Completed,
            'selected_count' => 0,
            'created_count' => 0,
            'linked_count' => 0,
            'reused_count' => 0,
            'mapping_count' => 0,
            'started_at' => now(),
            'completed_at' => now(),
            'metadata' => ['fixture' => true],
        ];
    }
}
