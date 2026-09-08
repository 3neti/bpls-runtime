<?php

namespace App\Http\Controllers\Staff;

use App\Actions\AnalyzeRevenueCodeSchedule;
use App\Actions\ProposeFeeRuleRevision;
use App\Enums\FeeCatalogVersionStatus;
use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\FeeRuleScope;
use App\Enums\RevenueCodeProvisionStatus;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProposeFeeRuleRevisionRequest;
use App\Models\BusinessDivision;
use App\Models\FeeRule;
use App\Models\FeeRuleAuditEvent;
use App\Models\FeeRuleOfficeAssignment;
use App\Models\FeeRuleRange;
use App\Models\RevenueCodeProvision;
use App\Models\RevenueCodeProvisionClause;
use App\Models\RevenueCodeProvisionRow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FeeRuleController extends Controller
{
    public function __construct(
        private readonly AnalyzeRevenueCodeSchedule $analyzeRevenueCodeSchedule,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize(UserPermission::ViewFeeRules->value);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', Rule::enum(FeeRuleCategory::class)],
            'scope' => ['nullable', Rule::enum(FeeRuleScope::class)],
            'calculation_type' => ['nullable', Rule::enum(FeeRuleCalculationType::class)],
            'business_division' => ['nullable', 'string', 'max:80'],
            'office' => ['nullable', 'string', 'max:80'],
            'determination_channel' => ['nullable', Rule::enum(FeeDeterminationChannel::class)],
            'application_type' => ['nullable', Rule::in(['new', 'renewal'])],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'status' => ['nullable', Rule::in(['active', 'incomplete', 'inactive', 'superseded'])],
        ]);

        $feeRules = FeeRule::query()
            ->with(['lineOfBusiness', 'lineOfBusinesses', 'businessDivision', 'feeCategory', 'revenueAccount', 'officeAssignments', 'catalogVersion', 'currentReconciliation'])
            ->withCount('ranges')
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('legal_basis', 'like', "%{$search}%")
                        ->orWhere('legacy_source_id', 'like', "%{$search}%")
                        ->orWhereHas('revenueAccount', fn ($query) => $query->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                        ->orWhereHas('businessDivision', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('officeAssignments', fn ($query) => $query->where('office_label', 'like', "%{$search}%"))
                        ->orWhereHas('lineOfBusinesses', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('lineOfBusiness', function ($query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['scope'] ?? null, fn ($query, string $scope) => $query->where('scope', $scope))
            ->when($filters['calculation_type'] ?? null, fn ($query, string $calculationType) => $query->where('calculation_type', $calculationType))
            ->when($filters['business_division'] ?? null, fn ($query, string $code) => $query->whereHas('businessDivision', fn ($query) => $query->where('code', $code)))
            ->when($filters['office'] ?? null, fn ($query, string $code) => $query->whereHas('officeAssignments', fn ($query) => $query->where('office_code', $code)))
            ->when($filters['determination_channel'] ?? null, fn ($query, string $channel) => $query->where('determination_channel', $channel))
            ->when($filters['application_type'] ?? null, fn ($query, string $type) => $query->whereJsonContains('metadata->application_types', $type))
            ->when($filters['year'] ?? null, fn ($query, int $year) => $query->whereDate('effective_from', '<=', "{$year}-12-31")->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', "{$year}-01-01")))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('metadata->catalog_status')->orWhere('metadata->catalog_status', '!=', 'incomplete'))
                ->where(fn ($query) => $query->whereNull('fee_catalog_version_id')->orWhereHas('catalogVersion', fn ($query) => $query->where('status', FeeCatalogVersionStatus::Active))))
            ->when(($filters['status'] ?? null) === 'incomplete', fn ($query) => $query->where('metadata->catalog_status', 'incomplete'))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false)->where(fn ($query) => $query->whereNull('metadata->catalog_status')->orWhere('metadata->catalog_status', '!=', 'incomplete')))
            ->when(($filters['status'] ?? null) === 'superseded', fn ($query) => $query->whereHas('catalogVersion', fn ($query) => $query->where('status', FeeCatalogVersionStatus::Superseded)))
            ->orderByRaw('case when fee_catalog_version_id is null then 0 else 1 end')
            ->orderByRaw('case when fee_catalog_version_id is null then code end')
            ->orderByRaw('business_division_id is null')
            ->orderBy('business_division_id')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (FeeRule $feeRule): array => $this->feeRulePayload($feeRule));

        return Inertia::render('fee-rules/Index', [
            'filters' => [
                'q' => $filters['q'] ?? '',
                'category' => $filters['category'] ?? '',
                'scope' => $filters['scope'] ?? '',
                'calculation_type' => $filters['calculation_type'] ?? '',
                'business_division' => $filters['business_division'] ?? '',
                'office' => $filters['office'] ?? '',
                'determination_channel' => $filters['determination_channel'] ?? '',
                'application_type' => $filters['application_type'] ?? '',
                'year' => $filters['year'] ?? '',
                'status' => $filters['status'] ?? 'active',
            ],
            'feeRules' => $feeRules,
            'revenueCodeProvisions' => RevenueCodeProvision::query()
                ->with(['feeRule.currentReconciliation'])
                ->orderBy('section_reference')
                ->get()
                ->map(fn (RevenueCodeProvision $provision): array => [
                    'id' => $provision->id,
                    'code' => $provision->code,
                    'section_reference' => $provision->section_reference,
                    'title' => $provision->title,
                    'provision_type' => $provision->provision_type->value,
                    'evidence_summary' => $provision->evidence_summary,
                    'reconciliation_status' => $provision->reconciliation_status->value,
                    'reconciliation_notes' => $provision->reconciliation_notes,
                    'known_ambiguities' => $provision->metadata['known_ambiguities'] ?? [],
                    'fee_rule' => $provision->feeRule ? [
                        'id' => $provision->feeRule->id,
                        'code' => $provision->feeRule->code,
                        'name' => $provision->feeRule->name,
                        'execution_status' => $provision->feeRule->currentReconciliation?->execution_status->value,
                    ] : null,
                ])
                ->values()
                ->all(),
            'revenueCodeScheduleMatrices' => $this->scheduleMatricesPayload(),
            'revenueCodePolicyBoundaries' => $this->policyBoundaryPayload(),
            'summary' => [
                'total_rules' => FeeRule::query()->count(),
                'active_rules' => FeeRule::query()->where('is_active', true)->count(),
                'mrc_rules' => FeeRule::query()->where('legacy_source_id', 'like', 'LEGAL-MRC-001%')->count(),
                'blocked_policy_count' => FeeRule::query()
                    ->whereHas('currentReconciliation', fn ($query) => $query->where('execution_status', FeeRuleExecutionStatus::Blocked))
                    ->count(),
                'executable_rule_count' => FeeRule::query()
                    ->whereHas('currentReconciliation', fn ($query) => $query->where('execution_status', FeeRuleExecutionStatus::Executable))
                    ->count(),
                'provisions_recorded' => RevenueCodeProvision::query()->count(),
                'provisions_requiring_reconciliation' => RevenueCodeProvision::query()
                    ->where('reconciliation_status', RevenueCodeProvisionStatus::ReconciliationRequired)
                    ->count(),
                'provisions_linked_to_rules' => RevenueCodeProvision::query()->whereNotNull('fee_rule_id')->count(),
                'policy_boundary_clauses' => RevenueCodeProvisionClause::query()->count(),
                'policy_boundary_clauses_requiring_reconciliation' => RevenueCodeProvisionClause::query()
                    ->where('reconciliation_status', RevenueCodeProvisionStatus::ReconciliationRequired)
                    ->count(),
            ],
            'categories' => $this->options(FeeRuleCategory::cases()),
            'scopes' => $this->options(FeeRuleScope::cases()),
            'calculationTypes' => $this->options(FeeRuleCalculationType::cases()),
            'businessDivisions' => BusinessDivision::query()->where('is_active', true)->orderBy('name')->get(['code', 'name'])->map(fn (BusinessDivision $division): array => ['value' => $division->code, 'label' => str($division->name)->lower()->headline()->toString()])->all(),
            'offices' => FeeRuleOfficeAssignment::query()->select(['office_code', 'office_label'])->distinct()->orderBy('office_label')->get()->map(fn ($office): array => ['value' => $office->office_code, 'label' => $office->office_label])->all(),
            'determinationChannels' => collect(FeeDeterminationChannel::cases())->map(fn (FeeDeterminationChannel $channel): array => ['value' => $channel->value, 'label' => match ($channel) {
                FeeDeterminationChannel::ConcernedOfficePaymentOrder => 'Concerned office',
                FeeDeterminationChannel::TreasuryLineOfBusiness => 'Treasury LOB',
                FeeDeterminationChannel::AutomaticAssessment => 'Automatic assessment',
                FeeDeterminationChannel::ReferenceOnly => 'Reference only',
            }])->all(),
        ]);
    }

    public function show(FeeRule $feeRule): Response
    {
        Gate::authorize(UserPermission::ViewFeeRules->value);

        $feeRule->load([
            'lineOfBusiness',
            'lineOfBusinesses',
            'businessDivision',
            'feeCategory',
            'revenueAccount',
            'officeAssignments',
            'catalogVersion',
            'currentReconciliation',
            'ranges' => fn ($query) => $query->orderBy('min_basis_cents'),
            'revisions.proposedBy',
            'auditEvents.actor',
        ]);

        return Inertia::render('fee-rules/Show', [
            'feeRule' => [
                ...$this->feeRulePayload($feeRule),
                'ranges' => $feeRule->ranges->map(fn (FeeRuleRange $range): array => [
                    'id' => $range->id,
                    'min_basis_cents' => $range->min_basis_cents,
                    'max_basis_cents' => $range->max_basis_cents,
                    'amount_cents' => $range->amount_cents,
                    'rate_basis_points' => $range->rate_basis_points,
                ])->values()->all(),
                'revisions' => $feeRule->revisions->sortByDesc('version')->map(fn ($revision): array => [
                    'id' => $revision->id,
                    'version' => $revision->version,
                    'status' => $revision->status,
                    'currency' => $revision->currency,
                    'previous_amount_minor' => $revision->previous_amount_minor,
                    'proposed_amount_minor' => $revision->proposed_amount_minor,
                    'effective_from' => $revision->effective_from->toDateString(),
                    'effective_until' => $revision->effective_until?->toDateString(),
                    'reason' => $revision->reason,
                    'authority' => $revision->authority,
                    'proposed_by' => $revision->proposedBy?->name,
                    'proposed_at' => $revision->proposed_at->toIso8601String(),
                    'executable' => false,
                ])->values()->all(),
                'audit_events' => $feeRule->auditEvents->sortByDesc('occurred_at')->map(fn (FeeRuleAuditEvent $event): array => [
                    'id' => $event->id,
                    'event' => $event->event,
                    'actor' => $event->actor?->name,
                    'occurred_at' => $event->occurred_at->toIso8601String(),
                    'snapshot' => $event->snapshot,
                ])->values()->all(),
            ],
            'scopeNote' => 'This detail page is read-only evidence. A recorded ordinance extract is executable only when its current reconciliation explicitly authorizes deterministic execution.',
            'canProposeRevision' => auth()->user()?->can(UserPermission::ManageFeeRules->value) ?? false,
        ]);
    }

    public function proposeRevision(ProposeFeeRuleRevisionRequest $request, FeeRule $feeRule, ProposeFeeRuleRevision $propose): RedirectResponse
    {
        Gate::authorize(UserPermission::ManageFeeRules->value);
        $data = $request->validated();
        $propose->handle(
            $feeRule,
            $data['proposed_amount_minor'],
            $data['effective_from'],
            $data['effective_until'] ?? null,
            $data['reason'],
            $data['authority'],
            auth()->user(),
        );

        return back()->with('status', 'Fee revision recorded as Proposed — not executable.');
    }

    /**
     * @return array<string, mixed>
     */
    private function feeRulePayload(FeeRule $feeRule): array
    {
        return [
            'id' => $feeRule->id,
            'code' => $feeRule->code,
            'name' => $feeRule->name,
            'category' => $feeRule->category->value,
            'scope' => $feeRule->scope->value,
            'family' => $feeRule->scope === FeeRuleScope::Application ? 'application_wide' : 'line_of_business',
            'family_label' => $feeRule->scope === FeeRuleScope::Application ? 'Application-wide Fee' : 'Line-of-Business Fee',
            'currency' => 'PHP',
            'responsible_office' => data_get($feeRule->metadata, 'responsible_office'),
            'calculation_type' => $feeRule->calculation_type->value,
            'basis' => $feeRule->basis,
            'amount_cents' => $feeRule->amount_cents,
            'rate_basis_points' => $feeRule->rate_basis_points,
            'effective_from' => $feeRule->effective_from->toDateString(),
            'effective_until' => $feeRule->effective_until?->toDateString(),
            'is_active' => $feeRule->is_active,
            'legal_basis' => $feeRule->legal_basis,
            'legacy_source_id' => $feeRule->legacy_source_id,
            'revenue_code' => $feeRule->revenueAccount?->code,
            'business_division' => $feeRule->businessDivision ? ['code' => $feeRule->businessDivision->code, 'name' => str($feeRule->businessDivision->name)->lower()->headline()->toString()] : null,
            'lines_of_business' => $feeRule->lineOfBusinesses->map(fn ($line): array => ['id' => $line->id, 'code' => $line->code, 'name' => $line->name])->values()->all(),
            'owner' => $this->owner($feeRule),
            'determination_channel' => $feeRule->determination_channel->value,
            'amount_basis' => $this->amountBasis($feeRule),
            'display_status' => match (true) {
                $feeRule->catalogVersion?->status === FeeCatalogVersionStatus::Superseded => 'Superseded',
                data_get($feeRule->metadata, 'catalog_status') === 'incomplete' => 'Incomplete',
                $feeRule->is_active => 'Active',
                default => 'Inactive',
            },
            'line_of_business' => $feeRule->lineOfBusiness ? [
                'id' => $feeRule->lineOfBusiness->id,
                'code' => $feeRule->lineOfBusiness->code,
                'name' => $feeRule->lineOfBusiness->name,
            ] : null,
            'range_count' => (int) ($feeRule->ranges_count ?? $feeRule->ranges()->count()),
            'catalog_status' => $feeRule->metadata['catalog_status'] ?? null,
            'application_types' => $feeRule->metadata['application_types'] ?? null,
            'policy_boundaries' => $feeRule->metadata['policy_boundaries'] ?? [],
            'policy_note' => $feeRule->metadata['policy_note'] ?? null,
            'reconciliation_required' => ($feeRule->metadata['reconciliation_required'] ?? false) === true,
            'current_reconciliation' => $feeRule->currentReconciliation ? [
                'id' => $feeRule->currentReconciliation->id,
                'version' => $feeRule->currentReconciliation->version,
                'legal_authority' => $feeRule->currentReconciliation->legal_authority,
                'evidence_reference' => $feeRule->currentReconciliation->evidence_reference,
                'original_text' => $feeRule->currentReconciliation->original_text,
                'normalized_interpretation' => $feeRule->currentReconciliation->normalized_interpretation,
                'decision_authority' => $feeRule->currentReconciliation->decision_authority,
                'decision_reference' => $feeRule->currentReconciliation->decision_reference,
                'effective_from' => $feeRule->currentReconciliation->effective_from->toDateString(),
                'effective_until' => $feeRule->currentReconciliation->effective_until?->toDateString(),
                'execution_status' => $feeRule->currentReconciliation->execution_status->value,
                'execution_reason' => $feeRule->currentReconciliation->execution_reason,
                'decided_at' => $feeRule->currentReconciliation->decided_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function amountBasis(FeeRule $feeRule): string
    {
        if ($feeRule->calculation_type === FeeRuleCalculationType::Fixed && $feeRule->amount_cents > 0) {
            return 'Fixed amount';
        }
        if ($feeRule->calculation_type === FeeRuleCalculationType::Range) {
            return match ($feeRule->basis) {
                'declared_gross_sales' => 'Tiered by gross sales',
                'capital_investment' => 'Tiered by capital investment',
                default => 'Tiered amount entered during review',
            };
        }
        if ($feeRule->calculation_type === FeeRuleCalculationType::Formula) {
            $formula = strtolower((string) (data_get($feeRule->metadata, 'formula') ?? data_get($feeRule->metadata, 'legacy_formula')));

            return match (true) {
                str_contains($formula, 'employee') => 'Based on employees',
                str_contains($formula, 'area') => 'Based on business area',
                default => 'Formula amount entered during review',
            };
        }

        $assignment = $feeRule->officeAssignments->first();
        $office = $assignment instanceof FeeRuleOfficeAssignment ? $assignment->office_label : null;

        return $office === null ? 'Amount entered during review' : "Amount entered by {$office}";
    }

    private function owner(FeeRule $feeRule): string
    {
        $assignment = $feeRule->officeAssignments->first();
        if ($assignment instanceof FeeRuleOfficeAssignment) {
            return $assignment->office_label;
        }

        return match ($feeRule->determination_channel) {
            FeeDeterminationChannel::TreasuryLineOfBusiness => 'Municipal Treasury',
            FeeDeterminationChannel::AutomaticAssessment => 'Assessment',
            FeeDeterminationChannel::ReferenceOnly => 'Reference only',
            default => 'Concerned office',
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function scheduleMatricesPayload(): array
    {
        return RevenueCodeProvision::query()
            ->with('feeRule.currentReconciliation')
            ->whereIn('code', [
                'MRC-2A-02-A-MANUFACTURERS',
                'MRC-2A-02-B-WHOLESALERS',
                'MRC-2A-02-E-CONTRACTORS',
                'MRC-2A-02-G-ENUMERATED-SERVICES',
            ])
            ->orderBy('section_reference')
            ->get()
            ->map(fn (RevenueCodeProvision $provision): array => $this->scheduleMatrixPayload($provision))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function scheduleMatrixPayload(RevenueCodeProvision $provision): array
    {
        $analysis = $this->analyzeRevenueCodeSchedule->handle($provision);
        $analysisRows = collect($analysis['rows'])->keyBy('code');

        return [
            'provision' => [
                'id' => $provision->id,
                'code' => $provision->code,
                'section_reference' => $provision->section_reference,
                'title' => $provision->title,
                'reconciliation_status' => $provision->reconciliation_status->value,
                'linked_fee_rule_code' => $provision->feeRule?->code,
                'linked_fee_rule_execution_status' => $provision->feeRule?->currentReconciliation?->execution_status->value,
            ],
            'summary' => $analysis['summary'],
            'rows' => $provision->rows()->orderBy('sequence')->get()->map(
                fn (RevenueCodeProvisionRow $row): array => [
                    'id' => $row->id,
                    'sequence' => $row->sequence,
                    'code' => $row->code,
                    'source_basis_text' => $row->source_basis_text,
                    'source_value_text' => $row->source_value_text,
                    'basis_from_cents' => $row->basis_from_cents,
                    'basis_below_cents' => $row->basis_below_cents,
                    'amount_cents' => $row->amount_cents,
                    'rate_basis_points' => $row->rate_basis_points,
                    'is_ceiling' => $row->is_ceiling,
                    'normalization_status' => $row->normalization_status->value,
                    'normalization_notes' => $row->normalization_notes,
                    'issues' => $analysisRows->get($row->code)['issues'] ?? [],
                ],
            )->values()->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function policyBoundaryPayload(): array
    {
        return RevenueCodeProvision::query()
            ->with('clauses')
            ->whereHas('clauses')
            ->orderBy('section_reference')
            ->get()
            ->map(fn (RevenueCodeProvision $provision): array => [
                'provision' => [
                    'code' => $provision->code,
                    'section_reference' => $provision->section_reference,
                    'title' => $provision->title,
                    'reconciliation_status' => $provision->reconciliation_status->value,
                ],
                'clauses' => $provision->clauses->map(fn (RevenueCodeProvisionClause $clause): array => [
                    'id' => $clause->id,
                    'sequence' => $clause->sequence,
                    'code' => $clause->code,
                    'clause_type' => $clause->clause_type->value,
                    'source_text' => $clause->source_text,
                    'candidate_interpretation' => $clause->candidate_interpretation,
                    'amount_cents' => $clause->amount_cents,
                    'rate_basis_points' => $clause->rate_basis_points,
                    'is_ceiling' => $clause->is_ceiling,
                    'reconciliation_status' => $clause->reconciliation_status->value,
                    'execution_blocker' => $clause->execution_blocker,
                    'candidate_values_are_non_executable' => ($clause->metadata['candidate_values_are_non_executable'] ?? false) === true,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, FeeRuleCategory|FeeRuleScope|FeeRuleCalculationType>  $cases
     * @return array<int, array{label: string, value: string}>
     */
    private function options(array $cases): array
    {
        return collect($cases)
            ->map(fn (FeeRuleCategory|FeeRuleScope|FeeRuleCalculationType $case): array => [
                'label' => str($case->value)->replace('_', ' ')->title()->toString(),
                'value' => $case->value,
            ])
            ->values()
            ->all();
    }
}
