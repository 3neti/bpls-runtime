<?php

namespace Database\Factories;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\LegacyApplicationMappingExecution;
use App\Models\LegacyApplicationMappingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyApplicationMappingExecution>
 */
class LegacyApplicationMappingExecutionFactory extends Factory
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
            'run_reference' => fake()->unique()->regexify('application-execution-[A-Za-z0-9]{8}'),
            'selection_hash' => hash('sha256', fake()->uuid()),
            'status' => LegacyMappingExecutionStatus::Executing,
            'started_at' => now(),
            'metadata' => [],
        ];
    }
}
