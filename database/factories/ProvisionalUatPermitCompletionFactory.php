<?php

namespace Database\Factories;

use App\Models\PermitApplication;
use App\Models\ProvisionalUatPermitCompletion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProvisionalUatPermitCompletion>
 */
class ProvisionalUatPermitCompletionFactory extends Factory
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
            'decided_by_id' => User::factory(),
            'status' => 'approved_for_preview_release',
            'decision' => 'go',
            'permit_number' => fake()->unique()->numerify('UAT-IPIL-2099-######'),
            'synthetic_signature_reference' => 'SYNTHETIC-UAT-MAYOR-SIGNATURE',
            'decided_at' => now(),
            'semantic_classification' => 'provisional_uat',
            'source_snapshot' => [
                'semantic_classification' => 'provisional_uat',
                'production_authority' => false,
            ],
        ];
    }
}
