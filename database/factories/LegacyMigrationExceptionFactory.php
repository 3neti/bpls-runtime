<?php

namespace Database\Factories;

use App\Enums\MigrationExceptionSeverity;
use App\Enums\MigrationExceptionStatus;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMigrationException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyMigrationException>
 */
class LegacyMigrationExceptionFactory extends Factory
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
            'legacy_record_id' => null,
            'dataset_key' => 'business_owners',
            'line_number' => 1,
            'code' => 'missing_legacy_id',
            'severity' => MigrationExceptionSeverity::Error,
            'status' => MigrationExceptionStatus::Open,
            'message' => 'The staged document has no stable legacy identifier.',
            'context' => ['identity_field' => '_id'],
            'resolved_by_id' => null,
            'resolved_at' => null,
        ];
    }
}
