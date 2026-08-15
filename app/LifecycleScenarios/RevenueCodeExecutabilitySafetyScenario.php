<?php

namespace App\LifecycleScenarios;

use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreatePermitApplication;
use App\Enums\FeeRuleExecutionStatus;
use App\Models\Assessment;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\User;
use Database\Seeders\RevenueCodeFeeCatalogSeeder;
use RuntimeException;

final class RevenueCodeExecutabilitySafetyScenario
{
    public function __construct(
        private readonly RevenueCodeFeeCatalogSeeder $revenueCodeFeeCatalogSeeder,
        private readonly CreatePermitApplication $createPermitApplication,
        private readonly CreateAssessmentForPermitApplication $createAssessment,
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

        $lineOfBusiness = $this->lineOfBusiness($runId);
        $exactApplication = $this->permitApplication($runId, 'exact', 'additional', $lineOfBusiness, $operator);
        $blockedApplication = $this->permitApplication($runId, 'blocked', 'new', $lineOfBusiness, $operator);
        $exactAssessment = $exactApplication->assessments()->whereNull('superseded_at')->first()
            ?? $this->createAssessment->handle($exactApplication, $operator);
        $exactRule = $this->feeRule('MRC-3A-04-BUSINESS-INSPECTION');
        $blockedRule = $this->feeRule('MRC-3A-02-NEW-MAYORS-PERMIT-MICRO');
        $expectedRefusal = 'Fee rule [MRC-3A-02-NEW-MAYORS-PERMIT-MICRO] is not executable: Municipal enterprise-scale eligibility is unresolved; the micro-industry amount cannot be applied to every new business.';
        $manifest = $this->scenarioManifest->initial($scenario, $runId, $actors);

        $steps = [
            $this->step('actors-resolved', 'Resolve actual application users', ['operator_id' => $operator->id], ['operator_id' => $operator->id]),
            $this->step('revenue-code-reconciliations-present', 'Prepare versioned Revenue Code reconciliation evidence', ['exact_status' => 'executable', 'blocked_status' => 'blocked'], ['exact_status' => $exactRule->currentReconciliation?->execution_status->value, 'blocked_status' => $blockedRule->currentReconciliation?->execution_status->value]),
            $this->step('exact-fee-assessed', 'Execute the exact annual business inspection fee through the authoritative assessment action', ['line_code' => $exactRule->code, 'total_amount_cents' => 35_000], ['line_code' => $exactAssessment->lines->sole()->code, 'total_amount_cents' => $exactAssessment->total_amount_cents, 'assessment_id' => $exactAssessment->id]),
            $this->step('blocked-assessment-prepared', 'Prepare the exact application precondition for browser refusal', ['assessment_count' => 0], ['assessment_count' => $blockedApplication->assessments()->count(), 'permit_application_id' => $blockedApplication->id]),
        ];

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest['resources'] = [
            'record_type' => 'financial_reconciliation_execution',
            'record_id' => $exactAssessment->id,
            'public_reference' => $runId,
            'exact_application_id' => $exactApplication->id,
            'exact_application_number' => $exactApplication->application_number,
            'exact_assessment_id' => $exactAssessment->id,
            'exact_assessment_total_amount_cents' => $exactAssessment->total_amount_cents,
            'exact_fee_rule_id' => $exactRule->id,
            'exact_fee_rule_code' => $exactRule->code,
            'exact_reconciliation_id' => $exactRule->currentReconciliation?->id,
            'exact_reconciliation_status' => $exactRule->currentReconciliation?->execution_status->value,
            'blocked_application_id' => $blockedApplication->id,
            'blocked_application_number' => $blockedApplication->application_number,
            'blocked_fee_rule_id' => $blockedRule->id,
            'blocked_fee_rule_code' => $blockedRule->code,
            'blocked_reconciliation_id' => $blockedRule->currentReconciliation?->id,
            'blocked_reconciliation_status' => $blockedRule->currentReconciliation?->execution_status->value,
            'expected_refusal_message' => $expectedRefusal,
            'assessment_index_url' => route('staff.permit-applications.assessments.index', absolute: false),
            'exact_assessment_url' => route('staff.permit-applications.assessments.show', $exactAssessment, false),
            'exact_fee_rule_url' => route('staff.fee-rules.show', $exactRule, false),
            'blocked_fee_rule_url' => route('staff.fee-rules.show', $blockedRule, false),
        ];
        $manifest['steps'] = $steps;
        $manifest['result']['terminal'] = collect($steps)->every(fn (array $step): bool => $step['passed']) ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed';
        $manifest['artifacts'] = ['root' => '.'];

        $artifactStore->putJson('terminal/prepare.json', [
            'run_id' => $runId,
            'exact' => [
                'permit_application_id' => $exactApplication->id,
                'assessment_id' => $exactAssessment->id,
                'fee_rule_id' => $exactRule->id,
                'fee_rule_code' => $exactRule->code,
                'amount_cents' => $exactAssessment->total_amount_cents,
                'reconciliation_id' => $exactRule->currentReconciliation?->id,
            ],
            'blocked' => [
                'permit_application_id' => $blockedApplication->id,
                'fee_rule_id' => $blockedRule->id,
                'fee_rule_code' => $blockedRule->code,
                'reconciliation_id' => $blockedRule->currentReconciliation?->id,
                'expected_refusal_message' => $expectedRefusal,
            ],
        ]);
        $artifactStore->putJson('terminal/execution.json', [
            'steps' => $steps,
            'terminal_phase_performed_exact_assessment' => true,
            'browser_phase_performs_blocked_assessment_attempt' => true,
            'external_calls' => 0,
            'irreversible_actions' => false,
            'notifications' => false,
        ]);
        $artifactStore->putJson('storyboard/storyboard.json', $this->storyboard($runId, $exactApplication, $blockedApplication));
        $artifactStore->put('storyboard/storyboard.html', $this->storyboardHtml($runId, $exactApplication, $blockedApplication));
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
        $exactAssessment = $this->assessment((int) $manifest['resources']['exact_assessment_id']);
        $blockedApplication = $this->permitApplicationById((int) $manifest['resources']['blocked_application_id']);
        $exactRule = $this->feeRule((string) $manifest['resources']['exact_fee_rule_code']);
        $blockedRule = $this->feeRule((string) $manifest['resources']['blocked_fee_rule_code']);
        $browserReport = $artifactStore->readJson('browser/report.json') ?? [];
        $boundary = $blockedApplication->metadata['assessment_policy_boundary'] ?? [];
        $exactSnapshot = $exactAssessment->lines->sole()->rule_snapshot['reconciliation'] ?? [];

        $checks = [
            $this->step('audit-browser-result', 'Browser evidence runner passed', ['browser' => true], ['browser' => (bool) data_get($browserReport, 'result.passed')]),
            $this->step('audit-exact-assessment', 'Canonical assessment preserves the exact reconciled fee', ['line_code' => $exactRule->code, 'amount_cents' => 35_000, 'reconciliation_id' => $exactRule->currentReconciliation?->id], ['line_code' => $exactAssessment->lines->sole()->code, 'amount_cents' => $exactAssessment->total_amount_cents, 'reconciliation_id' => $exactSnapshot['fee_rule_reconciliation_id'] ?? null]),
            $this->step('audit-blocked-assessment', 'Unresolved rule created no assessment and recorded the policy boundary', ['assessment_count' => 0, 'reason' => $manifest['resources']['expected_refusal_message']], ['assessment_count' => $blockedApplication->assessments()->count(), 'reason' => $boundary['reason'] ?? null]),
            $this->step('audit-reconciliation-statuses', 'Canonical reconciliation statuses remain unchanged', ['exact_status' => FeeRuleExecutionStatus::Executable->value, 'blocked_status' => FeeRuleExecutionStatus::Blocked->value], ['exact_status' => $exactRule->currentReconciliation?->execution_status->value, 'blocked_status' => $blockedRule->currentReconciliation?->execution_status->value]),
            $this->step('audit-browser-authority-chain', 'Browser shows the exact and blocked reconciliation states', ['exact_visible' => true, 'blocked_visible' => true, 'refusal_visible' => true], ['exact_visible' => data_get($browserReport, 'revenue_code_execution.exact_visible'), 'blocked_visible' => data_get($browserReport, 'revenue_code_execution.blocked_visible'), 'refusal_visible' => data_get($browserReport, 'revenue_code_execution.refusal_visible')]),
        ];
        $passed = collect($checks)->every(fn (array $check): bool => $check['passed']);

        $manifest['steps'] = [...($manifest['steps'] ?? []), ...$checks];
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
                'exact_assessment_id' => $exactAssessment->id,
                'exact_assessment_total_amount_cents' => $exactAssessment->total_amount_cents,
                'exact_reconciliation' => $exactSnapshot,
                'blocked_permit_application_id' => $blockedApplication->id,
                'blocked_assessment_count' => $blockedApplication->assessments()->count(),
                'blocked_policy_boundary' => $boundary,
            ],
            'browser' => $browserReport,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('summary.html', $this->summaryRenderer->html($manifest));

        return $manifest;
    }

    private function lineOfBusiness(string $runId): LineOfBusiness
    {
        $suffix = str($runId)->upper()->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-')->limit(28, '')->toString();

        return LineOfBusiness::query()->firstOrCreate(
            ['code' => 'SCENARIO-RECON-'.$suffix],
            [
                'name' => 'Scenario Revenue Reconciliation '.$suffix,
                'description' => 'Scenario-only business activity isolated from ordinance line-of-business tax schedules.',
                'is_active' => true,
            ],
        );
    }

    private function permitApplication(string $runId, string $case, string $type, LineOfBusiness $lineOfBusiness, User $operator): PermitApplication
    {
        $applicationNumber = 'SCN-REV-'.strtoupper(substr(hash('sha256', $runId), 0, 16)).'-'.strtoupper($case);
        $existing = PermitApplication::query()->where('application_number', $applicationNumber)->first();

        if ($existing instanceof PermitApplication) {
            return $existing;
        }

        return $this->createPermitApplication->handle([
            'owner_name' => 'Scenario Revenue Reconciliation Owner',
            'owner_address' => 'Scenario verification address',
            'business_name' => 'Scenario Revenue Reconciliation '.$case,
            'registration_number' => 'SCENARIO-'.$runId.'-'.$case,
            'business_address' => 'Scenario verification address',
            'barangay' => 'Poblacion',
            'application_number' => $applicationNumber,
            'type' => $type,
            'application_year' => 2023,
            'lines' => [[
                'line_of_business_id' => $lineOfBusiness->id,
                'declared_gross_sales_cents' => 100_000,
                'capital_investment_cents' => 100_000,
                'quantity' => 1,
            ]],
        ], $operator);
    }

    private function feeRule(string $code): FeeRule
    {
        return FeeRule::query()->with('currentReconciliation')->where('code', $code)->sole();
    }

    private function assessment(int $id): Assessment
    {
        return Assessment::query()->with('lines')->whereKey($id)->sole();
    }

    private function permitApplicationById(int $id): PermitApplication
    {
        return PermitApplication::query()->whereKey($id)->sole();
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

    /** @return array<string, mixed> */
    private function storyboard(string $runId, PermitApplication $exactApplication, PermitApplication $blockedApplication): array
    {
        return [
            'title' => 'Revenue Code executability and reconciliation safety',
            'summary' => 'BPLO staff sees an exact ordinance fee execute with its accepted authority chain while an unresolved enterprise-scale fee refuses assessment without creating a financial record.',
            'run_id' => $runId,
            'records' => [
                'exact_application_id' => $exactApplication->id,
                'blocked_application_id' => $blockedApplication->id,
            ],
            'frames' => [
                ['title' => 'Exact ordinance fee executes', 'description' => 'The authoritative assessment action creates one PHP 350 annual inspection fee and snapshots its financial reconciliation.', 'dialogue' => 'Exact, deterministic, accepted policy may execute.', 'duration_seconds' => 5],
                ['title' => 'Authority chain remains visible', 'description' => 'The catalog separates original ordinance text, normalized interpretation, decision authority, and execution status.', 'dialogue' => 'Extraction is evidence; reconciliation authorizes execution.', 'duration_seconds' => 5],
                ['title' => 'Ambiguous fee refuses', 'description' => 'The browser attempts assessment for a new business whose micro-industry eligibility is unresolved.', 'dialogue' => 'No assessment is created and the reason remains visible.', 'duration_seconds' => 5],
            ],
        ];
    }

    private function storyboardHtml(string $runId, PermitApplication $exactApplication, PermitApplication $blockedApplication): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Revenue Code executability and reconciliation safety</title></head><body><h1>Revenue Code executability and reconciliation safety</h1><p>Run ID: '.e($runId).'</p><p>Exact application: '.e($exactApplication->application_number).'</p><p>Blocked application: '.e($blockedApplication->application_number).'</p><p>Exact accepted policy executes through the Domain. Ambiguous policy remains recorded, visible, and non-executable.</p></body></html>';
    }
}
