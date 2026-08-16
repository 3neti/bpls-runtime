<?php

namespace Database\Factories;

use App\Enums\LegacyImportBatchStatus;
use App\Models\LegacyImportBatch;
use App\Models\LegacySource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyImportBatch>
 */
class LegacyImportBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_source_id' => LegacySource::factory(),
            'run_reference' => 'migration-run-'.fake()->unique()->uuid(),
            'manifest_schema_version' => 'bpls.legacy-staging.v1',
            'manifest_checksum' => hash('sha256', fake()->uuid()),
            'status' => LegacyImportBatchStatus::Staged,
            'source_record_count' => 0,
            'staged_record_count' => 0,
            'exception_count' => 0,
            'mapping_count' => 0,
            'started_at' => now(),
            'completed_at' => now(),
            'metadata' => ['fixture' => true],
        ];
    }
}
