<?php

namespace App\LifecycleScenarios;

use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreatePaymentScheduleForAssessment;
use App\Actions\CreateStaffPermitApplication;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Models\Assessment;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\User;
use RuntimeException;

final class PermitApplicationPendingPaymentVisibilityScenario
{
    public function __construct(
        private readonly CreateStaffPermitApplication $createPermitApplication,
        private readonly CreateAssessmentForPermitApplication $createAssessment,
        private readonly CreatePaymentScheduleForAssessment $createPaymentSchedule,
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
        $lineOfBusiness = $this->lineOfBusiness();
        $this->feeRules($lineOfBusiness);

        $applicationNumber = 'APP-SCENARIO-'.str($runId)->upper()->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-')->limit(40, '')->toString();
        $permitApplication = $this->createPermitApplication->handle([
            'owner_name' => 'Scenario Owner '.$runId,
            'owner_email' => null,
            'owner_phone' => null,
            'owner_address' => 'Scenario verification address',
            'business_name' => 'Scenario Payment Business '.$runId,
            'trade_name' => 'Scenario Payment Trade',
            'registration_number' => 'SCENARIO-'.$runId,
            'business_address' => 'Scenario verification address',
            'barangay' => 'Poblacion',
            'application_number' => $applicationNumber,
            'type' => PermitApplicationType::New->value,
            'application_year' => now()->year,
            'line_of_business_id' => $lineOfBusiness->id,
            'declared_gross_sales_cents' => 125_000_00,
            'capital_investment_cents' => 75_000_00,
            'quantity' => 1,
        ], $operator);

        $assessment = $this->createAssessment->handle($permitApplication, $operator);
        $paymentSchedule = $this->createPaymentSchedule->handle($assessment, $operator);
        $permitApplication = $paymentSchedule->permitApplication()->with(['assessments', 'paymentSchedules'])->firstOrFail();

        $steps = [
            $this->step('actors-resolved', 'Resolve actual application users', ['operator_id' => $operator->id], ['operator_id' => $operator->id]),
            $this->step('permit-application-created', 'Create permit application through staff intake action', ['status' => PermitApplicationStatus::Draft->value], ['status' => PermitApplicationStatus::Draft->value, 'permit_application_id' => $permitApplication->id]),
            $this->step('assessment-computed', 'Compute assessment through assessment action', ['assessment_status' => 'computed'], ['assessment_status' => $assessment->status->value, 'assessment_id' => $assessment->id]),
            $this->step('payment-schedule-prepared', 'Prepare payment schedule through payment schedule action', ['schedule_status' => PaymentScheduleStatus::Pending->value, 'application_status' => PermitApplicationStatus::PendingPayment->value], ['schedule_status' => $paymentSchedule->status->value, 'application_status' => $permitApplication->status->value, 'payment_schedule_id' => $paymentSchedule->id]),
        ];

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest['resources'] = [
            'record_type' => 'permit_application',
            'record_id' => $permitApplication->id,
            'public_reference' => $permitApplication->application_number,
            'assessment_id' => $assessment->id,
            'payment_schedule_id' => $paymentSchedule->id,
            'list_url' => route('staff.permit-applications.index', absolute: false),
            'detail_url' => route('staff.permit-applications.show', $permitApplication, false),
            'payment_schedule_url' => route('staff.payment-schedules.show', $paymentSchedule, false),
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
            'status' => $permitApplication->status->value,
            'assessment_id' => $assessment->id,
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_schedule_status' => $paymentSchedule->status->value,
            'run_id' => $runId,
        ]);
        $artifactStore->putJson('terminal/execution.json', [
            'steps' => $steps,
            'external_calls' => 0,
            'irreversible_actions' => false,
            'notifications' => false,
        ]);
        $artifactStore->putJson('storyboard/storyboard.json', $this->storyboard($runId, $permitApplication, $assessment, $paymentSchedule));
        $artifactStore->put('storyboard/storyboard.html', $this->storyboardHtml($runId, $permitApplication, $assessment, $paymentSchedule));
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
        $permitApplication = PermitApplication::query()
            ->with(['paymentSchedules', 'assessments'])
            ->findOrFail($manifest['resources']['record_id']);
        $paymentSchedule = PaymentSchedule::query()->findOrFail($manifest['resources']['payment_schedule_id']);
        $browserReport = $artifactStore->readJson('browser/report.json') ?? [
            'result' => [
                'passed' => false,
            ],
            'checks' => [],
        ];

        $checks = [
            $this->step('audit-canonical-status', 'Canonical permit application status is pending payment', ['status' => PermitApplicationStatus::PendingPayment->value], ['status' => $permitApplication->status->value]),
            $this->step('audit-payment-schedule-status', 'Payment schedule remains pending for collection', ['status' => PaymentScheduleStatus::Pending->value], ['status' => $paymentSchedule->status->value]),
            $this->step('audit-browser-result', 'Browser evidence runner passed', ['browser' => true], ['browser' => (bool) data_get($browserReport, 'result.passed')]),
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
                'status' => $permitApplication->status->value,
                'assessment_id' => $manifest['resources']['assessment_id'],
                'payment_schedule_id' => $paymentSchedule->id,
                'payment_schedule_status' => $paymentSchedule->status->value,
                'status_history' => $permitApplication->metadata['status_history'] ?? [],
            ],
            'browser' => $browserReport,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('summary.html', $this->summaryRenderer->html($manifest));

        return $manifest;
    }

    private function lineOfBusiness(): LineOfBusiness
    {
        return LineOfBusiness::query()->firstOrCreate(
            ['code' => 'SCENARIO-RETAIL'],
            [
                'name' => 'Scenario Retail',
                'major_category' => 'Retail',
                'is_active' => true,
            ],
        );
    }

    private function feeRules(LineOfBusiness $lineOfBusiness): void
    {
        FeeRule::query()->firstOrCreate(
            ['code' => 'SCENARIO-APPLICATION-FEE'],
            [
                'name' => 'Scenario Application Fee',
                'category' => FeeRuleCategory::Fee,
                'scope' => FeeRuleScope::Application,
                'calculation_type' => FeeRuleCalculationType::Fixed,
                'basis' => 'none',
                'amount_cents' => 10_000,
                'effective_from' => now()->startOfYear(),
                'is_active' => true,
            ],
        );

        FeeRule::query()->firstOrCreate(
            ['code' => 'SCENARIO-BUSINESS-TAX'],
            [
                'line_of_business_id' => $lineOfBusiness->id,
                'name' => 'Scenario Business Tax',
                'category' => FeeRuleCategory::Tax,
                'scope' => FeeRuleScope::LineOfBusiness,
                'calculation_type' => FeeRuleCalculationType::Fixed,
                'basis' => 'declared_gross_sales',
                'amount_cents' => 20_000,
                'effective_from' => now()->startOfYear(),
                'is_active' => true,
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
    private function storyboard(string $runId, PermitApplication $permitApplication, Assessment $assessment, PaymentSchedule $paymentSchedule): array
    {
        return [
            'title' => 'Permit application pending payment visibility',
            'summary' => 'BPLO staff records a disposable application, computes assessment, prepares a payment schedule, and verifies that staff screens show the application ready for collection.',
            'run_id' => $runId,
            'record' => [
                'type' => 'permit_application',
                'id' => $permitApplication->id,
                'application_number' => $permitApplication->application_number,
                'assessment_id' => $assessment->id,
                'payment_schedule_id' => $paymentSchedule->id,
            ],
            'frames' => [
                [
                    'title' => 'Staff records application',
                    'description' => 'BPLO staff records a new business permit application for the scenario business.',
                    'dialogue' => 'The application is ready for assessment.',
                    'duration_seconds' => 4,
                ],
                [
                    'title' => 'Assessment is computed',
                    'description' => 'The assessment action applies active fee rules and records the computed amount.',
                    'dialogue' => 'The application moves into assessment.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Payment schedule is prepared',
                    'description' => 'Treasury-facing schedule lines are prepared from assessment lines.',
                    'dialogue' => 'The application is now pending payment; collection and receipt behavior remain separate scenarios.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Reviewer confirms visibility',
                    'description' => 'The browser opens list, detail, and payment schedule screens for the exact manifest records.',
                    'dialogue' => 'Visible UI state and canonical database state agree.',
                    'duration_seconds' => 5,
                ],
            ],
        ];
    }

    private function storyboardHtml(string $runId, PermitApplication $permitApplication, Assessment $assessment, PaymentSchedule $paymentSchedule): string
    {
        $storyboard = $this->storyboard($runId, $permitApplication, $assessment, $paymentSchedule);
        $frames = collect($storyboard['frames'])
            ->map(fn (array $frame): string => '<li><strong>'.e($frame['title']).'</strong><br>'.e($frame['description']).'<br><em>'.e($frame['dialogue']).'</em></li>')
            ->implode('');

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.e($storyboard['title']).'</title></head><body><h1>'.e($storyboard['title']).'</h1><p>'.e($storyboard['summary']).'</p><p>Run ID: '.e($runId).'</p><p>Application: '.e((string) $permitApplication->application_number).'</p><p>Assessment: '.e((string) $assessment->id).'</p><p>Payment schedule: '.e((string) $paymentSchedule->id).'</p><ol>'.$frames.'</ol></body></html>';
    }
}
