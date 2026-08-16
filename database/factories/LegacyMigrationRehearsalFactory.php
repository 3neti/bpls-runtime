<?php

namespace Database\Factories;

use App\Enums\LegacyMigrationRehearsalStatus;
use App\Models\LegacyApplicationMappingExecution;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingExecution;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMigrationReadinessAssessment;
use App\Models\LegacyMigrationRehearsal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyMigrationRehearsal>
 */
class LegacyMigrationRehearsalFactory extends Factory
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
            'legacy_mapping_execution_id' => function (array $attributes): int {
                $plan = LegacyMappingPlan::factory()->create(['legacy_import_batch_id' => $attributes['legacy_import_batch_id']]);

                return LegacyMappingExecution::factory()->create(['legacy_mapping_plan_id' => $plan->id])->id;
            },
            'legacy_application_mapping_execution_id' => function (array $attributes): int {
                $plan = LegacyApplicationMappingPlan::factory()->create(['legacy_import_batch_id' => $attributes['legacy_import_batch_id']]);

                return LegacyApplicationMappingExecution::factory()->create(['legacy_application_mapping_plan_id' => $plan->id])->id;
            },
            'legacy_declaration_mapping_execution_id' => null,
            'legacy_financial_mapping_execution_id' => null,
            'legacy_permit_evidence_execution_id' => null,
            'legacy_migration_readiness_assessment_id' => fn (array $attributes): int => LegacyMigrationReadinessAssessment::factory()->create(['legacy_import_batch_id' => $attributes['legacy_import_batch_id']])->id,
            'run_reference' => 'migration-rehearsal-'.fake()->unique()->uuid(),
            'verifier_version' => 'bpls.legacy-migration-rehearsal.v1',
            'selection_hash' => hash('sha256', fake()->uuid()),
            'dependency_snapshot_hash' => hash('sha256', fake()->uuid()),
            'status' => LegacyMigrationRehearsalStatus::Verified,
            'check_count' => 0,
            'passed_count' => 0,
            'blocked_count' => 0,
            'checks' => [],
            'started_at' => now(),
            'completed_at' => now(),
            'metadata' => ['fixture' => true],
        ];
    }
}
