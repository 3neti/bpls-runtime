<?php

namespace App\LifecycleScenarios;

use App\Actions\BuildPermitApplicationTimeline;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreatePaymentScheduleForAssessment;
use App\Actions\CreatePermitApplication;
use App\Actions\DescribeOnlinePaymentBoundary;
use App\Actions\DescribePaymentPolicyBoundary;
use App\Actions\RecordAssessmentDecision;
use App\Enums\AssessmentDecisionAction;
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

final class CitizenPermitProcessingVisibilityScenario
{
    public function __construct(
        private readonly CreatePermitApplication $createPermitApplication,
        private readonly CreateAssessmentForPermitApplication $createAssessment,
        private readonly RecordAssessmentDecision $recordAssessmentDecision,
        private readonly CreatePaymentScheduleForAssessment $createPaymentSchedule,
        private readonly BuildPermitApplicationTimeline $buildPermitApplicationTimeline,
        private readonly DescribePaymentPolicyBoundary $describePaymentPolicyBoundary,
        private readonly DescribeOnlinePaymentBoundary $describeOnlinePaymentBoundary,
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

        $applicant = $actors['applicant'] ?? throw new RuntimeException('Scenario citizen applicant actor was not resolved.');
        $operator = $actors['operator'] ?? throw new RuntimeException('Scenario operator actor was not resolved.');
        $approver = $actors['approver'] ?? User::query()
            ->where('email', config('lifecycle_scenarios.actors.assessment_approver.email'))
            ->firstOrFail();
        $manifest = $this->scenarioManifest->initial($scenario, $runId, $actors);
        $lineOfBusiness = $this->lineOfBusiness();
        $this->feeRule($lineOfBusiness);
        $applicationNumber = 'APP-CITIZEN-'.str($runId)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->limit(44, '')
            ->toString();

        $permitApplication = $this->createPermitApplication->handle([
            'owner_name' => $applicant->name,
            'owner_email' => $applicant->email,
            'owner_phone' => '09170000000',
            'owner_address' => 'Scenario citizen address',
            'business_name' => 'Citizen Processing Business '.$runId,
            'trade_name' => 'Citizen Processing Trade',
            'registration_number' => 'CITIZEN-PROCESSING-'.$runId,
            'business_address' => 'Scenario citizen business address',
            'barangay' => 'Poblacion',
            'application_number' => $applicationNumber,
            'type' => PermitApplicationType::New->value,
            'application_year' => now()->year,
            'lines' => [[
                'line_of_business_id' => $lineOfBusiness->id,
                'declared_gross_sales_cents' => 125_000_00,
                'capital_investment_cents' => 75_000_00,
                'quantity' => 1,
                'started_on' => '2020-01-15',
            ]],
        ], $applicant);
        $assessment = $this->createAssessment->handle($permitApplication, $operator);
        $this->recordAssessmentDecision->handle($assessment, $approver, AssessmentDecisionAction::Approved);
        $paymentSchedule = $this->createPaymentSchedule->handle($assessment, $operator);
        $permitApplication->refresh();
        $timeline = $this->buildPermitApplicationTimeline->handle($permitApplication);
        $timelineKeys = collect($timeline)->pluck('key')->all();
        $paymentPolicyBoundary = $this->describePaymentPolicyBoundary->handle($paymentSchedule);
        $onlinePaymentBoundary = $this->describeOnlinePaymentBoundary->handle($paymentSchedule);

        $steps = [
            $this->step('actors-resolved', 'Resolve actual citizen and municipal operator', [
                'applicant_id' => $applicant->id,
                'operator_id' => $operator->id,
            ], [
                'applicant_id' => $applicant->id,
                'operator_id' => $operator->id,
            ]),
            $this->step('citizen-owned-application-prepared', 'Prepare a citizen-owned application already inside municipal processing through the canonical intake action', [
                'submitted_by_id' => $applicant->id,
                'application_number' => $applicationNumber,
            ], [
                'submitted_by_id' => $permitApplication->submitted_by_id,
                'application_number' => $permitApplication->application_number,
                'permit_application_id' => $permitApplication->id,
            ], 'applicant'),
            $this->step('assessment-computed', 'Compute the assessment through the canonical municipal assessment action', [
                'status' => 'computed',
                'total_amount_cents' => $assessment->total_amount_cents,
            ], [
                'status' => $assessment->status->value,
                'total_amount_cents' => $assessment->total_amount_cents,
                'assessment_id' => $assessment->id,
            ]),
            $this->step('payment-schedule-prepared', 'Prepare the payment schedule through the canonical Treasury scheduling action', [
                'application_status' => PermitApplicationStatus::PendingPayment->value,
                'schedule_status' => PaymentScheduleStatus::Pending->value,
                'balance_amount_cents' => $paymentSchedule->total_amount_cents,
            ], [
                'application_status' => $permitApplication->status->value,
                'schedule_status' => $paymentSchedule->status->value,
                'balance_amount_cents' => $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents,
                'payment_schedule_id' => $paymentSchedule->id,
            ]),
            $this->step('online-payment-boundary-preserved', 'Keep online payment and reconciliation unavailable', [
                'status' => 'blocked',
                'can_pay_online' => false,
                'can_reconcile_online' => false,
            ], [
                'status' => $onlinePaymentBoundary['status'],
                'can_pay_online' => $onlinePaymentBoundary['can_pay_online'],
                'can_reconcile_online' => $onlinePaymentBoundary['can_reconcile_online'],
            ]),
            $this->step('citizen-timeline-projected', 'Project authoritative application events for citizen review', [
                'event_keys' => $timelineKeys,
            ], [
                'event_keys' => $timelineKeys,
            ]),
        ];

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest['resources'] = [
            'record_type' => 'permit_application',
            'record_id' => $permitApplication->id,
            'public_reference' => $permitApplication->application_number,
            'application_number' => $permitApplication->application_number,
            'application_status' => $permitApplication->status->value,
            'business_name' => $permitApplication->business->name,
            'assessment_id' => $assessment->id,
            'assessment_status' => $assessment->status->value,
            'assessment_total_amount_cents' => $assessment->total_amount_cents,
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_schedule_status' => $paymentSchedule->status->value,
            'payment_total_amount_cents' => $paymentSchedule->total_amount_cents,
            'payment_paid_amount_cents' => $paymentSchedule->paid_amount_cents,
            'payment_balance_amount_cents' => $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents,
            'payment_policy_status' => $paymentPolicyBoundary['status'],
            'online_payment_status' => $onlinePaymentBoundary['status'],
            'can_pay_online' => $onlinePaymentBoundary['can_pay_online'],
            'citizen_timeline_event_count' => count($timelineKeys),
            'citizen_timeline_event_keys' => $timelineKeys,
            'list_url' => route('citizen.permit-applications.index', absolute: false),
            'detail_url' => route('citizen.permit-applications.show', $permitApplication, false),
        ];
        $manifest['steps'] = $steps;
        $manifest['result']['terminal'] = collect($steps)->every(fn (array $step): bool => $step['passed']) ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed';
        $manifest['artifacts'] = ['root' => '.'];

        $artifactStore->putJson('terminal/prepare.json', [
            'permit_application_id' => $permitApplication->id,
            'submitted_by_id' => $permitApplication->submitted_by_id,
            'application_number' => $permitApplication->application_number,
            'application_status' => $permitApplication->status->value,
            'assessment' => [
                'id' => $assessment->id,
                'status' => $assessment->status->value,
                'total_amount_cents' => $assessment->total_amount_cents,
            ],
            'payment_schedule' => [
                'id' => $paymentSchedule->id,
                'status' => $paymentSchedule->status->value,
                'total_amount_cents' => $paymentSchedule->total_amount_cents,
                'paid_amount_cents' => $paymentSchedule->paid_amount_cents,
            ],
            'payment_policy_boundary' => $paymentPolicyBoundary,
            'online_payment_boundary' => $onlinePaymentBoundary,
            'timeline' => $timeline,
            'run_id' => $runId,
        ]);
        $artifactStore->putJson('terminal/execution.json', [
            'steps' => $steps,
            'external_calls' => 0,
            'irreversible_actions' => false,
            'notifications' => false,
        ]);
        $storyboard = $this->storyboard($runId, $permitApplication, $assessment, $paymentSchedule);
        $artifactStore->putJson('storyboard/storyboard.json', $storyboard);
        $artifactStore->put('storyboard/storyboard.html', $this->storyboardHtml($storyboard));
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
        $assessment = Assessment::query()->findOrFail($manifest['resources']['assessment_id']);
        $paymentSchedule = PaymentSchedule::query()->findOrFail($manifest['resources']['payment_schedule_id']);
        $paymentPolicyBoundary = $this->describePaymentPolicyBoundary->handle($paymentSchedule);
        $onlinePaymentBoundary = $this->describeOnlinePaymentBoundary->handle($paymentSchedule);
        $timelineKeys = collect($this->buildPermitApplicationTimeline->handle($permitApplication))->pluck('key')->all();
        $browserReport = $artifactStore->readJson('browser/report.json') ?? ['result' => ['passed' => false]];

        $canonical = [
            'permit_application_id' => $permitApplication->id,
            'submitted_by_id' => $permitApplication->submitted_by_id,
            'application_number' => $permitApplication->application_number,
            'application_status' => $permitApplication->status->value,
            'assessment_id' => $assessment->id,
            'assessment_status' => $assessment->status->value,
            'assessment_total_amount_cents' => $assessment->total_amount_cents,
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_schedule_status' => $paymentSchedule->status->value,
            'payment_total_amount_cents' => $paymentSchedule->total_amount_cents,
            'payment_paid_amount_cents' => $paymentSchedule->paid_amount_cents,
            'payment_balance_amount_cents' => max(0, $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents),
            'payment_policy_status' => $paymentPolicyBoundary['status'],
            'online_payment_status' => $onlinePaymentBoundary['status'],
            'can_pay_online' => $onlinePaymentBoundary['can_pay_online'],
        ];
        $checks = [
            $this->step('audit-citizen-ownership', 'Canonical application belongs to the manifest citizen', [
                'submitted_by_id' => data_get($manifest, 'actors.applicant.id'),
            ], $canonical),
            $this->step('audit-processing-state', 'Canonical application, assessment, and schedule retain the prepared processing state', [
                'application_status' => $manifest['resources']['application_status'],
                'assessment_id' => $manifest['resources']['assessment_id'],
                'assessment_status' => $manifest['resources']['assessment_status'],
                'assessment_total_amount_cents' => $manifest['resources']['assessment_total_amount_cents'],
                'payment_schedule_id' => $manifest['resources']['payment_schedule_id'],
                'payment_schedule_status' => $manifest['resources']['payment_schedule_status'],
                'payment_balance_amount_cents' => $manifest['resources']['payment_balance_amount_cents'],
            ], $canonical),
            $this->step('audit-browser-processing-state', 'Citizen browser evidence matches canonical processing and financial state', [
                'application_status' => $canonical['application_status'],
                'assessment_id' => $canonical['assessment_id'],
                'assessment_status' => $canonical['assessment_status'],
                'assessment_total_amount_cents' => $canonical['assessment_total_amount_cents'],
                'payment_schedule_id' => $canonical['payment_schedule_id'],
                'payment_schedule_status' => $canonical['payment_schedule_status'],
                'payment_total_amount_cents' => $canonical['payment_total_amount_cents'],
                'payment_paid_amount_cents' => $canonical['payment_paid_amount_cents'],
                'payment_balance_amount_cents' => $canonical['payment_balance_amount_cents'],
            ], data_get($browserReport, 'citizen_processing', [])),
            $this->step('audit-browser-online-payment-boundary', 'Citizen browser preserves the canonical online-payment boundary', [
                'online_payment_status' => 'blocked',
                'can_pay_online' => false,
                'payment_action_visible' => false,
            ], [
                'online_payment_status' => data_get($browserReport, 'citizen_processing.online_payment_status'),
                'can_pay_online' => data_get($browserReport, 'citizen_processing.can_pay_online'),
                'payment_action_visible' => data_get($browserReport, 'citizen_processing.payment_action_visible'),
            ]),
            $this->step('audit-citizen-timeline', 'Canonical citizen timeline retains the prepared event order', [
                'event_count' => $manifest['resources']['citizen_timeline_event_count'],
                'event_keys' => $manifest['resources']['citizen_timeline_event_keys'],
            ], [
                'event_count' => count($timelineKeys),
                'event_keys' => $timelineKeys,
            ]),
            $this->step('audit-browser-citizen-timeline', 'Citizen browser shows the exact canonical timeline event order', [
                'event_count' => count($timelineKeys),
                'event_keys' => $timelineKeys,
            ], [
                'event_count' => data_get($browserReport, 'citizen_processing.timeline_event_count'),
                'event_keys' => data_get($browserReport, 'citizen_processing.timeline_event_keys'),
            ]),
            $this->step('audit-browser-result', 'Browser evidence runner passed', [
                'browser' => true,
            ], [
                'browser' => (bool) data_get($browserReport, 'result.passed'),
            ]),
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
            'canonical' => $canonical,
            'browser' => $browserReport,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('summary.html', $this->summaryRenderer->html($manifest));

        return $manifest;
    }

    private function lineOfBusiness(): LineOfBusiness
    {
        return LineOfBusiness::query()->firstOrCreate(
            ['code' => 'SCENARIO-CITIZEN-PROCESSING'],
            [
                'name' => 'Scenario Citizen Processing',
                'major_category' => 'Services',
                'is_active' => true,
            ],
        );
    }

    private function feeRule(LineOfBusiness $lineOfBusiness): void
    {
        FeeRule::query()->updateOrCreate(
            ['code' => 'SCENARIO-CITIZEN-PROCESSING-FEE'],
            [
                'line_of_business_id' => $lineOfBusiness->id,
                'name' => 'Scenario Citizen Processing Fee',
                'category' => FeeRuleCategory::Fee,
                'scope' => FeeRuleScope::LineOfBusiness,
                'calculation_type' => FeeRuleCalculationType::Fixed,
                'basis' => 'none',
                'amount_cents' => 85_000,
                'rate_basis_points' => null,
                'effective_from' => now()->startOfYear()->toDateString(),
                'effective_until' => null,
                'legal_basis' => 'Lifecycle scenario evidence only',
                'is_active' => true,
                'metadata' => [
                    'application_types' => [PermitApplicationType::New->value],
                    'scenario_only' => true,
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     * @return array<string, mixed>
     */
    private function step(string $key, string $action, array $expected, array $actual, string $actor = 'operator'): array
    {
        return [
            'key' => $key,
            'actor' => $actor,
            'action' => $action,
            'expected' => $expected,
            'actual' => $actual,
            'passed' => $expected === array_intersect_key($actual, $expected),
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
            'title' => 'Citizen tracks an application in municipal processing',
            'summary' => 'A citizen reviews the authoritative assessment, payment-schedule state, and recorded progress for the exact owned application without receiving staff controls or an online-payment action.',
            'run_id' => $runId,
            'record' => [
                'type' => 'permit_application',
                'id' => $permitApplication->id,
                'reference' => $permitApplication->application_number,
            ],
            'frames' => [
                [
                    'title' => 'Municipality prepares the application record',
                    'description' => 'Municipal staff compute the assessment and prepare a payment schedule through the production actions.',
                    'dialogue' => 'The application is now pending payment.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Citizen sees the assessed amount',
                    'description' => 'The owned application shows the same assessment status and total recorded by the municipality.',
                    'dialogue' => 'The displayed amount comes from the persisted assessment snapshot.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Citizen sees the payment boundary',
                    'description' => 'The payment status and balance are visible while online payment and reconciliation remain unavailable.',
                    'dialogue' => 'No payment or Treasury mutation is performed.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Citizen reviews recorded progress',
                    'description' => 'The application timeline shows the same ordered lifecycle events held by the authoritative records.',
                    'dialogue' => 'The timeline reports the journey; it does not perform a workflow transition.',
                    'duration_seconds' => 5,
                ],
            ],
            'evidence' => [
                'assessment_id' => $assessment->id,
                'payment_schedule_id' => $paymentSchedule->id,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $storyboard
     */
    private function storyboardHtml(array $storyboard): string
    {
        $frames = collect($storyboard['frames'])
            ->map(fn (array $frame): string => '<li><strong>'.e($frame['title']).'</strong><br>'.e($frame['description']).'<br><em>'.e($frame['dialogue']).'</em></li>')
            ->implode('');

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.e($storyboard['title']).'</title></head><body><h1>'.e($storyboard['title']).'</h1><p>'.e($storyboard['summary']).'</p><p>Run ID: '.e($storyboard['run_id']).'</p><p>'.e($storyboard['record']['reference']).'</p><ol>'.$frames.'</ol></body></html>';
    }
}
