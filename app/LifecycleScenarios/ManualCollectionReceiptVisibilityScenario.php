<?php

namespace App\LifecycleScenarios;

use App\Actions\AttemptPermitApplicationRelease;
use App\Actions\BuildCollectionsByRevenueSourceReport;
use App\Actions\BuildDailyCollectionsReport;
use App\Actions\BuildPaidEstablishmentsReport;
use App\Actions\BuildPermitApplicationTimeline;
use App\Actions\CompletePermitClearance;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreateCitizenPermitApplicationDraft;
use App\Actions\CreatePaymentScheduleForAssessment;
use App\Actions\CreatePermitApplication;
use App\Actions\DescribeCitizenPaymentSchedule;
use App\Actions\DescribeOnlinePaymentBoundary;
use App\Actions\DescribePermitArtifact;
use App\Actions\DescribePermitReleaseReadiness;
use App\Actions\DescribePermitVerificationBoundary;
use App\Actions\DescribeReceiptVoidBoundary;
use App\Actions\EnsurePermitApplicationClearances;
use App\Actions\IssueManualCollectionReceipt;
use App\Actions\RecordPaymentScheduleCollection;
use App\Actions\SimplePdfDocument;
use App\Actions\StoreCitizenPermitApplicationDocument;
use App\Actions\StorePermitApplicationDocument;
use App\Actions\SubmitCitizenPermitApplication;
use App\Actions\VoidReceipt;
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
use App\Exceptions\UnresolvedReceiptPolicy;
use App\Models\Assessment;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ManualCollectionReceiptVisibilityScenario
{
    public function __construct(
        private readonly CreatePermitApplication $createPermitApplication,
        private readonly CreateCitizenPermitApplicationDraft $createCitizenPermitApplicationDraft,
        private readonly SubmitCitizenPermitApplication $submitCitizenPermitApplication,
        private readonly StorePermitApplicationDocument $storePermitApplicationDocument,
        private readonly StoreCitizenPermitApplicationDocument $storeCitizenPermitApplicationDocument,
        private readonly CreateAssessmentForPermitApplication $createAssessment,
        private readonly CreatePaymentScheduleForAssessment $createPaymentSchedule,
        private readonly RecordPaymentScheduleCollection $recordCollection,
        private readonly IssueManualCollectionReceipt $issueReceipt,
        private readonly EnsurePermitApplicationClearances $ensureClearances,
        private readonly CompletePermitClearance $completeClearance,
        private readonly AttemptPermitApplicationRelease $attemptRelease,
        private readonly DescribeCitizenPaymentSchedule $describeCitizenPaymentSchedule,
        private readonly BuildDailyCollectionsReport $buildDailyCollectionsReport,
        private readonly BuildCollectionsByRevenueSourceReport $buildCollectionsByRevenueSourceReport,
        private readonly BuildPaidEstablishmentsReport $buildPaidEstablishmentsReport,
        private readonly BuildPermitApplicationTimeline $buildPermitApplicationTimeline,
        private readonly DescribeOnlinePaymentBoundary $describeOnlinePaymentBoundary,
        private readonly DescribePermitArtifact $describePermitArtifact,
        private readonly DescribePermitReleaseReadiness $describeReleaseReadiness,
        private readonly DescribePermitVerificationBoundary $describeVerificationBoundary,
        private readonly DescribeReceiptVoidBoundary $describeReceiptVoidBoundary,
        private readonly VoidReceipt $voidReceipt,
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
        $isCitizenOriginated = $scenario->key === 'citizen_new_permit_lifecycle_authority_boundary';
        $applicant = $isCitizenOriginated
            ? ($actors['applicant'] ?? throw new RuntimeException('Scenario applicant actor was not resolved.'))
            : null;
        $manifest = $this->scenarioManifest->initial($scenario, $runId, $actors);
        $lineOfBusiness = $this->lineOfBusiness();
        $secondaryLineOfBusiness = $this->secondaryLineOfBusiness();
        $this->feeRules($lineOfBusiness);

        $applicationData = [
            'owner_name' => 'Scenario Owner '.$runId,
            'owner_email' => null,
            'owner_phone' => null,
            'owner_address' => 'Scenario verification address',
            'business_name' => 'Scenario Receipt Business '.$runId,
            'trade_name' => 'Scenario Receipt Trade',
            'registration_number' => 'SCENARIO-'.$runId,
            'business_address' => 'Scenario verification address',
            'barangay' => 'Poblacion',
            'ownership_type' => 'sole-proprietorship',
            'occupancy' => 'rented',
            'building_name' => 'Scenario Commerce Building',
            'property_index_number' => 'SCENARIO-PIN-001',
            'business_area_square_meters' => '84.50',
            'male_employee_count' => 3,
            'female_employee_count' => 4,
            'business_contact_number' => '09170000000',
            'business_email' => 'scenario-business@example.test',
            'established_on' => '2018-01-15',
            'started_on' => '2018-02-01',
            'registered_on' => '2018-01-10',
            'application_year' => now()->year,
            'lines' => [
                [
                    'line_of_business_id' => $lineOfBusiness->id,
                    'declared_gross_sales_cents' => 125_000_00,
                    'capital_investment_cents' => 75_000_00,
                    'quantity' => 1,
                    'started_on' => '2018-02-01',
                ],
                [
                    'line_of_business_id' => $secondaryLineOfBusiness->id,
                    'declared_gross_sales_cents' => 45_000_75,
                    'capital_investment_cents' => 15_000_50,
                    'quantity' => 2,
                    'started_on' => '2021-06-01',
                ],
            ],
        ];

        if ($isCitizenOriginated) {
            $permitApplication = $this->createCitizenPermitApplicationDraft->handle($applicationData, $applicant);
            $supportingDocument = $this->storeScenarioDocument($permitApplication, $applicant, $runId, citizen: true);
            $permitApplication = $this->submitCitizenPermitApplication->handle($permitApplication, $applicant);
        } else {
            $permitApplication = $this->createPermitApplication->handle([
                ...$applicationData,
                'application_number' => 'APP-SCENARIO-'.$this->boundedRunReference($runId, 40),
                'type' => PermitApplicationType::New->value,
            ], $operator);
            $supportingDocument = $this->storeScenarioDocument($permitApplication, $operator, $runId);
        }

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
        $receiptVoidBlocked = false;

        try {
            $this->voidReceipt->handle($receipt, $operator);
        } catch (UnresolvedReceiptPolicy) {
            $receiptVoidBlocked = true;
        }

        $receipt->refresh();
        $collection->refresh();
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
        $permitArtifact = $this->describePermitArtifact->handle($permitApplication);
        $releaseBlocked = false;

        try {
            $this->attemptRelease->handle($permitApplication, $operator);
        } catch (UnresolvedPermitReleasePolicy) {
            $releaseBlocked = true;
        }

        $paymentSchedule->refresh();
        $collection->refresh();
        $receipt->refresh();
        $permitApplication = $paymentSchedule->permitApplication()->firstOrFail();
        $permitApplication->load('business');
        $applicationDisplayReference = $permitApplication->application_number ?? 'Application #'.$permitApplication->id;
        $reportSearch = $permitApplication->application_number ?? $permitApplication->business->name;
        $onlinePaymentBoundary = $this->describeOnlinePaymentBoundary->handle($paymentSchedule);
        $citizenPaymentSchedule = $isCitizenOriginated
            ? $this->describeCitizenPaymentSchedule->handle($paymentSchedule)
            : null;
        $dailyCollectionsReport = $this->buildDailyCollectionsReport->handle([
            'date_from' => $collection->received_at->toDateString(),
            'date_to' => $collection->received_at->toDateString(),
        ]);
        $revenueSourceReport = $this->buildCollectionsByRevenueSourceReport->handle([
            'date_from' => $collection->received_at->toDateString(),
            'date_to' => $collection->received_at->toDateString(),
        ]);
        $revenueSourceRow = collect($revenueSourceReport['rows'])
            ->firstWhere('code', 'SCENARIO-RECEIPT-APPLICATION-FEE');
        $paidEstablishmentsReport = $this->buildPaidEstablishmentsReport->handle([
            'year' => $permitApplication->application_year,
            'q' => $reportSearch,
        ]);
        $paidEstablishmentRow = collect($paidEstablishmentsReport['rows'])
            ->firstWhere('application_id', $permitApplication->id);
        $verificationBoundary = $this->describeVerificationBoundary->handle($permitApplication);
        $receiptVoidBoundary = $this->describeReceiptVoidBoundary->handle($receipt);
        $timeline = $this->buildPermitApplicationTimeline->handle($permitApplication);
        $timelineKeys = collect($timeline)->pluck('key')->all();

        $steps = [
            $this->step('actors-resolved', $isCitizenOriginated ? 'Resolve actual citizen and municipal operator' : 'Resolve actual application users', $isCitizenOriginated ? ['applicant_id' => $applicant?->id, 'operator_id' => $operator->id] : ['operator_id' => $operator->id], $isCitizenOriginated ? ['applicant_id' => $applicant?->id, 'operator_id' => $operator->id] : ['operator_id' => $operator->id]),
            $this->step('permit-application-created', $isCitizenOriginated ? 'Create permit application through citizen draft action' : 'Create permit application through staff intake action', ['created_as' => PermitApplicationStatus::Draft->value, 'submitted_by_id' => $isCitizenOriginated ? $applicant?->id : $operator->id], ['created_as' => PermitApplicationStatus::Draft->value, 'submitted_by_id' => $permitApplication->submitted_by_id, 'permit_application_id' => $permitApplication->id], $isCitizenOriginated ? 'applicant' : 'operator'),
            $this->step('business-activities-recorded', $isCitizenOriginated ? 'Record declared business activities through the citizen draft action' : 'Record multiple business activities through the staff intake action', ['activity_count' => 2], ['activity_count' => $permitApplication->lines->count(), 'activity_line_ids' => $permitApplication->lines->pluck('id')->all()], $isCitizenOriginated ? 'applicant' : 'operator'),
            $this->step('supporting-document-recorded', 'Record supporting evidence through permit document action', ['document_id' => $supportingDocument->id, 'storage_private' => true], ['document_id' => $supportingDocument->id, 'storage_private' => $supportingDocument->storage_disk === 'local' && Storage::disk('local')->exists($supportingDocument->path)], $isCitizenOriginated ? 'applicant' : 'operator'),
            ...($isCitizenOriginated ? [
                $this->step('citizen-application-submitted', 'Submit citizen draft through the formal submission action', ['status' => PermitApplicationStatus::Assessment->value, 'citizen_submitted' => true, 'municipality_received' => true, 'official_application_number' => null], ['status' => PermitApplicationStatus::Assessment->value, 'citizen_submitted' => data_get($permitApplication->metadata, 'citizen_submission.submitted_at') !== null, 'municipality_received' => data_get($permitApplication->metadata, 'municipal_receipt.received_at') !== null, 'official_application_number' => $permitApplication->application_number], 'applicant'),
            ] : []),
            $this->step('assessment-computed', 'Compute assessment through assessment action', ['assessment_status' => 'computed'], ['assessment_status' => $assessment->status->value, 'assessment_id' => $assessment->id]),
            $this->step('payment-schedule-prepared', 'Prepare payment schedule through payment schedule action', ['application_status' => PermitApplicationStatus::PendingPayment->value], ['application_status' => $permitApplication->status->value, 'payment_schedule_id' => $paymentSchedule->id]),
            $this->step('collection-recorded', 'Record full over-the-counter collection through Treasury action', ['payment_schedule_status' => PaymentScheduleStatus::Paid->value, 'collection_status' => TreasuryCollectionStatus::PendingReceipt->value], ['payment_schedule_status' => $paymentSchedule->status->value, 'collection_status' => $collectionStatusBeforeReceipt->value, 'collection_id' => $collection->id]),
            $this->step('online-payment-boundary-recorded', 'Describe online payment and reconciliation boundary without calling a gateway', ['online_payment_status' => 'blocked', 'can_pay_online' => false, 'can_reconcile_online' => false], ['online_payment_status' => $onlinePaymentBoundary['status'], 'can_pay_online' => $onlinePaymentBoundary['can_pay_online'], 'can_reconcile_online' => $onlinePaymentBoundary['can_reconcile_online']]),
            $this->step('manual-receipt-issued', 'Issue manual receipt through receipt action', ['receipt_status' => ReceiptStatus::Issued->value, 'collection_status' => TreasuryCollectionStatus::Receipted->value], ['receipt_status' => $receipt->status->value, 'collection_status' => $collection->status->value, 'receipt_id' => $receipt->id]),
            $this->step('receipt-void-blocked', 'Attempt receipt void through receipt policy boundary action', ['void_blocked' => true, 'receipt_status' => ReceiptStatus::Issued->value, 'collection_status' => TreasuryCollectionStatus::Receipted->value], ['void_blocked' => $receiptVoidBlocked, 'receipt_status' => $receipt->status->value, 'collection_status' => $collection->status->value, 'receipt_id' => $receipt->id]),
            $this->step('clearance-checklist-completed', 'Complete clearance checklist through clearance actions', ['completed_clearances' => 3, 'all_completed' => true], ['completed_clearances' => $completedClearances, 'all_completed' => $permitApplication->clearances->every(fn ($clearance): bool => $clearance->status === PermitClearanceStatus::Completed)]),
            $this->step('release-ready-for-authority-review', 'Describe release readiness without issuing permit', ['ready_for_authority_review' => true, 'can_release' => false], ['ready_for_authority_review' => $releaseReadiness['ready_for_authority_review'], 'can_release' => $releaseReadiness['can_release']]),
            $this->step('permit-artifact-available-for-authority-review', 'Describe generated permit artifact without issuing permit', ['status' => 'generated_artifact_available', 'ready_for_authority_review' => true, 'can_issue' => false, 'can_release' => false], ['status' => $permitArtifact['status'], 'ready_for_authority_review' => $permitArtifact['ready_for_authority_review'], 'can_issue' => $permitArtifact['can_issue'], 'can_release' => $permitArtifact['can_release']]),
            $this->step('permit-release-blocked', 'Attempt permit release through release boundary action', ['release_blocked' => true, 'application_status' => PermitApplicationStatus::PendingPayment->value], ['release_blocked' => $releaseBlocked, 'application_status' => $permitApplication->status->value]),
            $this->step('application-timeline-projected', 'Project authoritative lifecycle records into chronological review evidence', ['event_count' => $isCitizenOriginated ? 14 : 11, 'release_boundary_visible' => true], ['event_count' => count($timelineKeys), 'release_boundary_visible' => in_array("release-blocked:{$permitApplication->id}", $timelineKeys, true)]),
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
            'application_display_reference' => $applicationDisplayReference,
            'citizen_application_display_reference' => $permitApplication->application_number ?? 'Application record #'.$permitApplication->id,
            'public_application_display_reference' => $permitApplication->application_number ?? 'Unnumbered application',
            'application_status' => $permitApplication->status->value,
            'submitted_by_id' => $permitApplication->submitted_by_id,
            'business_activities' => $permitApplication->lines
                ->map(fn ($line): array => [
                    'id' => $line->id,
                    'code' => $line->lineOfBusiness?->code,
                    'name' => $line->lineOfBusiness?->name,
                    'declared_gross_sales_cents' => $line->declared_gross_sales_cents,
                    'capital_investment_cents' => $line->capital_investment_cents,
                    'quantity' => $line->quantity,
                    'started_on' => $line->started_on?->toDateString(),
                ])
                ->values()
                ->all(),
            'establishment_ownership_type' => $permitApplication->business->ownership_type,
            'establishment_occupancy' => $permitApplication->business->occupancy,
            'establishment_building_name' => $permitApplication->business->building_name,
            'establishment_property_index_number' => $permitApplication->business->property_index_number,
            'establishment_business_area_square_meters' => $permitApplication->business->business_area_square_meters,
            'establishment_male_employee_count' => $permitApplication->business->male_employee_count,
            'establishment_female_employee_count' => $permitApplication->business->female_employee_count,
            'establishment_started_on' => $permitApplication->business->started_on?->toDateString(),
            'supporting_document_id' => $supportingDocument->id,
            'supporting_document_label' => $supportingDocument->label,
            'supporting_document_name' => $supportingDocument->original_name,
            'supporting_document_download_url' => route('staff.permit-applications.documents.download', [$permitApplication, $supportingDocument], false),
            'assessment_id' => $assessment->id,
            'assessment_status' => $assessment->status->value,
            'assessment_total_amount_cents' => $assessment->total_amount_cents,
            'assessment_pdf_url' => route('staff.permit-applications.assessments.pdf', $assessment, false),
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_schedule_status' => $paymentSchedule->status->value,
            'payment_total_amount_cents' => $paymentSchedule->total_amount_cents,
            'payment_paid_amount_cents' => $paymentSchedule->paid_amount_cents,
            'payment_balance_amount_cents' => $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents,
            'online_payment_boundary_status' => $onlinePaymentBoundary['status'],
            'collection_id' => $collection->id,
            'collection_status' => $collection->status->value,
            'collection_amount_cents' => $collection->amount_cents,
            'receipt_id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'receipt_status' => $receipt->status->value,
            'permit_application_create_url' => route('staff.permit-applications.create', absolute: false),
            'permit_application_url' => route('staff.permit-applications.show', $permitApplication, false),
            'payment_schedule_queue_url' => route('staff.payment-schedules.index', [
                'q' => $reportSearch,
                'status' => PaymentScheduleStatus::Paid->value,
            ], false),
            'payment_schedule_url' => route('staff.payment-schedules.show', $paymentSchedule, false),
            'receipt_queue_url' => route('staff.receipts.index', [
                'q' => $receipt->receipt_number,
                'status' => ReceiptStatus::Issued->value,
            ], false),
            'receipt_url' => route('staff.receipts.show', $receipt, false),
            'receipt_pdf_url' => route('staff.receipts.pdf', $receipt, false),
            'daily_collection_report_url' => route('staff.reports.daily-collections.index', [
                'date_from' => $collection->received_at->toDateString(),
                'date_to' => $collection->received_at->toDateString(),
            ], false),
            'daily_collection_report_download_url' => route('staff.reports.daily-collections.download', [
                'date_from' => $collection->received_at->toDateString(),
                'date_to' => $collection->received_at->toDateString(),
            ], false),
            'revenue_source_report_url' => route('staff.reports.revenue-sources.index', [
                'date_from' => $collection->received_at->toDateString(),
                'date_to' => $collection->received_at->toDateString(),
            ], false),
            'revenue_source_report_download_url' => route('staff.reports.revenue-sources.download', [
                'date_from' => $collection->received_at->toDateString(),
                'date_to' => $collection->received_at->toDateString(),
            ], false),
            'revenue_source_code' => 'SCENARIO-RECEIPT-APPLICATION-FEE',
            'paid_establishments_report_url' => route('staff.reports.paid-establishments.index', [
                'year' => $permitApplication->application_year,
                'q' => $reportSearch,
            ], false),
            'paid_establishments_report_download_url' => route('staff.reports.paid-establishments.download', [
                'year' => $permitApplication->application_year,
                'q' => $reportSearch,
            ], false),
            'paid_establishment_business_name' => $permitApplication->business->name,
            'receipt_void_boundary_reference' => $receiptVoidBoundary['reference'],
            'application_form_pdf_url' => route('staff.permit-applications.application-form.pdf', $permitApplication, false),
            'permit_pdf_url' => route('staff.permit-applications.permit.pdf', $permitApplication, false),
            'permit_artifact_status' => $permitArtifact['status'],
            'permit_verification_reference' => $verificationBoundary['reference'],
            'permit_verification_url' => route('public.permits.verify', [
                'permitApplication' => $permitApplication,
                'verificationCode' => $verificationBoundary['reference'],
            ], false),
            'permit_verification_view_url' => route('public.permits.verify.view', [
                'permitApplication' => $permitApplication,
                'verificationCode' => $verificationBoundary['reference'],
            ], false),
            'permit_timeline_event_count' => count($timelineKeys),
            'permit_timeline_event_keys' => $timelineKeys,
            ...($isCitizenOriginated ? [
                'public_reference' => $applicationDisplayReference,
                'clearances_completed' => $permitApplication->clearances->where('status', PermitClearanceStatus::Completed)->count(),
                'clearances_total' => $permitApplication->clearances->count(),
                'ready_for_authority_review' => $releaseReadiness['ready_for_authority_review'],
                'can_release' => $releaseReadiness['can_release'],
                'authority_review_status' => $releaseReadiness['authority_boundary']['status'],
                'permit_artifact_available' => $permitArtifact['available'],
                'permit_verification_status' => $permitArtifact['verification_status'],
                'can_issue' => $permitArtifact['can_issue'],
                'can_make_legally_effective' => $permitArtifact['can_make_legally_effective'],
                'online_payment_status' => $onlinePaymentBoundary['status'],
                'can_pay_online' => $onlinePaymentBoundary['can_pay_online'],
                'can_reconcile_online' => $onlinePaymentBoundary['can_reconcile_online'],
                'payment_line_count' => $citizenPaymentSchedule['lines']->count(),
                'payment_line_codes' => $citizenPaymentSchedule['lines']->pluck('code')->all(),
                'payment_collection_count' => $citizenPaymentSchedule['collections']->count(),
                'payment_allocation_count' => $citizenPaymentSchedule['collections']->sum(fn (array $item): int => $item['allocations']->count()),
                'payment_policy_status' => $citizenPaymentSchedule['payment_policy_boundary']['status'],
                'can_split_installments' => $citizenPaymentSchedule['payment_policy_boundary']['can_split_installments'],
                'citizen_timeline_event_count' => count($timelineKeys),
                'citizen_timeline_event_keys' => $timelineKeys,
                'list_url' => route('citizen.permit-applications.index', absolute: false),
                'detail_url' => route('citizen.permit-applications.show', $permitApplication, false),
                'payment_detail_url' => route('citizen.payment-schedules.show', $paymentSchedule, false),
            ] : []),
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
            'supporting_document' => [
                'id' => $supportingDocument->id,
                'label' => $supportingDocument->label,
                'original_name' => $supportingDocument->original_name,
                'path' => $supportingDocument->path,
                'storage_disk' => $supportingDocument->storage_disk,
            ],
            'assessment_id' => $assessment->id,
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_schedule_status' => $paymentSchedule->status->value,
            'online_payment_boundary' => $onlinePaymentBoundary,
            'collection_id' => $collection->id,
            'collection_status' => $collection->status->value,
            'receipt_id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'receipt_status' => $receipt->status->value,
            'receipt_void_boundary' => $receiptVoidBoundary,
            'daily_collections_report' => [
                'date_from' => $dailyCollectionsReport['filters']['date_from'],
                'date_to' => $dailyCollectionsReport['filters']['date_to'],
                'row_count' => $dailyCollectionsReport['summary']['row_count'],
                'total_amount_cents' => $dailyCollectionsReport['summary']['total_amount_cents'],
                'receipt_number' => collect($dailyCollectionsReport['rows'])
                    ->firstWhere('receipt_number', $receipt->receipt_number)['receipt_number'] ?? null,
            ],
            'revenue_source_report' => [
                'date_from' => $revenueSourceReport['filters']['date_from'],
                'date_to' => $revenueSourceReport['filters']['date_to'],
                'source_count' => $revenueSourceReport['summary']['source_count'],
                'total_amount_cents' => $revenueSourceReport['summary']['total_amount_cents'],
                'source_code' => $revenueSourceRow['code'] ?? null,
            ],
            'paid_establishments_report' => [
                'year' => $paidEstablishmentsReport['filters']['year'],
                'row_count' => $paidEstablishmentsReport['summary']['row_count'],
                'paid_amount_cents' => $paidEstablishmentsReport['summary']['paid_amount_cents'],
                'application_number' => $paidEstablishmentRow['application_number'] ?? null,
                'business_name' => $paidEstablishmentRow['business_name'] ?? null,
            ],
            'clearances' => $permitApplication->clearances
                ->map(fn ($clearance): array => [
                    'id' => $clearance->id,
                    'code' => $clearance->code,
                    'status' => $clearance->status->value,
                ])
                ->values()
                ->all(),
            'release_policy_boundary' => $permitApplication->metadata['release_policy_boundary'] ?? null,
            'permit_artifact' => $permitArtifact,
            'release_readiness' => $releaseReadiness,
            'verification_boundary' => $verificationBoundary,
            'timeline' => $timeline,
            'run_id' => $runId,
        ]);
        $artifactStore->putJson('terminal/execution.json', [
            'steps' => $steps,
            'external_calls' => 0,
            'irreversible_actions' => false,
            'notifications' => false,
        ]);
        $artifactStore->putJson('storyboard/storyboard.json', $this->storyboard($scenario, $runId, $permitApplication, $paymentSchedule, $collection, $receipt));
        $artifactStore->put('storyboard/storyboard.html', $this->storyboardHtml($scenario, $runId, $permitApplication, $paymentSchedule, $collection, $receipt));
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
        $assessment = Assessment::query()->findOrFail($manifest['resources']['assessment_id']);
        $collection = TreasuryCollection::query()->with('receipt')->findOrFail($manifest['resources']['collection_id']);
        $receipt = Receipt::query()->findOrFail($manifest['resources']['record_id']);
        $supportingDocument = PermitApplicationDocument::query()->findOrFail($manifest['resources']['supporting_document_id']);
        $permitApplication = PermitApplication::query()
            ->with(['business', 'clearances', 'lines.lineOfBusiness'])
            ->findOrFail($manifest['resources']['permit_application_id']);
        $onlinePaymentBoundary = $this->describeOnlinePaymentBoundary->handle($paymentSchedule);
        $dailyCollectionsReport = $this->buildDailyCollectionsReport->handle([
            'date_from' => $collection->received_at->toDateString(),
            'date_to' => $collection->received_at->toDateString(),
        ]);
        $dailyCollectionsReportRow = collect($dailyCollectionsReport['rows'])
            ->firstWhere('receipt_number', $receipt->receipt_number);
        $revenueSourceReport = $this->buildCollectionsByRevenueSourceReport->handle([
            'date_from' => $collection->received_at->toDateString(),
            'date_to' => $collection->received_at->toDateString(),
        ]);
        $revenueSourceRow = collect($revenueSourceReport['rows'])
            ->firstWhere('code', $manifest['resources']['revenue_source_code']);
        $paidEstablishmentsReport = $this->buildPaidEstablishmentsReport->handle([
            'year' => $permitApplication->application_year,
            'q' => $permitApplication->application_number ?? $permitApplication->business->name,
        ]);
        $paidEstablishmentRow = collect($paidEstablishmentsReport['rows'])
            ->firstWhere('application_id', $permitApplication->id);
        $releaseReadiness = $this->describeReleaseReadiness->handle($permitApplication);
        $permitArtifact = $this->describePermitArtifact->handle($permitApplication);
        $verificationBoundary = $this->describeVerificationBoundary->handle($permitApplication);
        $receiptVoidBoundary = $this->describeReceiptVoidBoundary->handle($receipt);
        $timeline = $this->buildPermitApplicationTimeline->handle($permitApplication);
        $timelineKeys = collect($timeline)->pluck('key')->all();
        $browserReport = $artifactStore->readJson('browser/report.json') ?? [
            'result' => [
                'passed' => false,
            ],
            'checks' => [],
        ];

        $checks = [
            ...(($manifest['scenario']['key'] ?? null) === 'citizen_new_permit_lifecycle_authority_boundary' ? [
                $this->step('audit-citizen-origin', 'Application retains the citizen registry and submission origin throughout municipal processing', ['submitted_by_id' => data_get($manifest, 'actors.applicant.id'), 'application_number' => null, 'citizen_submitted' => true, 'municipality_received' => true], ['submitted_by_id' => $permitApplication->submitted_by_id, 'application_number' => $permitApplication->application_number, 'citizen_submitted' => data_get($permitApplication->metadata, 'citizen_submission.submitted_at') !== null, 'municipality_received' => data_get($permitApplication->metadata, 'municipal_receipt.received_at') !== null]),
                $this->step('audit-browser-citizen-milestone', 'Citizen browser evidence agrees with the exact final canonical record', ['application_status' => $permitApplication->status->value, 'payment_schedule_id' => $paymentSchedule->id, 'receipt_id' => $receipt->id, 'ready_for_authority_review' => true, 'can_release' => false], ['application_status' => data_get($browserReport, 'citizen_processing.application_status'), 'payment_schedule_id' => data_get($browserReport, 'citizen_processing.payment_schedule_id'), 'receipt_id' => data_get($browserReport, 'citizen_authority_review.receipt_id'), 'ready_for_authority_review' => data_get($browserReport, 'citizen_authority_review.ready_for_authority_review'), 'can_release' => data_get($browserReport, 'citizen_authority_review.can_release')]),
            ] : []),
            $this->step('audit-payment-schedule-paid', 'Payment schedule is paid', ['status' => PaymentScheduleStatus::Paid->value], ['status' => $paymentSchedule->status->value]),
            $this->step('audit-business-activities', 'Canonical permit application retains every prepared business activity', ['activities' => $manifest['resources']['business_activities']], ['activities' => $permitApplication->lines->map(fn ($line): array => ['id' => $line->id, 'code' => $line->lineOfBusiness?->code, 'name' => $line->lineOfBusiness?->name, 'declared_gross_sales_cents' => $line->declared_gross_sales_cents, 'capital_investment_cents' => $line->capital_investment_cents, 'quantity' => $line->quantity, 'started_on' => $line->started_on?->toDateString()])->values()->all()]),
            $this->step('audit-browser-business-activities', 'Browser intake controls and detail rows agree with canonical business activities', ['intake_add_remove_verified' => true, 'intake_mobile_visible' => true, 'activities' => $manifest['resources']['business_activities']], ['intake_add_remove_verified' => data_get($browserReport, 'business_activities.intake_add_remove_verified'), 'intake_mobile_visible' => data_get($browserReport, 'business_activities.intake_mobile_visible'), 'activities' => data_get($browserReport, 'business_activities.activities')]),
            $this->step('audit-establishment-profile', 'Structured establishment profile remains attached to the exact business', ['ownership_type' => $manifest['resources']['establishment_ownership_type'], 'occupancy' => $manifest['resources']['establishment_occupancy'], 'business_area_square_meters' => $manifest['resources']['establishment_business_area_square_meters'], 'male_employee_count' => $manifest['resources']['establishment_male_employee_count'], 'female_employee_count' => $manifest['resources']['establishment_female_employee_count'], 'started_on' => $manifest['resources']['establishment_started_on']], ['ownership_type' => $permitApplication->business->ownership_type, 'occupancy' => $permitApplication->business->occupancy, 'business_area_square_meters' => $permitApplication->business->business_area_square_meters, 'male_employee_count' => $permitApplication->business->male_employee_count, 'female_employee_count' => $permitApplication->business->female_employee_count, 'started_on' => $permitApplication->business->started_on?->toDateString()]),
            $this->step('audit-browser-establishment-profile', 'Browser evidence shows the real intake surface and exact canonical establishment profile', ['ownership_type' => $manifest['resources']['establishment_ownership_type'], 'occupancy' => $manifest['resources']['establishment_occupancy'], 'business_area_square_meters' => $manifest['resources']['establishment_business_area_square_meters'], 'male_employee_count' => $manifest['resources']['establishment_male_employee_count'], 'female_employee_count' => $manifest['resources']['establishment_female_employee_count'], 'started_on' => $manifest['resources']['establishment_started_on'], 'intake_form_visible' => true, 'intake_form_mobile_visible' => true, 'mobile_visible' => true], ['ownership_type' => data_get($browserReport, 'establishment_profile.ownership_type'), 'occupancy' => data_get($browserReport, 'establishment_profile.occupancy'), 'business_area_square_meters' => data_get($browserReport, 'establishment_profile.business_area_square_meters'), 'male_employee_count' => data_get($browserReport, 'establishment_profile.male_employee_count'), 'female_employee_count' => data_get($browserReport, 'establishment_profile.female_employee_count'), 'started_on' => data_get($browserReport, 'establishment_profile.started_on'), 'intake_form_visible' => data_get($browserReport, 'establishment_profile.intake_form_visible'), 'intake_form_mobile_visible' => data_get($browserReport, 'establishment_profile.intake_form_mobile_visible'), 'mobile_visible' => data_get($browserReport, 'establishment_profile.mobile_visible')]),
            $this->step('audit-supporting-document', 'Supporting evidence remains attached to the exact permit application in private storage', ['permit_application_id' => $permitApplication->id, 'document_id' => $manifest['resources']['supporting_document_id'], 'storage_private' => true], ['permit_application_id' => $supportingDocument->permit_application_id, 'document_id' => $supportingDocument->id, 'storage_private' => $supportingDocument->storage_disk === 'local' && Storage::disk('local')->exists($supportingDocument->path)]),
            $this->step('audit-browser-supporting-document', 'Browser evidence observed and downloaded the exact supporting document', ['document_id' => $supportingDocument->id, 'label' => $supportingDocument->label, 'download_available' => true], ['document_id' => data_get($browserReport, 'supporting_document.id'), 'label' => data_get($browserReport, 'supporting_document.label'), 'download_available' => data_get($browserReport, 'supporting_document.download_available')]),
            $this->step('audit-online-payment-boundary', 'Online payment and reconciliation boundary remains blocked', ['status' => 'blocked', 'can_pay_online' => false, 'can_reconcile_online' => false], ['status' => $onlinePaymentBoundary['status'], 'can_pay_online' => $onlinePaymentBoundary['can_pay_online'], 'can_reconcile_online' => $onlinePaymentBoundary['can_reconcile_online']]),
            $this->step('audit-browser-online-payment-boundary', 'Browser evidence observed the online payment and reconciliation boundary', ['status' => 'blocked', 'can_pay_online' => false, 'can_reconcile_online' => false], ['status' => data_get($browserReport, 'online_payment_boundary.status'), 'can_pay_online' => data_get($browserReport, 'online_payment_boundary.can_pay_online'), 'can_reconcile_online' => data_get($browserReport, 'online_payment_boundary.can_reconcile_online')]),
            $this->step('audit-collection-receipted', 'Collection is receipted', ['status' => TreasuryCollectionStatus::Receipted->value], ['status' => $collection->status->value]),
            $this->step('audit-receipt-issued', 'Manual receipt is issued', ['status' => ReceiptStatus::Issued->value, 'numbering_authority' => 'manual'], ['status' => $receipt->status->value, 'numbering_authority' => $receipt->numbering_authority]),
            $this->step('audit-daily-collection-report-row', 'Daily collection report contains the exact receipted collection', ['receipt_number' => $receipt->receipt_number, 'amount_cents' => $collection->amount_cents], ['receipt_number' => $dailyCollectionsReportRow['receipt_number'] ?? null, 'amount_cents' => $dailyCollectionsReportRow['amount_cents'] ?? null]),
            $this->step('audit-browser-daily-collection-report-row', 'Browser evidence observed the daily collection report row', ['receipt_number' => $receipt->receipt_number, 'amount_cents' => $collection->amount_cents], ['receipt_number' => data_get($browserReport, 'reports.daily_collection.receipt_number'), 'amount_cents' => data_get($browserReport, 'reports.daily_collection.amount_cents')]),
            $this->step('audit-revenue-source-report-row', 'Revenue source report contains the scenario collection allocation source', ['source_code' => $manifest['resources']['revenue_source_code'], 'source_present' => true], ['source_code' => $revenueSourceRow['code'] ?? null, 'source_present' => $revenueSourceRow !== null]),
            $this->step('audit-browser-revenue-source-report-row', 'Browser evidence observed the revenue source report row', ['source_code' => $manifest['resources']['revenue_source_code'], 'csv_export_visible' => true], ['source_code' => data_get($browserReport, 'reports.revenue_source.source_code'), 'csv_export_visible' => data_get($browserReport, 'reports.revenue_source.csv_export_visible')]),
            $this->step('audit-paid-establishments-report-row', 'Paid establishments report contains the scenario paid permit schedule', ['application_number' => $permitApplication->application_number, 'business_name' => $permitApplication->business->name], ['application_number' => $paidEstablishmentRow['application_number'] ?? null, 'business_name' => $paidEstablishmentRow['business_name'] ?? null]),
            $this->step('audit-browser-paid-establishments-report-row', 'Browser evidence observed the paid establishments report row', ['application_number' => $permitApplication->application_number, 'csv_export_visible' => true], ['application_number' => data_get($browserReport, 'reports.paid_establishments.application_number'), 'csv_export_visible' => data_get($browserReport, 'reports.paid_establishments.csv_export_visible')]),
            $this->step('audit-receipt-void-boundary', 'Receipt void boundary remains blocked without financial mutation', ['reference' => $receiptVoidBoundary['reference'], 'status' => 'blocked', 'can_void' => false, 'receipt_status' => ReceiptStatus::Issued->value, 'collection_status' => TreasuryCollectionStatus::Receipted->value], ['reference' => $manifest['resources']['receipt_void_boundary_reference'] ?? null, 'status' => $receiptVoidBoundary['status'], 'can_void' => $receiptVoidBoundary['can_void'], 'receipt_status' => $receipt->status->value, 'collection_status' => $collection->status->value]),
            $this->step('audit-browser-receipt-void-boundary', 'Browser evidence observed the same receipt void boundary', ['reference' => $receiptVoidBoundary['reference'], 'status' => 'blocked', 'can_void' => false], ['reference' => data_get($browserReport, 'receipt_void_boundary.reference'), 'status' => data_get($browserReport, 'receipt_void_boundary.status'), 'can_void' => data_get($browserReport, 'receipt_void_boundary.can_void')]),
            $this->step('audit-clearances-completed', 'Clearance checklist evidence is complete', ['completed_clearances' => 3, 'all_completed' => true], ['completed_clearances' => $permitApplication->clearances->where('status', PermitClearanceStatus::Completed)->count(), 'all_completed' => $permitApplication->clearances->isNotEmpty() && $permitApplication->clearances->every(fn ($clearance): bool => $clearance->status === PermitClearanceStatus::Completed)]),
            $this->step('audit-release-readiness', 'Release readiness is ready for authority review but not releasable', ['ready_for_authority_review' => true, 'can_release' => false], ['ready_for_authority_review' => $releaseReadiness['ready_for_authority_review'], 'can_release' => $releaseReadiness['can_release']]),
            $this->step('audit-authority-boundary', 'Authority boundary separates software evidence from human issuance authority', ['status' => 'ready_for_authority_review', 'artifact_statement' => 'Generated permit artifacts support authority review but do not issue, release, or make a permit legally effective.'], ['status' => $releaseReadiness['authority_boundary']['status'], 'artifact_statement' => $releaseReadiness['authority_boundary']['artifact_statement']]),
            $this->step('audit-permit-artifact', 'Generated permit artifact remains review evidence and not issuance', ['status' => 'generated_artifact_available', 'ready_for_authority_review' => true, 'can_issue' => false, 'can_release' => false, 'can_make_legally_effective' => false], ['status' => $permitArtifact['status'], 'ready_for_authority_review' => $permitArtifact['ready_for_authority_review'], 'can_issue' => $permitArtifact['can_issue'], 'can_release' => $permitArtifact['can_release'], 'can_make_legally_effective' => $permitArtifact['can_make_legally_effective']]),
            $this->step('audit-browser-permit-artifact', 'Browser evidence observed the same permit artifact boundary', ['permit_pdf_url' => $permitArtifact['permit_pdf_url'], 'verification_reference' => $permitArtifact['verification_reference'], 'panel_visible' => true, 'not_legally_effective_visible' => true, 'open_affordance_visible' => true], ['permit_pdf_url' => data_get($browserReport, 'permit_artifact.permit_pdf_url'), 'verification_reference' => data_get($browserReport, 'permit_artifact.verification_reference'), 'panel_visible' => data_get($browserReport, 'permit_artifact.panel_visible'), 'not_legally_effective_visible' => data_get($browserReport, 'permit_artifact.not_legally_effective_visible'), 'open_affordance_visible' => data_get($browserReport, 'permit_artifact.open_affordance_visible')]),
            $this->step('audit-release-boundary', 'Permit release remains blocked by explicit policy boundary', ['status' => PermitApplicationStatus::PendingPayment->value, 'blocked_transition' => PermitApplicationStatus::Released->value], ['status' => $permitApplication->status->value, 'blocked_transition' => $permitApplication->metadata['release_policy_boundary']['blocked_transition'] ?? null]),
            $this->step('audit-application-timeline', 'Timeline projection matches the exact authoritative lifecycle records prepared for this run', ['event_count' => $manifest['resources']['permit_timeline_event_count'], 'event_keys' => $manifest['resources']['permit_timeline_event_keys']], ['event_count' => count($timelineKeys), 'event_keys' => $timelineKeys]),
            $this->step('audit-browser-application-timeline', 'Browser evidence shows the exact canonical timeline event keys', ['event_count' => count($timelineKeys), 'event_keys' => $timelineKeys], ['event_count' => data_get($browserReport, 'timeline.event_count'), 'event_keys' => data_get($browserReport, 'timeline.event_keys')]),
            $this->step('audit-browser-application-form-pdf', 'Browser evidence confirms application form artifact renders exact intake facts', ['application_number' => $permitApplication->application_number, 'available' => true], ['application_number' => data_get($browserReport, 'documents.application_form.application_number'), 'available' => (bool) data_get($browserReport, 'documents.application_form.available')]),
            $this->step('audit-browser-assessment-pdf', 'Browser evidence confirms assessment artifact renders exact persisted assessment snapshot', ['assessment_id' => $assessment->id, 'total_amount_cents' => $assessment->total_amount_cents, 'available' => true], ['assessment_id' => data_get($browserReport, 'documents.assessment.assessment_id'), 'total_amount_cents' => data_get($browserReport, 'documents.assessment.total_amount_cents'), 'available' => (bool) data_get($browserReport, 'documents.assessment.available')]),
            $this->step('audit-verification-reference', 'Permit artifact verification reference matches canonical boundary', ['reference' => $verificationBoundary['reference'], 'can_verify_release' => false], ['reference' => $manifest['resources']['permit_verification_reference'] ?? null, 'can_verify_release' => $verificationBoundary['can_verify_release']]),
            $this->step('audit-browser-verification-reference', 'Browser evidence observed the same permit verification reference and exact interfaces', ['reference' => $verificationBoundary['reference'], 'api_url' => $manifest['resources']['permit_verification_url'], 'public_page_url' => $manifest['resources']['permit_verification_view_url']], ['reference' => data_get($browserReport, 'verification.reference'), 'api_url' => data_get($browserReport, 'verification.api_url'), 'public_page_url' => data_get($browserReport, 'verification.public_page_url')]),
            $this->step('audit-browser-public-verification', 'Browser evidence confirms public verification is artifact-only', ['status' => 'artifact_only', 'can_verify_release' => false, 'page_visible' => true, 'mobile_visible' => true], ['status' => data_get($browserReport, 'verification.public_status'), 'can_verify_release' => data_get($browserReport, 'verification.can_verify_release'), 'page_visible' => data_get($browserReport, 'verification.public_page_visible'), 'mobile_visible' => data_get($browserReport, 'verification.public_page_mobile_visible')]),
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
                'business_activities' => $permitApplication->lines
                    ->map(fn ($line): array => [
                        'id' => $line->id,
                        'code' => $line->lineOfBusiness?->code,
                        'name' => $line->lineOfBusiness?->name,
                        'declared_gross_sales_cents' => $line->declared_gross_sales_cents,
                        'capital_investment_cents' => $line->capital_investment_cents,
                        'quantity' => $line->quantity,
                        'started_on' => $line->started_on?->toDateString(),
                    ])
                    ->values()
                    ->all(),
                'establishment_profile' => [
                    'business_id' => $permitApplication->business->id,
                    'ownership_type' => $permitApplication->business->ownership_type,
                    'occupancy' => $permitApplication->business->occupancy,
                    'building_name' => $permitApplication->business->building_name,
                    'property_index_number' => $permitApplication->business->property_index_number,
                    'business_area_square_meters' => $permitApplication->business->business_area_square_meters,
                    'male_employee_count' => $permitApplication->business->male_employee_count,
                    'female_employee_count' => $permitApplication->business->female_employee_count,
                    'started_on' => $permitApplication->business->started_on?->toDateString(),
                ],
                'supporting_document' => [
                    'id' => $supportingDocument->id,
                    'permit_application_id' => $supportingDocument->permit_application_id,
                    'label' => $supportingDocument->label,
                    'original_name' => $supportingDocument->original_name,
                    'storage_disk' => $supportingDocument->storage_disk,
                    'path' => $supportingDocument->path,
                ],
                'payment_schedule_status' => $paymentSchedule->status->value,
                'paid_amount_cents' => $paymentSchedule->paid_amount_cents,
                'online_payment_boundary' => $onlinePaymentBoundary,
                'collection_id' => $collection->id,
                'collection_status' => $collection->status->value,
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'receipt_status' => $receipt->status->value,
                'numbering_authority' => $receipt->numbering_authority,
                'receipt_void_boundary' => $receiptVoidBoundary,
                'daily_collections_report' => [
                    'date_from' => $dailyCollectionsReport['filters']['date_from'],
                    'date_to' => $dailyCollectionsReport['filters']['date_to'],
                    'row_count' => $dailyCollectionsReport['summary']['row_count'],
                    'total_amount_cents' => $dailyCollectionsReport['summary']['total_amount_cents'],
                    'receipt_number' => $dailyCollectionsReportRow['receipt_number'] ?? null,
                ],
                'revenue_source_report' => [
                    'date_from' => $revenueSourceReport['filters']['date_from'],
                    'date_to' => $revenueSourceReport['filters']['date_to'],
                    'source_count' => $revenueSourceReport['summary']['source_count'],
                    'total_amount_cents' => $revenueSourceReport['summary']['total_amount_cents'],
                    'source_code' => $revenueSourceRow['code'] ?? null,
                ],
                'paid_establishments_report' => [
                    'year' => $paidEstablishmentsReport['filters']['year'],
                    'row_count' => $paidEstablishmentsReport['summary']['row_count'],
                    'paid_amount_cents' => $paidEstablishmentsReport['summary']['paid_amount_cents'],
                    'application_number' => $paidEstablishmentRow['application_number'] ?? null,
                    'business_name' => $paidEstablishmentRow['business_name'] ?? null,
                ],
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
                'permit_artifact' => $permitArtifact,
                'release_readiness' => $releaseReadiness,
                'verification_boundary' => $verificationBoundary,
                'timeline' => $timeline,
            ],
            'browser' => $browserReport,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('summary.html', $this->summaryRenderer->html($manifest));

        return $manifest;
    }

    private function storeScenarioDocument(PermitApplication $permitApplication, User $actor, string $runId, bool $citizen = false): PermitApplicationDocument
    {
        $document = new SimplePdfDocument(
            title: 'Scenario Supporting Evidence',
            documentCode: 'SCENARIO-EVIDENCE',
            subtitle: 'Permit application supporting document',
            footerNote: 'Lifecycle scenario evidence only.',
        );
        $page = $document->addPage('Supporting evidence');
        $document->text($page, 'Business registration evidence', 42, 710, 14, true);
        $document->wrappedText($page, "Run ID: {$runId}", 42, 680, 511, 10);
        $document->wrappedText($page, 'Receipt of this artifact does not establish statutory sufficiency, approval, or permit eligibility.', 42, 650, 511, 10);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'bpls-scenario-document-');

        if ($temporaryPath === false || file_put_contents($temporaryPath, $document->render()) === false) {
            throw new RuntimeException('Unable to prepare lifecycle scenario supporting document.');
        }

        try {
            $data = [
                'label' => 'Business registration evidence',
                'file' => new UploadedFile(
                    $temporaryPath,
                    'scenario-business-registration.pdf',
                    'application/pdf',
                    null,
                    true,
                ),
                'remarks' => 'Lifecycle scenario intake evidence.',
            ];

            if ($citizen) {
                return $this->storeCitizenPermitApplicationDocument->handle($permitApplication, $data, $actor);
            }

            return $this->storePermitApplicationDocument->handle($permitApplication, $data, $actor);
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
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

    private function secondaryLineOfBusiness(): LineOfBusiness
    {
        return LineOfBusiness::query()->firstOrCreate(
            ['code' => 'SCENARIO-REPAIR'],
            [
                'name' => 'Scenario Repair Services',
                'major_category' => 'Services',
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
    private function step(string $key, string $action, array $expected, array $actual, string $actor = 'operator'): array
    {
        return [
            'key' => $key,
            'actor' => $actor,
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
    private function storyboard(LifecycleScenarioDefinition $scenario, string $runId, PermitApplication $permitApplication, PaymentSchedule $paymentSchedule, TreasuryCollection $collection, Receipt $receipt): array
    {
        $isCitizenOriginated = $scenario->key === 'citizen_new_permit_lifecycle_authority_boundary';
        $isUnifiedPermitLifecycle = $isCitizenOriginated || $scenario->key === 'new_permit_lifecycle_authority_boundary';

        return [
            'title' => $isCitizenOriginated
                ? 'Citizen-originated new permit lifecycle to authority boundary'
                : ($isUnifiedPermitLifecycle ? 'New permit lifecycle to authority boundary' : 'Manual collection receipt visibility'),
            'summary' => $isUnifiedPermitLifecycle
                ? ($isCitizenOriginated
                    ? 'A citizen creates and formally submits an unnumbered new permit application; BPLO and Treasury continue the exact record through assessment, collection, receipt, clearances, artifact verification, and the authority boundary without legally issuing or releasing the permit.'
                    : 'BPLO/Treasury staff execute a new permit lifecycle through intake, assessment, payment schedule, collection, receipt, clearances, permit artifact generation, public verification, and the explicit authority boundary without legally issuing or releasing the permit.')
                : 'BPLO/Treasury staff prepare a collectible assessment, record full over-the-counter payment, issue a manual receipt, and verify the receipt is visible from Treasury surfaces.',
            'run_id' => $runId,
            'record' => [
                'type' => $isUnifiedPermitLifecycle ? 'permit_lifecycle' : 'receipt',
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'application_number' => $permitApplication->application_number,
                'payment_schedule_id' => $paymentSchedule->id,
                'collection_id' => $collection->id,
                'permit_verification_reference' => $this->describeVerificationBoundary->handle($permitApplication)['reference'],
                'receipt_void_boundary_reference' => $this->describeReceiptVoidBoundary->handle($receipt)['reference'],
            ],
            'frames' => [
                [
                    'title' => $isCitizenOriginated ? 'Citizen submits new permit application' : 'Staff records new permit application',
                    'description' => $isCitizenOriginated
                        ? 'The citizen saves a draft, attaches supporting evidence, and formally submits the exact unnumbered record into the municipal assessment queue.'
                        : 'Staff records a new business permit application, computes assessment, and prepares a payment schedule.',
                    'dialogue' => $isCitizenOriginated
                        ? 'Submission and municipal receipt are recorded without inventing an official application number.'
                        : 'The application is pending payment and ready for Treasury collection.',
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
                    'title' => 'Receipt void remains blocked',
                    'description' => 'The scenario attempts receipt voiding through the receipt policy boundary action.',
                    'dialogue' => 'The receipt and collection remain unchanged because reversal and reconciliation policy are unresolved.',
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
                    'title' => 'Permit artifact is verifiable as an artifact',
                    'description' => 'The scenario records a public verification reference for the generated permit artifact.',
                    'dialogue' => 'The public route confirms artifact identity only; it does not verify permit release.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => $isUnifiedPermitLifecycle ? 'Reviewer confirms lifecycle evidence' : 'Reviewer confirms receipt visibility',
                    'description' => 'The browser opens the payment schedule, receipt, permit detail, permit PDF, and public verification surfaces for the exact manifest records.',
                    'dialogue' => 'Visible UI state, document evidence, public verification, and canonical records agree.',
                    'duration_seconds' => 5,
                ],
            ],
        ];
    }

    private function storyboardHtml(LifecycleScenarioDefinition $scenario, string $runId, PermitApplication $permitApplication, PaymentSchedule $paymentSchedule, TreasuryCollection $collection, Receipt $receipt): string
    {
        $storyboard = $this->storyboard($scenario, $runId, $permitApplication, $paymentSchedule, $collection, $receipt);
        $frames = collect($storyboard['frames'])
            ->map(fn (array $frame): string => '<li><strong>'.e($frame['title']).'</strong><br>'.e($frame['description']).'<br><em>'.e($frame['dialogue']).'</em></li>')
            ->implode('');

        $applicationReference = $permitApplication->application_number ?? 'Application #'.$permitApplication->id;

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.e($storyboard['title']).'</title></head><body><h1>'.e($storyboard['title']).'</h1><p>'.e($storyboard['summary']).'</p><p>Run ID: '.e($runId).'</p><p>Application: '.e($applicationReference).'</p><p>Payment schedule: '.e((string) $paymentSchedule->id).'</p><p>Collection: '.e((string) $collection->id).'</p><p>Receipt: '.e($receipt->receipt_number).'</p><ol>'.$frames.'</ol></body></html>';
    }

    private function safeRunReference(string $runId): string
    {
        return $this->boundedRunReference($runId, 60);
    }

    private function boundedRunReference(string $runId, int $maximumLength): string
    {
        $reference = str($runId)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->toString();

        if (mb_strlen($reference) <= $maximumLength) {
            return $reference;
        }

        $hash = substr(hash('sha256', $runId), 0, 10);

        return mb_substr($reference, 0, $maximumLength - mb_strlen($hash) - 1).'-'.$hash;
    }
}
