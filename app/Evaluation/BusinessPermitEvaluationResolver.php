<?php

namespace App\Evaluation;

use App\Assessment\ApplicableFeeRuleQuery;
use App\Assessment\AssessmentCalculator;
use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationItemType;
use App\Enums\BusinessPermitEvaluationRevisionAction;
use App\Enums\BusinessPermitEvaluationSource;
use App\Enums\FeeRuleScope;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\BusinessPermitEvaluationItemRevision;
use App\Models\BusinessPermitEvaluationVersion;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PermitApplicationLine;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class BusinessPermitEvaluationResolver
{
    public const APPLICANT_LINES_ITEM_KEY = 'applicant.lines_of_business';

    public function __construct(
        private readonly ApplicableFeeRuleQuery $applicableFeeRuleQuery,
        private readonly AssessmentCalculator $calculator,
    ) {}

    /**
     * @return array{
     *   evaluation_id: int,
     *   version_id: int,
     *   version_sequence: int,
     *   persisted_fingerprint: string,
     *   current_fingerprint: string,
     *   fingerprint_current: bool,
     *   application: array<string, mixed>,
     *   resolved_line_of_business_ids: list<int>,
     *   items: list<array<string, mixed>>,
     *   projected_charges: list<array<string, mixed>>,
     *   financial_working_paper: array<string, mixed>,
     *   pricing_issues: list<string>,
     *   total_amount_cents: int
     * }
     */
    public function resolve(
        BusinessPermitEvaluation $evaluation,
        ?BusinessPermitEvaluationVersion $version = null,
    ): array {
        $evaluation->loadMissing([
            'permitApplication.lines.lineOfBusiness',
            'items.revisions.version',
            'items.revisions.actor',
            'currentVersion.counterCheck',
        ]);
        $version ??= $evaluation->currentVersion;

        if (! $version instanceof BusinessPermitEvaluationVersion) {
            throw new \LogicException("Evaluation [{$evaluation->id}] has no version.");
        }

        $items = $evaluation->items
            ->map(fn (BusinessPermitEvaluationItem $item): array => $this->resolveItem($item, $version))
            ->values();
        $lineOfBusinessIds = $this->resolvedLineOfBusinessIds($evaluation, $items);
        [$projectedCharges, $pricingIssues] = $this->projectRuleCharges($evaluation, $lineOfBusinessIds);
        $application = $this->applicationProjection($evaluation, $lineOfBusinessIds);
        $totalAmountCents = $projectedCharges->sum('amount_cents')
            + $items
                ->where('item_type', BusinessPermitEvaluationItemType::Charge->value)
                ->where('applicability', BusinessPermitEvaluationApplicability::Applicable->value)
                ->where('resolution', 'resolved')
                ->sum(fn (array $item): int => (int) data_get($item, 'value.amount_cents', 0));
        $financialWorkingPaper = $this->financialWorkingPaper(
            $evaluation,
            $lineOfBusinessIds,
            $items,
            $projectedCharges,
            $totalAmountCents,
        );
        $fingerprintPayload = $this->normalize([
            'evaluation_id' => $evaluation->id,
            'application' => $application,
            'items' => $items->map(fn (array $item): array => $this->fingerprintItem($item))->all(),
            'projected_charges' => $projectedCharges->map(fn (array $charge): array => Arr::except($charge, ['fee_rule', 'application_line']))->all(),
            'pricing_issues' => $pricingIssues,
        ]);
        $currentFingerprint = hash('sha256', json_encode(
            $fingerprintPayload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));

        return [
            'evaluation_id' => $evaluation->id,
            'version_id' => $version->id,
            'version_sequence' => $version->sequence,
            'persisted_fingerprint' => $version->fingerprint,
            'current_fingerprint' => $currentFingerprint,
            'fingerprint_current' => hash_equals($version->fingerprint, $currentFingerprint),
            'application' => $application,
            'resolved_line_of_business_ids' => $lineOfBusinessIds,
            'items' => array_values($items->all()),
            'projected_charges' => array_values($projectedCharges->all()),
            'financial_working_paper' => $financialWorkingPaper,
            'pricing_issues' => $pricingIssues,
            'total_amount_cents' => $totalAmountCents,
        ];
    }

    /**
     * @param  list<int>  $lineOfBusinessIds
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  Collection<int, array<string, mixed>>  $projectedCharges
     * @return array<string, mixed>
     */
    private function financialWorkingPaper(
        BusinessPermitEvaluation $evaluation,
        array $lineOfBusinessIds,
        Collection $items,
        Collection $projectedCharges,
        int $totalAmountCents,
    ): array {
        $lineNames = LineOfBusiness::query()->whereIn('id', $lineOfBusinessIds)->pluck('name', 'id');
        $applicationLines = $evaluation->permitApplication->lines->keyBy('line_of_business_id');
        $charges = $projectedCharges->map(fn (array $charge): array => [
            'identity' => $charge['key'],
            'source_type' => 'fee_rule',
            'evaluation_item_id' => null,
            'fee_rule_id' => $charge['fee_rule_id'],
            'scope' => $charge['line_of_business_id'] === null ? 'application' : 'line_of_business',
            'permit_application_line_id' => $charge['permit_application_line_id'],
            'line_of_business_id' => $charge['line_of_business_id'],
            'code' => $charge['code'],
            'label' => $charge['name'],
            'responsible_party' => $charge['responsible_party'],
            'proposal_amount_cents' => $charge['amount_cents'],
            'resolved_amount_cents' => $charge['amount_cents'],
            'applicability' => $charge['applicability'],
            'resolution' => $charge['resolution'],
            'source_classification' => $charge['source_classification'],
            'action' => 'governed_rule_projection',
            'reason' => null,
            'included_in_subtotal' => true,
            'included_in_grand_total' => true,
        ])->concat($items
            ->where('item_type', BusinessPermitEvaluationItemType::Charge->value)
            ->map(fn (array $item): array => [
                'identity' => $item['key'],
                'source_type' => 'evaluation_item',
                'evaluation_item_id' => $item['id'],
                'fee_rule_id' => null,
                'scope' => data_get($item, 'metadata.charge_scope', 'application'),
                'permit_application_line_id' => data_get($item, 'metadata.permit_application_line_id'),
                'line_of_business_id' => data_get($item, 'metadata.line_of_business_id'),
                'code' => data_get($item, 'metadata.code', str($item['key'])->upper()->replace('.', '-')->toString()),
                'label' => data_get($item, 'metadata.label', str($item['key'])->headline()->toString()),
                'responsible_party' => $item['responsible_party'],
                'proposal_amount_cents' => data_get($item, 'default_value.amount_cents'),
                'resolved_amount_cents' => $item['resolution'] === 'resolved'
                    && $item['applicability'] === BusinessPermitEvaluationApplicability::Applicable->value
                        ? data_get($item, 'value.amount_cents')
                        : null,
                'applicability' => $item['applicability'],
                'resolution' => $item['resolution'],
                'source_classification' => $item['source_classification'],
                'action' => $item['action'],
                'reason' => $item['reason'],
                'included_in_subtotal' => $item['resolution'] === 'resolved'
                    && $item['applicability'] === BusinessPermitEvaluationApplicability::Applicable->value,
                'included_in_grand_total' => $item['resolution'] === 'resolved'
                    && $item['applicability'] === BusinessPermitEvaluationApplicability::Applicable->value,
            ]))
            ->values();
        $requiredUnresolved = $items->filter(fn (array $item): bool => $item['item_type'] === BusinessPermitEvaluationItemType::Charge->value
            && $item['is_required']
            && $item['applicability'] !== BusinessPermitEvaluationApplicability::NotApplicable->value
            && $item['resolution'] !== 'resolved')->count();

        $sections = collect($lineOfBusinessIds)->map(function (int $lineOfBusinessId) use ($charges, $lineNames, $applicationLines): array {
            $sectionCharges = $charges->where('scope', 'line_of_business')->where('line_of_business_id', $lineOfBusinessId)->values();

            return [
                'line_of_business_id' => $lineOfBusinessId,
                'permit_application_line_id' => $applicationLines->get($lineOfBusinessId)?->id,
                'line_of_business_name' => $lineNames->get($lineOfBusinessId),
                'charges' => $sectionCharges->all(),
                'subtotal_amount_cents' => $sectionCharges->where('included_in_subtotal', true)->sum('resolved_amount_cents'),
            ];
        })->all();
        $applicationCharges = $charges->where('scope', 'application')->values();

        return [
            'line_sections' => $sections,
            'application_charges' => $applicationCharges->all(),
            'application_subtotal_amount_cents' => $applicationCharges->where('included_in_subtotal', true)->sum('resolved_amount_cents'),
            'required_unresolved_charge_count' => $requiredUnresolved,
            'grand_total_available' => $requiredUnresolved === 0,
            'grand_total_amount_cents' => $requiredUnresolved === 0 ? $totalAmountCents : null,
        ];
    }

    /** @return array<string, mixed> */
    private function resolveItem(BusinessPermitEvaluationItem $item, BusinessPermitEvaluationVersion $version): array
    {
        $revisions = $item->revisions
            ->filter(fn (BusinessPermitEvaluationItemRevision $candidate): bool => $candidate->version->sequence <= $version->sequence)
            ->sortBy(fn (BusinessPermitEvaluationItemRevision $candidate): int => $candidate->version->sequence)
            ->values();
        $revision = $revisions->last();
        $defaultRevision = $revisions->first(fn (BusinessPermitEvaluationItemRevision $candidate): bool => $candidate->action === BusinessPermitEvaluationRevisionAction::Proposal);

        $resolution = match (true) {
            ! $revision instanceof BusinessPermitEvaluationItemRevision => 'unresolved',
            $revision->action === BusinessPermitEvaluationRevisionAction::Supersession => 'superseded',
            $revision->applicability === BusinessPermitEvaluationApplicability::Undetermined => 'unresolved',
            $revision->applicability === BusinessPermitEvaluationApplicability::NotApplicable => 'resolved',
            $item->requires_confirmation
                && ! in_array($revision->action, [
                    BusinessPermitEvaluationRevisionAction::Confirmation,
                    BusinessPermitEvaluationRevisionAction::Correction,
                    BusinessPermitEvaluationRevisionAction::AuthorizedDetermination,
                ], true) => 'awaiting_responsible_confirmation',
            default => 'resolved',
        };

        return [
            'id' => $item->id,
            'key' => $item->key,
            'item_type' => $item->item_type->value,
            'responsible_party' => $item->responsible_party,
            'is_required' => $item->is_required,
            'requires_confirmation' => $item->requires_confirmation,
            'metadata' => $item->metadata,
            'revision_id' => $revision?->id,
            'action' => $revision?->action->value,
            'applicability' => $revision?->applicability->value ?? BusinessPermitEvaluationApplicability::Undetermined->value,
            'resolution' => $resolution,
            'value' => $revision?->value,
            'default_value' => $defaultRevision?->value,
            'default_source_classification' => $defaultRevision?->source_classification->value,
            'source_classification' => $revision?->source_classification->value,
            'actor_id' => $revision?->actor_id,
            'actor_name' => $revision?->actor?->name,
            'reason' => $revision?->reason,
            'occurred_at' => $revision?->occurred_at->toIso8601String(),
            'revision_history' => $revisions->map(fn (BusinessPermitEvaluationItemRevision $candidate): array => [
                'revision_id' => $candidate->id,
                'version_id' => $candidate->business_permit_evaluation_version_id,
                'version_sequence' => $candidate->version->sequence,
                'action' => $candidate->action->value,
                'applicability' => $candidate->applicability->value,
                'value' => $candidate->value,
                'source_classification' => $candidate->source_classification->value,
                'actor_id' => $candidate->actor_id,
                'actor_name' => $candidate->actor?->name,
                'reason' => $candidate->reason,
                'occurred_at' => $candidate->occurred_at->toIso8601String(),
                'dependency_fingerprint' => $candidate->dependency_fingerprint,
            ])->all(),
        ];
    }

    /**
     * Keep human-readable names out of financial identity. Actor IDs remain bound while
     * later profile-name corrections cannot make an otherwise unchanged version stale.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function fingerprintItem(array $item): array
    {
        $history = $item['revision_history'] ?? [];

        $item['revision_history'] = is_array($history)
            ? array_map(
                fn (mixed $revision): mixed => is_array($revision)
                    ? Arr::except($revision, ['actor_name'])
                    : $revision,
                $history,
            )
            : [];

        return Arr::except($item, ['revision_id', 'actor_name']);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return list<int>
     */
    private function resolvedLineOfBusinessIds(BusinessPermitEvaluation $evaluation, Collection $items): array
    {
        $lineItem = $items->firstWhere('key', self::APPLICANT_LINES_ITEM_KEY);
        $ids = is_array($lineItem) ? data_get($lineItem, 'value.line_of_business_ids') : null;

        if (! is_array($ids)) {
            return array_values($evaluation->permitApplication->lines
                ->pluck('line_of_business_id')
                ->filter()
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->sort()
                ->values()->all());
        }

        return array_values(collect($ids)
            ->filter(fn (mixed $id): bool => is_int($id) || ctype_digit((string) $id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()->all());
    }

    /**
     * @param  list<int>  $lineOfBusinessIds
     * @return array{Collection<int, array<string, mixed>>, list<string>}
     */
    private function projectRuleCharges(BusinessPermitEvaluation $evaluation, array $lineOfBusinessIds): array
    {
        $permitApplication = $evaluation->permitApplication;
        $feeRules = $this->applicableFeeRuleQuery->forApplicationFacts(
            $permitApplication->type,
            $permitApplication->application_year,
            $lineOfBusinessIds,
        );
        $applicationLinesByBusiness = $permitApplication->lines
            ->filter(fn (PermitApplicationLine $line): bool => $line->line_of_business_id !== null)
            ->keyBy('line_of_business_id');
        $charges = collect();
        $issues = [];

        foreach ($feeRules as $feeRule) {
            if ($feeRule->scope === FeeRuleScope::Application) {
                $this->appendProjectedCharge($charges, $issues, $feeRule);

                continue;
            }

            if ($feeRule->line_of_business_id === null
                || ! in_array($feeRule->line_of_business_id, $lineOfBusinessIds, true)) {
                continue;
            }

            $this->appendProjectedCharge(
                $charges,
                $issues,
                $feeRule,
                $applicationLinesByBusiness->get($feeRule->line_of_business_id),
            );
        }

        return [$charges->sortBy('key')->values(), array_values(array_unique($issues))];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $charges
     * @param  list<string>  $issues
     */
    private function appendProjectedCharge(
        Collection $charges,
        array &$issues,
        FeeRule $feeRule,
        ?PermitApplicationLine $applicationLine = null,
    ): void {
        try {
            $calculation = $this->calculator->calculate($feeRule, $applicationLine);
        } catch (UnsupportedAssessmentPolicy $exception) {
            $issues[] = $exception->getMessage();

            return;
        }

        $source = data_get($feeRule->metadata, 'semantic_classification') === BusinessPermitEvaluationSource::ProvisionalUat->value
            ? BusinessPermitEvaluationSource::ProvisionalUat
            : BusinessPermitEvaluationSource::GovernedRule;

        $charges->push([
            'key' => 'rule.'.$feeRule->id.'.line.'.($applicationLine instanceof PermitApplicationLine ? $applicationLine->id : 'none'),
            'item_type' => BusinessPermitEvaluationItemType::Charge->value,
            'responsible_party' => 'system',
            'applicability' => BusinessPermitEvaluationApplicability::Applicable->value,
            'resolution' => 'resolved',
            'source_classification' => $source->value,
            'fee_rule_id' => $feeRule->id,
            'permit_application_line_id' => $applicationLine?->id,
            'line_of_business_id' => $feeRule->scope === FeeRuleScope::LineOfBusiness
                ? $feeRule->line_of_business_id
                : null,
            'code' => $feeRule->code,
            'name' => $feeRule->name,
            'category' => $feeRule->category->value,
            'calculation_type' => $feeRule->calculation_type->value,
            'basis' => $feeRule->basis,
            'basis_amount_cents' => $calculation['basis_amount_cents'],
            'amount_cents' => $calculation['amount_cents'],
            'legal_basis' => $feeRule->legal_basis,
            'rule_snapshot' => $calculation['rule_snapshot'],
            'fee_rule' => $feeRule,
            'application_line' => $applicationLine,
        ]);
    }

    /**
     * @param  list<int>  $resolvedLineOfBusinessIds
     * @return array<string, mixed>
     */
    private function applicationProjection(BusinessPermitEvaluation $evaluation, array $resolvedLineOfBusinessIds): array
    {
        $permitApplication = $evaluation->permitApplication;

        return [
            'permit_application_id' => $permitApplication->id,
            'business_id' => $permitApplication->business_id,
            'application_type' => $permitApplication->type->value,
            'application_year' => $permitApplication->application_year,
            'submitted_at' => $permitApplication->submitted_at?->toIso8601String(),
            'declared_lines' => $permitApplication->lines
                ->sortBy('id')
                ->values()
                ->map(fn (PermitApplicationLine $line): array => [
                    'permit_application_line_id' => $line->id,
                    'line_of_business_id' => $line->line_of_business_id,
                    'declared_gross_sales_cents' => $line->declared_gross_sales_cents,
                    'capital_investment_cents' => $line->capital_investment_cents,
                    'quantity' => $line->quantity,
                    'started_on' => $line->started_on?->toDateString(),
                ])->all(),
            'resolved_line_of_business_ids' => $resolvedLineOfBusinessIds,
        ];
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function normalize(array $value): array
    {
        if (Arr::isAssoc($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => is_array($item) ? $this->normalize($item) : $item, $value);
    }
}
