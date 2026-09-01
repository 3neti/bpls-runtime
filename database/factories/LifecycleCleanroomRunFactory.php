<?php

namespace Database\Factories;

use App\Models\LifecycleCleanroomRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LifecycleCleanroomRun>
 */
class LifecycleCleanroomRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'status' => 'active',
            'started_by_id' => User::factory(),
            'actor_manifest' => [
                'actor_user_ids' => [],
                'actors' => [],
                'semantic_classification' => 'synthetic_only',
                'production_liability' => false,
            ],
            'owned_resource_manifest' => [
                'semantic_classification' => 'synthetic_only',
                'production_liability' => false,
            ],
        ];
    }
}
