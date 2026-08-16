<?php

namespace Database\Factories;

use App\Models\LegacyImportBatch;
use App\Models\LegacyRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyRecord>
 */
class LegacyRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payload = ['_id' => fake()->unique()->uuid(), 'name' => fake()->name()];

        return [
            'legacy_import_batch_id' => LegacyImportBatch::factory(),
            'legacy_source_id' => fn (array $attributes): int => LegacyImportBatch::query()->whereKey($attributes['legacy_import_batch_id'])->sole()->legacy_source_id,
            'dataset_key' => 'business_owners',
            'entity_type' => 'business_owner',
            'legacy_id' => $payload['_id'],
            'payload' => $payload,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'status' => 'staged',
            'line_number' => 1,
        ];
    }
}
