<?php

namespace Database\Factories;

use App\Models\BploRoutingWork;
use App\Models\PaperlessPaymentOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaperlessPaymentOrder>
 */
class PaperlessPaymentOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bplo_routing_work_id' => BploRoutingWork::factory(),
            'permit_application_id' => fn (array $attributes): int => BploRoutingWork::query()
                ->findOrFail($attributes['bplo_routing_work_id'])
                ->determination
                ->permit_application_id,
            'business_permit_evaluation_item_revision_id' => null,
            'issued_by_id' => User::factory(),
            'sequence' => 1,
            'status' => 'issued',
            'total_amount_cents' => 10_000,
            'source_snapshot' => ['classification' => 'factory_only'],
            'issued_at' => now(),
            'superseded_at' => null,
        ];
    }
}
