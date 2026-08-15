<?php

namespace Database\Factories;

use App\Enums\BillingGroupFieldType;
use App\Models\BillingGroup;
use App\Models\BillingGroupField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingGroupField>
 */
class BillingGroupFieldFactory extends Factory
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
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'field_type' => BillingGroupFieldType::Text,
            'is_required' => false,
            'is_unique' => false,
            'sort_order' => 1,
            'options' => null,
            'placeholder' => null,
            'default_value' => null,
        ];
    }
}
