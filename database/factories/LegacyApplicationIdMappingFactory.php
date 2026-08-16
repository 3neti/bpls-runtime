<?php

namespace Database\Factories;

use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyImportBatch;
use App\Models\PermitApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyApplicationIdMapping>
 */
class LegacyApplicationIdMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_application_mapping_execution_id' => null,
            'legacy_import_batch_id' => LegacyImportBatch::factory(),
            'legacy_source_id' => fn (array $attributes): int => LegacyImportBatch::query()->whereKey($attributes['legacy_import_batch_id'])->sole()->legacy_source_id,
            'permit_application_id' => PermitApplication::factory(),
            'dataset_key' => 'applications',
            'legacy_id' => fake()->unique()->uuid(),
            'status' => 'mapped',
            'mapping_basis' => 'factory',
            'metadata' => [],
        ];
    }
}
