<?php

namespace App\LifecycleScenarios;

use App\Actions\AttemptPermitApplicationRelease;
use App\Actions\CompletePermitClearance;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreatePaymentScheduleForAssessment;
use App\Actions\CreateStaffPermitApplication;
use App\Actions\DescribePermitReleaseReadiness;
use App\Actions\EnsurePermitApplicationClearances;
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
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use App\Models\User;
use RuntimeException;

final class ManualCollectionReceiptVisibilityScenario
{
    public function __construct(
        private readonly CreateStaffPermitApplication $createPermitApplication,
        private readonly CreateAssessmentForPermitApplication $createAssessment,
        private readonly CreatePaymentScheduleForAssessment $createPaymentSchedule,
        private readonly RecordPaymentScheduleCollection $recordCollection,
        private readonly IssueManualCollectionReceipt $issueReceipt,
        private readonly EnsurePermitApplicationClearances $ensureClearances,
        private readonly CompletePermitClearance $completeClearance,
        private readonly AttemptPermitApplicationRelease $attemptRelease,
        private readonly DescribePermitReleaseReadiness $describeReleaseReadiness,
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
            'business_name' => 'Scenario Receipt Business '.$runId,
            'trade_name' => 'Scenario Receipt Trade',
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
        $collection = $this->recordCollection->handle($paymentSchedule, [
            'amount_cents' => $paymentSchedule->total_amount_cents,
            'method' => TreasuryCollectionMethod::Cash->value,
            'payer_name' => 'Scenario Payer '.$runId,
            'reference_number' => 'SCENARIO-CASH-'.$this->safeRunReference($runId),
            'remarks' => 'Lifecycle scenario full OTC collection.',
        ], $operator);
        $collectionStatusBeforeReceipt = $collection->status;
        $receipt = $this->issueReceipt->handle($collection, [
            'numbering_authority' => 'manual',
            'receipt_number' => 'SCENARIO-OR-'.$this->safeRunReference($runId),
            'remarks' => 'Lifecycle scenario manual receipt.',
        ], $operator);
        $permitApplication = $this->ensureClearances->handle($permitApplication);
        $completedClearances = 0;

        foreach ($permitApplication->clearances as $clearance) {
            $this->completeClearance->handle($clearance, $operator, 'Lifecycle scenario clearance evidence.');
            $completedClearances++;
        }

        $permitApplication->load([
            'clearances' => fn ($query) => $query->orderBy('id'),
        ]);
        $releaseReadiness = $this->describeReleaseReadiness->handle($permitApplication);
        $releaseBlocked = false;

        try {
            $this->attemptRelease->handle($permitApplication, $operator);
        } catch (UnresolvedPermitReleasePolicy) {
            $releaseBlocked = true;
        }

        $paymentSchedule->refresh();
        $collection->refresh();
        $permitApplication = $paymentSchedule->permitApplication()->firstOrFail();

        $steps = [
            $this->step('actors-resolved', 'Resolve actual application users', ['operator_id' => $operator->id], ['operator_id' => $operator->id]),
            $this->step('permit-application-created', 'Create permit application through staff intake action', ['status' => PermitApplicationStatus::Draft->value], ['status' => PermitApplicationStatus::Draft->value, 'permit_application_id' => $permitApplication->id]),
            $this->step('assessment-computed', 'Compute assessment through assessment action', ['assessment_status' => 'computed'], ['assessment_status' => $assessment->status->value, 'assessment_id' => $assessment->id]),
            $this->step('payment-schedule-prepared', 'Prepare payment schedule through payment schedule action', ['application_status' => PermitApplicationStatus::PendingPayment->value], ['application_status' => $permitApplication->status->value, 'payment_schedule_id' => $paymentSchedule->id]),
            $this->step('collection-recorded', 'Record full over-the-counter collection through Treasury action', ['payment_schedule_status' => PaymentScheduleStatus::Paid->value, 'collection_status' => TreasuryCollectionStatus::PendingReceipt->value], ['payment_schedule_status' => $paymentSchedule->status->value, 'collection_status' => $collectionStatusBeforeReceipt->value, 'collection_id' => $collection->id]),
            $this->step('manual-receipt-issued', 'Issue manual receipt through receipt action', ['receipt_status' => ReceiptStatus::Issued->value, 'collection_status' => TreasuryCollectionStatus::Receipted->value], ['receipt_status' => $receipt->status->value, 'collection_status' => $collection->status->value, 'receipt_id' => $receipt->id]),
            $this->step('clearance-checklist-completed', 'Complete clearance checklist through clearance actions', ['completed_clearances' => 3, 'all_completed' => true], ['completed_clearances' => $completedClearances, 'all_completed' => $permitApplication->clearances->every(fn ($clearance): bool => $clearance->status === PermitClearanceStatus::Completed)]),
            $this->step('release-ready-for-authority-review', 'Describe release readiness without issuing permit', ['ready_for_authority_review' => true, 'can_release' => false], ['ready_for_authority_review' => $releaseReadiness['ready_for_authority_review'], 'can_release' => $releaseReadiness['can_release']]),
            $this->step('permit-release-blocked', 'Attempt permit release through release boundary action', ['release_blocked' => true, 'application_status' => PermitApplicationStatus::PendingPayment->value], ['release_blocked' => $releaseBlocked, 'application_status' => $permitApplication->status->value]),
        ];

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest['resources'] = [
            'record_type' => 'receipt',
            'record_id' => $receipt->id,
            'public_reference' => $receipt->receipt_number,
            'permit_application_id' => $permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'assessment_id' => $assessment->id,
            'payment_schedule_id' => $paymentSchedule->id,
            'collection_id' => $collection->id,
            'permit_application_url' => route('staff.permit-applications.show', $permitApplication, false),
            'payment_schedule_url' => route('staff.payment-schedules.show', $paymentSchedule, false),
            'receipt_url' => route('staff.receipts.show', $receipt, false),
            'receipt_pdf_url' => route('staff.receipts.pdf', $receipt, false),
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
            'assessment_id' => $assessment->id,
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_schedule_status' => $paymentSchedule->status->value,
            'collection_id' => $collection->id,
            'collection_status' => $collection->status->value,
            'receipt_id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'receipt_status' => $receipt->status->value,
            'clearances' => $permitApplication->clearances
                ->map(fn ($clearance): array => [
                    'id' => $clearance->id,
                    'code' => $clearance->code,
                    'status' => $clearance->status->value,
                ])
                ->values()
                ->all(),
            'release_policy_boundary' => $permitApplication->metadata['release_policy_boundary'] ?? null,
            'release_readiness' => $releaseReadiness,
            'run_id' => $runId,
        ]);
        $artifactStore->putJson('terminal/execution.json', [
            'steps' => $steps,
            'external_calls' => 0,
            'irreversible_actions' => false,
            'notifications' => false,
        ]);
        $artifactStore->putJson('storyboard/storyboard.json', $this->storyboard($runId, $permitApplication, $paymentSchedule, $collection, $receipt));
        $artifactStore->put('storyboard/storyboard.html', $this->storyboardHtml($runId, $permitApplication, $paymentSchedule, $collection, $receipt));
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
        $paymentSchedule = PaymentSchedule::query()->findOrFail($manifest['resources']['payment_schedule_id']);
        $collection = TreasuryCollection::query()->with('receipt')->findOrFail($manifest['resources']['collection_id']);
        $receipt = Receipt::query()->findOrFail($manifest['resources']['record_id']);
        $permitApplication = PermitApplication::query()
            ->with('clearances')
            ->findOrFail($manifest['resources']['permit_application_id']);
        $releaseReadiness = $this->describeReleaseReadiness->handle($permitApplication);
        $browserReport = $artifactStore->readJson('browser/report.json') ?? [
            'result' => [
                'passed' => false,
            ],
            'checks' => [],
        ];

        $checks = [
            $this->step('audit-payment-schedule-paid', 'Payment schedule is paid', ['status' => PaymentScheduleStatus::Paid->value], ['status' => $paymentSchedule->status->value]),
            $this->step('audit-collection-receipted', 'Collection is receipted', ['status' => TreasuryCollectionStatus::Receipted->value], ['status' => $collection->status->value]),
            $this->step('audit-receipt-issued', 'Manual receipt is issued', ['status' => ReceiptStatus::Issued->value, 'numbering_authority' => 'manual'], ['status' => $receipt->status->value, 'numbering_authority' => $receipt->numbering_authority]),
            $this->step('audit-clearances-completed', 'Clearance checklist evidence is complete', ['completed_clearances' => 3, 'all_completed' => true], ['completed_clearances' => $permitApplication->clearances->where('status', PermitClearanceStatus::Completed)->count(), 'all_completed' => $permitApplication->clearances->isNotEmpty() && $permitApplication->clearances->every(fn ($clearance): bool => $clearance->status === PermitClearanceStatus::Completed)]),
            $this->step('audit-release-readiness', 'Release readiness is ready for authority review but not releasable', ['ready_for_authority_review' => true, 'can_release' => false], ['ready_for_authority_review' => $releaseReadiness['ready_for_authority_review'], 'can_release' => $releaseReadiness['can_release']]),
            $this->step('audit-release-boundary', 'Permit release remains blocked by explicit policy boundary', ['status' => PermitApplicationStatus::PendingPayment->value, 'blocked_transition' => PermitApplicationStatus::Released->value], ['status' => $permitApplication->status->value, 'blocked_transition' => $permitApplication->metadata['release_policy_boundary']['blocked_transition'] ?? null]),
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
                'payment_schedule_id' => $paymentSchedule->id,
                'payment_schedule_status' => $paymentSchedule->status->value,
                'paid_amount_cents' => $paymentSchedule->paid_amount_cents,
                'collection_id' => $collection->id,
                'collection_status' => $collection->status->value,
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'receipt_status' => $receipt->status->value,
                'numbering_authority' => $receipt->numbering_authority,
                'permit_application_status' => $permitApplication->status->value,
                'clearances' => $permitApplication->clearances
                    ->map(fn ($clearance): array => [
                        'id' => $clearance->id,
                        'code' => $clearance->code,
                        'status' => $clearance->status->value,
                    ])
                    ->values()
                    ->all(),
                'release_policy_boundary' => $permitApplication->metadata['release_policy_boundary'] ?? null,
                'release_readiness' => $releaseReadiness,
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
            ['code' => 'SCENARIO-RECEIPT-APPLICATION-FEE'],
            [
                'name' => 'Scenario Receipt Application Fee',
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
            ['code' => 'SCENARIO-RECEIPT-BUSINESS-TAX'],
            [
                'line_of_business_id' => $lineOfBusiness->id,
                'name' => 'Scenario Receipt Business Tax',
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
    private function storyboard(string $runId, PermitApplication $permitApplication, PaymentSchedule $paymentSchedule, TreasuryCollection $collection, Receipt $receipt): array
    {
        return [
            'title' => 'Manual collection receipt visibility',
            'summary' => 'BPLO/Treasury staff prepare a collectible assessment, record full over-the-counter payment, issue a manual receipt, and verify the receipt is visible from Treasury surfaces.',
            'run_id' => $runId,
            'record' => [
                'type' => 'receipt',
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'application_number' => $permitApplication->application_number,
                'payment_schedule_id' => $paymentSchedule->id,
                'collection_id' => $collection->id,
            ],
            'frames' => [
                [
                    'title' => 'Assessment becomes collectible',
                    'description' => 'Staff records an application, computes assessment, and prepares a payment schedule.',
                    'dialogue' => 'The application is pending payment and ready for Treasury collection.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Treasury records collection',
                    'description' => 'The scenario records a full over-the-counter cash collection through the Treasury collection action.',
                    'dialogue' => 'The schedule is paid, but the collection remains pending receipt until receipt issuance.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Manual receipt is issued',
                    'description' => 'The scenario issues a manual receipt with a deterministic run reference.',
                    'dialogue' => 'Automatic numbering remains unresolved; this verifies the explicit manual-number boundary.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Clearance checklist is completed',
                    'description' => 'The scenario records clearance checklist evidence through clearance actions.',
                    'dialogue' => 'Clearance evidence is visible and auditable, but it is not permit issuance.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Release readiness is visible',
                    'description' => 'The scenario describes release readiness after payment, receipt, and clearance evidence.',
                    'dialogue' => 'The record is ready for authority review, but not permitted for release.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Release remains blocked',
                    'description' => 'The scenario attempts permit release through the release boundary action after full collection, receipt issuance, and clearance completion.',
                    'dialogue' => 'No permit is released until issuance authority and document policy are consciously resolved.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Reviewer confirms receipt visibility',
                    'description' => 'The browser opens the payment schedule and receipt screens for the exact manifest records.',
                    'dialogue' => 'Visible UI state and canonical Treasury records agree.',
                    'duration_seconds' => 5,
                ],
            ],
        ];
    }

    private function storyboardHtml(string $runId, PermitApplication $permitApplication, PaymentSchedule $paymentSchedule, TreasuryCollection $collection, Receipt $receipt): string
    {
        $storyboard = $this->storyboard($runId, $permitApplication, $paymentSchedule, $collection, $receipt);
        $frames = collect($storyboard['frames'])
            ->map(fn (array $frame): string => '<li><strong>'.e($frame['title']).'</strong><br>'.e($frame['description']).'<br><em>'.e($frame['dialogue']).'</em></li>')
            ->implode('');

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.e($storyboard['title']).'</title></head><body><h1>'.e($storyboard['title']).'</h1><p>'.e($storyboard['summary']).'</p><p>Run ID: '.e($runId).'</p><p>Application: '.e((string) $permitApplication->application_number).'</p><p>Payment schedule: '.e((string) $paymentSchedule->id).'</p><p>Collection: '.e((string) $collection->id).'</p><p>Receipt: '.e($receipt->receipt_number).'</p><ol>'.$frames.'</ol></body></html>';
    }

    private function safeRunReference(string $runId): string
    {
        return str($runId)->upper()->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-')->limit(60, '')->toString();
    }
}
