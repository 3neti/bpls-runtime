<?php

namespace App\Assessment;

use App\Data\Assessment\AssessmentContextData;
use App\Data\Assessment\AssessmentPriceComponentInput;
use App\Data\Assessment\AssessmentPriceInput;
use App\Data\Assessment\AssessmentPriceModifierInput;
use App\Enums\BusinessPermitEvaluationItemType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationType;
use App\Models\FeeRule;
use App\Models\FeeRuleReconciliation;
use App\Models\PaperlessPaymentOrder;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\TreasuryLineItem;
use Illuminate\Support\Collection;
use LogicException;

final class AssessmentPriceInputResolver
{
    public function __construct(
        private readonly ApplicableFeeRuleQuery $applicableFeeRuleQuery,
        private readonly AssessmentCalculator $calculator,
    ) {}

    /** @param array<string, mixed>|null $evaluationProjection */
    public function resolve(PermitApplication $application, ?array $evaluationProjection): AssessmentPriceInput
    {
        $application->loadMissing(['lines.lineOfBusiness']);

        $components = $this->componentCollection();
        $modifiers = $this->modifierCollection();

        $nelsonPath = data_get($application->metadata, 'nelson_reconciliation_v1.commissioned_path') === true;
        if ($nelsonPath) {
            $this->appendNelsonPaymentOrderFacts($application, $components, $modifiers);
            $this->appendTreasuryLobFacts($application, $components, $modifiers);
        } elseif (is_array($evaluationProjection)) {
            foreach ($evaluationProjection['projected_charges'] as $charge) {
                $components->push($this->feeRuleComponent($charge, $application));
            }
            $this->appendPaymentOrderFacts($application, $evaluationProjection, $components, $modifiers);
        } else {
            $this->appendDirectFeeFacts($application, $components);
            $this->appendProvisionalOfficeFacts($application, $components);
        }

        if ($nelsonPath && $application->type === PermitApplicationType::New
            && $components->contains(fn (AssessmentPriceComponentInput $component): bool => $component->type === 'business_tax')) {
            throw new LogicException('Business Tax is prohibited for New Applications.');
        }
        if ($nelsonPath && (! $application->treasuryLineOfBusinessAssignments()->whereNull('removed_at')->exists() || $components->isEmpty())) {
            throw new LogicException('Assessment requires Treasury-assigned Lines of Business and confirmed financial items.');
        }

        return new AssessmentPriceInput(
            schema_version: AssessmentPriceInput::Schema,
            currency: 'PHP',
            assessment_context: new AssessmentContextData(
                permit_application_id: $application->id,
                application_type: $application->type->value,
                application_year: $application->application_year,
                evaluation_version_id: $evaluationProjection['version_id'] ?? null,
                evaluation_fingerprint: $evaluationProjection['current_fingerprint'] ?? null,
            ),
            components: array_values($components->sortBy('exact_once_key')->values()->all()),
            modifiers: array_values($modifiers->sortBy('key')->values()->all()),
            taxes: [],
            composition_policy_version: $nelsonPath
                ? 'bpls.assessment-composition.nelson-new-no-business-tax.v1'
                : 'bpls.assessment-composition.fixed-minor-units.v1',
        );
    }

    /**
     * @param  Collection<int, AssessmentPriceComponentInput>  $components
     * @param  Collection<int, AssessmentPriceModifierInput>  $modifiers
     */
    private function appendNelsonPaymentOrderFacts(PermitApplication $application, Collection $components, Collection $modifiers): void
    {
        PaperlessPaymentOrder::query()
            ->where('permit_application_id', $application->id)
            ->whereNull('business_permit_evaluation_item_revision_id')
            ->where('status', 'issued')->whereNull('superseded_at')
            ->with(['lines', 'routingWork'])->orderBy('id')->get()
            ->each(function (PaperlessPaymentOrder $order) use ($components, $modifiers): void {
                if ((int) $order->lines->sum('amount_cents') !== $order->total_amount_cents) {
                    throw new LogicException("Payment Order [{$order->id}] does not reconcile to its lines.");
                }
                foreach ($order->lines as $line) {
                    $default = (int) data_get($line->source_snapshot, 'default_amount_minor', $line->amount_cents);
                    $determined = (int) data_get($line->source_snapshot, 'determined_amount_minor', $line->amount_cents);
                    $key = "paperless_payment_order_line:{$line->id}";
                    $components->push(new AssessmentPriceComponentInput(
                        key: $line->code,
                        type: 'paperless_payment_order',
                        label: $line->name,
                        scope: 'application',
                        permit_application_line_id: null,
                        line_of_business_id: null,
                        line_of_business_name: null,
                        responsible_office: $order->routingWork->office_code,
                        currency: 'PHP',
                        amount_minor: $default,
                        source_type: 'paperless_payment_order_line',
                        source_identity: (string) $line->id,
                        source_version: (string) data_get($line->source_snapshot, 'fee_rule_version'),
                        exact_once_key: $key,
                        legal_basis: null,
                        explanation: [
                            'paperless_payment_order_id' => $order->id,
                            'paperless_payment_order_line_id' => $line->id,
                            'category' => FeeRuleCategory::Fee->value,
                            'calculation_type' => data_get($line->source_snapshot, 'calculation.rule_snapshot.calculation_type', 'fixed'),
                            'basis' => data_get($line->source_snapshot, 'calculation.rule_snapshot.basis', 'concerned_office_payment_order'),
                            'basis_amount_minor' => data_get($line->source_snapshot, 'calculation.basis_amount_cents', $default),
                            'rule_snapshot' => [...$line->source_snapshot, 'financial_source' => 'concerned_office_payment_order'],
                        ],
                    ));
                    if ($default !== $determined) {
                        $modifiers->push(new AssessmentPriceModifierInput(
                            key: "office_item_variance:{$line->id}", type: 'case_override', target_exact_once_key: $key,
                            currency: 'PHP', amount_minor: $determined - $default,
                            reason: (string) data_get($line->source_snapshot, 'reason'), authority: (string) data_get($line->source_snapshot, 'authority'),
                            actor_id: $order->issued_by_id, office: $order->routingWork->office_code, occurred_at: $order->issued_at->toIso8601String(),
                        ));
                    }
                }
            });
    }

    /**
     * @param  Collection<int, AssessmentPriceComponentInput>  $components
     * @param  Collection<int, AssessmentPriceModifierInput>  $modifiers
     */
    private function appendTreasuryLobFacts(PermitApplication $application, Collection $components, Collection $modifiers): void
    {
        TreasuryLineItem::query()
            ->whereHas('assignment', fn ($query) => $query->where('permit_application_id', $application->id)->whereNull('removed_at'))
            ->whereNull('removed_at')->with(['assignment.lineOfBusiness', 'feeRule'])->orderBy('id')->get()
            ->each(function (TreasuryLineItem $item) use ($components, $modifiers): void {
                if ($item->feeRule->category === FeeRuleCategory::Tax) {
                    throw new LogicException('Business Tax is prohibited for New Applications.');
                }
                $applicationWide = data_get($item->source_snapshot, 'scope') === FeeRuleScope::Application->value;
                $key = $applicationWide
                    ? 'treasury_application_fee:'.data_get($item->source_snapshot, 'exact_once_key', $item->id)
                    : "treasury_line_item:{$item->id}";
                $components->push(new AssessmentPriceComponentInput(
                    key: $item->code, type: 'treasury_lob_fee', label: $item->name,
                    scope: $applicationWide ? FeeRuleScope::Application->value : FeeRuleScope::LineOfBusiness->value,
                    permit_application_line_id: $applicationWide ? null : data_get($item->source_snapshot, 'permit_application_line_id'),
                    line_of_business_id: $applicationWide ? null : $item->assignment->line_of_business_id,
                    line_of_business_name: $applicationWide ? null : $item->assignment->lineOfBusiness->name,
                    responsible_office: null, currency: $item->currency,
                    amount_minor: $item->default_amount_cents, source_type: 'treasury_line_item', source_identity: (string) $item->id,
                    source_version: (string) data_get($item->source_snapshot, 'fee_rule_version'), exact_once_key: $key,
                    legal_basis: $item->feeRule->legal_basis,
                    explanation: [
                        'fee_rule_id' => $item->fee_rule_id, 'category' => FeeRuleCategory::Fee->value,
                        'calculation_type' => data_get($item->source_snapshot, 'calculation.rule_snapshot.calculation_type', 'fixed'),
                        'basis' => data_get($item->source_snapshot, 'calculation.rule_snapshot.basis', 'treasury_lob_determination'),
                        'basis_amount_minor' => data_get($item->source_snapshot, 'calculation.basis_amount_cents', $item->default_amount_cents),
                        'rule_snapshot' => [...$item->source_snapshot, 'financial_source' => 'treasury_lob_component'],
                    ],
                ));
                if ($item->variance_cents !== 0) {
                    $modifiers->push(new AssessmentPriceModifierInput(
                        key: "treasury_item_variance:{$item->id}", type: 'case_override', target_exact_once_key: $key,
                        currency: $item->currency, amount_minor: $item->variance_cents,
                        reason: (string) data_get($item->source_snapshot, 'reason'), authority: (string) data_get($item->source_snapshot, 'authority'),
                        actor_id: $item->determined_by_id, office: 'treasury', occurred_at: $item->determined_at->toIso8601String(),
                    ));
                }
            });
    }

    /** @param array<string, mixed> $charge */
    private function feeRuleComponent(array $charge, PermitApplication $application): AssessmentPriceComponentInput
    {
        $feeRule = $charge['fee_rule'];
        if (! $feeRule instanceof FeeRule) {
            throw new LogicException('Assessment price resolution requires an already-resolved FeeRule fact.');
        }

        $applicationLine = $charge['application_line'] ?? null;
        $calculation = $this->calculator->calculate(
            $feeRule,
            $applicationLine instanceof PermitApplicationLine ? $applicationLine : null,
            $application,
        );
        if ($calculation['amount_cents'] !== $charge['amount_cents']
            || $calculation['rule_snapshot'] !== $charge['rule_snapshot']) {
            throw new LogicException("Evaluation and canonical price input disagree for [{$feeRule->code}].");
        }

        $sourceIdentity = $applicationLine instanceof PermitApplicationLine
            ? "{$feeRule->id}:application_line:{$applicationLine->id}"
            : "{$feeRule->id}:application";
        $lineOfBusiness = $applicationLine instanceof PermitApplicationLine
            ? $applicationLine->lineOfBusiness
            : $feeRule->lineOfBusiness;

        return new AssessmentPriceComponentInput(
            key: $feeRule->code,
            type: $feeRule->category === FeeRuleCategory::Tax ? 'business_tax' : 'governed_fee',
            label: $feeRule->name,
            scope: $feeRule->scope->value,
            permit_application_line_id: $applicationLine?->id,
            line_of_business_id: $feeRule->scope === FeeRuleScope::LineOfBusiness ? $feeRule->line_of_business_id : null,
            line_of_business_name: $feeRule->scope === FeeRuleScope::LineOfBusiness ? $lineOfBusiness?->name : null,
            responsible_office: null,
            currency: 'PHP',
            amount_minor: $calculation['amount_cents'],
            source_type: 'fee_rule',
            source_identity: $sourceIdentity,
            source_version: $this->feeRuleVersion($feeRule),
            exact_once_key: "fee_rule:{$sourceIdentity}",
            legal_basis: $feeRule->legal_basis,
            explanation: [
                'fee_rule_id' => $feeRule->id,
                'category' => $feeRule->category->value,
                'calculation_type' => $feeRule->calculation_type->value,
                'basis' => $feeRule->basis,
                'basis_amount_minor' => $calculation['basis_amount_cents'],
                'rule_snapshot' => $calculation['rule_snapshot'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $projection
     * @param  Collection<int, AssessmentPriceComponentInput>  $components
     * @param  Collection<int, AssessmentPriceModifierInput>  $modifiers
     */
    private function appendPaymentOrderFacts(
        PermitApplication $application,
        array $projection,
        Collection $components,
        Collection $modifiers,
    ): void {
        $items = $projection['items'] ?? [];
        $eligibleRevisionIds = collect(is_array($items) ? $items : [])
            ->filter(fn (mixed $item): bool => is_array($item)
                && $item['item_type'] === BusinessPermitEvaluationItemType::Charge->value
                && $item['applicability'] === 'applicable'
                && $item['resolution'] === 'resolved')
            ->pluck('revision_id')->filter()->values();

        PaperlessPaymentOrder::query()
            ->where('permit_application_id', $application->id)
            ->whereIn('business_permit_evaluation_item_revision_id', $eligibleRevisionIds)
            ->where('status', 'issued')
            ->whereNull('superseded_at')
            ->with(['lines.lineOfBusiness', 'routingWork', 'evaluationItemRevision.item'])
            ->orderBy('id')
            ->get()
            ->each(function (PaperlessPaymentOrder $order) use ($projection, $components, $modifiers): void {
                if ((int) $order->lines->sum('amount_cents') !== $order->total_amount_cents) {
                    throw new LogicException("Paperless Payment Order [{$order->id}] does not reconcile to its lines.");
                }

                foreach ($order->lines as $line) {
                    $revision = $order->evaluationItemRevision;
                    $scheduled = data_get($revision->value, 'determination.scheduled_amount_minor');
                    $determined = data_get($revision->value, 'determination.determined_amount_minor', $line->amount_cents);
                    $scheduled = is_int($scheduled) ? $scheduled : $line->amount_cents;
                    $determined = is_int($determined) ? $determined : $line->amount_cents;
                    $exactOnceKey = "paperless_payment_order_line:{$line->id}";

                    $components->push(new AssessmentPriceComponentInput(
                        key: $line->code,
                        type: 'paperless_payment_order',
                        label: $line->name,
                        scope: (string) data_get($line->source_snapshot, 'scope', $line->line_of_business_id === null ? 'application' : 'line_of_business'),
                        permit_application_line_id: $line->permit_application_line_id,
                        line_of_business_id: $line->line_of_business_id,
                        line_of_business_name: $line->lineOfBusiness?->name,
                        responsible_office: $order->routingWork->office_code,
                        currency: 'PHP',
                        amount_minor: $scheduled,
                        source_type: 'paperless_payment_order_line',
                        source_identity: (string) $line->id,
                        source_version: "evaluation:{$projection['current_fingerprint']}",
                        exact_once_key: $exactOnceKey,
                        legal_basis: null,
                        explanation: [
                            'business_permit_evaluation_item_id' => $revision?->business_permit_evaluation_item_id,
                            'paperless_payment_order_id' => $order->id,
                            'paperless_payment_order_line_id' => $line->id,
                            'category' => FeeRuleCategory::Fee->value,
                            'calculation_type' => 'fixed',
                            'basis' => 'paperless_payment_order',
                            'basis_amount_minor' => $scheduled,
                            'rule_snapshot' => [
                                'source' => 'paperless_payment_order',
                                'paperless_payment_order_id' => $order->id,
                                'paperless_payment_order_line_id' => $line->id,
                                'office_code' => $order->routingWork->office_code,
                                'office_label' => $order->routingWork->office_label,
                                'evaluation_version_id' => $projection['version_id'],
                                'evaluation_fingerprint' => $projection['current_fingerprint'],
                                'issued_by_id' => $order->issued_by_id,
                                'issued_at' => $order->issued_at->toIso8601String(),
                                'source_snapshot' => $order->source_snapshot,
                            ],
                        ],
                    ));

                    if ($scheduled !== $determined) {
                        $reason = (string) data_get($revision->value, 'determination.reason', $revision?->reason);
                        $authority = (string) data_get($revision->value, 'determination.authority');
                        $modifiers->push(new AssessmentPriceModifierInput(
                            key: "case_override:{$revision?->id}",
                            type: 'case_override',
                            target_exact_once_key: $exactOnceKey,
                            currency: 'PHP',
                            amount_minor: $determined - $scheduled,
                            reason: $reason,
                            authority: $authority,
                            actor_id: $revision?->actor_id,
                            office: $order->routingWork->office_code,
                            occurred_at: $revision?->occurred_at->toIso8601String() ?? $order->issued_at->toIso8601String(),
                        ));
                    }
                }
            });
    }

    /** @param Collection<int, AssessmentPriceComponentInput> $components */
    private function appendDirectFeeFacts(PermitApplication $application, Collection $components): void
    {
        $rules = $this->applicableFeeRuleQuery->forPermitApplication($application);
        foreach ($rules->where('scope', FeeRuleScope::Application) as $rule) {
            $components->push($this->feeRuleComponentFromModels($rule, $application));
        }
        foreach ($application->lines as $line) {
            foreach ($rules->where('scope', FeeRuleScope::LineOfBusiness)->where('line_of_business_id', $line->line_of_business_id) as $rule) {
                $components->push($this->feeRuleComponentFromModels($rule, $application, $line));
            }
        }
    }

    private function feeRuleComponentFromModels(
        FeeRule $rule,
        PermitApplication $application,
        ?PermitApplicationLine $line = null,
    ): AssessmentPriceComponentInput {
        $calculation = $this->calculator->calculate($rule, $line, $application);

        return $this->feeRuleComponent([
            'fee_rule' => $rule,
            'application_line' => $line,
            'amount_cents' => $calculation['amount_cents'],
            'rule_snapshot' => $calculation['rule_snapshot'],
        ], $application);
    }

    /** @param Collection<int, AssessmentPriceComponentInput> $components */
    private function appendProvisionalOfficeFacts(PermitApplication $application, Collection $components): void
    {
        $application->officeChargeContributions()
            ->where('status', 'approved')->where('is_applicable', true)->orderBy('office_code')->get()
            ->each(function ($contribution) use ($components): void {
                $components->push(new AssessmentPriceComponentInput(
                    key: 'UAT-OFFICE-'.str($contribution->office_code)->upper()->toString(),
                    type: 'paperless_payment_order',
                    label: $contribution->office_label,
                    scope: 'application',
                    permit_application_line_id: null,
                    line_of_business_id: null,
                    line_of_business_name: null,
                    responsible_office: $contribution->office_code,
                    currency: 'PHP',
                    amount_minor: $contribution->amount_cents ?? 0,
                    source_type: 'provisional_office_contribution',
                    source_identity: (string) $contribution->id,
                    source_version: 'provisional_uat.v1',
                    exact_once_key: "provisional_office_contribution:{$contribution->id}",
                    legal_basis: null,
                    explanation: [
                        'category' => FeeRuleCategory::Fee->value,
                        'calculation_type' => 'fixed',
                        'basis' => 'manual_office_assessment',
                        'basis_amount_minor' => $contribution->amount_cents ?? 0,
                        'rule_snapshot' => [
                            'semantic_classification' => 'provisional_uat',
                            'generalizes_municipal_policy' => false,
                            'real_taxpayer_liability' => false,
                        ],
                    ],
                ));
            });
    }

    private function feeRuleVersion(FeeRule $feeRule): string
    {
        return implode(':', [
            'fee_rule',
            $feeRule->id,
            $feeRule->effective_from->toDateString(),
            $feeRule->effective_until?->toDateString() ?? 'open',
            $feeRule->currentReconciliation instanceof FeeRuleReconciliation
                ? $feeRule->currentReconciliation->version
                : 'unreconciled',
        ]);
    }

    /** @return Collection<int, AssessmentPriceComponentInput> */
    private function componentCollection(): Collection
    {
        return new Collection;
    }

    /** @return Collection<int, AssessmentPriceModifierInput> */
    private function modifierCollection(): Collection
    {
        return new Collection;
    }
}
