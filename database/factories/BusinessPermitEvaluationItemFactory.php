<?php

namespace Database\Factories;

use App\Enums\BusinessPermitEvaluationItemType;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessPermitEvaluationItem> */
class BusinessPermitEvaluationItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_permit_evaluation_id' => BusinessPermitEvaluation::factory(),
            'key' => fake()->unique()->slug(3),
            'item_type' => BusinessPermitEvaluationItemType::Fact,
            'responsible_party' => 'test',
            'is_required' => true,
            'requires_confirmation' => false,
            'metadata' => [],
        ];
    }
}
