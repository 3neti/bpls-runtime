<?php

namespace Database\Factories;

use App\Models\LegacySource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacySource>
 */
class LegacySourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'legacy-source-'.fake()->unique()->slug(2),
            'title' => fake()->company().' legacy export',
            'source_type' => 'convex_export',
            'baseline' => fake()->uuid(),
            'archive_checksum' => hash('sha256', fake()->uuid()),
            'provenance' => ['origin' => 'factory'],
            'status' => 'registered',
        ];
    }
}
