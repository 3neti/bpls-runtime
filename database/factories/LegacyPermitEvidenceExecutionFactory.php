<?php

namespace Database\Factories;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\LegacyPermitEvidenceExecution;
use App\Models\LegacyPermitEvidencePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyPermitEvidenceExecution>
 */
class LegacyPermitEvidenceExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_permit_evidence_plan_id' => LegacyPermitEvidencePlan::factory(),
            'run_reference' => 'permit-evidence-execution-'.fake()->unique()->uuid(),
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
