<?php

namespace Database\Factories;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\LegacyHistoricalFinancialPreservationExecution;
use App\Models\LegacyHistoricalFinancialPreservationPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyHistoricalFinancialPreservationExecution>
 */
class LegacyHistoricalFinancialPreservationExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_historical_financial_preservation_plan_id' => LegacyHistoricalFinancialPreservationPlan::factory(),
            'run_reference' => 'historical-preservation-execution-'.fake()->unique()->uuid(),
            'selection_hash' => hash('sha256', fake()->uuid()),
            'status' => LegacyMappingExecutionStatus::Completed,
            'selected_count' => 0,
            'created_count' => 0,
            'reused_count' => 0,
            'started_at' => now(),
            'completed_at' => now(),
            'metadata' => ['fixture' => true, 'future_policy_executable' => false],
        ];
    }
}
