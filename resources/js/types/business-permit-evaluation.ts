/**
 * Hand-aligned mirror of `App\Data\Evaluation\BusinessPermitEvaluationData`
 * and its nested Data objects (`app/Data/Evaluation/*.php`).
 *
 * This is a typed product boundary, not a second source of truth: every
 * field here is a read-only projection compiled by
 * `App\Actions\DescribeBusinessPermitEvaluation`. Mutations never post
 * this shape back — they call the narrow domain actions (confirm/correct/
 * counter-check/prepare-assessment) with an explicit expected version and
 * fingerprint.
 *
 * Kept as a hand-aligned file rather than a generated one for this pass;
 * see the Warp/Oz Evaluator scaffold report for why automatic
 * `spatie/laravel-typescript-transformer` generation was deferred.
 */

export type EvaluationApplicability =
    'applicable' | 'not_applicable' | 'undetermined';

export type EvaluationItemType = 'fact' | 'determination' | 'charge';

export type EvaluationLens = 'citizen' | 'internal';

/** Structured item values are domain-shaped (e.g. `{ amount_cents }`,
 * `{ inspection: {...} }`, `{ line_of_business_ids: number[] }`); never
 * assume a fixed shape beyond what a specific item's `key` implies. */
export type EvaluationValue =
    Record<string, unknown> | string | number | boolean | null;

export type EvaluationRevision = {
    version_sequence: number;
    action: string | null;
    applicability: EvaluationApplicability;
    value: EvaluationValue;
    source_classification: string | null;
    /** Always null under the citizen lens. */
    actor_name: string | null;
    reason: string | null;
    occurred_at: string | null;
};

export type EvaluationItem = {
    id: number;
    key: string;
    label: string;
    line_of_business_id: number | null;
    line_of_business_name: string | null;
    /** Internal product explanation of why this office owns the item. */
    department_selection_reason: string | null;
    item_type: EvaluationItemType;
    responsible_party: string;
    is_required: boolean;
    requires_confirmation: boolean;
    /** True when the current viewer is the authorized actor or holds the
     * responsible role for this item — an actor-specific fact, not part
     * of the shared Evaluation truth. */
    is_mine: boolean;
    applicability: EvaluationApplicability;
    resolution: string;
    action: string | null;
    /** The system/default proposal. Kept distinct from `resolved_value`
     * on purpose — never collapse the two into one `amount`. */
    default_value: EvaluationValue;
    default_source_classification: string | null;
    resolved_value: EvaluationValue;
    source_classification: string | null;
    reason: string | null;
    occurred_at: string | null;
    inspection_required: boolean;
    history: EvaluationRevision[];
};

export type EvaluationTreasuryCounterCheck = {
    assessment_id: number | null;
    /** Available only under the internal lens. */
    assessment_snapshot_hash: string | null;
    result: 'no_correction' | 'material_correction' | null;
    checked_at: string;
    checked_by: string;
    reason: string | null;
    /** Always null under the citizen lens. */
    evidence_provenance: string | null;
};

export type EvaluationVersion = {
    id: number;
    sequence: number;
    fingerprint: string;
    fingerprint_current: boolean;
    treasury_counter_check: EvaluationTreasuryCounterCheck | null;
};

export type EvaluationApplicationSummary = {
    id: number;
    application_number: string | null;
    tracking_reference: string | null;
    business_name: string;
    owner_name: string;
    type: string;
    year: number;
};

export type EvaluationDeclaredLine = {
    line_of_business_id: number;
    line_of_business_name: string | null;
    declared_gross_sales_cents: number | null;
    capital_investment_cents: number | null;
    quantity: number | null;
};

export type EvaluationResolvedLine = {
    id: number;
    name: string | null;
};

export type EvaluationProjectedCharge = {
    key: string;
    fee_rule_id: number;
    code: string;
    name: string;
    amount_cents: number;
    basis: string;
    basis_amount_cents: number | null;
    legal_basis: string | null;
    source_classification: string;
};

export type EvaluationWorkingPaperCharge = {
    identity: string;
    source_type: 'fee_rule' | 'evaluation_item';
    evaluation_item_id: number | null;
    fee_rule_id: number | null;
    scope: 'application' | 'line_of_business';
    permit_application_line_id: number | null;
    line_of_business_id: number | null;
    code: string;
    label: string;
    responsible_party: string;
    proposal_amount_cents: number | null;
    resolved_amount_cents: number | null;
    applicability: EvaluationApplicability;
    resolution: string;
    source_classification: string | null;
    action: string | null;
    reason: string | null;
    included_in_subtotal: boolean;
    included_in_grand_total: boolean;
};

export type EvaluationWorkingPaperLine = {
    line_of_business_id: number;
    permit_application_line_id: number | null;
    line_of_business_name: string | null;
    charges: EvaluationWorkingPaperCharge[];
    subtotal_amount_cents: number;
};

export type EvaluationFinancialWorkingPaper = {
    line_sections: EvaluationWorkingPaperLine[];
    application_charges: EvaluationWorkingPaperCharge[];
    application_subtotal_amount_cents: number;
    required_unresolved_charge_count: number;
    grand_total_available: boolean;
    grand_total_amount_cents: number | null;
};

export type EvaluationReadinessOutcome = {
    ready: boolean;
    issues: string[];
};

export type EvaluationReadiness = {
    commissioned: EvaluationReadinessOutcome;
    provisional_uat: EvaluationReadinessOutcome;
};

export type EvaluationLatestAssessment = {
    id: number;
    sequence: number;
    total_amount_cents: number;
    superseded: boolean;
    decision: string | null;
    evaluation_version_id: number | null;
    evaluation_fingerprint: string | null;
    /** The exact-version proof: true only when this Assessment was
     * prepared from this exact Evaluation version + fingerprint. */
    consumes_current_evaluation: boolean;
};

export type BusinessPermitEvaluationData = {
    id: number;
    version: EvaluationVersion;
    status_label: string;
    application: EvaluationApplicationSummary;
    applicant_declaration: EvaluationDeclaredLine[];
    municipal_resolved_lines: EvaluationResolvedLine[];
    items: EvaluationItem[];
    projected_charges: EvaluationProjectedCharge[];
    financial_working_paper: EvaluationFinancialWorkingPaper;
    current_evaluated_amount_cents: number;
    pricing_issues: string[];
    readiness: EvaluationReadiness;
    my_item_ids: number[];
    latest_assessment: EvaluationLatestAssessment | null;
    financial_lock: boolean;
    lens: EvaluationLens;
};

export type EvaluationLineOfBusinessOption = {
    id: number;
    code: string | null;
    name: string;
};

export type EvaluationCapabilities = {
    initialize: boolean;
    contribute: boolean;
    counter_check: boolean;
    correct_lines_of_business: boolean;
    prepare_assessment: boolean;
};
