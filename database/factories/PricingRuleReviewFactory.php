<?php

namespace Database\Factories;

use App\Models\FeeRuleReconciliation;
use App\Models\PricingRuleReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PricingRuleReview> */
class PricingRuleReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fee_rule_reconciliation_id' => FeeRuleReconciliation::factory(),
            'fee_rule_id' => fn (array $attributes): int => FeeRuleReconciliation::query()->whereKey($attributes['fee_rule_reconciliation_id'])->firstOrFail()->fee_rule_id,
            'recorded_by' => User::factory(),
            'review_reference' => 'Test-only content review',
        ];
    }
}
