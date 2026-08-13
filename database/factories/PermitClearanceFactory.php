<?php

namespace Database\Factories;

use App\Enums\PermitClearanceStatus;
use App\Models\PermitApplication;
use App\Models\PermitClearance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermitClearance>
 */
class PermitClearanceFactory extends Factory
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
            'code' => fake()->unique()->bothify('clearance-###'),
            'label' => fake()->words(3, true),
            'status' => PermitClearanceStatus::Pending,
            'source_snapshot' => [],
        ];
    }
}
