<?php

namespace Database\Factories;

use App\Models\BploRoutingSuggestion;
use App\Models\PermitApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BploRoutingSuggestion>
 */
class BploRoutingSuggestionFactory extends Factory
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
            'routing_determination_id' => null,
            'profile_version' => 'factory-routing-profile-v1',
            'profile_keys' => ['factory-retail'],
            'status' => BploRoutingSuggestion::AwaitingConfirmation,
            'situational_context' => 'Factory-only provisional BPLO routing suggestion.',
            'suggested_work' => [],
            'application_facts_snapshot' => ['facts_hash' => hash('sha256', fake()->uuid())],
            'lodged_at' => now(),
            'review_due_at' => now()->addMinutes(15),
            'resolved_at' => null,
        ];
    }
}
