<?php

namespace App\Assessment;

use App\Assessment\Price\Price;
use App\Data\Assessment\AssessmentPriceComponentInput;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationType;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\FeeRule;
use App\Models\FeeRuleReconciliation;
use App\Models\PermitApplication;
use Illuminate\Support\Arr;

/** Read-only adapter for an existing reconciled fixed fee; never promotes catalogue drafts. */
final class ReconciledFixedFeePriceAdapter
{
    public function __construct(
        private readonly ApplicableFeeRuleQuery $applicableRules,
        private readonly AssessmentCalculator $calculator,
    ) {}

    public function price(int $applicationId, int $feeRuleId, int $reconciliationId): Price
    {
        $application = PermitApplication::query()->findOrFail($applicationId);
        if ($application->type !== PermitApplicationType::New) {
            throw new UnsupportedAssessmentPolicy('This adapter supports New applications only; renewal policy is unchanged.');
        }
        $rule = $this->applicableRules->forPermitApplication($application)->firstWhere('id', $feeRuleId);
        if (! $rule instanceof FeeRule
            || $rule->scope !== FeeRuleScope::Application
            || $rule->category !== FeeRuleCategory::Fee
            || $rule->calculation_type !== FeeRuleCalculationType::Fixed
            || $rule->basis !== 'none'
            || $rule->amount_cents < 0) {
            throw new UnsupportedAssessmentPolicy('An applicable fixed application-wide fee with an explicit amount is required.');
        }
        $reconciliation = $rule->currentReconciliation;
        if (! $reconciliation instanceof FeeRuleReconciliation
            || $reconciliation->id !== $reconciliationId
            || $reconciliation->execution_status !== FeeRuleExecutionStatus::Executable
            || $reconciliation->decided_at === null
            || $reconciliation->decided_at->isFuture()) {
            throw new UnsupportedAssessmentPolicy('The exact current executable reconciliation is required.');
        }
        foreach (['legal_authority', 'evidence_reference', 'decision_authority', 'decision_reference'] as $field) {
            $value = $reconciliation->getAttribute($field);
            if (! is_string($value) || trim($value) === '') {
                throw new UnsupportedAssessmentPolicy('Reconciliation authority and decision references must be complete.');
            }
        }
        $asOfDate = sprintf('%04d-01-01', $application->application_year);
        if ($reconciliation->effective_from->toDateString() > $asOfDate
            || ($reconciliation->effective_until !== null && $reconciliation->effective_until->toDateString() < $asOfDate)) {
            throw new UnsupportedAssessmentPolicy('Reconciliation is outside the application-year policy period.');
        }
        $calculation = $this->calculator->calculate($rule, null, $application);
        $sourceIdentity = "{$rule->id}:application";
        $account = $rule->revenueAccount;
        $evidence = [
            'application_id' => $application->id,
            'application_year' => $application->application_year,
            'rule_snapshot' => $calculation['rule_snapshot'],
            'reconciliation' => Arr::only($reconciliation->attributesToArray(), [
                'id', 'version', 'legal_authority', 'evidence_reference', 'decision_authority',
                'decision_reference', 'effective_from', 'effective_until', 'execution_status', 'decided_at',
            ]),
            'revenue_account' => $account?->only(['id', 'code', 'name']),
        ];

        return Price::fromComponent(new AssessmentPriceComponentInput(
            key: $rule->code, type: 'governed_fee', label: $rule->name, scope: 'application',
            permit_application_line_id: null, line_of_business_id: null, line_of_business_name: null,
            responsible_office: null, currency: 'PHP', amount_minor: $calculation['amount_cents'],
            source_type: 'fee_rule', source_identity: $sourceIdentity,
            source_version: 'reconciled-fixed.v1:'.hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR)),
            exact_once_key: "fee_rule:{$sourceIdentity}", legal_basis: $rule->legal_basis,
            explanation: $evidence,
        ));
    }
}
