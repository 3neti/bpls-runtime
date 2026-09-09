<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->slug(2);

        return [
            'name' => $code,
            'code' => $code,
            'display_name' => fake()->unique()->jobTitle(),
            'guard_name' => 'web',
            'description' => fake()->sentence(),
        ];
    }
}
