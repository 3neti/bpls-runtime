<?php

namespace Database\Factories;

use App\Models\Storyboard;
use App\Models\StoryboardFrame;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoryboardFrame>
 */
class StoryboardFrameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'storyboard_id' => Storyboard::factory(),
            'position' => fake()->numberBetween(1, 50),
            'title' => fake()->sentence(3),
            'image_path' => null,
            'description' => fake()->paragraph(),
            'dialogue' => fake()->sentence(),
            'duration_seconds' => fake()->numberBetween(2, 12),
        ];
    }
}
