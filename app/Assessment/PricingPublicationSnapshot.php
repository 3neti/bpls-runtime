<?php

namespace App\Assessment;

use App\Models\FeeRule;
use Illuminate\Support\Arr;

final class PricingPublicationSnapshot
{
    /** @return array<string, mixed> */
    public function capture(FeeRule $rule): array
    {
        $rule->load(['ranges', 'revenueAccount', 'currentReconciliation', 'officeAssignments', 'lineOfBusinessAssignments']);

        return [
            'rule' => Arr::except($rule->attributesToArray(), ['created_at', 'updated_at']),
            'ranges' => $rule->ranges->sortBy('id')->map(fn ($range) => Arr::except($range->attributesToArray(), ['created_at', 'updated_at']))->values()->all(),
            'account' => $rule->revenueAccount?->only(['id', 'code', 'name', 'is_active']),
            'decision' => $rule->currentReconciliation?->attributesToArray(),
            'offices' => $rule->officeAssignments->sortBy('id')->map->attributesToArray()->values()->all(),
            'lobs' => $rule->lineOfBusinessAssignments->sortBy('id')->map->attributesToArray()->values()->all(),
        ];
    }
}
