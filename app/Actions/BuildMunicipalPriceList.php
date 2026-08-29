<?php

namespace App\Actions;

use App\Assessment\ApplicableFeeRuleQuery;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\FeeRulePublicationSource;
use App\Enums\FeeRuleScope;
use App\Enums\MunicipalServiceOfferingCode;
use App\Models\FeeRule;
use App\Models\FeeRuleRange;
use App\Models\FeeRuleReconciliation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BuildMunicipalPriceList
{
    public function __construct(private ApplicableFeeRuleQuery $applicableFeeRuleQuery) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(bool $includeInternalEvidence = false, ?Carbon $asOf = null): array
    {
        $effectiveDate = ($asOf ?? now())->startOfDay();
        $applicationYear = (int) $effectiveDate->format('Y');
        $services = collect(MunicipalServiceOfferingCode::cases())
            ->map(fn (MunicipalServiceOfferingCode $offering): array => $this->servicePayload(
                offering: $offering,
                applicationYear: $applicationYear,
                includeInternalEvidence: $includeInternalEvidence,
                asOfDate: $effectiveDate,
            ))
            ->values();

        return [
            'catalog' => [
                'title' => 'Municipal Services & Fees',
                'scope' => 'Business Permit and Licensing Services',
                'as_of_date' => $effectiveDate->toDateString(),
                'application_year' => $applicationYear,
                'read_only' => true,
                'audience' => $includeInternalEvidence ? 'internal' : 'public',
                'service_count' => $services->count(),
                'confirmed_exact_charge_count' => $services->sum(
                    fn (array $service): int => count($service['pricing']['confirmed_charges']),
                ),
            ],
            'services' => $services->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function servicePayload(
        MunicipalServiceOfferingCode $offering,
        int $applicationYear,
        bool $includeInternalEvidence,
        CarbonInterface $asOfDate,
    ): array {
        $feeRules = $this->applicableFeeRuleQuery->forApplicationFacts(
            applicationType: $offering->applicationType(),
            applicationYear: $applicationYear,
        );
        $feeRules->loadMissing('lineOfBusiness');
        $ambiguousRuleKeys = $this->ambiguousRuleKeys($feeRules);
        $confirmedCharges = $feeRules
            ->filter(fn (FeeRule $feeRule): bool => $offering->publishesConfirmedAssessmentRules()
                && $offering->publishesRuleCode($feeRule->code)
                && ! $ambiguousRuleKeys->contains($this->ruleApplicabilityKey($feeRule))
                && $this->mayPublishExactAmount($feeRule))
            ->map(fn (FeeRule $feeRule): array => $this->confirmedChargePayload(
                feeRule: $feeRule,
                offering: $offering,
                applicationYear: $applicationYear,
            ))
            ->values();

        $startRouteName = $offering->startRouteName();

        $payload = [
            'code' => $offering->value,
            'name' => $offering->title(),
            'application_type' => $offering->applicationType()->value,
            'description' => $offering->description(),
            'availability' => $offering->availability(),
            'availability_label' => $offering->availabilityLabel(),
            'can_start_online' => $startRouteName !== null,
            'start_url' => $startRouteName !== null ? route($startRouteName, absolute: false) : null,
            'pricing' => [
                'status' => $confirmedCharges->isNotEmpty()
                    ? 'confirmed_exact_with_other_possible_charges'
                    : 'municipal_confirmation_required',
                'confirmed_charges' => $confirmedCharges->all(),
                'other_charges_heading' => 'Other charges may apply',
                'other_charges_message' => 'Business tax, permit fees, and charges from concerned municipal offices may depend on the business information and applicable municipal rules.',
                'office_determined_message' => 'Determined by the concerned municipal office when applicable.',
                'confirmation_message' => 'Municipal confirmation is still required for charges that are not shown as confirmed.',
            ],
        ];

        if ($includeInternalEvidence) {
            $lineOfBusinessFeeRules = $this->lineOfBusinessFeeRules($offering, $asOfDate);

            $payload['internal'] = [
                'selected_rule_count' => $feeRules->count(),
                'ambiguous_rule_keys' => $ambiguousRuleKeys->all(),
                'rules' => $feeRules
                    ->map(fn (FeeRule $feeRule): array => $this->internalRulePayload(
                        feeRule: $feeRule,
                        applicationYear: $applicationYear,
                        ambiguous: $ambiguousRuleKeys->contains($this->ruleApplicabilityKey($feeRule)),
                    ))
                    ->values()
                    ->all(),
                'line_of_business_pricing' => $lineOfBusinessFeeRules
                    ->map(fn (FeeRule $feeRule): array => $this->internalRulePayload(
                        feeRule: $feeRule,
                        applicationYear: $applicationYear,
                        ambiguous: false,
                    ))
                    ->values()
                    ->all(),
                'office_determined' => [
                    'status' => 'office_determined',
                    'display' => 'Determined by the concerned municipal office when applicable.',
                    'system_computed' => false,
                    'official_price_recorded' => false,
                ],
            ];
        }

        return $payload;
    }

    /**
     * Recorded pricing knowledge scoped to a Line of Business, independent of
     * any specific business's declared lines. This is browsing evidence for
     * staff only; it never feeds automatic assessment selection.
     *
     * @return Collection<int, FeeRule>
     */
    private function lineOfBusinessFeeRules(MunicipalServiceOfferingCode $offering, CarbonInterface $asOfDate): Collection
    {
        return FeeRule::query()
            ->with(['lineOfBusiness', 'ranges', 'currentReconciliation'])
            ->where('scope', FeeRuleScope::LineOfBusiness->value)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $asOfDate)
            ->where(function ($query) use ($asOfDate): void {
                $query
                    ->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $asOfDate);
            })
            ->orderBy('code')
            ->get()
            ->filter(fn (FeeRule $feeRule): bool => $this->appliesToApplicationType($feeRule, $offering))
            ->values();
    }

    private function appliesToApplicationType(FeeRule $feeRule, MunicipalServiceOfferingCode $offering): bool
    {
        $applicationTypes = $feeRule->metadata['application_types'] ?? null;

        if ($applicationTypes === null) {
            return true;
        }

        if (! is_array($applicationTypes)) {
            return false;
        }

        return in_array($offering->applicationType()->value, $applicationTypes, true);
    }

    /**
     * @param  Collection<int, FeeRule>  $feeRules
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function ambiguousRuleKeys(Collection $feeRules): \Illuminate\Support\Collection
    {
        return $feeRules
            ->groupBy(fn (FeeRule $feeRule): string => $this->ruleApplicabilityKey($feeRule))
            ->filter(fn (Collection $versions): bool => $versions->count() > 1)
            ->keys()
            ->values();
    }

    private function ruleApplicabilityKey(FeeRule $feeRule): string
    {
        return implode('|', [
            $feeRule->code,
            $feeRule->scope->value,
            $feeRule->line_of_business_id ?? 'application',
        ]);
    }

    private function mayPublishExactAmount(FeeRule $feeRule): bool
    {
        $reconciliation = $feeRule->currentReconciliation;

        return FeeRulePublicationSource::forRule($feeRule)->mayPublishExactAmount()
            && $feeRule->calculation_type === FeeRuleCalculationType::Fixed
            && ($feeRule->metadata['reconciliation_required'] ?? false) === true
            && $reconciliation instanceof FeeRuleReconciliation
            && $reconciliation->execution_status === FeeRuleExecutionStatus::Executable
            && filled($feeRule->legal_basis)
            && filled($feeRule->legacy_source_id);
    }

    /** @return array<string, mixed> */
    private function confirmedChargePayload(
        FeeRule $feeRule,
        MunicipalServiceOfferingCode $offering,
        int $applicationYear,
    ): array {
        $reconciliation = $feeRule->currentReconciliation;

        if (! $reconciliation instanceof FeeRuleReconciliation) {
            throw new \LogicException('A published exact amount requires reconciliation evidence.');
        }

        return [
            'kind' => 'fixed',
            'label' => $feeRule->name,
            'amount_cents' => $feeRule->amount_cents,
            'cadence' => 'year',
            'traceability' => [
                'fee_rule_id' => $feeRule->id,
                'rule_code' => $feeRule->code,
                'scope' => $feeRule->scope->value,
                'line_of_business_id' => $feeRule->line_of_business_id,
                'application_type' => $offering->applicationType()->value,
                'application_year' => $applicationYear,
                'effective_from' => $feeRule->effective_from->toDateString(),
                'effective_until' => $feeRule->effective_until?->toDateString(),
                'legal_basis' => $feeRule->legal_basis,
                'legal_source_id' => $feeRule->legacy_source_id,
                'source_classification' => FeeRulePublicationSource::forRule($feeRule)->value,
                'reconciliation_id' => $reconciliation->id,
                'reconciliation_version' => $reconciliation->version,
                'reconciliation_effective_from' => $reconciliation->effective_from->toDateString(),
                'reconciliation_effective_until' => $reconciliation->effective_until?->toDateString(),
                'legal_authority' => $reconciliation->legal_authority,
                'evidence_reference' => $reconciliation->evidence_reference,
                'execution_status' => $reconciliation->execution_status->value,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function internalRulePayload(FeeRule $feeRule, int $applicationYear, bool $ambiguous): array
    {
        $source = FeeRulePublicationSource::forRule($feeRule);
        $reconciliation = $feeRule->currentReconciliation;
        $isExecutable = $reconciliation instanceof FeeRuleReconciliation
            && $reconciliation->execution_status === FeeRuleExecutionStatus::Executable;
        $mayShowRecordedCandidate = in_array($source, [
            FeeRulePublicationSource::AcceptedMunicipalAuthority,
            FeeRulePublicationSource::MunicipalConfirmationRequired,
        ], true);

        return [
            'id' => $feeRule->id,
            'code' => $feeRule->code,
            'name' => $feeRule->name,
            'category' => $feeRule->category->value,
            'scope' => $feeRule->scope->value,
            'line_of_business' => $feeRule->lineOfBusiness ? [
                'id' => $feeRule->lineOfBusiness->id,
                'code' => $feeRule->lineOfBusiness->code,
                'name' => $feeRule->lineOfBusiness->name,
            ] : null,
            'calculation_type' => $feeRule->calculation_type->value,
            'basis' => $feeRule->basis,
            'recorded_amount_cents' => $mayShowRecordedCandidate && $feeRule->calculation_type === FeeRuleCalculationType::Fixed
                ? $feeRule->amount_cents
                : null,
            'rate_basis_points' => $mayShowRecordedCandidate ? $feeRule->rate_basis_points : null,
            'range_count' => $feeRule->relationLoaded('ranges') ? $feeRule->ranges->count() : 0,
            'ranges' => $mayShowRecordedCandidate && $feeRule->relationLoaded('ranges')
                ? $feeRule->ranges
                    ->sortBy('min_basis_cents')
                    ->values()
                    ->map(fn (FeeRuleRange $range): array => [
                        'min_basis_cents' => $range->min_basis_cents,
                        'max_basis_cents' => $range->max_basis_cents,
                        'amount_cents' => $range->amount_cents,
                        'rate_basis_points' => $range->rate_basis_points,
                    ])
                    ->all()
                : [],
            'policy_note' => is_string($feeRule->metadata['policy_note'] ?? null) ? $feeRule->metadata['policy_note'] : null,
            'effective_from' => $feeRule->effective_from->toDateString(),
            'effective_until' => $feeRule->effective_until?->toDateString(),
            'legal_basis' => $feeRule->legal_basis,
            'legal_source_id' => $feeRule->legacy_source_id,
            'source_classification' => $source->value,
            'publication_status' => $this->mayPublishExactAmount($feeRule) && ! $ambiguous
                ? 'confirmed_exact'
                : 'not_published_exact',
            'selected_by_assessment' => true,
            'automatic_assessment_status' => $isExecutable && ! $ambiguous
                ? 'used_by_assessment'
                : 'not_available_for_automatic_assessment',
            'automatic_assessment_label' => $isExecutable && ! $ambiguous
                ? 'Used by assessment'
                : 'Not available for automatic assessment',
            'application_year' => $applicationYear,
            'overlap_ambiguous' => $ambiguous,
            'reconciliation' => $reconciliation instanceof FeeRuleReconciliation ? [
                'id' => $reconciliation->id,
                'version' => $reconciliation->version,
                'legal_authority' => $reconciliation->legal_authority,
                'evidence_reference' => $reconciliation->evidence_reference,
                'decision_authority' => $reconciliation->decision_authority,
                'decision_reference' => $reconciliation->decision_reference,
                'effective_from' => $reconciliation->effective_from->toDateString(),
                'effective_until' => $reconciliation->effective_until?->toDateString(),
                'execution_status' => $reconciliation->execution_status->value,
                'execution_reason' => $reconciliation->execution_reason,
            ] : null,
            'plain_language_status' => $this->plainLanguageRuleStatus($feeRule, $source, $isExecutable, $ambiguous),
        ];
    }

    private function plainLanguageRuleStatus(
        FeeRule $feeRule,
        FeeRulePublicationSource $source,
        bool $isExecutable,
        bool $ambiguous,
    ): string {
        if ($ambiguous) {
            return 'Overlapping effective rule versions prevent a coherent current price from being shown.';
        }

        if (! $source->mayPublishExactAmount()) {
            return $source === FeeRulePublicationSource::MunicipalConfirmationRequired
                ? 'Recorded from the Revenue Code, but not yet available for automatic assessment.'
                : 'This source is not publishable as a municipal price.';
        }

        if (! $isExecutable) {
            return 'Municipal confirmation is still required before automatic assessment.';
        }

        return Str::headline($feeRule->calculation_type->value).' charge currently used by assessment.';
    }
}
