<?php

namespace Database\Factories;

use App\Models\CollectionAllocation;
use App\Models\PaymentScheduleLine;
use App\Models\TreasuryCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionAllocation>
 */
class CollectionAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'treasury_collection_id' => TreasuryCollection::factory(),
            'payment_schedule_line_id' => PaymentScheduleLine::factory(),
            'amount_cents' => fake()->numberBetween(1_000, 100_000),
            'source_snapshot' => [],
        ];
    }
}
