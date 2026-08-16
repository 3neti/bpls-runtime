<?php

namespace Database\Factories;

use App\Models\LegacyIdMapping;
use App\Models\LegacyImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyIdMapping>
 */
class LegacyIdMappingFactory extends Factory
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
            'legacy_source_id' => fn (array $attributes): int => LegacyImportBatch::query()->whereKey($attributes['legacy_import_batch_id'])->sole()->legacy_source_id,
            'dataset_key' => 'business_owners',
            'entity_type' => 'business_owner',
            'legacy_id' => fake()->unique()->uuid(),
            'target_type' => 'business_owner',
            'target_id' => fake()->numberBetween(1, 1000),
            'status' => 'mapped',
            'mapping_basis' => 'Exact legacy identifier.',
            'metadata' => null,
        ];
    }
}
