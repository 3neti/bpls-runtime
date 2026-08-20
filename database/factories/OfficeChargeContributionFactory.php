<?php

namespace Database\Factories;

use App\Models\OfficeChargeContribution;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfficeChargeContribution>
 */
class OfficeChargeContributionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'permit_application_id' => PermitApplication::factory(),
            'submitted_by_id' => User::factory(),
            'office_code' => 'engineering',
            'office_label' => 'Municipal Engineering Office',
            'is_applicable' => true,
            'status' => 'approved',
            'amount_cents' => 125_00,
            'submitted_at' => now(),
            'semantic_classification' => 'provisional_uat',
            'source_snapshot' => [
                'semantic_classification' => 'provisional_uat',
                'scenario_scope' => 'factory',
            ],
        ];
    }
}
