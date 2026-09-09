<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->slug(3);

        return [
            'name' => $code,
            'code' => $code,
            'display_name' => str($code)->replace('-', ' ')->title()->toString(),
            'guard_name' => 'web',
            'description' => fake()->sentence(),
        ];
    }
}
