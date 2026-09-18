<?php

namespace App\Actions;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleScope;
use App\Models\FeeRule;
use Illuminate\Support\Facades\DB;

class RetireEvaluatorUatPricingFixture
{
    public function handle(): int
    {
        return DB::transaction(function (): int {
            $rules = FeeRule::query()
                ->where('code', 'like', 'EVAL-UAT-BASE%')
                ->where('name', 'Evaluator UAT base proposal')
                ->where('scope', FeeRuleScope::Application->value)
                ->where('calculation_type', FeeRuleCalculationType::Fixed->value)
                ->where('basis', 'none')
                ->where('amount_cents', 10_000)
                ->whereDate('effective_from', '2099-01-01')
                ->whereDate('effective_until', '2099-12-31')
                ->where('metadata->semantic_classification', 'provisional_uat')
                ->where('metadata->production_liability', false)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();

            $rules->each(fn (FeeRule $rule) => $rule->update(['is_active' => false]));

            return $rules->count();
        });
    }
}
