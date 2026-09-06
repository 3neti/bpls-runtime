<?php

namespace Database\Factories;

use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\TreasuryLineOfBusinessAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreasuryLineOfBusinessAssignment>
 */
class TreasuryLineOfBusinessAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'permit_application_id' => PermitApplication::factory(),
            'line_of_business_id' => LineOfBusiness::factory(),
            'assigned_by_id' => User::factory(),
            'sequence' => 1,
            'status' => 'assigned',
            'source_snapshot' => ['classification' => 'synthetic_test_fixture'],
            'assigned_at' => now(),
            'removed_at' => null,
        ];
    }
}
