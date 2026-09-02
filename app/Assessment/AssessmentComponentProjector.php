<?php

namespace App\Assessment;

use App\Enums\AssessmentComponentScope;
use App\Enums\AssessmentComponentType;
use App\Enums\FeeRuleCategory;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use Illuminate\Support\Collection;

final class AssessmentComponentProjector
{
    /** @return Collection<int, AssessmentComponent> */
    public function fromAssessment(Assessment $assessment): Collection
    {
        $assessment->loadMissing(['lines' => fn ($query) => $query->orderBy('id')]);

        return $assessment->lines
            ->map(fn (AssessmentLine $line): AssessmentComponent => $this->fromLine($line));
    }

    private function fromLine(AssessmentLine $line): AssessmentComponent
    {
        [$type, $sourceType, $sourceId, $responsibleOffice, $policyVersion, $recordedById, $recordedAt] = match (true) {
            $line->paperless_payment_order_line_id !== null => [
                AssessmentComponentType::PaperlessPaymentOrder,
                'paperless_payment_order_line',
                (string) $line->paperless_payment_order_line_id,
                data_get($line->rule_snapshot, 'office_code'),
                $this->paperlessPolicyVersion($line),
                data_get($line->rule_snapshot, 'issued_by_id'),
                data_get($line->rule_snapshot, 'issued_at'),
            ],
            $line->fee_rule_id !== null && $line->category === FeeRuleCategory::Tax => [
                AssessmentComponentType::BusinessTax,
                'fee_rule',
                $this->feeRuleSourceId($line),
                null,
                $this->feeRulePolicyVersion($line),
                null,
                null,
            ],
            $line->fee_rule_id !== null => [
                AssessmentComponentType::GovernedFee,
                'fee_rule',
                $this->feeRuleSourceId($line),
                null,
                $this->feeRulePolicyVersion($line),
                null,
                null,
            ],
            default => throw new UnsupportedAssessmentPolicy("Assessment line [{$line->id}] has no accepted AssessmentComponent provenance type."),
        };

        $scope = $line->line_of_business_id === null
            ? AssessmentComponentScope::Application
            : AssessmentComponentScope::LineOfBusiness;

        return new AssessmentComponent(
            key: $line->code,
            type: $type,
            scope: $scope,
            permitApplicationLineId: $line->permit_application_line_id,
            lineOfBusinessId: $line->line_of_business_id,
            sourceType: $sourceType,
            sourceId: $sourceId,
            exactOnceKey: "{$sourceType}:{$sourceId}",
            responsibleOffice: is_string($responsibleOffice) ? $responsibleOffice : null,
            policyVersion: $policyVersion,
            amountCents: $line->amount_cents,
            orderingPhase: 100,
            percentageBaseKeys: [],
            roundingInstruction: 'none_fixed_minor_units',
            explanationSnapshot: [
                'assessment_line_id' => $line->id,
                'code' => $line->code,
                'name' => $line->name,
                'category' => $line->category->value,
                'calculation_type' => $line->calculation_type->value,
                'basis' => $line->basis,
                'basis_amount_cents' => $line->basis_amount_cents,
                'legal_basis' => $line->legal_basis,
                'rule_snapshot' => $line->rule_snapshot,
            ],
            recordedById: is_int($recordedById) ? $recordedById : null,
            recordedAt: is_string($recordedAt) ? $recordedAt : null,
        );
    }

    private function paperlessPolicyVersion(AssessmentLine $line): string
    {
        $fingerprint = data_get($line->rule_snapshot, 'evaluation_fingerprint');

        if (! is_string($fingerprint) || $fingerprint === '') {
            throw new UnsupportedAssessmentPolicy("Assessment line [{$line->id}] has no exact Evaluation policy fingerprint.");
        }

        return "evaluation:{$fingerprint}";
    }

    private function feeRulePolicyVersion(AssessmentLine $line): string
    {
        $effectiveFrom = data_get($line->rule_snapshot, 'effective_from');
        $effectiveUntil = data_get($line->rule_snapshot, 'effective_until') ?? 'open';

        if (! is_string($effectiveFrom) || $effectiveFrom === '') {
            throw new UnsupportedAssessmentPolicy("Assessment line [{$line->id}] has no governed policy effective date.");
        }

        return "fee_rule:{$line->fee_rule_id}:{$effectiveFrom}:{$effectiveUntil}";
    }

    private function feeRuleSourceId(AssessmentLine $line): string
    {
        return $line->permit_application_line_id === null
            ? "{$line->fee_rule_id}:application"
            : "{$line->fee_rule_id}:application_line:{$line->permit_application_line_id}";
    }
}
