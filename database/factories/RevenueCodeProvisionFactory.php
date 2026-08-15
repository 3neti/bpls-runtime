<?php

namespace Database\Factories;

use App\Enums\RevenueCodeProvisionStatus;
use App\Enums\RevenueCodeProvisionType;
use App\Models\RevenueCodeProvision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevenueCodeProvision>
 */
class RevenueCodeProvisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('MRC-TEST-###'),
            'source_id' => 'LEGAL-MRC-001',
            'section_reference' => 'Test section',
            'title' => fake()->sentence(4),
            'provision_type' => RevenueCodeProvisionType::FixedFee,
            'evidence_summary' => 'Test summary of the governing ordinance provision.',
            'reconciliation_status' => RevenueCodeProvisionStatus::Recorded,
            'effective_from' => '2023-01-01',
            'metadata' => [],
        ];
    }
}
