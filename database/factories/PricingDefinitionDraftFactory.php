<?php

namespace Database\Factories;

use App\Models\PricingDefinitionDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PricingDefinitionDraft> */
class PricingDefinitionDraftFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('DRAFT-########'),
            'revision' => 1, 'method' => 'manual_determination', 'basis' => 'office_determination',
            'unit_code' => null, 'amount_minor' => null, 'currency' => 'PHP',
            'revenue_account_code' => null, 'source_sha256' => hash('sha256', 'test source'),
            'source_locator' => 'test-fixture', 'source_evidence' => ['classification' => 'source_observed'],
        ];
    }
}
