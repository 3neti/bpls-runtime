<?php

namespace Database\Factories;

use App\Models\BploRoutingDetermination;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BploRoutingDetermination>
 */
class BploRoutingDeterminationFactory extends Factory
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
            'determined_by_id' => User::factory(),
            'situational_context' => 'Factory-only situational routing context.',
            'application_facts_snapshot' => ['automatic_lob_rule' => false],
            'determined_at' => now(),
        ];
    }
}
