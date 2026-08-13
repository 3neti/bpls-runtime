<?php

namespace Database\Factories;

use App\Enums\PaymentScheduleStatus;
use App\Models\Assessment;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentSchedule>
 */
class PaymentScheduleFactory extends Factory
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
            'assessment_id' => Assessment::factory(),
            'sequence' => 1,
            'status' => PaymentScheduleStatus::Pending,
            'payment_mode' => 'single',
            'total_amount_cents' => 0,
            'paid_amount_cents' => 0,
            'source_snapshot' => [],
        ];
    }
}
