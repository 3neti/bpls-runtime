<?php

namespace App\LifecycleScenarios;

use App\Actions\CreateStaffPermitApplication;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Models\Assessment;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\User;
use RuntimeException;

final class AssessmentPolicyBoundaryVisibilityScenario
{
    public function __construct(
        private readonly CreateStaffPermitApplication $createPermitApplication,
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
        $manifest = $this->scenarioManifest->initial($scenario, $runId, $actors);
        $lineOfBusiness = $this->lineOfBusiness($runId);
        $feeRule = $this->formulaFeeRule($runId, $lineOfBusiness);
        $applicationNumber = 'APP-SCENARIO-'.str($runId)->upper()->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-')->limit(40, '')->toString();

        $permitApplication = $this->createPermitApplication->handle([
            'owner_name' => 'Scenario Formula Boundary Owner',
            'owner_email' => null,
            'owner_phone' => null,
            'owner_address' => 'Scenario verification address',
            'business_name' => 'Scenario Formula Boundary Store',
            'trade_name' => 'Formula Boundary',
            'registration_number' => 'SCENARIO-'.$runId,
            'business_address' => 'Scenario verification address',
            'barangay' => 'Poblacion',
            'application_number' => $applicationNumber,
            'type' => 'new',
            'application_year' => now()->year,
            'line_of_business_id' => $lineOfBusiness->id,
            'declared_gross_sales_cents' => 125_000_00,
            'capital_investment_cents' => 75_000_00,
            'quantity' => 1,
        ], $operator);

        $expectedMessage = "Formula assessment policy is not implemented for fee rule [{$feeRule->code}].";
        $steps = [
            $this->step('actors-resolved', 'Resolve actual application users', ['operator_id' => $operator->id], ['operator_id' => $operator->id]),
            $this->step('formula-boundary-application-created', 'Create scenario permit application through staff intake action', ['application_number' => $applicationNumber, 'line_of_business' => $lineOfBusiness->name], ['application_number' => $permitApplication->application_number, 'line_of_business' => $lineOfBusiness->name, 'permit_application_id' => $permitApplication->id]),
            $this->step('formula-fee-rule-prepared', 'Prepare a scenario-scoped formula fee rule without implementing formula semantics', ['fee_rule_code' => $feeRule->code, 'calculation_type' => FeeRuleCalculationType::Formula->value], ['fee_rule_code' => $feeRule->code, 'calculation_type' => $feeRule->calculation_type->value, 'fee_rule_id' => $feeRule->id]),
        ];

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest['resources'] = [
            'record_type' => 'permit_application',
            'record_id' => $permitApplication->id,
            'public_reference' => $permitApplication->application_number,
            'application_number' => $permitApplication->application_number,
            'fee_rule_id' => $feeRule->id,
            'fee_rule_code' => $feeRule->code,
            'expected_policy_message' => $expectedMessage,
            'assessment_index_url' => route('staff.permit-applications.assessments.index', absolute: false),
        ];
        $manifest['steps'] = $steps;
        $manifest['result']['terminal'] = collect($steps)->every(fn (array $step): bool => $step['passed']) ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed';
        $manifest['artifacts'] = [
            'root' => '.',
        ];

        $artifactStore->putJson('terminal/prepare.json', [
            'permit_application_id' => $permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'fee_rule_id' => $feeRule->id,
            'fee_rule_code' => $feeRule->code,
            'expected_policy_message' => $expectedMessage,
            'run_id' => $runId,
        ]);
        $artifactStore->putJson('terminal/execution.json', [
            'steps' => $steps,
            'browser_phase_performs_assessment_attempt' => true,
            'external_calls' => 0,
            'irreversible_actions' => false,
            'notifications' => false,
        ]);
        $artifactStore->putJson('storyboard/storyboard.json', $this->storyboard($runId, $permitApplication, $feeRule));
        $artifactStore->put('storyboard/storyboard.html', $this->storyboardHtml($runId, $permitApplication, $feeRule));
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
        $permitApplication = PermitApplication::query()->findOrFail($manifest['resources']['record_id']);
        $browserReport = $artifactStore->readJson('browser/report.json') ?? [];
        $expectedMessage = (string) $manifest['resources']['expected_policy_message'];
        $boundary = $permitApplication->metadata['assessment_policy_boundary'] ?? [];

        $checks = [
            $this->step('audit-browser-result', 'Browser evidence runner passed', ['browser' => true], ['browser' => (bool) data_get($browserReport, 'result.passed')]),
            $this->step('audit-no-assessment-created', 'Unsupported formula policy did not create an assessment snapshot', ['assessment_count' => 0], ['assessment_count' => Assessment::query()->where('permit_application_id', $permitApplication->id)->count()]),
            $this->step('audit-canonical-boundary-recorded', 'Canonical permit application records the assessment policy boundary', ['status' => 'blocked', 'reason' => $expectedMessage], ['status' => $boundary['status'] ?? null, 'reason' => $boundary['reason'] ?? null]),
            $this->step('audit-browser-boundary-visible', 'Browser evidence shows the same assessment policy boundary', ['boundary_visible' => true, 'reason_visible' => true], ['boundary_visible' => data_get($browserReport, 'assessment_policy_boundary.boundary_visible'), 'reason_visible' => data_get($browserReport, 'assessment_policy_boundary.reason_visible')]),
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
                'permit_application_id' => $permitApplication->id,
                'application_number' => $permitApplication->application_number,
                'assessment_count' => Assessment::query()->where('permit_application_id', $permitApplication->id)->count(),
                'assessment_policy_boundary' => $boundary,
            ],
            'browser' => $browserReport,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('summary.html', $this->summaryRenderer->html($manifest));

        return $manifest;
    }

    private function lineOfBusiness(string $runId): LineOfBusiness
    {
        return LineOfBusiness::query()->firstOrCreate(
            ['code' => 'SCENARIO-FORMULA-'.str($runId)->upper()->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-')->limit(24, '')->toString()],
            [
                'name' => 'Scenario Formula Boundary',
                'description' => 'Scenario-only line of business for assessment policy-boundary verification.',
                'is_active' => true,
            ],
        );
    }

    private function formulaFeeRule(string $runId, LineOfBusiness $lineOfBusiness): FeeRule
    {
        $code = 'SCENARIO-FORMULA-FEE-'.str($runId)->upper()->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-')->limit(20, '')->toString();

        return FeeRule::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => 'Scenario Formula Policy Boundary Fee',
                'category' => FeeRuleCategory::Fee,
                'scope' => FeeRuleScope::LineOfBusiness,
                'line_of_business_id' => $lineOfBusiness->id,
                'calculation_type' => FeeRuleCalculationType::Formula,
                'basis' => 'none',
                'amount_cents' => 0,
                'rate_basis_points' => null,
                'effective_from' => now()->startOfYear()->toDateString(),
                'effective_until' => null,
                'legal_basis' => 'Scenario fixture: formula policy boundary remains unresolved.',
                'is_active' => true,
                'legacy_source_id' => 'SCENARIO-FORMULA-POLICY-BOUNDARY',
                'metadata' => [
                    'scenario_run_id' => $runId,
                    'policy_boundaries' => ['formula_assessment_policy'],
                ],
            ],
        );
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
    private function storyboard(string $runId, PermitApplication $permitApplication, FeeRule $feeRule): array
    {
        return [
            'title' => 'Assessment policy boundary visibility',
            'summary' => 'BPLO staff attempts assessment for a scenario application whose line of business has an unresolved formula fee rule. The domain refuses to invent formula behavior, and the staff UI shows the assessment policy boundary.',
            'run_id' => $runId,
            'record' => [
                'type' => 'permit_application',
                'id' => $permitApplication->id,
                'application_number' => $permitApplication->application_number,
                'fee_rule_code' => $feeRule->code,
            ],
            'frames' => [
                [
                    'title' => 'Staff opens Permit Assessments',
                    'description' => 'The exact prepared application is visible in the assessment queue.',
                    'dialogue' => 'The scenario uses a scenario-scoped formula fee rule.',
                    'duration_seconds' => 4,
                ],
                [
                    'title' => 'Staff attempts assessment',
                    'description' => 'The real assessment action calls the single domain calculation path and refuses unsupported formula policy.',
                    'dialogue' => 'No assessment snapshot is created when policy is unresolved.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Boundary remains visible',
                    'description' => 'The assessment queue shows the explicit policy-boundary message for reviewer inspection.',
                    'dialogue' => 'Unknown formula policy remains a seam, not invented behavior.',
                    'duration_seconds' => 5,
                ],
            ],
        ];
    }

    private function storyboardHtml(string $runId, PermitApplication $permitApplication, FeeRule $feeRule): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Assessment policy boundary visibility</title></head><body><h1>Assessment policy boundary visibility</h1><p>Run ID: '.e($runId).'</p><p>Application: '.e($permitApplication->application_number).'</p><p>Formula fee rule: '.e($feeRule->code).'</p><p>This storyboard verifies refusal to invent formula assessment policy. It does not define formula behavior.</p></body></html>';
    }
}
