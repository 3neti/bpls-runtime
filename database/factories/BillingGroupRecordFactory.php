<?php

namespace Database\Factories;

use App\Enums\BillingGroupRecordStatus;
use App\Models\BillingGroup;
use App\Models\BillingGroupRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BillingGroupRecord>
 */
class BillingGroupRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billing_group_id' => BillingGroup::factory(),
            'created_by_id' => User::factory(),
            'draft_reference' => 'BGRD-'.Str::ulid(),
            'status' => BillingGroupRecordStatus::Draft,
            'description' => fake()->sentence(),
            'record_date' => now()->toDateString(),
            'payor_name' => fake()->name(),
            'field_values' => [],
            'schema_snapshot' => [],
            'source_snapshot' => ['origin' => 'factory'],
        ];
    }
}
