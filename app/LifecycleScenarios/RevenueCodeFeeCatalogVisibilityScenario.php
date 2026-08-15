<?php

namespace App\LifecycleScenarios;

use App\Actions\AnalyzeRevenueCodeSchedule;
use App\Models\FeeRule;
use App\Models\RevenueCodeProvision;
use App\Models\RevenueCodeProvisionClause;
use App\Models\User;
use Database\Seeders\RevenueCodeFeeCatalogSeeder;
use RuntimeException;

final class RevenueCodeFeeCatalogVisibilityScenario
{
    /** @var array<int, string> */
    private const SCHEDULE_PROVISION_CODES = [
        'MRC-2A-02-A-MANUFACTURERS',
        'MRC-2A-02-B-WHOLESALERS',
        'MRC-2A-02-E-CONTRACTORS',
        'MRC-2A-02-G-ENUMERATED-SERVICES',
    ];

    /** @var array<int, array{provision_code: string, finding_row_code: string, finding_text: string, ceiling_row_code: string}> */
    private const SCHEDULE_FINDINGS = [
        ['provision_code' => 'MRC-2A-02-A-MANUFACTURERS', 'finding_row_code' => 'MRC-2A-02-A-ROW-18', 'finding_text' => 'normalization required', 'ceiling_row_code' => 'MRC-2A-02-A-ROW-20'],
        ['provision_code' => 'MRC-2A-02-B-WHOLESALERS', 'finding_row_code' => 'MRC-2A-02-B-ROW-08', 'finding_text' => 'overlap', 'ceiling_row_code' => 'MRC-2A-02-B-ROW-24'],
        ['provision_code' => 'MRC-2A-02-E-CONTRACTORS', 'finding_row_code' => 'MRC-2A-02-E-ROW-15', 'finding_text' => 'overlap', 'ceiling_row_code' => 'MRC-2A-02-E-ROW-19'],
        ['provision_code' => 'MRC-2A-02-G-ENUMERATED-SERVICES', 'finding_row_code' => 'MRC-2A-02-G-ROW-15', 'finding_text' => 'overlap', 'ceiling_row_code' => 'MRC-2A-02-G-ROW-19'],
    ];

    /** @var array<int, string> */
    private const POLICY_BOUNDARY_PROVISION_CODES = [
        'MRC-2A-02-B-WHOLESALERS',
        'MRC-2A-02-C-EXPORTERS-ESSENTIALS',
        'MRC-2A-02-D-RETAILERS',
        'MRC-2A-02-E-CONTRACTORS',
        'MRC-2A-02-F-FINANCIAL-INSTITUTIONS',
        'MRC-2A-02-G-ENUMERATED-SERVICES',
        'MRC-2A-02-H-PEDDLERS',
        'MRC-2B-02-04-MOBILE-TRADERS',
        'MRC-2B-05-06-PUBLIC-UTILITY-VEHICLES',
        'MRC-2B-07-AMUSEMENT-OPERATORS',
        'MRC-2B-08-09-OTHER-BUSINESSES',
        'MRC-2C-01-PETROLEUM-EXEMPTION',
        'MRC-2C-02-NEWLY-STARTED-BUSINESS',
        'MRC-2D-01-SITUS-DEFINITIONS',
        'MRC-2D-01-SALES-ALLOCATION',
        'MRC-2D-01-PORT-ROUTE-SALES',
        'MRC-2E-01-BUSINESS-TAX-SCOPE',
        'MRC-2E-02-03-ACCRUAL-PAYMENT',
        'MRC-2E-04-A-C-PERMIT-RECEIPT-REQUIREMENTS',
        'MRC-2E-04-D-E-DECLARATIONS-DEFICIENCY',
        'MRC-2E-04-F-LOST-RECEIPT-CERTIFICATION',
        'MRC-2E-04-G-LOCATION-TRANSFER',
        'MRC-2E-04-RETIREMENT',
        'MRC-2E-04-DEATH-TAX-MAPPING',
        'MRC-2F-01-PIL',
        'MRC-3A-01-02-PERMIT-SCOPE-ENTERPRISE-SCALE',
        'MRC-3A-02-A-01-06-GENERAL-PERMIT-FEES',
        'MRC-3A-02-A-07-13-SPECIAL-PERMIT-FEES',
        'MRC-3A-02-B-NEW-MICRO-PERMIT',
        'MRC-3A-03-PAYMENT-PRORATION',
        'MRC-3A-05-REGISTRATION-PLATE',
        'MRC-3B-01-DEFINITIONS',
        'MRC-3B-02-PERMIT-FEES',
        'MRC-3B-03-04-FRANCHISE-LICENSING-REGISTRATION',
        'MRC-3B-05-06-PAYMENT-APPLICABILITY',
        'MRC-3B-07-OPERATIONS',
        'MRC-3B-08-PENALTIES',
        'MRC-3C-01-SPECIAL-DERBY-FEES',
        'MRC-3C-02-EXCLUSIONS',
        'MRC-3C-03-PAYMENT-TIMING',
        'MRC-3C-04-APPLICABILITY',
        'MRC-3D-01-DEFINITIONS',
        'MRC-3D-02-IMPOUNDING-EXPENSES',
        'MRC-3D-03-RELEASE-PAYMENT',
        'MRC-3D-04-CUSTODY-NOTICE',
        'MRC-3D-04-AUCTION-DISPOSITION',
        'MRC-3D-05-PENALTIES-DAMAGES',
        'MRC-3E-01-DAILY-PERMIT-FEE',
        'MRC-3E-02-PAYMENT-TIMING',
        'MRC-3E-03-EXEMPTIONS',
        'MRC-3E-04-ADMINISTRATION',
        'MRC-3F-01-DEFINITION',
        'MRC-3F-02-FEES',
        'MRC-3F-03-PAYMENT',
        'MRC-3F-04-REGISTRY',
        'MRC-3F-05-APPLICABILITY',
        'MRC-3G-01-EXCAVATION-FEES',
        'MRC-3G-02-PAYMENT-DEPOSIT-FORFEITURE',
        'MRC-3G-03-ADMINISTRATION',
        'MRC-3H-01-IMPLEMENTING-AGENCY',
        'MRC-3H-02-SEALING-TESTING',
        'MRC-3H-03-FEES',
        'MRC-3H-04-PAYMENT-SURCHARGE',
        'MRC-3H-05-PLACE-OF-PAYMENT',
        'MRC-3H-06-EXEMPTIONS',
        'MRC-3H-07-ADMINISTRATION',
        'MRC-3H-08-PROHIBITED-PRACTICES',
        'MRC-3H-09-PENALTIES',
        'MRC-3H-10-COMPROMISE',
        'MRC-3I-01-CALIBRATION-SEALING',
        'MRC-3I-02-UNDERDELIVERY',
        'MRC-3I-03-SANCTIONS',
        'MRC-3I-04-FEES',
    ];

    public function __construct(
        private readonly AnalyzeRevenueCodeSchedule $analyzeRevenueCodeSchedule,
        private readonly RevenueCodeFeeCatalogSeeder $revenueCodeFeeCatalogSeeder,
        private readonly ScenarioManifest $scenarioManifest,
        private readonly ScenarioSummaryRenderer $summaryRenderer,
    ) {}

    /**
     * @param  array<string, User>  $actors
     * @return array<string, mixed>
     */
    public function prepare(LifecycleScenarioDefinition $scenario, string $runId, array $actors, ScenarioArtifactStore $artifactStore): array
    {
        $existingManifest = $artifactStore->readJson('manifest.json');
        if (is_array($existingManifest) && ($existingManifest['result']['terminal'] ?? null) === 'passed') {
            return $existingManifest;
        }

        $operator = $actors['operator'] ?? throw new RuntimeException('Scenario operator actor was not resolved.');

        $this->revenueCodeFeeCatalogSeeder->run();

        $feeRule = $this->feeRule();
        $feeRule->load(['lineOfBusiness', 'ranges' => fn ($query) => $query->orderBy('min_basis_cents')]);
        $provision = $this->provision();
        $scheduleMatrices = $this->scheduleMatrices();
        $scheduleSummary = $this->scheduleSummary($scheduleMatrices);
        $scheduleAnalysis = $scheduleMatrices[$provision->code];
        $policyBoundarySummary = $this->policyBoundarySummary();
        $policyBoundaryClauseCodes = $this->policyBoundaryClauseCodes();
        $manifest = $this->scenarioManifest->initial($scenario, $runId, $actors);

        $steps = [
            $this->step('actors-resolved', 'Resolve actual application users', ['operator_id' => $operator->id], ['operator_id' => $operator->id]),
            $this->step('fee-catalog-seeded', 'Prepare deterministic Revenue Code fee catalog evidence', ['rule_code' => 'MRC-2A-02-B-RETAIL-BUSINESS-TAX'], ['rule_code' => $feeRule->code, 'fee_rule_id' => $feeRule->id]),
            $this->step('fee-rule-ranges-present', 'Verify persisted range brackets for the selected business tax rule', ['range_count' => 23, 'first_range_amount_cents' => 2266], ['range_count' => $feeRule->ranges->count(), 'first_range_amount_cents' => $feeRule->ranges->first()?->amount_cents]),
            $this->step('policy-boundary-present', 'Verify unresolved Revenue Code policy boundary remains explicit', ['policy_boundary' => 'new_business_initial_local_business_tax_exemption'], ['policy_boundary' => $feeRule->metadata['policy_boundaries'][0] ?? null]),
            $this->step('provision-coverage-recorded', 'Verify provision coverage is distinct from executable policy', ['provision_count' => 75, 'reconciliation_required_count' => 74], ['provision_count' => RevenueCodeProvision::query()->count(), 'reconciliation_required_count' => RevenueCodeProvision::query()->where('reconciliation_status', 'reconciliation_required')->count()]),
            $this->step('ambiguous-provision-linked', 'Link the disputed legal provision to its blocked fee rule without authorizing execution', ['provision_code' => 'MRC-2A-02-B-WHOLESALERS', 'reconciliation_status' => 'reconciliation_required', 'fee_rule_code' => $feeRule->code], ['provision_code' => $provision->code, 'reconciliation_status' => $provision->reconciliation_status->value, 'fee_rule_code' => $provision->feeRule?->code]),
            $this->step('schedule-matrix-analyzed', 'Analyze exact source rows for mechanical reconciliation findings', ['row_count' => 24, 'overlap_count' => 1, 'gap_count' => 0, 'reconciliation_required_count' => 3, 'ceiling_count' => 1, 'execution_ready' => false], $scheduleAnalysis['summary']),
            $this->step('schedule-matrices-analyzed', 'Analyze every extracted Section 2A.02 schedule without authorizing execution', ['schedule_count' => 4, 'row_count' => 82, 'overlap_count' => 3, 'gap_count' => 0, 'reconciliation_required_count' => 7, 'ceiling_count' => 4, 'execution_ready_count' => 0], $scheduleSummary),
            $this->step('policy-boundary-clauses-recorded', 'Record non-schedule legal clauses without authorizing financial execution', ['provision_count' => 73, 'clause_count' => 392, 'reconciliation_required_count' => 392, 'ceiling_count' => 5, 'execution_ready_count' => 0], $policyBoundarySummary),
        ];

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest['resources'] = [
            'record_type' => 'fee_rule',
            'record_id' => $feeRule->id,
            'public_reference' => $feeRule->code,
            'fee_rule_code' => $feeRule->code,
            'fee_rule_name' => $feeRule->name,
            'line_of_business' => $feeRule->lineOfBusiness?->name,
            'legal_basis' => $feeRule->legal_basis,
            'legacy_source_id' => $feeRule->legacy_source_id,
            'catalog_status' => $feeRule->metadata['catalog_status'] ?? null,
            'application_types' => $feeRule->metadata['application_types'] ?? [],
            'policy_boundaries' => $feeRule->metadata['policy_boundaries'] ?? [],
            'range_count' => $feeRule->ranges->count(),
            'first_range_amount_cents' => $feeRule->ranges->first()?->amount_cents,
            'provision_id' => $provision->id,
            'provision_code' => $provision->code,
            'provision_status' => $provision->reconciliation_status->value,
            'provision_count' => RevenueCodeProvision::query()->count(),
            'reconciliation_required_count' => RevenueCodeProvision::query()->where('reconciliation_status', 'reconciliation_required')->count(),
            'schedule_matrix' => $scheduleAnalysis['summary'],
            'schedule_matrices' => collect($scheduleMatrices)->map(fn (array $matrix): array => $matrix['summary'])->all(),
            'schedule_summary' => $scheduleSummary,
            'schedule_provision_codes' => self::SCHEDULE_PROVISION_CODES,
            'schedule_findings' => self::SCHEDULE_FINDINGS,
            'policy_boundary_summary' => $policyBoundarySummary,
            'policy_boundary_clause_codes' => $policyBoundaryClauseCodes,
            'overlap_row_code' => 'MRC-2A-02-B-ROW-08',
            'malformed_row_code' => 'MRC-2A-02-B-ROW-18',
            'ceiling_row_code' => 'MRC-2A-02-B-ROW-24',
            'list_url' => route('staff.fee-rules.index', absolute: false),
            'detail_url' => route('staff.fee-rules.show', $feeRule, false),
        ];
        $manifest['steps'] = $steps;
        $manifest['result']['terminal'] = collect($steps)->every(fn (array $step): bool => $step['passed']) ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed';
        $manifest['artifacts'] = [
            'root' => '.',
        ];

        $artifactStore->putJson('terminal/prepare.json', [
            'fee_rule' => [
                'id' => $feeRule->id,
                'code' => $feeRule->code,
                'name' => $feeRule->name,
                'line_of_business' => $feeRule->lineOfBusiness?->name,
                'legal_basis' => $feeRule->legal_basis,
                'legacy_source_id' => $feeRule->legacy_source_id,
                'metadata' => [
                    'catalog_status' => $feeRule->metadata['catalog_status'] ?? null,
                    'application_types' => $feeRule->metadata['application_types'] ?? [],
                    'policy_boundaries' => $feeRule->metadata['policy_boundaries'] ?? [],
                    'policy_note' => $feeRule->metadata['policy_note'] ?? null,
                ],
            ],
            'range_count' => $feeRule->ranges->count(),
            'first_range' => [
                'min_basis_cents' => $feeRule->ranges->first()?->min_basis_cents,
                'max_basis_cents' => $feeRule->ranges->first()?->max_basis_cents,
                'amount_cents' => $feeRule->ranges->first()?->amount_cents,
            ],
            'provision' => [
                'id' => $provision->id,
                'code' => $provision->code,
                'section_reference' => $provision->section_reference,
                'reconciliation_status' => $provision->reconciliation_status->value,
                'reconciliation_notes' => $provision->reconciliation_notes,
                'fee_rule_code' => $provision->feeRule?->code,
            ],
            'schedule_matrix' => [
                'summary' => $scheduleAnalysis['summary'],
                'rows' => $scheduleAnalysis['rows'],
            ],
            'schedule_matrices' => $scheduleMatrices,
            'policy_boundary_summary' => $policyBoundarySummary,
            'policy_boundary_clauses' => RevenueCodeProvisionClause::query()
                ->with('provision')
                ->whereIn('code', $policyBoundaryClauseCodes)
                ->orderBy('code')
                ->get()
                ->map(fn (RevenueCodeProvisionClause $clause): array => [
                    'code' => $clause->code,
                    'provision_code' => $clause->provision->code,
                    'clause_type' => $clause->clause_type->value,
                    'source_text' => $clause->source_text,
                    'candidate_interpretation' => $clause->candidate_interpretation,
                    'reconciliation_status' => $clause->reconciliation_status->value,
                    'execution_blocker' => $clause->execution_blocker,
                    'candidate_values_are_non_executable' => ($clause->metadata['candidate_values_are_non_executable'] ?? false) === true,
                ])->values()->all(),
            'run_id' => $runId,
        ]);
        $artifactStore->putJson('terminal/execution.json', [
            'steps' => $steps,
            'external_calls' => 0,
            'irreversible_actions' => false,
            'notifications' => false,
        ]);
        $artifactStore->putJson('storyboard/storyboard.json', $this->storyboard($runId, $feeRule));
        $artifactStore->put('storyboard/storyboard.html', $this->storyboardHtml($runId, $feeRule));
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('review.md', $this->summaryRenderer->reviewMarkdown());

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function audit(array $manifest, ScenarioArtifactStore $artifactStore): array
    {
        $feeRule = $this->feeRuleById((int) $manifest['resources']['record_id']);
        $browserReport = $artifactStore->readJson('browser/report.json') ?? [];
        $provision = $this->provisionById((int) $manifest['resources']['provision_id']);
        $scheduleMatrices = $this->scheduleMatrices();
        $scheduleSummary = $this->scheduleSummary($scheduleMatrices);
        $scheduleAnalysis = $scheduleMatrices[$provision->code];
        $policyBoundarySummary = $this->policyBoundarySummary();
        $policyBoundaryClauseCodes = $this->policyBoundaryClauseCodes();
        $applicationTypes = array_values($feeRule->metadata['application_types'] ?? []);
        $policyBoundaries = array_values($feeRule->metadata['policy_boundaries'] ?? []);

        $checks = [
            $this->step('audit-browser-result', 'Browser evidence runner passed', ['browser' => true], ['browser' => (bool) data_get($browserReport, 'result.passed')]),
            $this->step('audit-canonical-fee-rule', 'Canonical fee rule still matches prepared Revenue Code evidence', ['fee_rule_code' => $manifest['resources']['fee_rule_code'], 'range_count' => $manifest['resources']['range_count']], ['fee_rule_code' => $feeRule->code, 'range_count' => $feeRule->ranges->count()]),
            $this->step('audit-browser-fee-catalog', 'Browser evidence shows catalog and exact fee-rule detail', ['fee_rule_code' => $feeRule->code, 'detail_visible' => true, 'range_amount_visible' => true, 'legal_basis_visible' => true], ['fee_rule_code' => data_get($browserReport, 'fee_catalog.fee_rule_code'), 'detail_visible' => data_get($browserReport, 'fee_catalog.detail_visible'), 'range_amount_visible' => data_get($browserReport, 'fee_catalog.range_amount_visible'), 'legal_basis_visible' => data_get($browserReport, 'fee_catalog.legal_basis_visible')]),
            $this->step('audit-browser-fee-catalog-applicability', 'Browser evidence shows the persisted fee-rule applicability', ['application_types' => $applicationTypes], ['application_types' => data_get($browserReport, 'fee_catalog.application_types_visible', [])]),
            $this->step('audit-browser-fee-catalog-policy-boundaries', 'Browser evidence shows every persisted unresolved policy boundary', ['policy_boundaries' => $policyBoundaries], ['policy_boundaries' => data_get($browserReport, 'fee_catalog.policy_boundaries_visible', [])]),
            $this->step('audit-provision-coverage', 'Canonical provision register retains the prepared coverage and policy boundary', ['provision_code' => $manifest['resources']['provision_code'], 'reconciliation_status' => 'reconciliation_required', 'fee_rule_code' => $feeRule->code], ['provision_code' => $provision->code, 'reconciliation_status' => $provision->reconciliation_status->value, 'fee_rule_code' => $provision->feeRule?->code]),
            $this->step('audit-browser-provision-coverage', 'Browser evidence shows the legal provision separately from executable policy', ['provision_visible' => true, 'reconciliation_required_visible' => true, 'linked_rule_visible' => true], ['provision_visible' => data_get($browserReport, 'fee_catalog.provision_visible'), 'reconciliation_required_visible' => data_get($browserReport, 'fee_catalog.reconciliation_required_visible'), 'linked_rule_visible' => data_get($browserReport, 'fee_catalog.linked_rule_visible')]),
            $this->step('audit-schedule-matrix', 'Canonical row analysis retains the exact prepared findings', $manifest['resources']['schedule_matrix'], $scheduleAnalysis['summary']),
            $this->step('audit-browser-schedule-matrix', 'Browser matrix shows the exact overlap, malformed row, ceiling row, and execution refusal', ['matrix_visible' => true, 'overlap_visible' => true, 'malformed_visible' => true, 'ceiling_visible' => true, 'execution_refused_visible' => true], ['matrix_visible' => data_get($browserReport, 'fee_catalog.matrix_visible'), 'overlap_visible' => data_get($browserReport, 'fee_catalog.overlap_visible'), 'malformed_visible' => data_get($browserReport, 'fee_catalog.malformed_visible'), 'ceiling_visible' => data_get($browserReport, 'fee_catalog.ceiling_visible'), 'execution_refused_visible' => data_get($browserReport, 'fee_catalog.execution_refused_visible')]),
            $this->step('audit-schedule-matrices', 'Canonical schedule analyses retain the exact prepared aggregate findings', $manifest['resources']['schedule_summary'], $scheduleSummary),
            $this->step('audit-browser-schedule-matrices', 'Browser evidence covers every extracted schedule and its execution refusal', ['schedule_provision_codes' => self::SCHEDULE_PROVISION_CODES, 'execution_refused' => true], ['schedule_provision_codes' => data_get($browserReport, 'fee_catalog.schedule_provision_codes', []), 'execution_refused' => data_get($browserReport, 'fee_catalog.all_schedules_execution_refused')]),
            $this->step('audit-policy-boundary-clauses', 'Canonical non-schedule clauses retain the prepared reconciliation boundary', $manifest['resources']['policy_boundary_summary'], $policyBoundarySummary),
            $this->step('audit-browser-policy-boundary-clauses', 'Browser evidence shows every non-schedule clause and its execution refusal', ['policy_boundary_clause_codes' => $policyBoundaryClauseCodes, 'execution_refused' => true], ['policy_boundary_clause_codes' => data_get($browserReport, 'fee_catalog.policy_boundary_clause_codes', []), 'execution_refused' => data_get($browserReport, 'fee_catalog.all_policy_boundary_clauses_execution_refused')]),
        ];
        $passed = collect($checks)->every(fn (array $check): bool => $check['passed']);

        $manifest['steps'] = [
            ...($manifest['steps'] ?? []),
            ...$checks,
        ];
        $manifest['result']['audit'] = $passed ? 'passed' : 'failed';
        $manifest['result']['browser'] = data_get($browserReport, 'result.passed') ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed'
            && $manifest['result']['browser'] === 'passed'
            && $manifest['result']['audit'] === 'passed';
        $manifest['artifacts']['screenshots'] = data_get($browserReport, 'artifacts.screenshots', []);

        $artifactStore->putJson('terminal/audit.json', [
            'checks' => $checks,
            'passed' => $passed,
            'canonical' => [
                'fee_rule_id' => $feeRule->id,
                'fee_rule_code' => $feeRule->code,
                'range_count' => $feeRule->ranges->count(),
                'application_types' => $applicationTypes,
                'policy_boundaries' => $policyBoundaries,
                'provision_id' => $provision->id,
                'provision_code' => $provision->code,
                'provision_status' => $provision->reconciliation_status->value,
                'provision_fee_rule_id' => $provision->fee_rule_id,
                'schedule_matrix' => $scheduleAnalysis,
                'schedule_matrices' => $scheduleMatrices,
                'schedule_summary' => $scheduleSummary,
                'policy_boundary_summary' => $policyBoundarySummary,
            ],
            'browser' => $browserReport,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('summary.html', $this->summaryRenderer->html($manifest));

        return $manifest;
    }

    private function feeRule(): FeeRule
    {
        return FeeRule::query()
            ->where('code', 'MRC-2A-02-B-RETAIL-BUSINESS-TAX')
            ->sole();
    }

    private function provision(): RevenueCodeProvision
    {
        return RevenueCodeProvision::query()
            ->with('feeRule')
            ->where('code', 'MRC-2A-02-B-WHOLESALERS')
            ->sole();
    }

    private function feeRuleById(int $id): FeeRule
    {
        return FeeRule::query()
            ->with(['lineOfBusiness', 'ranges' => fn ($query) => $query->orderBy('min_basis_cents')])
            ->whereKey($id)
            ->sole();
    }

    private function provisionById(int $id): RevenueCodeProvision
    {
        return RevenueCodeProvision::query()
            ->with('feeRule')
            ->whereKey($id)
            ->sole();
    }

    /** @return array<string, array<string, mixed>> */
    private function scheduleMatrices(): array
    {
        return RevenueCodeProvision::query()
            ->whereIn('code', self::SCHEDULE_PROVISION_CODES)
            ->orderBy('section_reference')
            ->get()
            ->mapWithKeys(function (RevenueCodeProvision $provision): array {
                $analysis = $this->analyzeRevenueCodeSchedule->handle($provision);

                return [$provision->code => [
                    'provision_id' => $provision->id,
                    'provision_code' => $provision->code,
                    'section_reference' => $provision->section_reference,
                    'summary' => $analysis['summary'],
                    'rows' => $analysis['rows'],
                ]];
            })
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $matrices
     * @return array<string, int>
     */
    private function scheduleSummary(array $matrices): array
    {
        $summaries = collect($matrices)->pluck('summary');

        return [
            'schedule_count' => $summaries->count(),
            'row_count' => $summaries->sum('row_count'),
            'overlap_count' => $summaries->sum('overlap_count'),
            'gap_count' => $summaries->sum('gap_count'),
            'reconciliation_required_count' => $summaries->sum('reconciliation_required_count'),
            'ceiling_count' => $summaries->sum('ceiling_count'),
            'execution_ready_count' => $summaries->where('execution_ready', true)->count(),
        ];
    }

    /** @return array<string, int> */
    private function policyBoundarySummary(): array
    {
        $clauses = RevenueCodeProvisionClause::query()
            ->whereHas('provision', fn ($query) => $query->whereIn('code', self::POLICY_BOUNDARY_PROVISION_CODES))
            ->get();

        return [
            'provision_count' => $clauses->pluck('revenue_code_provision_id')->unique()->count(),
            'clause_count' => $clauses->count(),
            'reconciliation_required_count' => RevenueCodeProvisionClause::query()
                ->whereHas('provision', fn ($query) => $query->whereIn('code', self::POLICY_BOUNDARY_PROVISION_CODES))
                ->where('reconciliation_status', 'reconciliation_required')
                ->count(),
            'ceiling_count' => $clauses->where('is_ceiling', true)->count(),
            'execution_ready_count' => 0,
        ];
    }

    /** @return array<int, string> */
    private function policyBoundaryClauseCodes(): array
    {
        return RevenueCodeProvisionClause::query()
            ->whereHas('provision', fn ($query) => $query->whereIn('code', self::POLICY_BOUNDARY_PROVISION_CODES))
            ->join('revenue_code_provisions', 'revenue_code_provisions.id', '=', 'revenue_code_provision_clauses.revenue_code_provision_id')
            ->orderBy('revenue_code_provisions.section_reference')
            ->orderBy('revenue_code_provision_clauses.sequence')
            ->pluck('revenue_code_provision_clauses.code')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     * @return array<string, mixed>
     */
    private function step(string $key, string $action, array $expected, array $actual): array
    {
        return [
            'key' => $key,
            'actor' => 'operator',
            'action' => $action,
            'expected' => $expected,
            'actual' => $actual,
            'passed' => collect($expected)->every(fn (mixed $value, string $field): bool => ($actual[$field] ?? null) === $value),
            'occurred_at' => now()->toIso8601String(),
            'evidence' => $actual,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storyboard(string $runId, FeeRule $feeRule): array
    {
        return [
            'title' => 'Revenue Code fee catalog visibility',
            'summary' => 'BPLO staff verifies that legal-provision coverage remains distinct from executable fee policy while the catalog exposes range evidence and unresolved policy boundaries.',
            'run_id' => $runId,
            'record' => [
                'type' => 'fee_rule',
                'id' => $feeRule->id,
                'code' => $feeRule->code,
            ],
            'frames' => [
                [
                    'title' => 'Staff opens Taxes and Fees',
                    'description' => 'The catalog lists persisted Revenue Code rules with legal provenance and policy-boundary labels.',
                    'dialogue' => 'The fee catalog is visible evidence, not editable policy.',
                    'duration_seconds' => 4,
                ],
                [
                    'title' => 'Staff reviews ordinance coverage',
                    'description' => 'The provision register records the Section 2A.02 tax families, Article E permit administration, transfer, retirement, deficiency, and recordkeeping boundaries, the Article F PIL schedule, and the characterized Chapter III permit provisions without making unresolved provisions executable.',
                    'dialogue' => 'Recorded legal coverage is not calculation authority.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Staff reviews row-level findings',
                    'description' => 'The matrices preserve 82 source rows across manufacturers, wholesalers, contractors, and enumerated services while identifying overlaps, malformed values, and ceiling rates without authorizing any candidate value.',
                    'dialogue' => 'Mechanical findings support municipal reconciliation; they do not decide policy.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Staff reviews non-schedule policy boundaries',
                    'description' => 'Tax-scope exclusions, payment dates, receipts, record retention, declarations, location transfer, retirement verification and settlement, permit cancellation, estate continuation, tax mapping, deficiency terms, and PIL use remain traceable clauses with explicit execution blockers.',
                    'dialogue' => 'Candidate facts support reconciliation; they are not executable policy.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Staff opens a fee-rule detail',
                    'description' => 'The detail page shows the selected business tax rule, applicability, legal source, and persisted range brackets.',
                    'dialogue' => 'The disputed brackets remain visible evidence but cannot execute until the Municipality accepts a reconciliation.',
                    'duration_seconds' => 5,
                ],
            ],
        ];
    }

    private function storyboardHtml(string $runId, FeeRule $feeRule): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Revenue Code fee catalog visibility</title></head><body><h1>Revenue Code fee catalog visibility</h1><p>Run ID: '.e($runId).'</p><p>Fee rule: '.e($feeRule->code).' - '.e($feeRule->name).'</p><p>The row-level matrices and non-schedule clauses record source evidence and reconciliation candidates only. They do not define or execute assessment policy.</p></body></html>';
    }
}
