<?php

namespace Database\Factories;

use App\Models\BploRoutingDetermination;
use App\Models\BploRoutingWork;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BploRoutingWork>
 */
class BploRoutingWorkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bplo_routing_determination_id' => BploRoutingDetermination::factory(),
            'office_code' => 'engineering',
            'office_label' => 'Engineering',
            'situational_reason' => 'Factory-only situational reason.',
            'required_work' => 'Determine the applicable office amount.',
            'permit_application_line_id' => null,
            'line_of_business_id' => null,
            'context_snapshot' => ['automatic_lob_rule' => false],
        ];
    }
}
