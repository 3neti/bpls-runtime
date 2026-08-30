<?php

namespace Database\Factories;

use App\Models\LifecycleScenarioSpecimen;
use App\Models\PermitApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LifecycleScenarioSpecimen>
 */
class LifecycleScenarioSpecimenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scenario_id' => fake()->unique()->slug(3),
            'scenario_revision' => 'v1',
            'permit_application_id' => PermitApplication::factory(),
            'semantic_result_hash' => hash('sha256', fake()->uuid()),
            'owned_resource_manifest' => [],
        ];
    }
}
