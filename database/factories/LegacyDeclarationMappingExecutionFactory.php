<?php

namespace Database\Factories;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\LegacyDeclarationMappingExecution;
use App\Models\LegacyDeclarationMappingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyDeclarationMappingExecution>
 */
class LegacyDeclarationMappingExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_declaration_mapping_plan_id' => LegacyDeclarationMappingPlan::factory(),
            'run_reference' => fake()->unique()->regexify('declaration-execution-[A-Za-z0-9]{8}'),
            'selection_hash' => hash('sha256', fake()->uuid()),
            'status' => LegacyMappingExecutionStatus::Executing,
            'started_at' => now(),
            'metadata' => [],
        ];
    }
}
