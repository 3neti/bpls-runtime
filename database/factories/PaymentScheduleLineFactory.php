<?php

namespace Database\Factories;

use App\Enums\FeeRuleCategory;
use App\Enums\PaymentScheduleLineStatus;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentScheduleLine>
 */
class PaymentScheduleLineFactory extends Factory
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
            'code' => fake()->unique()->bothify('PAY-####'),
            'name' => fake()->words(3, true),
            'category' => FeeRuleCategory::Fee,
            'status' => PaymentScheduleLineStatus::Pending,
            'amount_cents' => fake()->numberBetween(1_000, 100_000),
            'paid_amount_cents' => 0,
            'source_snapshot' => [],
        ];
    }
}
