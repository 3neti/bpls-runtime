<?php

namespace Database\Factories;

use App\Models\InstitutionalPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstitutionalPosition>
 */
class InstitutionalPositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->jobTitle(),
            'authority_classification' => 'institutional_capability_seat',
            'assignment_status' => 'unassigned',
            'metadata' => ['production_commissioned' => false],
        ];
    }
}
