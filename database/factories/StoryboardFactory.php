<?php

namespace Database\Factories;

use App\Models\Storyboard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Storyboard>
 */
class StoryboardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by_id' => User::factory(),
            'title' => fake()->sentence(4),
            'summary' => fake()->paragraph(),
        ];
    }
}
