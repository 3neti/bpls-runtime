<?php

namespace Database\Factories;

use App\Enums\LegacyDocumentObjectStagingStatus;
use App\Models\LegacyDocumentObjectStagingRun;
use App\Models\LegacyImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyDocumentObjectStagingRun>
 */
class LegacyDocumentObjectStagingRunFactory extends Factory
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
            'run_reference' => 'document-staging-'.fake()->unique()->uuid(),
            'manifest_schema_version' => 'bpls.legacy-document-objects.v1',
            'manifest_checksum' => hash('sha256', fake()->uuid()),
            'status' => LegacyDocumentObjectStagingStatus::Staged,
            'object_count' => 0,
            'staged_count' => 0,
            'started_at' => now(),
            'completed_at' => now(),
            'metadata' => ['fixture' => true],
        ];
    }
}
