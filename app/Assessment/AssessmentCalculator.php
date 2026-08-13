<?php

namespace App\Assessment;

use App\Enums\FeeRuleCalculationType;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\FeeRule;
use App\Models\FeeRuleRange;
use App\Models\PermitApplicationLine;

class AssessmentCalculator
{
    /**
     * @return array{
     *     basis_amount_cents: int,
     *     amount_cents: int,
     *     range_id: int|null,
     *     rule_snapshot: array<string, mixed>
     * }
     */
    public function calculate(FeeRule $feeRule, ?PermitApplicationLine $applicationLine = null): array
    {
        $basisAmountCents = $this->basisAmountCents($feeRule, $applicationLine);
        $range = null;

        $amountCents = match ($feeRule->calculation_type) {
            FeeRuleCalculationType::Fixed => $feeRule->amount_cents,
            FeeRuleCalculationType::Range => $this->rangeAmountCents($feeRule, $basisAmountCents, $range),
            FeeRuleCalculationType::Formula => throw new UnsupportedAssessmentPolicy(
                "Formula assessment policy is not implemented for fee rule [{$feeRule->code}]."
            ),
        };

        return [
            'basis_amount_cents' => $basisAmountCents,
            'amount_cents' => $amountCents,
            'range_id' => $range?->id,
            'rule_snapshot' => $this->ruleSnapshot($feeRule, $range),
        ];
    }

    private function basisAmountCents(FeeRule $feeRule, ?PermitApplicationLine $applicationLine): int
    {
        if ($feeRule->basis === 'none') {
            return 0;
        }

        if (! $applicationLine instanceof PermitApplicationLine) {
            throw new UnsupportedAssessmentPolicy(
                "Fee rule [{$feeRule->code}] requires basis [{$feeRule->basis}] but no application line was supplied."
            );
        }

        return match ($feeRule->basis) {
            'declared_gross_sales' => $applicationLine->declared_gross_sales_cents,
            'capital_investment' => $applicationLine->capital_investment_cents,
            default => throw new UnsupportedAssessmentPolicy(
                "Assessment basis [{$feeRule->basis}] is not implemented for fee rule [{$feeRule->code}]."
            ),
        };
    }

    private function rangeAmountCents(FeeRule $feeRule, int $basisAmountCents, ?FeeRuleRange &$range): int
    {
        $range = $feeRule->ranges
            ->first(fn (FeeRuleRange $candidate): bool => $candidate->min_basis_cents <= $basisAmountCents
                && ($candidate->max_basis_cents === null || $candidate->max_basis_cents >= $basisAmountCents));

        if (! $range instanceof FeeRuleRange) {
            throw new UnsupportedAssessmentPolicy(
                "No assessment range matches fee rule [{$feeRule->code}] and basis [{$basisAmountCents}]."
            );
        }

        if ($range->rate_basis_points !== null) {
            throw new UnsupportedAssessmentPolicy(
                "Rate-based assessment range [{$range->id}] requires confirmed rounding policy."
            );
        }

        return $range->amount_cents;
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleSnapshot(FeeRule $feeRule, ?FeeRuleRange $range): array
    {
        return [
            'fee_rule_id' => $feeRule->id,
            'line_of_business_id' => $feeRule->line_of_business_id,
            'code' => $feeRule->code,
            'name' => $feeRule->name,
            'category' => $feeRule->category->value,
            'scope' => $feeRule->scope->value,
            'calculation_type' => $feeRule->calculation_type->value,
            'basis' => $feeRule->basis,
            'amount_cents' => $feeRule->amount_cents,
            'rate_basis_points' => $feeRule->rate_basis_points,
            'effective_from' => $feeRule->effective_from?->toDateString(),
            'effective_until' => $feeRule->effective_until?->toDateString(),
            'legal_basis' => $feeRule->legal_basis,
            'legacy_source_id' => $feeRule->legacy_source_id,
            'range' => $range instanceof FeeRuleRange ? [
                'fee_rule_range_id' => $range->id,
                'min_basis_cents' => $range->min_basis_cents,
                'max_basis_cents' => $range->max_basis_cents,
                'amount_cents' => $range->amount_cents,
                'rate_basis_points' => $range->rate_basis_points,
            ] : null,
        ];
    }
}
