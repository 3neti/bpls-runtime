<?php

namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;

/**
 * The typed product boundary for the Business Permit Evaluator.
 *
 * This object is a compiled, read-only projection of the canonical
 * domain (`BusinessPermitEvaluation` + its current
 * `BusinessPermitEvaluationVersion`/items/revisions). It is never
 * persisted independently and is never accepted back from the frontend
 * as a whole. Mutations flow exclusively through the existing narrow
 * domain actions (e.g. `CompleteBusinessPermitEvaluationResponsibility`,
 * `CorrectEvaluationLinesOfBusiness`, `RecordBusinessPermitEvaluationCounterCheck`),
 * each of which validates the expected version/fingerprint, records an
 * append-only revision, and triggers recompilation of this projection.
 *
 * `version.sequence` + `version.fingerprint` are the concurrency token a
 * mutation must echo back; a stale token fails closed in the domain
 * action, not here.
 */
class BusinessPermitEvaluationData extends Data
{
    /**
     * @param  array<int, EvaluationDeclaredLineData>  $applicant_declaration
     * @param  array<int, EvaluationResolvedLineData>  $municipal_resolved_lines
     * @param  array<int, EvaluationItemData>  $items
     * @param  array<int, EvaluationProjectedChargeData>  $projected_charges
     * @param  array<string, mixed>  $financial_working_paper
     * @param  array<int, string>  $pricing_issues
     * @param  array<int, int>  $my_item_ids
     */
    public function __construct(
        public readonly int $id,
        public readonly EvaluationVersionData $version,
        public readonly string $status_label,
        public readonly EvaluationApplicationSummaryData $application,
        public readonly array $applicant_declaration,
        public readonly array $municipal_resolved_lines,
        public readonly array $items,
        public readonly array $projected_charges,
        public readonly array $financial_working_paper,
        public readonly int $current_evaluated_amount_cents,
        public readonly array $pricing_issues,
        public readonly EvaluationReadinessData $readiness,
        public readonly array $my_item_ids,
        public readonly ?EvaluationLatestAssessmentData $latest_assessment,
        public readonly bool $financial_lock,
        public readonly string $lens,
    ) {}
}
