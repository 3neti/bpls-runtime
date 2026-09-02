<?php

namespace Database\Factories;

use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermitApplicationDeclaration>
 */
class PermitApplicationDeclarationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $snapshot = ['schema_version' => 1, 'application' => ['type' => 'new']];

        return [
            'permit_application_id' => PermitApplication::factory(),
            'declared_by_id' => User::factory(),
            'schema_version' => 1,
            'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
            'snapshot' => $snapshot,
            'declared_at' => now(),
        ];
    }
}
