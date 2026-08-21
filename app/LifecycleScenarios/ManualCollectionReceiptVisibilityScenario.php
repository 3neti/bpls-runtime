<?php

namespace App\LifecycleScenarios;

use App\Actions\AttemptPermitApplicationRelease;
use App\Actions\BuildBusinessTaxByMajorTypeReport;
use App\Actions\BuildCollectionsByRevenueSourceReport;
use App\Actions\BuildDailyCollectionsReport;
use App\Actions\BuildMunicipalityConfiguration;
use App\Actions\BuildNelsonWalkthroughEvidence;
use App\Actions\BuildPaidEstablishmentsReport;
use App\Actions\BuildPaymentSummaryReport;
use App\Actions\BuildPermitApplicationTimeline;
use App\Actions\BuildTotalCapitalGrossSummaryReport;
use App\Actions\CompletePermitClearance;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreateCitizenPermitApplicationDraft;
use App\Actions\CreatePaymentScheduleForAssessment;
use App\Actions\CreatePermitApplication;
use App\Actions\DescribeAllAbstractReportBoundary;
use App\Actions\DescribeAnnexCDnfbpReportBoundary;
use App\Actions\DescribeBspReportBoundary;
use App\Actions\DescribeCitizenPaymentSchedule;
use App\Actions\DescribeCmciLdcsReportBoundary;
use App\Actions\DescribeOnlinePaymentBoundary;
use App\Actions\DescribePermitArtifact;
use App\Actions\DescribePermitReleaseReadiness;
use App\Actions\DescribePermitVerificationBoundary;
use App\Actions\DescribePldsReportBoundary;
use App\Actions\DescribeReceiptVoidBoundary;
use App\Actions\EnsurePermitApplicationClearances;
use App\Actions\IssueManualCollectionReceipt;
use App\Actions\RecordAssessmentDecision;
use App\Actions\RecordOfficeChargeContribution;
use App\Actions\RecordPaymentScheduleCollection;
use App\Actions\RecordProvisionalUatPermitDecision;
use App\Actions\SimplePdfDocument;
use App\Actions\StoreCitizenPermitApplicationDocument;
use App\Actions\StorePermitApplicationDocument;
use App\Actions\SubmitCitizenPermitApplication;
use App\Actions\VoidReceipt;
use App\Enums\AssessmentDecisionAction;
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
use App\Notifications\PermitApplicationReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ManualCollectionReceiptVisibilityScenario
{
    private const int ScenarioApplicationYear = 2001;

    public function __construct(
        private readonly CreatePermitApplication $createPermitApplication,
        private readonly CreateCitizenPermitApplicationDraft $createCitizenPermitApplicationDraft,
        private readonly SubmitCitizenPermitApplication $submitCitizenPermitApplication,
        private readonly StorePermitApplicationDocument $storePermitApplicationDocument,
        private readonly StoreCitizenPermitApplicationDocument $storeCitizenPermitApplicationDocument,
        private readonly CreateAssessmentForPermitApplication $createAssessment,
        private readonly RecordAssessmentDecision $recordAssessmentDecision,
        private readonly RecordOfficeChargeContribution $recordOfficeChargeContribution,
        private readonly RecordProvisionalUatPermitDecision $recordProvisionalUatPermitDecision,
        private readonly CreatePaymentScheduleForAssessment $createPaymentSchedule,
        private readonly RecordPaymentScheduleCollection $recordCollection,
        private readonly IssueManualCollectionReceipt $issueReceipt,
        private readonly EnsurePermitApplicationClearances $ensureClearances,
        private readonly CompletePermitClearance $completeClearance,
        private readonly AttemptPermitApplicationRelease $attemptRelease,
        private readonly DescribeCitizenPaymentSchedule $describeCitizenPaymentSchedule,
        private readonly BuildDailyCollectionsReport $buildDailyCollectionsReport,
        private readonly BuildBusinessTaxByMajorTypeReport $buildBusinessTaxByMajorTypeReport,
        private readonly BuildTotalCapitalGrossSummaryReport $buildTotalCapitalGrossSummaryReport,
        private readonly DescribeAllAbstractReportBoundary $describeAllAbstractReportBoundary,
        private readonly DescribeAnnexCDnfbpReportBoundary $describeAnnexCDnfbpReportBoundary,
        private readonly DescribeBspReportBoundary $describeBspReportBoundary,
        private readonly DescribeCmciLdcsReportBoundary $describeCmciLdcsReportBoundary,
        private readonly DescribePldsReportBoundary $describePldsReportBoundary,
        private readonly BuildCollectionsByRevenueSourceReport $buildCollectionsByRevenueSourceReport,
        private readonly BuildPaidEstablishmentsReport $buildPaidEstablishmentsReport,
        private readonly BuildPaymentSummaryReport $buildPaymentSummaryReport,
        private readonly BuildPermitApplicationTimeline $buildPermitApplicationTimeline,
        private readonly DescribeOnlinePaymentBoundary $describeOnlinePaymentBoundary,
        private readonly DescribePermitArtifact $describePermitArtifact,
        private readonly DescribePermitReleaseReadiness $describeReleaseReadiness,
        private readonly DescribePermitVerificationBoundary $describeVerificationBoundary,
        private readonly DescribeReceiptVoidBoundary $describeReceiptVoidBoundary,
        private readonly VoidReceipt $voidReceipt,
        private readonly BuildMunicipalityConfiguration $buildMunicipalityConfiguration,
        private readonly BuildNelsonWalkthroughEvidence $buildNelsonWalkthroughEvidence,
        private readonly ScenarioManifest $scenarioManifest,
        private readonly ScenarioSummaryRenderer $summaryRenderer,
        private readonly NelsonWalkthroughRenderer $nelsonWalkthroughRenderer,
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
        $assessmentPreparer = $actors['assessment_officer'] ?? $operator;
        $assessmentPreparationActor = isset($actors['assessment_officer']) ? 'assessment_officer' : 'operator';
        $assessmentApprover = $actors['approver'] ?? User::query()
            ->where('email', config('lifecycle_scenarios.actors.assessment_approver.email'))
            ->firstOrFail();
        $assessmentDecisionActor = 'approver';
        $isCitizenOriginated = $this->isCitizenOriginated($scenario->key);
        $isNelsonWalkthrough = $this->isStakeholderWalkthrough($scenario->key);
        $isWeekendPreview = $scenario->key === 'stakeholder_preview_cycle_1';
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
            'application_year' => self::ScenarioApplicationYear,
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
            $supportingDocument = $this->storeScenarioDocument($permitApplication, $applicant, $runId, citizen: true, label: 'Proof of Registration · DTI');
            if ($isWeekendPreview) {
                $this->storeWeekendScenarioDocuments($permitApplication, $applicant, $runId, citizen: true);
            }
            $permitApplication = $this->submitCitizenPermitApplication->handle($permitApplication, $applicant);
        } else {
            $permitApplication = $this->createPermitApplication->handle([
                ...$applicationData,
                'application_number' => 'APP-SCENARIO-'.$this->boundedRunReference($runId, 40),
                'type' => PermitApplicationType::New->value,
            ], $operator);
            $supportingDocument = $this->storeScenarioDocument($permitApplication, $operator, $runId, label: 'Proof of Registration · DTI');
            if ($isWeekendPreview) {
                $this->storeWeekendScenarioDocuments($permitApplication, $operator, $runId);
            }
        }

        $officeChargeContributions = collect();
        if ($isWeekendPreview) {
            foreach (config('stakeholder_preview.weekend_hypothesis.office_charges', []) as $officeCode => $office) {
                $officeActor = $actors[$officeCode] ?? throw new RuntimeException("Scenario office actor [{$officeCode}] was not resolved.");
                $officeChargeContributions->push($this->recordOfficeChargeContribution->handle(
                    $permitApplication,
                    $officeActor,
                    true,
                    (int) $office['scenario_amount_cents'],
                ));
            }
        }

        $assessment = $this->createAssessment->handle($permitApplication, $assessmentPreparer);
        $assessmentDecision = $this->recordAssessmentDecision->handle(
            $assessment,
            $assessmentApprover,
            AssessmentDecisionAction::Approved,
        );
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

        $provisionalPermitCompletion = null;
        if ($isWeekendPreview) {
            $provisionalPermitCompletion = $this->recordProvisionalUatPermitDecision->handle(
                $permitApplication,
                $actors['mayor_office'] ?? throw new RuntimeException('Scenario Mayor Office actor was not resolved.'),
                'go',
                'Deterministic weekend UAT go decision for stakeholder validation only.',
            );
            $provisionalPermitCompletion = $this->recordProvisionalUatPermitDecision->handle(
                $permitApplication,
                $actors['releasing'] ?? throw new RuntimeException('Scenario Releasing actor was not resolved.'),
                'release',
            );
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
        $applicationDisplayReference = $isNelsonWalkthrough
            ? ($permitApplication->application_number ?? 'Application #'.$permitApplication->id)
            : ($permitApplication->application_number
                ?? $permitApplication->tracking_reference
                ?? 'Application #'.$permitApplication->id);
        $citizenApplicationDisplayReference = $permitApplication->application_number
            ?? $permitApplication->tracking_reference
            ?? 'Application record #'.$permitApplication->id;
        $receiptNotices = $isCitizenOriginated
            ? $applicant?->notifications
                ->where('type', PermitApplicationReceived::class)
                ->filter(fn ($notification): bool => (int) data_get($notification->data, 'permit_application_id') === $permitApplication->id)
                ->values()
            : collect();
        $receiptNotice = $receiptNotices->first();
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
        $businessTaxByMajorTypeReport = $this->buildBusinessTaxByMajorTypeReport->handle([
            'date_from' => $collection->received_at->toDateString(),
            'date_to' => $collection->received_at->toDateString(),
            'receipt_from' => $receipt->receipt_number,
            'receipt_to' => $receipt->receipt_number,
        ]);
        $businessTaxByMajorTypeRow = collect($businessTaxByMajorTypeReport['rows'])
            ->firstWhere('major_type', 'Retail');
        $totalCapitalGrossSummaryReport = $this->buildTotalCapitalGrossSummaryReport->handle([
            'date_from' => $collection->received_at->toDateString(),
            'date_to' => $collection->received_at->toDateString(),
        ]);
        $totalCapitalGrossSummaryRow = collect($totalCapitalGrossSummaryReport['rows'])
            ->firstWhere('application_id', $permitApplication->id);
        $allAbstractReport = $this->describeAllAbstractReportBoundary->handle();
        $annexCDnfbpReport = $this->describeAnnexCDnfbpReportBoundary->handle();
        $bspReport = $this->describeBspReportBoundary->handle();
        $cmciLdcsReport = $this->describeCmciLdcsReportBoundary->handle();
        $pldsReport = $this->describePldsReportBoundary->handle();
        $paidEstablishmentsReport = $this->buildPaidEstablishmentsReport->handle([
            'year' => $permitApplication->application_year,
            'q' => $reportSearch,
        ]);
        $paidEstablishmentRow = collect($paidEstablishmentsReport['rows'])
            ->firstWhere('application_id', $permitApplication->id);
        $paymentSummaryReport = $this->buildPaymentSummaryReport->handle([
            'year' => $permitApplication->application_year,
            'q' => $reportSearch,
        ]);
        $paymentSummaryRow = collect($paymentSummaryReport['rows'])
            ->firstWhere('payment_schedule_id', $paymentSchedule->id);
        $verificationBoundary = $this->describeVerificationBoundary->handle($permitApplication);
        $receiptVoidBoundary = $this->describeReceiptVoidBoundary->handle($receipt);
        $timeline = $this->buildPermitApplicationTimeline->handle($permitApplication);
        $timelineKeys = collect($timeline)->pluck('key')->all();
        $walkthroughEvidence = $isNelsonWalkthrough
            ? $this->buildNelsonWalkthroughEvidence->handle()
            : null;
        $municipalityConfiguration = $isNelsonWalkthrough
            ? $this->buildMunicipalityConfiguration->handle()
            : null;

        $steps = [
            $this->step('actors-resolved', $isCitizenOriginated ? 'Resolve actual citizen and municipal operator' : 'Resolve actual application users', $isCitizenOriginated ? ['applicant_id' => $applicant?->id, 'operator_id' => $operator->id] : ['operator_id' => $operator->id], $isCitizenOriginated ? ['applicant_id' => $applicant?->id, 'operator_id' => $operator->id] : ['operator_id' => $operator->id]),
            $this->step('permit-application-created', $isCitizenOriginated ? 'Create permit application through citizen draft action' : 'Create permit application through staff intake action', ['created_as' => PermitApplicationStatus::Draft->value, 'submitted_by_id' => $isCitizenOriginated ? $applicant?->id : $operator->id], ['created_as' => PermitApplicationStatus::Draft->value, 'submitted_by_id' => $permitApplication->submitted_by_id, 'permit_application_id' => $permitApplication->id], $isCitizenOriginated ? 'applicant' : 'operator'),
            $this->step('business-activities-recorded', $isCitizenOriginated ? 'Record declared business activities through the citizen draft action' : 'Record multiple business activities through the staff intake action', ['activity_count' => 2], ['activity_count' => $permitApplication->lines->count(), 'activity_line_ids' => $permitApplication->lines->pluck('id')->all()], $isCitizenOriginated ? 'applicant' : 'operator'),
            $this->step('supporting-document-recorded', 'Record supporting evidence through permit document action', ['document_id' => $supportingDocument->id, 'storage_private' => true], ['document_id' => $supportingDocument->id, 'storage_private' => $supportingDocument->storage_disk === 'local' && Storage::disk('local')->exists($supportingDocument->path)], $isCitizenOriginated ? 'applicant' : 'operator'),
            ...($isCitizenOriginated ? [
                $this->step('citizen-application-submitted', 'Submit citizen draft through the formal submission action', ['status' => PermitApplicationStatus::Assessment->value, 'citizen_submitted' => true, 'municipality_received' => true, 'official_application_number' => null], ['status' => PermitApplicationStatus::Assessment->value, 'citizen_submitted' => data_get($permitApplication->metadata, 'citizen_submission.submitted_at') !== null, 'municipality_received' => data_get($permitApplication->metadata, 'municipal_receipt.received_at') !== null, 'official_application_number' => $permitApplication->application_number], 'applicant'),
                $this->step('citizen-receipt-notice-recorded', 'Record one factual in-app receipt notice through the submission action', ['notice_count' => 1, 'kind' => 'permit_application_received', 'tracking_reference' => $permitApplication->tracking_reference, 'external_delivery' => false], ['notice_count' => $receiptNotices->count(), 'kind' => data_get($receiptNotice?->data, 'kind'), 'tracking_reference' => data_get($receiptNotice?->data, 'tracking_reference'), 'external_delivery' => false], 'applicant'),
            ] : []),
            $this->step('assessment-computed', 'Compute assessment through assessment action', ['assessment_status' => 'computed'], ['assessment_status' => $assessment->status->value, 'assessment_id' => $assessment->id], $assessmentPreparationActor),
            $this->step('assessment-approved', 'Record Municipal Treasurer approval of the exact persisted assessment amount', ['action' => AssessmentDecisionAction::Approved->value, 'assessment_id' => $assessment->id, 'preparer_id' => $assessment->assessed_by_id, 'approver_id' => $assessmentApprover->id], ['action' => $assessmentDecision->action->value, 'assessment_id' => $assessmentDecision->assessment_id, 'preparer_id' => $assessment->assessed_by_id, 'approver_id' => $assessmentDecision->decided_by_id], $assessmentDecisionActor),
            $this->step('payment-schedule-prepared', 'Prepare payment schedule through payment schedule action', ['application_status' => PermitApplicationStatus::PendingPayment->value], ['application_status' => $permitApplication->status->value, 'payment_schedule_id' => $paymentSchedule->id]),
            $this->step('collection-recorded', 'Record full over-the-counter collection through Treasury action', ['payment_schedule_status' => PaymentScheduleStatus::Paid->value, 'collection_status' => TreasuryCollectionStatus::PendingReceipt->value], ['payment_schedule_status' => $paymentSchedule->status->value, 'collection_status' => $collectionStatusBeforeReceipt->value, 'collection_id' => $collection->id]),
            $this->step('online-payment-boundary-recorded', 'Describe online payment and reconciliation boundary without calling a gateway', ['online_payment_status' => 'blocked', 'can_pay_online' => false, 'can_reconcile_online' => false], ['online_payment_status' => $onlinePaymentBoundary['status'], 'can_pay_online' => $onlinePaymentBoundary['can_pay_online'], 'can_reconcile_online' => $onlinePaymentBoundary['can_reconcile_online']]),
            $this->step('manual-receipt-issued', 'Issue manual receipt through receipt action', ['receipt_status' => ReceiptStatus::Issued->value, 'collection_status' => TreasuryCollectionStatus::Receipted->value], ['receipt_status' => $receipt->status->value, 'collection_status' => $collection->status->value, 'receipt_id' => $receipt->id]),
            $this->step('payment-summary-report-row-projected', 'Payment summary contains the exact paid schedule and receipted collection evidence', ['payment_schedule_id' => $paymentSchedule->id, 'paid_amount_cents' => $paymentSchedule->paid_amount_cents, 'receipted_amount_cents' => $receipt->amount_cents], ['payment_schedule_id' => $paymentSummaryRow['payment_schedule_id'] ?? null, 'paid_amount_cents' => $paymentSummaryRow['paid_amount_cents'] ?? null, 'receipted_amount_cents' => $paymentSummaryRow['receipted_amount_cents'] ?? null]),
            $this->step('business-tax-by-major-type-report-row-projected', 'Business tax by major type contains the exact receipted Tax allocation under the first activity classification', ['major_type' => 'Retail', 'amount_cents' => 20_000], ['major_type' => $businessTaxByMajorTypeRow['major_type'] ?? null, 'amount_cents' => $businessTaxByMajorTypeRow['amount_cents'] ?? null]),
            $this->step('total-capital-gross-summary-report-row-projected', 'Total capital and gross summary contains declarations once and the exact lifetime receipted collection', ['application_id' => $permitApplication->id, 'capital_investment_cents' => 9_000_050, 'gross_sales_cents' => 17_000_075, 'payment_amount_cents' => $receipt->amount_cents, 'remaining_balance_cents' => 0, 'payment_status' => 'Completed'], ['application_id' => $totalCapitalGrossSummaryRow['application_id'] ?? null, 'capital_investment_cents' => $totalCapitalGrossSummaryRow['capital_investment_cents'] ?? null, 'gross_sales_cents' => $totalCapitalGrossSummaryRow['gross_sales_cents'] ?? null, 'payment_amount_cents' => $totalCapitalGrossSummaryRow['payment_amount_cents'] ?? null, 'remaining_balance_cents' => $totalCapitalGrossSummaryRow['remaining_balance_cents'] ?? null, 'payment_status' => $totalCapitalGrossSummaryRow['payment_status'] ?? null]),
            $this->step('all-abstract-completeness-boundary-recorded', 'Refuse to label the permit-only evidence set as an All Abstract until non-permit Treasury coverage and reconciliation controls exist', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'permit_collection_excluded' => true], ['status' => $allAbstractReport['status'], 'can_generate' => $allAbstractReport['can_generate'], 'can_export' => $allAbstractReport['can_export'], 'official_row_count' => $allAbstractReport['row_count'], 'permit_collection_excluded' => ! collect($allAbstractReport['rows'])->contains('collection_id', $collection->id)]),
            $this->step('annex-c-dnfbp-authority-boundary-recorded', 'Keep the artifact-ready application out of ANNEX C until permit, DNFBP classification, and semester authority exist', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'artifact_excluded' => true], ['status' => $annexCDnfbpReport['status'], 'can_generate' => $annexCDnfbpReport['can_generate'], 'can_export' => $annexCDnfbpReport['can_export'], 'official_row_count' => $annexCDnfbpReport['row_count'], 'artifact_excluded' => ! collect($annexCDnfbpReport['rows'])->contains('application_id', $permitApplication->id)]),
            $this->step('bsp-authority-boundary-recorded', 'Keep the artifact-ready application out of official BSP output until permit and regulatory classification authority exist', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'artifact_excluded' => true], ['status' => $bspReport['status'], 'can_generate' => $bspReport['can_generate'], 'can_export' => $bspReport['can_export'], 'official_row_count' => $bspReport['row_count'], 'artifact_excluded' => ! collect($bspReport['rows'])->contains('application_id', $permitApplication->id)]),
            $this->step('cmci-ldcs-authority-boundary-recorded', 'Keep the artifact-ready application out of official CMCI output until legal permit release exists', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'artifact_excluded' => true], ['status' => $cmciLdcsReport['status'], 'can_generate' => $cmciLdcsReport['can_generate'], 'can_export' => $cmciLdcsReport['can_export'], 'official_row_count' => $cmciLdcsReport['row_count'], 'artifact_excluded' => ! collect($cmciLdcsReport['rows'])->contains('application_id', $permitApplication->id)]),
            $this->step('plds-authority-boundary-recorded', 'Keep the artifact-ready application out of official PLDS output until permit authority and report mappings exist', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'artifact_excluded' => true], ['status' => $pldsReport['status'], 'can_generate' => $pldsReport['can_generate'], 'can_export' => $pldsReport['can_export'], 'official_row_count' => $pldsReport['row_count'], 'artifact_excluded' => ! collect($pldsReport['rows'])->contains('application_id', $permitApplication->id)]),
            $this->step('receipt-void-blocked', 'Attempt receipt void through receipt policy boundary action', ['void_blocked' => true, 'receipt_status' => ReceiptStatus::Issued->value, 'collection_status' => TreasuryCollectionStatus::Receipted->value], ['void_blocked' => $receiptVoidBlocked, 'receipt_status' => $receipt->status->value, 'collection_status' => $collection->status->value, 'receipt_id' => $receipt->id]),
            $this->step('clearance-checklist-completed', 'Complete clearance checklist through clearance actions', ['completed_clearances' => 3, 'all_completed' => true], ['completed_clearances' => $completedClearances, 'all_completed' => $permitApplication->clearances->every(fn ($clearance): bool => $clearance->status === PermitClearanceStatus::Completed)]),
            $this->step('release-ready-for-authority-review', 'Describe release readiness without issuing permit', ['ready_for_authority_review' => true, 'can_release' => false], ['ready_for_authority_review' => $releaseReadiness['ready_for_authority_review'], 'can_release' => $releaseReadiness['can_release']]),
            $this->step('permit-artifact-available-for-authority-review', 'Describe generated permit artifact without issuing permit', ['status' => 'generated_artifact_available', 'ready_for_authority_review' => true, 'can_issue' => false, 'can_release' => false], ['status' => $permitArtifact['status'], 'ready_for_authority_review' => $permitArtifact['ready_for_authority_review'], 'can_issue' => $permitArtifact['can_issue'], 'can_release' => $permitArtifact['can_release']]),
            $this->step('permit-release-blocked', 'Attempt permit release through release boundary action', ['release_blocked' => true, 'application_status' => PermitApplicationStatus::PendingPayment->value], ['release_blocked' => $releaseBlocked, 'application_status' => $permitApplication->status->value]),
            $this->step('application-timeline-projected', 'Project authoritative lifecycle records into chronological review evidence', ['event_count' => $isWeekendPreview ? 20 : ($isCitizenOriginated ? 16 : 13), 'release_boundary_visible' => true], ['event_count' => count($timelineKeys), 'release_boundary_visible' => in_array("release-blocked:{$permitApplication->id}", $timelineKeys, true)]),
        ];

        if ($isWeekendPreview) {
            $steps[] = $this->step(
                'concerned-office-charges-consolidated',
                'Consolidate five staff-entered preview office charges into the real assessment snapshot',
                ['count' => 5, 'classification' => 'provisional_uat'],
                ['count' => $officeChargeContributions->count(), 'classification' => $officeChargeContributions->first()?->semantic_classification],
            );
            $steps[] = $this->step(
                'provisional-uat-permit-released',
                'Complete the synthetic numbering, signature, and release hypothesis without changing production permit authority',
                ['status' => 'released_in_preview', 'classification' => 'provisional_uat'],
                ['status' => $provisionalPermitCompletion?->status, 'classification' => $provisionalPermitCompletion?->semantic_classification],
            );
        }

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest['resources'] = [
            'record_type' => 'receipt',
            'record_id' => $receipt->id,
            'public_reference' => $receipt->receipt_number,
            'permit_application_id' => $permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'tracking_reference' => $permitApplication->tracking_reference,
            'application_display_reference' => $applicationDisplayReference,
            'citizen_application_display_reference' => $citizenApplicationDisplayReference,
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
            'office_charge_contribution_count' => $officeChargeContributions->count(),
            'office_charge_total_amount_cents' => $officeChargeContributions->sum('amount_cents'),
            'assessment_prepared_by_id' => $assessment->assessed_by_id,
            'assessment_decision_id' => $assessmentDecision->id,
            'assessment_decision_action' => $assessmentDecision->action->value,
            'assessment_approved_by_id' => $assessmentDecision->decided_by_id,
            'assessment_approved_at' => $assessmentDecision->decided_at->toIso8601String(),
            'assessment_snapshot_hash' => $assessmentDecision->assessment_snapshot_hash,
            'assessment_approver_distinct_from_preparer' => $assessmentDecision->decided_by_id !== $assessment->assessed_by_id,
            'assessment_url' => route('staff.permit-applications.assessments.show', $assessment, false),
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
            'payment_summary_report_url' => route('staff.reports.payment-summary.index', [
                'year' => $permitApplication->application_year,
                'q' => $reportSearch,
            ], false),
            'payment_summary_report_download_url' => route('staff.reports.payment-summary.download', [
                'year' => $permitApplication->application_year,
                'q' => $reportSearch,
            ], false),
            'payment_summary_business_name' => $permitApplication->business->name,
            'business_tax_by_major_type_report_url' => route('staff.reports.business-tax-by-major-type.index', [
                'date_from' => $collection->received_at->toDateString(),
                'date_to' => $collection->received_at->toDateString(),
                'receipt_from' => $receipt->receipt_number,
                'receipt_to' => $receipt->receipt_number,
            ], false),
            'business_tax_by_major_type_report_download_url' => route('staff.reports.business-tax-by-major-type.download', [
                'date_from' => $collection->received_at->toDateString(),
                'date_to' => $collection->received_at->toDateString(),
                'receipt_from' => $receipt->receipt_number,
                'receipt_to' => $receipt->receipt_number,
            ], false),
            'business_tax_major_type' => 'Retail',
            'business_tax_major_amount_cents' => 20_000,
            'total_capital_gross_summary_report_url' => route('staff.reports.total-capital-gross-summary.index', [
                'date_from' => $collection->received_at->toDateString(),
                'date_to' => $collection->received_at->toDateString(),
            ], false),
            'total_capital_gross_summary_report_download_url' => route('staff.reports.total-capital-gross-summary.download', [
                'date_from' => $collection->received_at->toDateString(),
                'date_to' => $collection->received_at->toDateString(),
            ], false),
            'total_capital_gross_capital_cents' => 9_000_050,
            'total_capital_gross_gross_cents' => 17_000_075,
            'total_capital_gross_payment_cents' => $receipt->amount_cents,
            'total_capital_gross_balance_cents' => 0,
            'total_capital_gross_payment_status' => 'Completed',
            'all_abstract_report_url' => route('staff.reports.all-abstract.index', absolute: false),
            'all_abstract_status' => $allAbstractReport['status'],
            'all_abstract_can_generate' => $allAbstractReport['can_generate'],
            'all_abstract_can_export' => $allAbstractReport['can_export'],
            'all_abstract_official_row_count' => $allAbstractReport['row_count'],
            'all_abstract_coverage_count' => count($allAbstractReport['coverage']),
            'all_abstract_control_count' => count($allAbstractReport['reconciliation_controls']),
            'all_abstract_permit_collection_excluded' => ! collect($allAbstractReport['rows'])->contains('collection_id', $collection->id),
            'annex_c_dnfbp_report_url' => route('staff.reports.annex-c-dnfbp.index', absolute: false),
            'annex_c_dnfbp_status' => $annexCDnfbpReport['status'],
            'annex_c_dnfbp_can_generate' => $annexCDnfbpReport['can_generate'],
            'annex_c_dnfbp_can_export' => $annexCDnfbpReport['can_export'],
            'annex_c_dnfbp_official_row_count' => $annexCDnfbpReport['row_count'],
            'annex_c_dnfbp_contract_column_count' => count($annexCDnfbpReport['columns']),
            'annex_c_dnfbp_artifact_excluded' => ! collect($annexCDnfbpReport['rows'])->contains('application_id', $permitApplication->id),
            'bsp_report_url' => route('staff.reports.bsp.index', absolute: false),
            'bsp_status' => $bspReport['status'],
            'bsp_can_generate' => $bspReport['can_generate'],
            'bsp_can_export' => $bspReport['can_export'],
            'bsp_official_row_count' => $bspReport['row_count'],
            'bsp_contract_column_count' => count($bspReport['columns']),
            'bsp_artifact_excluded' => ! collect($bspReport['rows'])->contains('application_id', $permitApplication->id),
            'cmci_ldcs_report_url' => route('staff.reports.cmci-ldcs.index', absolute: false),
            'cmci_ldcs_status' => $cmciLdcsReport['status'],
            'cmci_ldcs_can_generate' => $cmciLdcsReport['can_generate'],
            'cmci_ldcs_can_export' => $cmciLdcsReport['can_export'],
            'cmci_ldcs_official_row_count' => $cmciLdcsReport['row_count'],
            'cmci_ldcs_contract_column_count' => count($cmciLdcsReport['columns']),
            'cmci_ldcs_artifact_excluded' => ! collect($cmciLdcsReport['rows'])->contains('application_id', $permitApplication->id),
            'plds_report_url' => route('staff.reports.plds.index', absolute: false),
            'plds_status' => $pldsReport['status'],
            'plds_can_generate' => $pldsReport['can_generate'],
            'plds_can_export' => $pldsReport['can_export'],
            'plds_official_row_count' => $pldsReport['row_count'],
            'plds_contract_column_count' => count($pldsReport['columns']),
            'plds_artifact_excluded' => ! collect($pldsReport['rows'])->contains('application_id', $permitApplication->id),
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
            'provisional_uat_permit_status' => $provisionalPermitCompletion?->status,
            'provisional_uat_permit_number' => $provisionalPermitCompletion?->permit_number,
            'provisional_uat_signature_reference' => $provisionalPermitCompletion?->synthetic_signature_reference,
            'provisional_uat_released_at' => $provisionalPermitCompletion?->released_at?->toIso8601String(),
            ...($isCitizenOriginated ? [
                'public_reference' => $citizenApplicationDisplayReference,
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

        if ($isNelsonWalkthrough) {
            $manifest['walkthrough'] = [
                'evidence' => $walkthroughEvidence,
                'authority' => [
                    'configured_official_count' => data_get($municipalityConfiguration, 'authority.configured_official_count'),
                    'authorized_signatory_count' => data_get($municipalityConfiguration, 'authority.authorized_signatory_count'),
                    'permit_issuance_authorized' => data_get($municipalityConfiguration, 'authority.permit_issuance_authorized'),
                    'permit_release_authorized' => data_get($municipalityConfiguration, 'authority.permit_release_authorized'),
                    'legal_effect_authorized' => data_get($municipalityConfiguration, 'authority.legal_effect_authorized'),
                ],
                'artifacts' => [
                    'presenter_script' => 'walkthrough/presenter-script.md',
                    'summary_markdown' => 'walkthrough/what-nelson-is-seeing.md',
                    'summary_html' => 'walkthrough/what-nelson-is-seeing.html',
                    'migration_evidence' => 'walkthrough/migration-evidence.html',
                ],
            ];
        }

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
            'assessment_decision_id' => $assessmentDecision->id,
            'assessment_decision_action' => $assessmentDecision->action->value,
            'assessment_snapshot_hash' => $assessmentDecision->assessment_snapshot_hash,
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
            'payment_summary_report' => [
                'year' => $paymentSummaryReport['filters']['year'],
                'row_count' => $paymentSummaryReport['summary']['row_count'],
                'paid_amount_cents' => $paymentSummaryReport['summary']['paid_amount_cents'],
                'receipted_amount_cents' => $paymentSummaryReport['summary']['receipted_amount_cents'],
                'payment_schedule_id' => $paymentSummaryRow['payment_schedule_id'] ?? null,
                'application_number' => $paymentSummaryRow['application_number'] ?? null,
            ],
            'business_tax_by_major_type_report' => [
                'date_from' => $businessTaxByMajorTypeReport['filters']['date_from'],
                'date_to' => $businessTaxByMajorTypeReport['filters']['date_to'],
                'receipt_from' => $businessTaxByMajorTypeReport['filters']['receipt_from'],
                'receipt_to' => $businessTaxByMajorTypeReport['filters']['receipt_to'],
                'total_amount_cents' => $businessTaxByMajorTypeReport['summary']['total_amount_cents'],
                'major_type' => $businessTaxByMajorTypeRow['major_type'] ?? null,
                'amount_cents' => $businessTaxByMajorTypeRow['amount_cents'] ?? null,
            ],
            'total_capital_gross_summary_report' => [
                'date_from' => $totalCapitalGrossSummaryReport['filters']['date_from'],
                'date_to' => $totalCapitalGrossSummaryReport['filters']['date_to'],
                'row_count' => $totalCapitalGrossSummaryReport['summary']['row_count'],
                'application_id' => $totalCapitalGrossSummaryRow['application_id'] ?? null,
                'capital_investment_cents' => $totalCapitalGrossSummaryRow['capital_investment_cents'] ?? null,
                'gross_sales_cents' => $totalCapitalGrossSummaryRow['gross_sales_cents'] ?? null,
                'payment_amount_cents' => $totalCapitalGrossSummaryRow['payment_amount_cents'] ?? null,
                'remaining_balance_cents' => $totalCapitalGrossSummaryRow['remaining_balance_cents'] ?? null,
                'payment_status' => $totalCapitalGrossSummaryRow['payment_status'] ?? null,
            ],
            'all_abstract_report' => [
                'status' => $allAbstractReport['status'],
                'can_generate' => $allAbstractReport['can_generate'],
                'can_export' => $allAbstractReport['can_export'],
                'official_row_count' => $allAbstractReport['row_count'],
                'coverage_count' => count($allAbstractReport['coverage']),
                'control_count' => count($allAbstractReport['reconciliation_controls']),
                'permit_collection_excluded' => ! collect($allAbstractReport['rows'])->contains('collection_id', $collection->id),
                'blocked_by' => $allAbstractReport['blocked_by'],
            ],
            'annex_c_dnfbp_report' => [
                'status' => $annexCDnfbpReport['status'],
                'can_generate' => $annexCDnfbpReport['can_generate'],
                'can_export' => $annexCDnfbpReport['can_export'],
                'official_row_count' => $annexCDnfbpReport['row_count'],
                'contract_column_count' => count($annexCDnfbpReport['columns']),
                'artifact_excluded' => ! collect($annexCDnfbpReport['rows'])->contains('application_id', $permitApplication->id),
                'blocked_by' => $annexCDnfbpReport['blocked_by'],
            ],
            'bsp_report' => [
                'status' => $bspReport['status'],
                'can_generate' => $bspReport['can_generate'],
                'can_export' => $bspReport['can_export'],
                'official_row_count' => $bspReport['row_count'],
                'contract_column_count' => count($bspReport['columns']),
                'artifact_excluded' => ! collect($bspReport['rows'])->contains('application_id', $permitApplication->id),
                'blocked_by' => $bspReport['blocked_by'],
            ],
            'cmci_ldcs_report' => [
                'status' => $cmciLdcsReport['status'],
                'can_generate' => $cmciLdcsReport['can_generate'],
                'can_export' => $cmciLdcsReport['can_export'],
                'official_row_count' => $cmciLdcsReport['row_count'],
                'contract_column_count' => count($cmciLdcsReport['columns']),
                'artifact_excluded' => ! collect($cmciLdcsReport['rows'])->contains('application_id', $permitApplication->id),
                'blocked_by' => $cmciLdcsReport['blocked_by'],
            ],
            'plds_report' => [
                'status' => $pldsReport['status'],
                'can_generate' => $pldsReport['can_generate'],
                'can_export' => $pldsReport['can_export'],
                'official_row_count' => $pldsReport['row_count'],
                'contract_column_count' => count($pldsReport['columns']),
                'artifact_excluded' => ! collect($pldsReport['rows'])->contains('application_id', $permitApplication->id),
                'blocked_by' => $pldsReport['blocked_by'],
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
            'notifications' => $isCitizenOriginated,
            'in_app_notification_count' => $receiptNotices->count(),
            'external_notifications' => false,
        ]);
        $artifactStore->putJson('storyboard/storyboard.json', $this->storyboard($scenario, $runId, $permitApplication, $paymentSchedule, $collection, $receipt));
        $artifactStore->put('storyboard/storyboard.html', $this->storyboardHtml($scenario, $runId, $permitApplication, $paymentSchedule, $collection, $receipt));

        if ($isNelsonWalkthrough && is_array($walkthroughEvidence)) {
            $artifactStore->putJson('walkthrough/evidence.json', $walkthroughEvidence);
            $artifactStore->put('walkthrough/presenter-script.md', $this->nelsonWalkthroughRenderer->presenterScript($manifest));
            $artifactStore->put('walkthrough/what-nelson-is-seeing.md', $this->nelsonWalkthroughRenderer->summaryMarkdown($walkthroughEvidence));
            $artifactStore->put('walkthrough/what-nelson-is-seeing.html', $this->nelsonWalkthroughRenderer->summaryHtml($walkthroughEvidence));
            $artifactStore->put('walkthrough/migration-evidence.html', $this->nelsonWalkthroughRenderer->migrationEvidenceHtml($walkthroughEvidence));
        }

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
        $assessment = Assessment::query()->with('decision')->findOrFail($manifest['resources']['assessment_id']);
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
        $businessTaxByMajorTypeReport = $this->buildBusinessTaxByMajorTypeReport->handle([
            'date_from' => $collection->received_at->toDateString(),
            'date_to' => $collection->received_at->toDateString(),
            'receipt_from' => $receipt->receipt_number,
            'receipt_to' => $receipt->receipt_number,
        ]);
        $businessTaxByMajorTypeRow = collect($businessTaxByMajorTypeReport['rows'])
            ->firstWhere('major_type', $manifest['resources']['business_tax_major_type']);
        $totalCapitalGrossSummaryReport = $this->buildTotalCapitalGrossSummaryReport->handle([
            'date_from' => $collection->received_at->toDateString(),
            'date_to' => $collection->received_at->toDateString(),
        ]);
        $totalCapitalGrossSummaryRow = collect($totalCapitalGrossSummaryReport['rows'])
            ->firstWhere('application_id', $permitApplication->id);
        $allAbstractReport = $this->describeAllAbstractReportBoundary->handle();
        $annexCDnfbpReport = $this->describeAnnexCDnfbpReportBoundary->handle();
        $bspReport = $this->describeBspReportBoundary->handle();
        $cmciLdcsReport = $this->describeCmciLdcsReportBoundary->handle();
        $pldsReport = $this->describePldsReportBoundary->handle();
        $paidEstablishmentsReport = $this->buildPaidEstablishmentsReport->handle([
            'year' => $permitApplication->application_year,
            'q' => $permitApplication->application_number ?? $permitApplication->business->name,
        ]);
        $paidEstablishmentRow = collect($paidEstablishmentsReport['rows'])
            ->firstWhere('application_id', $permitApplication->id);
        $paymentSummaryReport = $this->buildPaymentSummaryReport->handle([
            'year' => $permitApplication->application_year,
            'q' => $permitApplication->application_number ?? $permitApplication->business->name,
        ]);
        $paymentSummaryRow = collect($paymentSummaryReport['rows'])
            ->firstWhere('payment_schedule_id', $paymentSchedule->id);
        $releaseReadiness = $this->describeReleaseReadiness->handle($permitApplication);
        $permitArtifact = $this->describePermitArtifact->handle($permitApplication);
        $verificationBoundary = $this->describeVerificationBoundary->handle($permitApplication);
        $receiptVoidBoundary = $this->describeReceiptVoidBoundary->handle($receipt);
        $timeline = $this->buildPermitApplicationTimeline->handle($permitApplication);
        $timelineKeys = collect($timeline)->pluck('key')->all();
        $isCitizenOriginated = $this->isCitizenOriginated((string) ($manifest['scenario']['key'] ?? ''));
        $isNelsonWalkthrough = $this->isStakeholderWalkthrough((string) ($manifest['scenario']['key'] ?? ''));
        $applicant = $isCitizenOriginated
            ? User::query()->findOrFail((int) data_get($manifest, 'actors.applicant.id'))
            : null;
        $receiptNotices = $isCitizenOriginated
            ? $applicant?->notifications
                ->where('type', PermitApplicationReceived::class)
                ->filter(fn ($notification): bool => (int) data_get($notification->data, 'permit_application_id') === $permitApplication->id)
                ->values()
            : collect();
        $receiptNotice = $receiptNotices->first();
        $browserReport = $artifactStore->readJson('browser/report.json') ?? [
            'result' => [
                'passed' => false,
            ],
            'checks' => [],
        ];

        $checks = [
            ...($isCitizenOriginated ? [
                $this->step('audit-citizen-origin', 'Application retains the citizen registry and submission origin throughout municipal processing', ['submitted_by_id' => data_get($manifest, 'actors.applicant.id'), 'application_number' => null, 'tracking_reference' => $manifest['resources']['tracking_reference'], 'citizen_submitted' => true, 'municipality_received' => true], ['submitted_by_id' => $permitApplication->submitted_by_id, 'application_number' => $permitApplication->application_number, 'tracking_reference' => $permitApplication->tracking_reference, 'citizen_submitted' => data_get($permitApplication->metadata, 'citizen_submission.submitted_at') !== null, 'municipality_received' => data_get($permitApplication->metadata, 'municipal_receipt.received_at') !== null]),
                $this->step('audit-citizen-receipt-notice', 'Canonical submission produced exactly one factual in-app receipt notice and no external delivery', ['notice_count' => 1, 'kind' => 'permit_application_received', 'tracking_reference' => $permitApplication->tracking_reference, 'external_delivery' => false], ['notice_count' => $receiptNotices->count(), 'kind' => data_get($receiptNotice?->data, 'kind'), 'tracking_reference' => data_get($receiptNotice?->data, 'tracking_reference'), 'external_delivery' => false]),
                $this->step('audit-browser-citizen-milestone', 'Citizen browser evidence agrees with the exact final canonical record', ['application_status' => $permitApplication->status->value, 'payment_schedule_id' => $paymentSchedule->id, 'receipt_id' => $receipt->id, 'ready_for_authority_review' => true, 'can_release' => false], ['application_status' => data_get($browserReport, 'citizen_processing.application_status'), 'payment_schedule_id' => data_get($browserReport, 'citizen_processing.payment_schedule_id'), 'receipt_id' => data_get($browserReport, 'citizen_authority_review.receipt_id'), 'ready_for_authority_review' => data_get($browserReport, 'citizen_authority_review.ready_for_authority_review'), 'can_release' => data_get($browserReport, 'citizen_authority_review.can_release')]),
            ] : []),
            $this->step('audit-assessment-treasurer-decision', 'Payment is bound to the exact immutable Treasurer-approved assessment snapshot', ['decision_id' => $manifest['resources']['assessment_decision_id'], 'action' => AssessmentDecisionAction::Approved->value, 'assessment_id' => $assessment->id, 'total_amount_cents' => $assessment->total_amount_cents, 'snapshot_hash' => $manifest['resources']['assessment_snapshot_hash']], ['decision_id' => $assessment->decision?->id, 'action' => $assessment->decision?->action->value, 'assessment_id' => $assessment->decision?->assessment_id, 'total_amount_cents' => $assessment->decision?->total_amount_cents, 'snapshot_hash' => $assessment->decision?->assessment_snapshot_hash]),
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
            $this->step('audit-payment-summary-report-row', 'Payment summary contains the exact paid schedule and receipt evidence', ['payment_schedule_id' => $paymentSchedule->id, 'paid_amount_cents' => $paymentSchedule->paid_amount_cents, 'receipted_amount_cents' => $receipt->amount_cents], ['payment_schedule_id' => $paymentSummaryRow['payment_schedule_id'] ?? null, 'paid_amount_cents' => $paymentSummaryRow['paid_amount_cents'] ?? null, 'receipted_amount_cents' => $paymentSummaryRow['receipted_amount_cents'] ?? null]),
            $this->step('audit-browser-payment-summary-report-row', 'Browser evidence observed the same payment summary row on desktop and mobile', ['payment_schedule_id' => $paymentSchedule->id, 'paid_amount_cents' => $paymentSchedule->paid_amount_cents, 'csv_export_visible' => true, 'mobile_visible' => true, 'mobile_horizontal_overflow' => false], ['payment_schedule_id' => data_get($browserReport, 'reports.payment_summary.payment_schedule_id'), 'paid_amount_cents' => data_get($browserReport, 'reports.payment_summary.paid_amount_cents'), 'csv_export_visible' => data_get($browserReport, 'reports.payment_summary.csv_export_visible'), 'mobile_visible' => data_get($browserReport, 'reports.payment_summary.mobile_visible'), 'mobile_horizontal_overflow' => data_get($browserReport, 'reports.payment_summary.mobile_horizontal_overflow')]),
            $this->step('audit-business-tax-by-major-type-report-row', 'Business tax by major type contains only the canonical receipted Tax allocation', ['major_type' => $manifest['resources']['business_tax_major_type'], 'amount_cents' => $manifest['resources']['business_tax_major_amount_cents']], ['major_type' => $businessTaxByMajorTypeRow['major_type'] ?? null, 'amount_cents' => $businessTaxByMajorTypeRow['amount_cents'] ?? null]),
            $this->step('audit-browser-business-tax-by-major-type-report-row', 'Browser evidence observed the same major type amount on desktop and mobile', ['major_type' => $manifest['resources']['business_tax_major_type'], 'amount_cents' => $manifest['resources']['business_tax_major_amount_cents'], 'csv_export_visible' => true, 'mobile_visible' => true, 'mobile_horizontal_overflow' => false], ['major_type' => data_get($browserReport, 'reports.business_tax_by_major_type.major_type'), 'amount_cents' => data_get($browserReport, 'reports.business_tax_by_major_type.amount_cents'), 'csv_export_visible' => data_get($browserReport, 'reports.business_tax_by_major_type.csv_export_visible'), 'mobile_visible' => data_get($browserReport, 'reports.business_tax_by_major_type.mobile_visible'), 'mobile_horizontal_overflow' => data_get($browserReport, 'reports.business_tax_by_major_type.mobile_horizontal_overflow')]),
            $this->step('audit-total-capital-gross-summary-report-row', 'Total capital and gross summary agrees with canonical declaration and collection records', ['application_id' => $permitApplication->id, 'capital_investment_cents' => $manifest['resources']['total_capital_gross_capital_cents'], 'gross_sales_cents' => $manifest['resources']['total_capital_gross_gross_cents'], 'payment_amount_cents' => $manifest['resources']['total_capital_gross_payment_cents'], 'remaining_balance_cents' => $manifest['resources']['total_capital_gross_balance_cents'], 'payment_status' => $manifest['resources']['total_capital_gross_payment_status']], ['application_id' => $totalCapitalGrossSummaryRow['application_id'] ?? null, 'capital_investment_cents' => $totalCapitalGrossSummaryRow['capital_investment_cents'] ?? null, 'gross_sales_cents' => $totalCapitalGrossSummaryRow['gross_sales_cents'] ?? null, 'payment_amount_cents' => $totalCapitalGrossSummaryRow['payment_amount_cents'] ?? null, 'remaining_balance_cents' => $totalCapitalGrossSummaryRow['remaining_balance_cents'] ?? null, 'payment_status' => $totalCapitalGrossSummaryRow['payment_status'] ?? null]),
            $this->step('audit-browser-total-capital-gross-summary-report-row', 'Browser evidence observed the same application and lifetime payment on desktop and mobile', ['application_id' => $permitApplication->id, 'payment_amount_cents' => $manifest['resources']['total_capital_gross_payment_cents'], 'csv_export_visible' => true, 'mobile_visible' => true, 'mobile_horizontal_overflow' => false], ['application_id' => data_get($browserReport, 'reports.total_capital_gross_summary.application_id'), 'payment_amount_cents' => data_get($browserReport, 'reports.total_capital_gross_summary.payment_amount_cents'), 'csv_export_visible' => data_get($browserReport, 'reports.total_capital_gross_summary.csv_export_visible'), 'mobile_visible' => data_get($browserReport, 'reports.total_capital_gross_summary.mobile_visible'), 'mobile_horizontal_overflow' => data_get($browserReport, 'reports.total_capital_gross_summary.mobile_horizontal_overflow')]),
            $this->step('audit-all-abstract-completeness-boundary', 'All Abstract contract excludes the real permit collection rather than overstating complete Treasury coverage', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'coverage_count' => 5, 'control_count' => 7, 'permit_collection_excluded' => true], ['status' => $allAbstractReport['status'], 'can_generate' => $allAbstractReport['can_generate'], 'can_export' => $allAbstractReport['can_export'], 'official_row_count' => $allAbstractReport['row_count'], 'coverage_count' => count($allAbstractReport['coverage']), 'control_count' => count($allAbstractReport['reconciliation_controls']), 'permit_collection_excluded' => ! collect($allAbstractReport['rows'])->contains('collection_id', $collection->id)]),
            $this->step('audit-browser-all-abstract-completeness-boundary', 'Browser evidence shows the same All Abstract refusal and coverage gaps on desktop and mobile', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'permit_collection_excluded' => true, 'mobile_visible' => true, 'mobile_horizontal_overflow' => false], ['status' => data_get($browserReport, 'reports.all_abstract.status'), 'can_generate' => data_get($browserReport, 'reports.all_abstract.can_generate'), 'can_export' => data_get($browserReport, 'reports.all_abstract.can_export'), 'official_row_count' => data_get($browserReport, 'reports.all_abstract.official_row_count'), 'permit_collection_excluded' => data_get($browserReport, 'reports.all_abstract.permit_collection_excluded'), 'mobile_visible' => data_get($browserReport, 'reports.all_abstract.mobile_visible'), 'mobile_horizontal_overflow' => data_get($browserReport, 'reports.all_abstract.mobile_horizontal_overflow')]),
            $this->step('audit-annex-c-dnfbp-authority-boundary', 'ANNEX C contract refuses to classify the artifact-ready application as a legally permitted DNFBP', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'contract_column_count' => 9, 'artifact_excluded' => true], ['status' => $annexCDnfbpReport['status'], 'can_generate' => $annexCDnfbpReport['can_generate'], 'can_export' => $annexCDnfbpReport['can_export'], 'official_row_count' => $annexCDnfbpReport['row_count'], 'contract_column_count' => count($annexCDnfbpReport['columns']), 'artifact_excluded' => ! collect($annexCDnfbpReport['rows'])->contains('application_id', $permitApplication->id)]),
            $this->step('audit-browser-annex-c-dnfbp-authority-boundary', 'Browser evidence shows the same ANNEX C refusal on desktop and mobile', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'artifact_excluded' => true, 'mobile_visible' => true, 'mobile_horizontal_overflow' => false], ['status' => data_get($browserReport, 'reports.annex_c_dnfbp.status'), 'can_generate' => data_get($browserReport, 'reports.annex_c_dnfbp.can_generate'), 'can_export' => data_get($browserReport, 'reports.annex_c_dnfbp.can_export'), 'official_row_count' => data_get($browserReport, 'reports.annex_c_dnfbp.official_row_count'), 'artifact_excluded' => data_get($browserReport, 'reports.annex_c_dnfbp.artifact_excluded'), 'mobile_visible' => data_get($browserReport, 'reports.annex_c_dnfbp.mobile_visible'), 'mobile_horizontal_overflow' => data_get($browserReport, 'reports.annex_c_dnfbp.mobile_horizontal_overflow')]),
            $this->step('audit-bsp-authority-boundary', 'BSP contract refuses to classify the artifact-ready application as a legally permitted regulated entity', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'contract_column_count' => 16, 'artifact_excluded' => true], ['status' => $bspReport['status'], 'can_generate' => $bspReport['can_generate'], 'can_export' => $bspReport['can_export'], 'official_row_count' => $bspReport['row_count'], 'contract_column_count' => count($bspReport['columns']), 'artifact_excluded' => ! collect($bspReport['rows'])->contains('application_id', $permitApplication->id)]),
            $this->step('audit-browser-bsp-authority-boundary', 'Browser evidence shows the same BSP refusal on desktop and mobile', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'artifact_excluded' => true, 'mobile_visible' => true, 'mobile_horizontal_overflow' => false], ['status' => data_get($browserReport, 'reports.bsp.status'), 'can_generate' => data_get($browserReport, 'reports.bsp.can_generate'), 'can_export' => data_get($browserReport, 'reports.bsp.can_export'), 'official_row_count' => data_get($browserReport, 'reports.bsp.official_row_count'), 'artifact_excluded' => data_get($browserReport, 'reports.bsp.artifact_excluded'), 'mobile_visible' => data_get($browserReport, 'reports.bsp.mobile_visible'), 'mobile_horizontal_overflow' => data_get($browserReport, 'reports.bsp.mobile_horizontal_overflow')]),
            $this->step('audit-cmci-ldcs-authority-boundary', 'CMCI contract refuses to classify the artifact-ready application as an official released permit', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'contract_column_count' => 18, 'artifact_excluded' => true], ['status' => $cmciLdcsReport['status'], 'can_generate' => $cmciLdcsReport['can_generate'], 'can_export' => $cmciLdcsReport['can_export'], 'official_row_count' => $cmciLdcsReport['row_count'], 'contract_column_count' => count($cmciLdcsReport['columns']), 'artifact_excluded' => ! collect($cmciLdcsReport['rows'])->contains('application_id', $permitApplication->id)]),
            $this->step('audit-browser-cmci-ldcs-authority-boundary', 'Browser evidence shows the same CMCI refusal on desktop and mobile', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'artifact_excluded' => true, 'mobile_visible' => true, 'mobile_horizontal_overflow' => false], ['status' => data_get($browserReport, 'reports.cmci_ldcs.status'), 'can_generate' => data_get($browserReport, 'reports.cmci_ldcs.can_generate'), 'can_export' => data_get($browserReport, 'reports.cmci_ldcs.can_export'), 'official_row_count' => data_get($browserReport, 'reports.cmci_ldcs.official_row_count'), 'artifact_excluded' => data_get($browserReport, 'reports.cmci_ldcs.artifact_excluded'), 'mobile_visible' => data_get($browserReport, 'reports.cmci_ldcs.mobile_visible'), 'mobile_horizontal_overflow' => data_get($browserReport, 'reports.cmci_ldcs.mobile_horizontal_overflow')]),
            $this->step('audit-plds-authority-boundary', 'PLDS contract refuses to classify the artifact-ready application as an official released permit', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'contract_column_count' => 23, 'artifact_excluded' => true], ['status' => $pldsReport['status'], 'can_generate' => $pldsReport['can_generate'], 'can_export' => $pldsReport['can_export'], 'official_row_count' => $pldsReport['row_count'], 'contract_column_count' => count($pldsReport['columns']), 'artifact_excluded' => ! collect($pldsReport['rows'])->contains('application_id', $permitApplication->id)]),
            $this->step('audit-browser-plds-authority-boundary', 'Browser evidence shows the same PLDS refusal on desktop and mobile', ['status' => 'blocked', 'can_generate' => false, 'can_export' => false, 'official_row_count' => 0, 'artifact_excluded' => true, 'mobile_visible' => true, 'mobile_horizontal_overflow' => false], ['status' => data_get($browserReport, 'reports.plds.status'), 'can_generate' => data_get($browserReport, 'reports.plds.can_generate'), 'can_export' => data_get($browserReport, 'reports.plds.can_export'), 'official_row_count' => data_get($browserReport, 'reports.plds.official_row_count'), 'artifact_excluded' => data_get($browserReport, 'reports.plds.artifact_excluded'), 'mobile_visible' => data_get($browserReport, 'reports.plds.mobile_visible'), 'mobile_horizontal_overflow' => data_get($browserReport, 'reports.plds.mobile_horizontal_overflow')]),
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

        if ($isNelsonWalkthrough) {
            $checks[] = $this->step('audit-nelson-authority-configuration', 'Browser presentation preserves the configured-official and legal-authority distinction', [
                'configured_official_count' => data_get($manifest, 'walkthrough.authority.configured_official_count'),
                'authorized_signatory_count' => 0,
                'permit_issuance_authorized' => false,
                'permit_release_authorized' => false,
                'legal_effect_authorized' => false,
            ], data_get($browserReport, 'nelson_walkthrough.authority', []));
            $checks[] = $this->step('audit-nelson-migration-evidence', 'Browser presentation agrees with the accepted migration evidence summary', [
                'application_count' => 407,
                'schedule_count' => 696,
                'fee_line_count' => 3_007,
                'completed_payment_count' => 660,
                'unpaid_schedule_count' => 36,
                'scheduled_amount_cents' => 412_770_810,
                'paid_amount_cents' => 397_445_008,
                'operational_financial_mutation_count' => 0,
                'rehearsal_passed' => true,
                'identity_reconciliation_count' => 736,
            ], data_get($browserReport, 'nelson_walkthrough.migration', []));
        }

        if (($manifest['scenario']['key'] ?? null) === 'stakeholder_preview_cycle_1') {
            $checks[] = $this->step('audit-browser-assessment-treasurer-decision', 'Treasury browser evidence distinguishes Assessment Officer preparation from Municipal Treasurer approval', [
                'action' => AssessmentDecisionAction::Approved->value,
                'snapshot_hash' => $assessment->decision?->assessment_snapshot_hash,
                'municipal_treasurer_label_visible' => true,
                'prepared_by_visible' => true,
                'approve_action_visible' => false,
            ], data_get($browserReport, 'stakeholder_preview.assessment_approval', []));
        }

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
                'payment_summary_report' => [
                    'year' => $paymentSummaryReport['filters']['year'],
                    'row_count' => $paymentSummaryReport['summary']['row_count'],
                    'paid_amount_cents' => $paymentSummaryReport['summary']['paid_amount_cents'],
                    'receipted_amount_cents' => $paymentSummaryReport['summary']['receipted_amount_cents'],
                    'payment_schedule_id' => $paymentSummaryRow['payment_schedule_id'] ?? null,
                    'application_number' => $paymentSummaryRow['application_number'] ?? null,
                ],
                'business_tax_by_major_type_report' => [
                    'date_from' => $businessTaxByMajorTypeReport['filters']['date_from'],
                    'date_to' => $businessTaxByMajorTypeReport['filters']['date_to'],
                    'receipt_from' => $businessTaxByMajorTypeReport['filters']['receipt_from'],
                    'receipt_to' => $businessTaxByMajorTypeReport['filters']['receipt_to'],
                    'total_amount_cents' => $businessTaxByMajorTypeReport['summary']['total_amount_cents'],
                    'major_type' => $businessTaxByMajorTypeRow['major_type'] ?? null,
                    'amount_cents' => $businessTaxByMajorTypeRow['amount_cents'] ?? null,
                ],
                'total_capital_gross_summary_report' => [
                    'date_from' => $totalCapitalGrossSummaryReport['filters']['date_from'],
                    'date_to' => $totalCapitalGrossSummaryReport['filters']['date_to'],
                    'row_count' => $totalCapitalGrossSummaryReport['summary']['row_count'],
                    'application_id' => $totalCapitalGrossSummaryRow['application_id'] ?? null,
                    'capital_investment_cents' => $totalCapitalGrossSummaryRow['capital_investment_cents'] ?? null,
                    'gross_sales_cents' => $totalCapitalGrossSummaryRow['gross_sales_cents'] ?? null,
                    'payment_amount_cents' => $totalCapitalGrossSummaryRow['payment_amount_cents'] ?? null,
                    'remaining_balance_cents' => $totalCapitalGrossSummaryRow['remaining_balance_cents'] ?? null,
                    'payment_status' => $totalCapitalGrossSummaryRow['payment_status'] ?? null,
                ],
                'all_abstract_report' => [
                    'status' => $allAbstractReport['status'],
                    'can_generate' => $allAbstractReport['can_generate'],
                    'can_export' => $allAbstractReport['can_export'],
                    'official_row_count' => $allAbstractReport['row_count'],
                    'coverage_count' => count($allAbstractReport['coverage']),
                    'control_count' => count($allAbstractReport['reconciliation_controls']),
                    'permit_collection_excluded' => ! collect($allAbstractReport['rows'])->contains('collection_id', $collection->id),
                    'blocked_by' => $allAbstractReport['blocked_by'],
                ],
                'annex_c_dnfbp_report' => [
                    'status' => $annexCDnfbpReport['status'],
                    'can_generate' => $annexCDnfbpReport['can_generate'],
                    'can_export' => $annexCDnfbpReport['can_export'],
                    'official_row_count' => $annexCDnfbpReport['row_count'],
                    'contract_column_count' => count($annexCDnfbpReport['columns']),
                    'artifact_excluded' => ! collect($annexCDnfbpReport['rows'])->contains('application_id', $permitApplication->id),
                    'blocked_by' => $annexCDnfbpReport['blocked_by'],
                ],
                'bsp_report' => [
                    'status' => $bspReport['status'],
                    'can_generate' => $bspReport['can_generate'],
                    'can_export' => $bspReport['can_export'],
                    'official_row_count' => $bspReport['row_count'],
                    'contract_column_count' => count($bspReport['columns']),
                    'artifact_excluded' => ! collect($bspReport['rows'])->contains('application_id', $permitApplication->id),
                    'blocked_by' => $bspReport['blocked_by'],
                ],
                'cmci_ldcs_report' => [
                    'status' => $cmciLdcsReport['status'],
                    'can_generate' => $cmciLdcsReport['can_generate'],
                    'can_export' => $cmciLdcsReport['can_export'],
                    'official_row_count' => $cmciLdcsReport['row_count'],
                    'contract_column_count' => count($cmciLdcsReport['columns']),
                    'artifact_excluded' => ! collect($cmciLdcsReport['rows'])->contains('application_id', $permitApplication->id),
                    'blocked_by' => $cmciLdcsReport['blocked_by'],
                ],
                'plds_report' => [
                    'status' => $pldsReport['status'],
                    'can_generate' => $pldsReport['can_generate'],
                    'can_export' => $pldsReport['can_export'],
                    'official_row_count' => $pldsReport['row_count'],
                    'contract_column_count' => count($pldsReport['columns']),
                    'artifact_excluded' => ! collect($pldsReport['rows'])->contains('application_id', $permitApplication->id),
                    'blocked_by' => $pldsReport['blocked_by'],
                ],
                'permit_application_status' => $permitApplication->status->value,
                'citizen_receipt_notice' => $receiptNotice === null ? null : [
                    'id' => $receiptNotice->id,
                    'type' => $receiptNotice->type,
                    'kind' => data_get($receiptNotice->data, 'kind'),
                    'tracking_reference' => data_get($receiptNotice->data, 'tracking_reference'),
                    'read_at' => $receiptNotice->read_at?->toIso8601String(),
                    'external_delivery' => false,
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
            ],
            'browser' => $browserReport,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('summary.html', $this->summaryRenderer->html($manifest));

        return $manifest;
    }

    private function storeScenarioDocument(PermitApplication $permitApplication, User $actor, string $runId, bool $citizen = false, string $label = 'Business registration evidence'): PermitApplicationDocument
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
                'label' => $label,
                'file' => new UploadedFile(
                    $temporaryPath,
                    'scenario-business-registration.pdf',
                    'application/pdf',
                    null,
                    true,
                ),
                'remarks' => 'Sample document prepared for this preview.',
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

    private function storeWeekendScenarioDocuments(PermitApplication $permitApplication, User $actor, string $runId, bool $citizen): void
    {
        foreach ([
            'Barangay Business Clearance',
            'Income Tax Return (ITR)',
            'Community Tax Certificate (CTC)',
            'Sworn Statement',
        ] as $label) {
            $this->storeScenarioDocument($permitApplication, $actor, $runId, $citizen, $label);
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
        FeeRule::query()->updateOrCreate(
            ['code' => 'SCENARIO-RECEIPT-APPLICATION-FEE'],
            [
                'name' => 'Scenario Receipt Application Fee',
                'category' => FeeRuleCategory::Fee,
                'scope' => FeeRuleScope::Application,
                'calculation_type' => FeeRuleCalculationType::Fixed,
                'basis' => 'none',
                'amount_cents' => 10_000,
                'effective_from' => self::ScenarioApplicationYear.'-01-01',
                'effective_until' => self::ScenarioApplicationYear.'-12-31',
                'is_active' => true,
            ],
        );

        FeeRule::query()->updateOrCreate(
            ['code' => 'SCENARIO-RECEIPT-BUSINESS-TAX'],
            [
                'line_of_business_id' => $lineOfBusiness->id,
                'name' => 'Scenario Receipt Business Tax',
                'category' => FeeRuleCategory::Tax,
                'scope' => FeeRuleScope::LineOfBusiness,
                'calculation_type' => FeeRuleCalculationType::Fixed,
                'basis' => 'declared_gross_sales',
                'amount_cents' => 20_000,
                'effective_from' => self::ScenarioApplicationYear.'-01-01',
                'effective_until' => self::ScenarioApplicationYear.'-12-31',
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
        $isCitizenOriginated = $this->isCitizenOriginated($scenario->key);
        $isNelsonWalkthrough = $this->isStakeholderWalkthrough($scenario->key);
        $isUnifiedPermitLifecycle = $isCitizenOriginated || $scenario->key === 'new_permit_lifecycle_authority_boundary';

        return [
            'title' => $isNelsonWalkthrough
                ? 'Nelson walkthrough: permit lifecycle and evidence boundary'
                : ($isCitizenOriginated
                ? 'Citizen-originated new permit lifecycle to authority boundary'
                : ($isUnifiedPermitLifecycle ? 'New permit lifecycle to authority boundary' : 'Manual collection receipt visibility')),
            'summary' => $isUnifiedPermitLifecycle
                ? ($isNelsonWalkthrough
                    ? 'A replayable municipal walkthrough follows one citizen application through BPLO and Treasury to authority review, then presents redacted production-migration evidence without claiming legal release, cutover, or unresolved policy.'
                    : ($isCitizenOriginated
                    ? 'A citizen creates and formally submits an unnumbered new permit application; BPLO and Treasury continue the exact record through assessment, collection, receipt, clearances, artifact verification, and the authority boundary without legally issuing or releasing the permit.'
                    : 'BPLO/Treasury staff execute a new permit lifecycle through intake, assessment, payment schedule, collection, receipt, clearances, permit artifact generation, public verification, and the explicit authority boundary without legally issuing or releasing the permit.'))
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
                ...($isNelsonWalkthrough ? [
                    [
                        'title' => 'Municipal authority remains explicit',
                        'description' => 'Configured officials and document associations are shown separately from signatory, issuance, release, and legal-effect authority.',
                        'dialogue' => 'Configuration is evidence. It is not authority by itself.',
                        'duration_seconds' => 5,
                    ],
                    [
                        'title' => 'Migration evidence is reviewable',
                        'description' => 'The walkthrough presents payload-safe production, calibration, rehearsal, and identity-reconciliation evidence.',
                        'dialogue' => 'Exact history is preserved; uncertain identities remain quarantined rather than guessed.',
                        'duration_seconds' => 5,
                    ],
                ] : []),
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

    private function isCitizenOriginated(string $scenarioKey): bool
    {
        return in_array($scenarioKey, [
            'citizen_new_permit_lifecycle_authority_boundary',
            'nelson_walkthrough',
            'stakeholder_preview_cycle_1',
        ], true);
    }

    private function isStakeholderWalkthrough(string $scenarioKey): bool
    {
        return in_array($scenarioKey, [
            'nelson_walkthrough',
            'stakeholder_preview_cycle_1',
        ], true);
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
