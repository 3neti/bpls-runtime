<?php

namespace Database\Factories;

use App\Enums\BillingGroupAcceptanceStatus;
use App\Models\BillingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingGroup>
 */
class BillingGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'acceptance_status' => BillingGroupAcceptanceStatus::Provisional,
            'is_active' => true,
            'metadata' => [],
        ];
    }
}
