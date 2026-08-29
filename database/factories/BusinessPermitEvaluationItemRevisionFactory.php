<?php

namespace Database\Factories;

use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationRevisionAction;
use App\Enums\BusinessPermitEvaluationSource;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\BusinessPermitEvaluationItemRevision;
use App\Models\BusinessPermitEvaluationVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessPermitEvaluationItemRevision> */
class BusinessPermitEvaluationItemRevisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_permit_evaluation_item_id' => BusinessPermitEvaluationItem::factory(),
            'business_permit_evaluation_version_id' => function (array $attributes): int {
                $item = BusinessPermitEvaluationItem::query()
                    ->whereKey($attributes['business_permit_evaluation_item_id'])
                    ->firstOrFail();

                return BusinessPermitEvaluationVersion::factory()->create([
                    'business_permit_evaluation_id' => $item->business_permit_evaluation_id,
                ])->id;
            },
            'action' => BusinessPermitEvaluationRevisionAction::Proposal,
            'applicability' => BusinessPermitEvaluationApplicability::Applicable,
            'value' => ['test' => true],
            'source_classification' => BusinessPermitEvaluationSource::ProvisionalUat,
            'occurred_at' => now(),
        ];
    }
}
