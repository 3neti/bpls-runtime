<?php

namespace App\LifecycleScenarios;

use App\Actions\AttemptPermitApplicationRelease;
use App\Actions\BuildPermitApplicationTimeline;
use App\Actions\CompletePermitClearance;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreatePaymentScheduleForAssessment;
use App\Actions\CreatePermitApplication;
use App\Actions\DescribeOnlinePaymentBoundary;
use App\Actions\DescribePermitReleaseReadiness;
use App\Actions\IssueManualCollectionReceipt;
use App\Actions\RecordPaymentScheduleCollection;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\PermitClearanceStatus;
use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionMethod;
use App\Enums\TreasuryCollectionStatus;
use App\Exceptions\UnresolvedPermitReleasePolicy;
use App\Models\Assessment;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use App\Models\User;
use RuntimeException;

final class CitizenPermitAuthorityReviewVisibilityScenario
{
    public function __construct(
        private readonly CreatePermitApplication $createPermitApplication,
        private readonly CreateAssessmentForPermitApplication $createAssessment,
        private readonly CreatePaymentScheduleForAssessment $createPaymentSchedule,
        private readonly RecordPaymentScheduleCollection $recordCollection,
        private readonly IssueManualCollectionReceipt $issueReceipt,
        private readonly CompletePermitClearance $completeClearance,
        private readonly AttemptPermitApplicationRelease $attemptRelease,
        private readonly DescribePermitReleaseReadiness $describeReleaseReadiness,
        private readonly DescribeOnlinePaymentBoundary $describeOnlinePaymentBoundary,
        private readonly BuildPermitApplicationTimeline $buildPermitApplicationTimeline,
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
        $manifest = $this->scenarioManifest->initial($scenario, $runId, $actors);
        $lineOfBusiness = $this->lineOfBusiness();
        $this->feeRule($lineOfBusiness);
        $applicationNumber = 'APP-CITIZEN-AUTHORITY-'.str($runId)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->limit(36, '')
            ->toString();

        $permitApplication = $this->createPermitApplication->handle([
            'owner_name' => $applicant->name,
            'owner_email' => $applicant->email,
            'owner_phone' => '09170000000',
            'owner_address' => 'Scenario citizen address',
            'business_name' => 'Citizen Authority Business '.$runId,
            'trade_name' => 'Citizen Authority Trade',
            'registration_number' => 'CITIZEN-AUTHORITY-'.$runId,
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
        $paymentSchedule = $this->createPaymentSchedule->handle($assessment, $operator);
        $collection = $this->recordCollection->handle($paymentSchedule, [
            'amount_cents' => $paymentSchedule->total_amount_cents,
            'method' => TreasuryCollectionMethod::Cash->value,
            'payer_name' => $applicant->name,
            'reference_number' => 'CITIZEN-AUTHORITY-CASH-'.str($runId)->upper()->replaceMatches('/[^A-Z0-9]+/', '-'),
            'remarks' => 'Citizen authority-review lifecycle evidence.',
        ], $operator);
        $collectionStatusBeforeReceipt = $collection->status;
        $receipt = $this->issueReceipt->handle($collection, [
            'numbering_authority' => 'manual',
            'receipt_number' => 'SCENARIO-CITIZEN-OR-'.str($runId)->upper()->replaceMatches('/[^A-Z0-9]+/', '-'),
            'remarks' => 'Citizen authority-review lifecycle evidence.',
        ], $operator);
        $permitApplication->load(['clearances' => fn ($query) => $query->oldest('id')]);

        foreach ($permitApplication->clearances as $clearance) {
            $this->completeClearance->handle($clearance, $operator, 'Lifecycle scenario clearance evidence.');
        }

        $permitApplication->load(['clearances' => fn ($query) => $query->oldest('id')]);

        $releaseBlocked = false;

        try {
            $this->attemptRelease->handle($permitApplication, $operator);
        } catch (UnresolvedPermitReleasePolicy) {
            $releaseBlocked = true;
        }

        $permitApplication->refresh();
        $paymentSchedule->refresh();
        $collection->refresh();
        $receipt->refresh();
        $releaseReadiness = $this->describeReleaseReadiness->handle($permitApplication);
        $onlinePaymentBoundary = $this->describeOnlinePaymentBoundary->handle($paymentSchedule);
        $timeline = $this->buildPermitApplicationTimeline->handle($permitApplication);
        $timelineKeys = collect($timeline)->pluck('key')->all();
        $completedClearances = $permitApplication->clearances->where('status', PermitClearanceStatus::Completed)->count();

        $steps = [
            $this->step('actors-resolved', 'Resolve actual citizen and municipal operator', [
                'applicant_id' => $applicant->id,
                'operator_id' => $operator->id,
            ], [
                'applicant_id' => $applicant->id,
                'operator_id' => $operator->id,
            ]),
            $this->step('citizen-owned-application-prepared', 'Prepare the citizen-owned municipal application record through the canonical intake action', [
                'submitted_by_id' => $applicant->id,
            ], [
                'submitted_by_id' => $permitApplication->submitted_by_id,
                'permit_application_id' => $permitApplication->id,
            ], 'applicant'),
            $this->step('assessment-and-schedule-prepared', 'Compute assessment and prepare payment schedule through canonical municipal actions', [
                'assessment_status' => 'computed',
                'application_status' => PermitApplicationStatus::PendingPayment->value,
            ], [
                'assessment_status' => $assessment->status->value,
                'application_status' => $permitApplication->status->value,
                'assessment_id' => $assessment->id,
                'payment_schedule_id' => $paymentSchedule->id,
            ]),
            $this->step('collection-recorded', 'Record full over-the-counter collection through the Treasury action', [
                'payment_schedule_status' => PaymentScheduleStatus::Paid->value,
                'collection_status_before_receipt' => TreasuryCollectionStatus::PendingReceipt->value,
            ], [
                'payment_schedule_status' => $paymentSchedule->status->value,
                'collection_status_before_receipt' => $collectionStatusBeforeReceipt->value,
                'collection_id' => $collection->id,
            ]),
            $this->step('manual-receipt-issued', 'Issue a manual receipt through the receipt action', [
                'collection_status' => TreasuryCollectionStatus::Receipted->value,
                'receipt_status' => ReceiptStatus::Issued->value,
            ], [
                'collection_status' => $collection->status->value,
                'receipt_status' => $receipt->status->value,
                'receipt_id' => $receipt->id,
            ]),
            $this->step('clearance-evidence-completed', 'Complete the current clearance checklist through clearance actions', [
                'completed' => 3,
                'total' => 3,
            ], [
                'completed' => $completedClearances,
                'total' => $permitApplication->clearances->count(),
            ]),
            $this->step('authority-review-boundary-reached', 'Describe authority-review readiness without issuing or releasing a permit', [
                'ready_for_authority_review' => true,
                'can_release' => false,
            ], [
                'ready_for_authority_review' => $releaseReadiness['ready_for_authority_review'],
                'can_release' => $releaseReadiness['can_release'],
            ]),
            $this->step('permit-release-refused', 'Attempt release through the authoritative policy boundary', [
                'release_blocked' => true,
                'application_status' => PermitApplicationStatus::PendingPayment->value,
            ], [
                'release_blocked' => $releaseBlocked,
                'application_status' => $permitApplication->status->value,
            ]),
            $this->step('online-payment-boundary-preserved', 'Keep online payment and reconciliation unavailable', [
                'status' => 'blocked',
                'can_pay_online' => false,
            ], [
                'status' => $onlinePaymentBoundary['status'],
                'can_pay_online' => $onlinePaymentBoundary['can_pay_online'],
            ]),
            $this->step('citizen-authority-timeline-projected', 'Project the complete authoritative journey for citizen review', [
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
            'assessment_id' => $assessment->id,
            'assessment_status' => $assessment->status->value,
            'assessment_total_amount_cents' => $assessment->total_amount_cents,
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_schedule_status' => $paymentSchedule->status->value,
            'payment_total_amount_cents' => $paymentSchedule->total_amount_cents,
            'payment_paid_amount_cents' => $paymentSchedule->paid_amount_cents,
            'payment_balance_amount_cents' => $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents,
            'collection_id' => $collection->id,
            'collection_status' => $collection->status->value,
            'collection_amount_cents' => $collection->amount_cents,
            'receipt_id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'receipt_status' => $receipt->status->value,
            'clearances_completed' => $completedClearances,
            'clearances_total' => $permitApplication->clearances->count(),
            'ready_for_authority_review' => $releaseReadiness['ready_for_authority_review'],
            'can_release' => $releaseReadiness['can_release'],
            'authority_review_status' => $releaseReadiness['authority_boundary']['status'],
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
            'assessment_id' => $assessment->id,
            'payment_schedule_id' => $paymentSchedule->id,
            'collection_id' => $collection->id,
            'receipt_id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'clearances' => $permitApplication->clearances->map(fn ($clearance): array => [
                'id' => $clearance->id,
                'code' => $clearance->code,
                'status' => $clearance->status->value,
            ])->values()->all(),
            'release_readiness' => $releaseReadiness,
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
        $storyboard = $this->storyboard($runId, $permitApplication, $receipt);
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
        $collection = TreasuryCollection::query()->findOrFail($manifest['resources']['collection_id']);
        $receipt = Receipt::query()->findOrFail($manifest['resources']['receipt_id']);
        $releaseReadiness = $this->describeReleaseReadiness->handle($permitApplication);
        $timelineKeys = collect($this->buildPermitApplicationTimeline->handle($permitApplication))->pluck('key')->all();
        $browserReport = $artifactStore->readJson('browser/report.json') ?? ['result' => ['passed' => false]];
        $canonical = [
            'permit_application_id' => $permitApplication->id,
            'submitted_by_id' => $permitApplication->submitted_by_id,
            'application_status' => $permitApplication->status->value,
            'assessment_id' => $assessment->id,
            'assessment_status' => $assessment->status->value,
            'assessment_total_amount_cents' => $assessment->total_amount_cents,
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_schedule_status' => $paymentSchedule->status->value,
            'payment_total_amount_cents' => $paymentSchedule->total_amount_cents,
            'payment_paid_amount_cents' => $paymentSchedule->paid_amount_cents,
            'payment_balance_amount_cents' => max(0, $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents),
            'collection_id' => $collection->id,
            'collection_status' => $collection->status->value,
            'collection_amount_cents' => $collection->amount_cents,
            'receipt_id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'receipt_status' => $receipt->status->value,
            'clearances_completed' => $permitApplication->clearances->where('status', PermitClearanceStatus::Completed)->count(),
            'clearances_total' => $permitApplication->clearances->count(),
            'ready_for_authority_review' => $releaseReadiness['ready_for_authority_review'],
            'can_release' => $releaseReadiness['can_release'],
            'authority_review_status' => $releaseReadiness['authority_boundary']['status'],
            'timeline_event_count' => count($timelineKeys),
            'timeline_event_keys' => $timelineKeys,
        ];
        $checks = [
            $this->step('audit-citizen-ownership', 'Canonical application belongs to the manifest citizen', [
                'submitted_by_id' => data_get($manifest, 'actors.applicant.id'),
            ], $canonical),
            $this->step('audit-paid-and-receipted', 'Canonical payment, collection, and receipt records remain complete', [
                'payment_schedule_status' => PaymentScheduleStatus::Paid->value,
                'payment_balance_amount_cents' => 0,
                'collection_status' => TreasuryCollectionStatus::Receipted->value,
                'receipt_status' => ReceiptStatus::Issued->value,
            ], $canonical),
            $this->step('audit-authority-boundary', 'Canonical readiness remains at the human authority boundary', [
                'application_status' => PermitApplicationStatus::PendingPayment->value,
                'clearances_completed' => 3,
                'clearances_total' => 3,
                'ready_for_authority_review' => true,
                'can_release' => false,
                'authority_review_status' => 'ready_for_authority_review',
            ], $canonical),
            $this->step('audit-timeline', 'Canonical timeline retains every prepared event in order', [
                'timeline_event_count' => $manifest['resources']['citizen_timeline_event_count'],
                'timeline_event_keys' => $manifest['resources']['citizen_timeline_event_keys'],
            ], $canonical),
            $this->step('audit-browser-authority-review', 'Citizen browser agrees with canonical collection, receipt, clearance, and authority evidence', [
                'collection_id' => $canonical['collection_id'],
                'collection_status' => $canonical['collection_status'],
                'collection_amount_cents' => $canonical['collection_amount_cents'],
                'receipt_id' => $canonical['receipt_id'],
                'receipt_number' => $canonical['receipt_number'],
                'receipt_status' => $canonical['receipt_status'],
                'clearances_completed' => $canonical['clearances_completed'],
                'clearances_total' => $canonical['clearances_total'],
                'ready_for_authority_review' => true,
                'can_release' => false,
                'authority_review_status' => 'ready_for_authority_review',
            ], data_get($browserReport, 'citizen_authority_review', [])),
            $this->step('audit-browser-timeline', 'Citizen browser shows every canonical event in order', [
                'timeline_event_count' => count($timelineKeys),
                'timeline_event_keys' => $timelineKeys,
            ], data_get($browserReport, 'citizen_authority_review', [])),
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
            ['code' => 'SCENARIO-CITIZEN-AUTHORITY'],
            [
                'name' => 'Scenario Citizen Authority Review',
                'major_category' => 'Services',
                'is_active' => true,
            ],
        );
    }

    private function feeRule(LineOfBusiness $lineOfBusiness): void
    {
        FeeRule::query()->updateOrCreate(
            ['code' => 'SCENARIO-CITIZEN-AUTHORITY-FEE'],
            [
                'line_of_business_id' => $lineOfBusiness->id,
                'name' => 'Scenario Citizen Authority Review Fee',
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
    private function storyboard(string $runId, PermitApplication $permitApplication, Receipt $receipt): array
    {
        return [
            'title' => 'Citizen follows a paid permit application to authority review',
            'summary' => 'A citizen sees collection, receipt, clearance, and authority-review evidence for an owned application while permit issuance and release remain explicitly unavailable.',
            'run_id' => $runId,
            'record' => [
                'type' => 'permit_application',
                'id' => $permitApplication->id,
                'reference' => $permitApplication->application_number,
            ],
            'frames' => [
                [
                    'title' => 'Treasury records payment',
                    'description' => 'Municipal staff record the full over-the-counter collection through the production Treasury action.',
                    'dialogue' => 'The payment schedule now has no remaining balance.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Treasury issues a receipt',
                    'description' => 'The manual receipt is linked to the exact collection and appears in the citizen record.',
                    'dialogue' => 'Receipt '.$receipt->receipt_number.' is recorded.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Municipality completes clearance evidence',
                    'description' => 'All current checklist entries are completed through the canonical clearance action.',
                    'dialogue' => 'Checklist completion is evidence, not permit release.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Citizen sees the authority boundary',
                    'description' => 'The application is ready for human authority review while issuance, release, and legal effect remain unresolved.',
                    'dialogue' => 'The software reports readiness and refuses release.',
                    'duration_seconds' => 5,
                ],
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
