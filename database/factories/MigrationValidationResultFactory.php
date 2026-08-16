<?php

namespace Database\Factories;

use App\Enums\MigrationValidationStatus;
use App\Models\LegacyImportBatch;
use App\Models\MigrationValidationResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MigrationValidationResult>
 */
class MigrationValidationResultFactory extends Factory
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
            'dataset_key' => 'business_owners',
            'check_key' => 'dataset_checksum',
            'status' => MigrationValidationStatus::Passed,
            'expected' => ['sha256' => hash('sha256', 'expected')],
            'actual' => ['sha256' => hash('sha256', 'expected')],
            'details' => null,
        ];
    }
}
