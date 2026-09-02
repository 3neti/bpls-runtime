<?php

namespace Database\Factories;

use App\Models\PaperlessPaymentOrder;
use App\Models\PaperlessPaymentOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaperlessPaymentOrderLine>
 */
class PaperlessPaymentOrderLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'paperless_payment_order_id' => PaperlessPaymentOrder::factory(),
            'permit_application_line_id' => null,
            'line_of_business_id' => null,
            'code' => 'FACTORY-OFFICE-FEE',
            'name' => 'Factory Office Fee',
            'amount_cents' => 10_000,
            'source_snapshot' => ['classification' => 'factory_only'],
        ];
    }
}
