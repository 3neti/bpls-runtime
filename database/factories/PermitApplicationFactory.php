<?php

namespace Database\Factories;

use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Models\Business;
use App\Models\PermitApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermitApplication>
 */
class PermitApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'application_number' => fake()->unique()->numerify('APP-2026-#####'),
            'type' => PermitApplicationType::New,
            'status' => PermitApplicationStatus::Draft,
            'application_year' => 2026,
            'submitted_at' => now(),
            'metadata' => [],
        ];
    }
}
