<?php

namespace Database\Factories;

use App\Enums\ReceiptStatus;
use App\Models\Assessment;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
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
            'payment_schedule_id' => PaymentSchedule::factory(),
            'permit_application_id' => PermitApplication::factory(),
            'assessment_id' => Assessment::factory(),
            'status' => ReceiptStatus::Issued,
            'numbering_authority' => 'manual',
            'receipt_number' => fake()->unique()->bothify('OR-####'),
            'amount_cents' => fake()->numberBetween(1_000, 100_000),
            'issued_at' => now(),
            'source_snapshot' => [],
        ];
    }
}
