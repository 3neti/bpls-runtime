<?php

namespace Database\Factories;

use App\Models\XChangePaymentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<XChangePaymentEvent>
 */
class XChangePaymentEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => fake()->uuid(),
            'payload_hash' => hash('sha256', fake()->uuid()),
            'partner_reference' => 'fixture-partner',
            'external_reference' => 'fixture-'.fake()->uuid(),
            'pay_code' => 'TEST',
            'provider_collection_id' => fake()->uuid(),
            'amount_minor' => 397500,
            'currency' => 'PHP',
            'occurred_at' => now(),
            'state' => 'accepted',
        ];
    }
}
