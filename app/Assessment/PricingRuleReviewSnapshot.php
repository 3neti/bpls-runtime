<?php

namespace App\Assessment;

use App\Models\FeeRule;
use App\Models\FeeRuleReconciliation;
use Illuminate\Support\Arr;

final class PricingRuleReviewSnapshot
{
    /** @return array<string, mixed> */
    public function capture(FeeRule $rule, FeeRuleReconciliation $decision): array
    {
        return [
            'schema_version' => 1,
            'rule' => Arr::except($rule->attributesToArray(), ['created_at', 'updated_at']),
            'decision' => Arr::except($decision->attributesToArray(), ['created_at', 'updated_at']),
            'account' => $rule->revenueAccount === null ? null : Arr::except($rule->revenueAccount->attributesToArray(), ['created_at', 'updated_at']),
        ];
    }

    /** @param array<string, mixed> $snapshot */
    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($this->normalize($snapshot), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->normalize(...), $value);
    }
}
