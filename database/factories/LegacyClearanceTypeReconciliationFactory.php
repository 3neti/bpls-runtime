<?php

namespace Database\Factories;

use App\Enums\LegacyClearanceTypeReconciliationStatus;
use App\Models\LegacyClearanceTypeReconciliation;
use App\Models\LegacySource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyClearanceTypeReconciliation>
 */
class LegacyClearanceTypeReconciliationFactory extends Factory
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
            'source_dataset' => 'clearance_types',
            'source_legacy_id' => fake()->unique()->uuid(),
            'target_code' => fake()->unique()->slug(2),
            'target_label' => fake()->words(2, true),
            'status' => LegacyClearanceTypeReconciliationStatus::Accepted,
            'decision_authority' => 'Test authority',
            'evidence_reference' => 'TEST-CLEARANCE-EVIDENCE',
            'decided_at' => now(),
            'metadata' => ['fixture' => true],
        ];
    }
}
