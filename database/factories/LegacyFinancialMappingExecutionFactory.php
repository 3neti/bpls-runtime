<?php

namespace Database\Factories;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\LegacyFinancialMappingExecution;
use App\Models\LegacyFinancialMappingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyFinancialMappingExecution>
 */
class LegacyFinancialMappingExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_financial_mapping_plan_id' => LegacyFinancialMappingPlan::factory(),
            'run_reference' => fake()->unique()->bothify('financial-execution-####'),
            'selection_hash' => hash('sha256', fake()->uuid()),
            'status' => LegacyMappingExecutionStatus::Executing,
            'started_at' => now(),
            'metadata' => [],
        ];
    }
}
