<?php

namespace App\Assessment;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleExecutionStatus;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\FeeRule;
use App\Models\FeeRuleRange;
use App\Models\FeeRuleReconciliation;
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
        $this->assertExecutableReconciliation($feeRule);

        $basisAmountCents = $this->basisAmountCents($feeRule, $applicationLine);
        $range = null;

        if ($feeRule->calculation_type === FeeRuleCalculationType::Range) {
            $range = $this->matchingRange($feeRule, $basisAmountCents);
            $amountCents = $range->amount_cents;
        } else {
            if ($feeRule->calculation_type === FeeRuleCalculationType::Formula) {
                throw new UnsupportedAssessmentPolicy(
                    "Formula assessment policy is not implemented for fee rule [{$feeRule->code}]."
                );
            }

            $amountCents = $feeRule->amount_cents;
        }

        return [
            'basis_amount_cents' => $basisAmountCents,
            'amount_cents' => $amountCents,
            'range_id' => $range?->id,
            'rule_snapshot' => $this->ruleSnapshot($feeRule, $range),
        ];
    }

    private function assertExecutableReconciliation(FeeRule $feeRule): void
    {
        if (($feeRule->metadata['reconciliation_required'] ?? false) !== true) {
            return;
        }

        $reconciliation = $feeRule->currentReconciliation;

        if (! $reconciliation instanceof FeeRuleReconciliation) {
            throw new UnsupportedAssessmentPolicy(
                "Fee rule [{$feeRule->code}] is not executable because no financial reconciliation is recorded."
            );
        }

        if ($reconciliation->execution_status !== FeeRuleExecutionStatus::Executable) {
            throw new UnsupportedAssessmentPolicy(
                "Fee rule [{$feeRule->code}] is not executable: {$reconciliation->execution_reason}"
            );
        }
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

    private function matchingRange(FeeRule $feeRule, int $basisAmountCents): FeeRuleRange
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

        return $range;
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleSnapshot(FeeRule $feeRule, ?FeeRuleRange $range): array
    {
        $reconciliation = $feeRule->currentReconciliation;

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
            'effective_from' => $feeRule->effective_from->toDateString(),
            'effective_until' => $feeRule->effective_until?->toDateString(),
            'legal_basis' => $feeRule->legal_basis,
            'legacy_source_id' => $feeRule->legacy_source_id,
            'reconciliation' => $reconciliation instanceof FeeRuleReconciliation ? [
                'fee_rule_reconciliation_id' => $reconciliation->id,
                'version' => $reconciliation->version,
                'legal_authority' => $reconciliation->legal_authority,
                'evidence_reference' => $reconciliation->evidence_reference,
                'normalized_interpretation' => $reconciliation->normalized_interpretation,
                'decision_authority' => $reconciliation->decision_authority,
                'decision_reference' => $reconciliation->decision_reference,
                'effective_from' => $reconciliation->effective_from->toDateString(),
                'effective_until' => $reconciliation->effective_until?->toDateString(),
                'execution_status' => $reconciliation->execution_status->value,
                'execution_reason' => $reconciliation->execution_reason,
            ] : null,
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
