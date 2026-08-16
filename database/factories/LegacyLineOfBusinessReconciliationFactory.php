<?php

namespace Database\Factories;

use App\Enums\LegacyLineOfBusinessReconciliationStatus;
use App\Models\LegacyLineOfBusinessReconciliation;
use App\Models\LegacySource;
use App\Models\LineOfBusiness;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyLineOfBusinessReconciliation>
 */
class LegacyLineOfBusinessReconciliationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_source_id' => LegacySource::factory(), 'source_dataset' => 'groups',
            'source_value_hash' => hash('sha256', fake()->uuid()), 'line_of_business_id' => LineOfBusiness::factory(),
            'status' => LegacyLineOfBusinessReconciliationStatus::Accepted, 'decision_authority' => 'Test authority',
            'evidence_reference' => 'TEST-EVIDENCE', 'decided_at' => now(), 'metadata' => ['fixture' => true],
        ];
    }
}
