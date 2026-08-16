<?php

namespace Database\Factories;

use App\Enums\LegacyMigrationReadinessStatus;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMigrationReadinessAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyMigrationReadinessAssessment>
 */
class LegacyMigrationReadinessAssessmentFactory extends Factory
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
            'run_reference' => 'migration-readiness-'.fake()->unique()->uuid(),
            'assessor_version' => 'bpls.legacy-migration-readiness.v1',
            'dependency_snapshot_hash' => hash('sha256', fake()->uuid()),
            'status' => LegacyMigrationReadinessStatus::Blocked,
            'rehearsal_ready' => false,
            'cutover_ready' => false,
            'check_count' => 1,
            'passed_count' => 0,
            'blocked_count' => 1,
            'checks' => [['key' => 'fixture', 'scope' => 'rehearsal', 'passed' => false]],
            'started_at' => now(),
            'completed_at' => now(),
            'metadata' => ['fixture' => true],
        ];
    }
}
