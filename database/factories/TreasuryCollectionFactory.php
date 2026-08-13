<?php

namespace Database\Factories;

use App\Enums\TreasuryCollectionChannel;
use App\Enums\TreasuryCollectionMethod;
use App\Enums\TreasuryCollectionStatus;
use App\Models\Assessment;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\TreasuryCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreasuryCollection>
 */
class TreasuryCollectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_schedule_id' => PaymentSchedule::factory(),
            'permit_application_id' => PermitApplication::factory(),
            'assessment_id' => Assessment::factory(),
            'status' => TreasuryCollectionStatus::PendingReceipt,
            'channel' => TreasuryCollectionChannel::OverTheCounter,
            'method' => TreasuryCollectionMethod::Cash,
            'amount_cents' => fake()->numberBetween(1_000, 100_000),
            'payer_name' => fake()->name(),
            'received_at' => now(),
            'source_snapshot' => [],
        ];
    }
}
