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
        $fingerprintPayload = $this->normalize([
            'evaluation_id' => $evaluation->id,
            'application' => $application,
            'items' => $items->map(fn (array $item): array => Arr::except($item, ['revision_id', 'actor_name']))->all(),
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
            'items' => $items->all(),
            'projected_charges' => $projectedCharges->all(),
            'pricing_issues' => $pricingIssues,
            'total_amount_cents' => $totalAmountCents,
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
     * @param  Collection<int, array<string, mixed>>  $items
     * @return list<int>
     */
    private function resolvedLineOfBusinessIds(BusinessPermitEvaluation $evaluation, Collection $items): array
    {
        $lineItem = $items->firstWhere('key', self::APPLICANT_LINES_ITEM_KEY);
        $ids = is_array($lineItem) ? data_get($lineItem, 'value.line_of_business_ids') : null;

        if (! is_array($ids)) {
            return $evaluation->permitApplication->lines
                ->pluck('line_of_business_id')
                ->filter()
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        return collect($ids)
            ->filter(fn (mixed $id): bool => is_int($id) || ctype_digit((string) $id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
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

        $charges->push([
            'key' => 'rule.'.$feeRule->id.'.line.'.($applicationLine?->id ?? 'none'),
            'item_type' => BusinessPermitEvaluationItemType::Charge->value,
            'responsible_party' => 'system',
            'applicability' => BusinessPermitEvaluationApplicability::Applicable->value,
            'resolution' => 'resolved',
            'source_classification' => BusinessPermitEvaluationSource::GovernedRule->value,
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

    /** @param list<int> $resolvedLineOfBusinessIds */
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

    /** @param array<mixed> $value @return array<mixed> */
    private function normalize(array $value): array
    {
        if (Arr::isAssoc($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => is_array($item) ? $this->normalize($item) : $item, $value);
    }
}
