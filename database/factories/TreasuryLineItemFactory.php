<?php

namespace Database\Factories;

use App\Models\FeeRule;
use App\Models\TreasuryLineItem;
use App\Models\TreasuryLineOfBusinessAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreasuryLineItem>
 */
class TreasuryLineItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'treasury_line_of_business_assignment_id' => TreasuryLineOfBusinessAssignment::factory(),
            'fee_rule_id' => FeeRule::factory(),
            'determined_by_id' => User::factory(),
            'code' => fake()->unique()->bothify('TREASURY-####'),
            'name' => fake()->words(3, true),
            'default_amount_cents' => 10_000,
            'determined_amount_cents' => 10_000,
            'variance_cents' => 0,
            'currency' => 'PHP',
            'source_snapshot' => ['classification' => 'synthetic_test_fixture'],
            'determined_at' => now(),
            'removed_at' => null,
        ];
    }
}
