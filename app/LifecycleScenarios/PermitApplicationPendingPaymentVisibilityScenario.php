<?php

namespace App\LifecycleScenarios;

use App\Actions\BuildTopEstablishmentsTaxDueReport;
use App\Actions\BuildUnpaidEstablishmentsReport;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreatePaymentScheduleForAssessment;
use App\Actions\CreateStaffPermitApplication;
use App\Actions\DescribePaymentPolicyBoundary;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use App\Models\FeeRule;
use App\Models\FeeRuleRange;
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
        private readonly BuildUnpaidEstablishmentsReport $buildUnpaidEstablishmentsReport,
        private readonly BuildTopEstablishmentsTaxDueReport $buildTopEstablishmentsTaxDueReport,
        private readonly DescribePaymentPolicyBoundary $describePaymentPolicyBoundary,
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
        $applicationType = $this->applicationType($scenario);
        $permitApplication = $this->createPermitApplication->handle([
            'owner_name' => 'Scenario Owner '.$runId,
            'owner_email' => null,
            'owner_phone' => null,
            'owner_address' => 'Scenario verification address',
            'business_name' => $this->businessName($scenario, $runId),
            'trade_name' => $this->tradeName($scenario),
            'registration_number' => 'SCENARIO-'.$runId,
            'business_address' => 'Scenario verification address',
            'barangay' => 'Poblacion',
            'application_number' => $applicationNumber,
            'type' => $applicationType->value,
            'application_year' => now()->year,
            'lines' => [[
                'line_of_business_id' => $lineOfBusiness->id,
                'declared_gross_sales_cents' => 125_000_00,
                'capital_investment_cents' => 75_000_00,
                'quantity' => 1,
            ]],
        ], $operator);

        $assessment = $this->createAssessment->handle($permitApplication, $operator);
        $rangeAssessmentLine = $this->rangeAssessmentLine($assessment);
        $paymentSchedule = $this->createPaymentSchedule->handle($assessment, $operator);
        $paymentPolicyBoundary = $this->describePaymentPolicyBoundary->handle($paymentSchedule);
        $permitApplication = $paymentSchedule->permitApplication()->with(['assessments', 'paymentSchedules'])->firstOrFail();
        $unpaidEstablishmentsReport = $this->buildUnpaidEstablishmentsReport->handle([
            'year' => $permitApplication->application_year,
            'q' => $permitApplication->application_number,
        ]);
        $unpaidEstablishmentRow = collect($unpaidEstablishmentsReport['rows'])
            ->firstWhere('application_number', $permitApplication->application_number);
        $topTaxDueReport = $this->buildTopEstablishmentsTaxDueReport->handle([
            'year' => $permitApplication->application_year,
            'q' => $permitApplication->application_number,
        ]);
        $topTaxDueRow = collect($topTaxDueReport['rows'])
            ->firstWhere('application_number', $permitApplication->application_number);
        $topTaxDueCents = $assessment->lines
            ->filter(fn (AssessmentLine $assessmentLine): bool => $assessmentLine->category === FeeRuleCategory::Tax)
            ->sum('amount_cents');

        $steps = [
            $this->step('actors-resolved', 'Resolve actual application users', ['operator_id' => $operator->id], ['operator_id' => $operator->id]),
            $this->step('permit-application-created', 'Create permit application through staff intake action', ['status' => PermitApplicationStatus::Draft->value, 'application_type' => $applicationType->value], ['status' => PermitApplicationStatus::Draft->value, 'application_type' => $permitApplication->type->value, 'permit_application_id' => $permitApplication->id]),
            ...$this->renewalPolicySteps($scenario, $permitApplication),
            ...$this->amendmentPolicySteps($scenario, $permitApplication),
            ...$this->transferPolicySteps($scenario, $permitApplication),
            ...$this->retirementPolicySteps($scenario, $permitApplication),
            $this->step('assessment-computed', 'Compute assessment through assessment action', ['assessment_status' => 'computed'], ['assessment_status' => $assessment->status->value, 'assessment_id' => $assessment->id]),
            $this->step('range-assessment-line-computed', 'Assessment action applied the gross-sales range fee rule', ['calculation_type' => FeeRuleCalculationType::Range->value, 'basis_amount_cents' => 12_500_000, 'amount_cents' => 20_000], ['calculation_type' => $rangeAssessmentLine->calculation_type->value, 'basis_amount_cents' => $rangeAssessmentLine->basis_amount_cents, 'amount_cents' => $rangeAssessmentLine->amount_cents, 'assessment_line_id' => $rangeAssessmentLine->id]),
            $this->step('business-tax-assessment-line-computed', 'Assessment action persisted gross-sales business tax meaning', ['category' => FeeRuleCategory::Tax->value, 'basis' => 'declared_gross_sales', 'line_of_business' => $lineOfBusiness->name], ['category' => $rangeAssessmentLine->category->value, 'basis' => $rangeAssessmentLine->basis, 'line_of_business' => $rangeAssessmentLine->lineOfBusiness?->name, 'assessment_line_id' => $rangeAssessmentLine->id]),
            $this->step('payment-schedule-prepared', 'Prepare payment schedule through payment schedule action', ['schedule_status' => PaymentScheduleStatus::Pending->value, 'application_status' => PermitApplicationStatus::PendingPayment->value], ['schedule_status' => $paymentSchedule->status->value, 'application_status' => $permitApplication->status->value, 'payment_schedule_id' => $paymentSchedule->id]),
            $this->step('payment-policy-boundary-recorded', 'Expose unresolved installment, due-date, surcharge, interest, PIL, and deficiency-tax policy without calculating them', ['payment_policy_status' => 'policy_boundary'], ['payment_policy_status' => $paymentPolicyBoundary['status'], 'blocked_calculation_count' => count($paymentPolicyBoundary['blocked_calculations'])]),
            $this->step('unpaid-establishments-report-row-projected', 'Unpaid establishments report contains the pending permit schedule', ['application_number' => $permitApplication->application_number, 'business_name' => $permitApplication->business->name], ['application_number' => $unpaidEstablishmentRow['application_number'] ?? null, 'business_name' => $unpaidEstablishmentRow['business_name'] ?? null]),
            $this->step('top-tax-due-report-row-projected', 'Top tax due report contains the pending permit assessment tax lines', ['application_number' => $permitApplication->application_number, 'tax_due_cents' => $topTaxDueCents], ['application_number' => $topTaxDueRow['application_number'] ?? null, 'tax_due_cents' => $topTaxDueRow['tax_due_cents'] ?? null]),
        ];

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest['resources'] = [
            'record_type' => 'permit_application',
            'record_id' => $permitApplication->id,
            'public_reference' => $permitApplication->application_number,
            'application_number' => $permitApplication->application_number,
            'application_type' => $permitApplication->type->value,
            'renewal_policy_status' => data_get($permitApplication->metadata, 'renewal_policy_boundary.status'),
            'amendment_policy_status' => data_get($permitApplication->metadata, 'amendment_policy_boundary.status'),
            'transfer_policy_status' => data_get($permitApplication->metadata, 'transfer_policy_boundary.status'),
            'retirement_policy_status' => data_get($permitApplication->metadata, 'retirement_policy_boundary.status'),
            'assessment_id' => $assessment->id,
            'assessment_url' => route('staff.permit-applications.assessments.show', $assessment, false),
            'assessment_total_amount_cents' => $assessment->total_amount_cents,
            'range_assessment_line_id' => $rangeAssessmentLine->id,
            'range_fee_rule_code' => $rangeAssessmentLine->code,
            'range_calculation_type' => $rangeAssessmentLine->calculation_type->value,
            'range_basis' => $rangeAssessmentLine->basis,
            'range_basis_amount_cents' => $rangeAssessmentLine->basis_amount_cents,
            'range_amount_cents' => $rangeAssessmentLine->amount_cents,
            'business_tax_assessment_line_id' => $rangeAssessmentLine->id,
            'business_tax_code' => $rangeAssessmentLine->code,
            'business_tax_name' => $rangeAssessmentLine->name,
            'business_tax_category' => $rangeAssessmentLine->category->value,
            'business_tax_line_of_business' => $rangeAssessmentLine->lineOfBusiness?->name,
            'business_tax_basis' => $rangeAssessmentLine->basis,
            'business_tax_declared_gross_sales_cents' => $rangeAssessmentLine->basis_amount_cents,
            'business_tax_amount_cents' => $rangeAssessmentLine->amount_cents,
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_policy_status' => $paymentPolicyBoundary['status'],
            'list_url' => route('staff.permit-applications.index', absolute: false),
            'detail_url' => route('staff.permit-applications.show', $permitApplication, false),
            'payment_schedule_url' => route('staff.payment-schedules.show', $paymentSchedule, false),
            'unpaid_establishments_report_url' => route('staff.reports.unpaid-establishments.index', [
                'year' => $permitApplication->application_year,
                'q' => $permitApplication->application_number,
            ], false),
            'unpaid_establishments_report_download_url' => route('staff.reports.unpaid-establishments.download', [
                'year' => $permitApplication->application_year,
                'q' => $permitApplication->application_number,
            ], false),
            'unpaid_establishment_business_name' => $permitApplication->business->name,
            'top_tax_due_report_url' => route('staff.reports.top-establishments-tax-due.index', [
                'year' => $permitApplication->application_year,
                'q' => $permitApplication->application_number,
            ], false),
            'top_tax_due_report_download_url' => route('staff.reports.top-establishments-tax-due.download', [
                'year' => $permitApplication->application_year,
                'q' => $permitApplication->application_number,
            ], false),
            'top_tax_due_business_name' => $permitApplication->business->name,
            'top_tax_due_cents' => $topTaxDueCents,
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
            'application_type' => $permitApplication->type->value,
            'status' => $permitApplication->status->value,
            'renewal_policy_boundary' => $permitApplication->metadata['renewal_policy_boundary'] ?? null,
            'amendment_policy_boundary' => $permitApplication->metadata['amendment_policy_boundary'] ?? null,
            'transfer_policy_boundary' => $permitApplication->metadata['transfer_policy_boundary'] ?? null,
            'retirement_policy_boundary' => $permitApplication->metadata['retirement_policy_boundary'] ?? null,
            'assessment_id' => $assessment->id,
            'assessment_total_amount_cents' => $assessment->total_amount_cents,
            'range_assessment_line' => [
                'id' => $rangeAssessmentLine->id,
                'code' => $rangeAssessmentLine->code,
                'calculation_type' => $rangeAssessmentLine->calculation_type->value,
                'basis' => $rangeAssessmentLine->basis,
                'basis_amount_cents' => $rangeAssessmentLine->basis_amount_cents,
                'amount_cents' => $rangeAssessmentLine->amount_cents,
                'rule_snapshot' => $rangeAssessmentLine->rule_snapshot,
            ],
            'business_tax_assessment_line' => [
                'id' => $rangeAssessmentLine->id,
                'code' => $rangeAssessmentLine->code,
                'name' => $rangeAssessmentLine->name,
                'category' => $rangeAssessmentLine->category->value,
                'line_of_business' => $rangeAssessmentLine->lineOfBusiness?->name,
                'basis' => $rangeAssessmentLine->basis,
                'declared_gross_sales_cents' => $rangeAssessmentLine->basis_amount_cents,
                'amount_cents' => $rangeAssessmentLine->amount_cents,
            ],
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_schedule_status' => $paymentSchedule->status->value,
            'payment_policy_boundary' => $paymentPolicyBoundary,
            'unpaid_establishments_report' => [
                'year' => $unpaidEstablishmentsReport['filters']['year'],
                'row_count' => $unpaidEstablishmentsReport['summary']['row_count'],
                'total_amount_cents' => $unpaidEstablishmentsReport['summary']['total_amount_cents'],
                'paid_amount_cents' => $unpaidEstablishmentsReport['summary']['paid_amount_cents'],
                'outstanding_amount_cents' => $unpaidEstablishmentsReport['summary']['outstanding_amount_cents'],
                'application_number' => $unpaidEstablishmentRow['application_number'] ?? null,
                'business_name' => $unpaidEstablishmentRow['business_name'] ?? null,
            ],
            'top_tax_due_report' => [
                'year' => $topTaxDueReport['filters']['year'],
                'row_count' => $topTaxDueReport['summary']['row_count'],
                'tax_due_cents' => $topTaxDueReport['summary']['tax_due_cents'],
                'application_number' => $topTaxDueRow['application_number'] ?? null,
                'business_name' => $topTaxDueRow['business_name'] ?? null,
            ],
            'run_id' => $runId,
        ]);
        $artifactStore->putJson('terminal/execution.json', [
            'steps' => $steps,
            'external_calls' => 0,
            'irreversible_actions' => false,
            'notifications' => false,
        ]);
        $artifactStore->putJson('storyboard/storyboard.json', $this->storyboard($scenario, $runId, $permitApplication, $assessment, $paymentSchedule));
        $artifactStore->put('storyboard/storyboard.html', $this->storyboardHtml($scenario, $runId, $permitApplication, $assessment, $paymentSchedule));
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
        $assessment = Assessment::query()
            ->with('lines')
            ->findOrFail($manifest['resources']['assessment_id']);
        $rangeAssessmentLine = $this->rangeAssessmentLine($assessment);
        $paymentSchedule = PaymentSchedule::query()->findOrFail($manifest['resources']['payment_schedule_id']);
        $paymentPolicyBoundary = $this->describePaymentPolicyBoundary->handle($paymentSchedule);
        $unpaidEstablishmentsReport = $this->buildUnpaidEstablishmentsReport->handle([
            'year' => $permitApplication->application_year,
            'q' => $permitApplication->application_number,
        ]);
        $unpaidEstablishmentRow = collect($unpaidEstablishmentsReport['rows'])
            ->firstWhere('application_number', $permitApplication->application_number);
        $topTaxDueReport = $this->buildTopEstablishmentsTaxDueReport->handle([
            'year' => $permitApplication->application_year,
            'q' => $permitApplication->application_number,
        ]);
        $topTaxDueRow = collect($topTaxDueReport['rows'])
            ->firstWhere('application_number', $permitApplication->application_number);
        $browserReport = $artifactStore->readJson('browser/report.json') ?? [
            'result' => [
                'passed' => false,
            ],
            'checks' => [],
        ];

        $checks = [
            $this->step('audit-canonical-status', 'Canonical permit application status is pending payment', ['status' => PermitApplicationStatus::PendingPayment->value], ['status' => $permitApplication->status->value]),
            $this->step('audit-application-type', 'Canonical permit application type matches the scenario', ['application_type' => $manifest['resources']['application_type']], ['application_type' => $permitApplication->type->value]),
            ...$this->renewalPolicyAuditSteps($manifest, $permitApplication, $browserReport),
            ...$this->amendmentPolicyAuditSteps($manifest, $permitApplication, $browserReport),
            ...$this->transferPolicyAuditSteps($manifest, $permitApplication, $browserReport),
            ...$this->retirementPolicyAuditSteps($manifest, $permitApplication, $browserReport),
            $this->step('audit-range-assessment-line', 'Canonical assessment line remains the computed gross-sales range line', ['calculation_type' => FeeRuleCalculationType::Range->value, 'basis_amount_cents' => $manifest['resources']['range_basis_amount_cents'], 'amount_cents' => $manifest['resources']['range_amount_cents']], ['calculation_type' => $rangeAssessmentLine->calculation_type->value, 'basis_amount_cents' => $rangeAssessmentLine->basis_amount_cents, 'amount_cents' => $rangeAssessmentLine->amount_cents, 'assessment_line_id' => $rangeAssessmentLine->id]),
            $this->step('audit-browser-range-assessment-line', 'Browser evidence shows the same gross-sales range assessment line', ['code' => $rangeAssessmentLine->code, 'calculation_type' => $rangeAssessmentLine->calculation_type->value, 'basis_amount_cents' => $rangeAssessmentLine->basis_amount_cents, 'amount_cents' => $rangeAssessmentLine->amount_cents], ['code' => data_get($browserReport, 'assessment.range_line.code'), 'calculation_type' => data_get($browserReport, 'assessment.range_line.calculation_type'), 'basis_amount_cents' => data_get($browserReport, 'assessment.range_line.basis_amount_cents'), 'amount_cents' => data_get($browserReport, 'assessment.range_line.amount_cents')]),
            $this->step('audit-business-tax-assessment-line', 'Canonical assessment line remains a gross-sales business tax', ['name' => $manifest['resources']['business_tax_name'], 'category' => FeeRuleCategory::Tax->value, 'line_of_business' => $manifest['resources']['business_tax_line_of_business'], 'declared_gross_sales_cents' => $manifest['resources']['business_tax_declared_gross_sales_cents'], 'amount_cents' => $manifest['resources']['business_tax_amount_cents']], ['name' => $rangeAssessmentLine->name, 'category' => $rangeAssessmentLine->category->value, 'line_of_business' => $rangeAssessmentLine->lineOfBusiness?->name, 'declared_gross_sales_cents' => $rangeAssessmentLine->basis_amount_cents, 'amount_cents' => $rangeAssessmentLine->amount_cents]),
            $this->step('audit-browser-business-tax-line', 'Browser evidence shows the same gross-sales business tax meaning', ['name' => $rangeAssessmentLine->name, 'category' => $rangeAssessmentLine->category->value, 'line_of_business' => $rangeAssessmentLine->lineOfBusiness?->name, 'declared_gross_sales_cents' => $rangeAssessmentLine->basis_amount_cents, 'amount_cents' => $rangeAssessmentLine->amount_cents], ['name' => data_get($browserReport, 'assessment.business_tax.name'), 'category' => data_get($browserReport, 'assessment.business_tax.category'), 'line_of_business' => data_get($browserReport, 'assessment.business_tax.line_of_business'), 'declared_gross_sales_cents' => data_get($browserReport, 'assessment.business_tax.declared_gross_sales_cents'), 'amount_cents' => data_get($browserReport, 'assessment.business_tax.amount_cents')]),
            $this->step('audit-payment-schedule-status', 'Payment schedule remains pending for collection', ['status' => PaymentScheduleStatus::Pending->value], ['status' => $paymentSchedule->status->value]),
            $this->step('audit-payment-policy-boundary', 'Canonical payment policy boundary remains explicit', ['payment_policy_status' => 'policy_boundary'], ['payment_policy_status' => $paymentPolicyBoundary['status'], 'blocked_calculation_count' => count($paymentPolicyBoundary['blocked_calculations'])]),
            $this->step('audit-browser-payment-policy-boundary', 'Browser evidence shows the payment policy boundary', ['payment_policy_status' => 'policy_boundary', 'installment_visible' => true, 'due_date_visible' => true, 'surcharge_visible' => true, 'pil_visible' => true], ['payment_policy_status' => data_get($browserReport, 'payment_policy_boundary.status'), 'installment_visible' => data_get($browserReport, 'payment_policy_boundary.installment_visible'), 'due_date_visible' => data_get($browserReport, 'payment_policy_boundary.due_date_visible'), 'surcharge_visible' => data_get($browserReport, 'payment_policy_boundary.surcharge_visible'), 'pil_visible' => data_get($browserReport, 'payment_policy_boundary.pil_visible')]),
            $this->step('audit-unpaid-establishments-report-row', 'Unpaid establishments report contains the scenario pending permit schedule', ['application_number' => $permitApplication->application_number, 'business_name' => $permitApplication->business->name], ['application_number' => $unpaidEstablishmentRow['application_number'] ?? null, 'business_name' => $unpaidEstablishmentRow['business_name'] ?? null]),
            $this->step('audit-browser-unpaid-establishments-report-row', 'Browser evidence observed the unpaid establishments report row', ['application_number' => $permitApplication->application_number, 'csv_export_visible' => true], ['application_number' => data_get($browserReport, 'reports.unpaid_establishments.application_number'), 'csv_export_visible' => data_get($browserReport, 'reports.unpaid_establishments.csv_export_visible')]),
            $this->step('audit-top-tax-due-report-row', 'Top tax due report contains the scenario pending permit assessment tax lines', ['application_number' => $permitApplication->application_number, 'tax_due_cents' => $manifest['resources']['top_tax_due_cents']], ['application_number' => $topTaxDueRow['application_number'] ?? null, 'tax_due_cents' => $topTaxDueRow['tax_due_cents'] ?? null]),
            $this->step('audit-browser-top-tax-due-report-row', 'Browser evidence observed the top tax due report row', ['application_number' => $permitApplication->application_number, 'tax_due_cents' => $manifest['resources']['top_tax_due_cents'], 'csv_export_visible' => true], ['application_number' => data_get($browserReport, 'reports.top_tax_due.application_number'), 'tax_due_cents' => data_get($browserReport, 'reports.top_tax_due.tax_due_cents'), 'csv_export_visible' => data_get($browserReport, 'reports.top_tax_due.csv_export_visible')]),
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
                'application_type' => $permitApplication->type->value,
                'status' => $permitApplication->status->value,
                'renewal_policy_boundary' => $permitApplication->metadata['renewal_policy_boundary'] ?? null,
                'amendment_policy_boundary' => $permitApplication->metadata['amendment_policy_boundary'] ?? null,
                'transfer_policy_boundary' => $permitApplication->metadata['transfer_policy_boundary'] ?? null,
                'retirement_policy_boundary' => $permitApplication->metadata['retirement_policy_boundary'] ?? null,
                'assessment_id' => $manifest['resources']['assessment_id'],
                'range_assessment_line' => [
                    'id' => $rangeAssessmentLine->id,
                    'code' => $rangeAssessmentLine->code,
                    'calculation_type' => $rangeAssessmentLine->calculation_type->value,
                    'basis' => $rangeAssessmentLine->basis,
                    'basis_amount_cents' => $rangeAssessmentLine->basis_amount_cents,
                    'amount_cents' => $rangeAssessmentLine->amount_cents,
                ],
                'business_tax_assessment_line' => [
                    'id' => $rangeAssessmentLine->id,
                    'code' => $rangeAssessmentLine->code,
                    'name' => $rangeAssessmentLine->name,
                    'category' => $rangeAssessmentLine->category->value,
                    'line_of_business' => $rangeAssessmentLine->lineOfBusiness?->name,
                    'basis' => $rangeAssessmentLine->basis,
                    'declared_gross_sales_cents' => $rangeAssessmentLine->basis_amount_cents,
                    'amount_cents' => $rangeAssessmentLine->amount_cents,
                ],
                'payment_schedule_id' => $paymentSchedule->id,
                'payment_schedule_status' => $paymentSchedule->status->value,
                'payment_policy_boundary' => $paymentPolicyBoundary,
                'unpaid_establishments_report' => [
                    'year' => $unpaidEstablishmentsReport['filters']['year'],
                    'row_count' => $unpaidEstablishmentsReport['summary']['row_count'],
                    'total_amount_cents' => $unpaidEstablishmentsReport['summary']['total_amount_cents'],
                    'paid_amount_cents' => $unpaidEstablishmentsReport['summary']['paid_amount_cents'],
                    'outstanding_amount_cents' => $unpaidEstablishmentsReport['summary']['outstanding_amount_cents'],
                    'application_number' => $unpaidEstablishmentRow['application_number'] ?? null,
                    'business_name' => $unpaidEstablishmentRow['business_name'] ?? null,
                ],
                'top_tax_due_report' => [
                    'year' => $topTaxDueReport['filters']['year'],
                    'row_count' => $topTaxDueReport['summary']['row_count'],
                    'tax_due_cents' => $topTaxDueReport['summary']['tax_due_cents'],
                    'application_number' => $topTaxDueRow['application_number'] ?? null,
                    'business_name' => $topTaxDueRow['business_name'] ?? null,
                ],
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
        FeeRule::query()->updateOrCreate(
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

        $businessTaxRule = FeeRule::query()->updateOrCreate(
            ['code' => 'SCENARIO-BUSINESS-TAX'],
            [
                'line_of_business_id' => $lineOfBusiness->id,
                'name' => 'Scenario Business Tax',
                'category' => FeeRuleCategory::Tax,
                'scope' => FeeRuleScope::LineOfBusiness,
                'calculation_type' => FeeRuleCalculationType::Range,
                'basis' => 'declared_gross_sales',
                'amount_cents' => 0,
                'rate_basis_points' => null,
                'effective_from' => now()->startOfYear(),
                'is_active' => true,
            ],
        );

        FeeRuleRange::query()->updateOrCreate(
            [
                'fee_rule_id' => $businessTaxRule->id,
                'min_basis_cents' => 10_000_000,
                'max_basis_cents' => 20_000_000,
            ],
            [
                'amount_cents' => 20_000,
                'rate_basis_points' => null,
            ],
        );
    }

    private function rangeAssessmentLine(Assessment $assessment): AssessmentLine
    {
        $assessment->loadMissing('lines.lineOfBusiness');

        $line = $assessment->lines
            ->first(fn (AssessmentLine $assessmentLine): bool => $assessmentLine->calculation_type === FeeRuleCalculationType::Range);

        if (! $line instanceof AssessmentLine) {
            throw new RuntimeException('Scenario assessment did not produce a range-based assessment line.');
        }

        return $line;
    }

    private function applicationType(LifecycleScenarioDefinition $scenario): PermitApplicationType
    {
        if ($scenario->key === 'amendment_permit_lifecycle_foundation') {
            return PermitApplicationType::Amendment;
        }

        if ($scenario->key === 'transfer_permit_lifecycle_foundation') {
            return PermitApplicationType::Transfer;
        }

        if ($scenario->key === 'renewal_permit_lifecycle_foundation') {
            return PermitApplicationType::Renewal;
        }

        if ($scenario->key === 'retirement_permit_lifecycle_foundation') {
            return PermitApplicationType::Retirement;
        }

        return PermitApplicationType::New;
    }

    private function businessName(LifecycleScenarioDefinition $scenario, string $runId): string
    {
        if ($this->applicationType($scenario) === PermitApplicationType::Amendment) {
            return 'Scenario Amendment Business '.$runId;
        }

        if ($this->applicationType($scenario) === PermitApplicationType::Transfer) {
            return 'Scenario Transfer Business '.$runId;
        }

        if ($this->applicationType($scenario) === PermitApplicationType::Renewal) {
            return 'Scenario Renewal Business '.$runId;
        }

        if ($this->applicationType($scenario) === PermitApplicationType::Retirement) {
            return 'Scenario Retirement Business '.$runId;
        }

        return 'Scenario Payment Business '.$runId;
    }

    private function tradeName(LifecycleScenarioDefinition $scenario): string
    {
        if ($this->applicationType($scenario) === PermitApplicationType::Amendment) {
            return 'Scenario Amendment Trade';
        }

        if ($this->applicationType($scenario) === PermitApplicationType::Transfer) {
            return 'Scenario Transfer Trade';
        }

        if ($this->applicationType($scenario) === PermitApplicationType::Renewal) {
            return 'Scenario Renewal Trade';
        }

        if ($this->applicationType($scenario) === PermitApplicationType::Retirement) {
            return 'Scenario Retirement Trade';
        }

        return 'Scenario Payment Trade';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function renewalPolicySteps(LifecycleScenarioDefinition $scenario, PermitApplication $permitApplication): array
    {
        if ($this->applicationType($scenario) !== PermitApplicationType::Renewal) {
            return [];
        }

        return [
            $this->step(
                'renewal-policy-boundary-recorded',
                'Record renewal policy boundary without inventing unresolved renewal tax behavior',
                ['renewal_policy_status' => 'policy_boundary'],
                [
                    'renewal_policy_status' => data_get($permitApplication->metadata, 'renewal_policy_boundary.status'),
                    'application_type' => $permitApplication->type->value,
                ],
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function amendmentPolicySteps(LifecycleScenarioDefinition $scenario, PermitApplication $permitApplication): array
    {
        if ($this->applicationType($scenario) !== PermitApplicationType::Amendment) {
            return [];
        }

        return [
            $this->step(
                'amendment-policy-boundary-recorded',
                'Record amendment policy boundary without inventing unresolved amended-field behavior',
                ['amendment_policy_status' => 'policy_boundary'],
                [
                    'amendment_policy_status' => data_get($permitApplication->metadata, 'amendment_policy_boundary.status'),
                    'application_type' => $permitApplication->type->value,
                ],
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function transferPolicySteps(LifecycleScenarioDefinition $scenario, PermitApplication $permitApplication): array
    {
        if ($this->applicationType($scenario) !== PermitApplicationType::Transfer) {
            return [];
        }

        return [
            $this->step(
                'transfer-policy-boundary-recorded',
                'Record transfer policy boundary without executing unresolved legal transfer behavior',
                ['transfer_policy_status' => 'policy_boundary'],
                [
                    'transfer_policy_status' => data_get($permitApplication->metadata, 'transfer_policy_boundary.status'),
                    'application_type' => $permitApplication->type->value,
                ],
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function retirementPolicySteps(LifecycleScenarioDefinition $scenario, PermitApplication $permitApplication): array
    {
        if ($this->applicationType($scenario) !== PermitApplicationType::Retirement) {
            return [];
        }

        return [
            $this->step(
                'retirement-policy-boundary-recorded',
                'Record retirement policy boundary without executing unresolved closure behavior',
                ['retirement_policy_status' => 'policy_boundary'],
                [
                    'retirement_policy_status' => data_get($permitApplication->metadata, 'retirement_policy_boundary.status'),
                    'application_type' => $permitApplication->type->value,
                ],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $browserReport
     * @return array<int, array<string, mixed>>
     */
    private function renewalPolicyAuditSteps(array $manifest, PermitApplication $permitApplication, array $browserReport): array
    {
        if (($manifest['resources']['application_type'] ?? null) !== PermitApplicationType::Renewal->value) {
            return [];
        }

        return [
            $this->step(
                'audit-renewal-policy-boundary',
                'Canonical renewal policy boundary remains explicit',
                ['renewal_policy_status' => 'policy_boundary'],
                [
                    'renewal_policy_status' => data_get($permitApplication->metadata, 'renewal_policy_boundary.status'),
                    'unresolved_policy_count' => count(data_get($permitApplication->metadata, 'renewal_policy_boundary.unresolved_policy', [])),
                ],
            ),
            $this->step(
                'audit-browser-renewal-policy-boundary',
                'Browser evidence shows the renewal policy boundary',
                ['renewal_policy_status' => 'policy_boundary'],
                [
                    'renewal_policy_status' => data_get($browserReport, 'renewal_policy.status'),
                    'unresolved_visible' => data_get($browserReport, 'renewal_policy.unresolved_visible'),
                ],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $browserReport
     * @return array<int, array<string, mixed>>
     */
    private function amendmentPolicyAuditSteps(array $manifest, PermitApplication $permitApplication, array $browserReport): array
    {
        if (($manifest['resources']['application_type'] ?? null) !== PermitApplicationType::Amendment->value) {
            return [];
        }

        return [
            $this->step(
                'audit-amendment-policy-boundary',
                'Canonical amendment policy boundary remains explicit',
                ['amendment_policy_status' => 'policy_boundary'],
                [
                    'amendment_policy_status' => data_get($permitApplication->metadata, 'amendment_policy_boundary.status'),
                    'unresolved_policy_count' => count(data_get($permitApplication->metadata, 'amendment_policy_boundary.unresolved_policy', [])),
                ],
            ),
            $this->step(
                'audit-browser-amendment-policy-boundary',
                'Browser evidence shows the amendment policy boundary',
                ['amendment_policy_status' => 'policy_boundary'],
                [
                    'amendment_policy_status' => data_get($browserReport, 'amendment_policy.status'),
                    'unresolved_visible' => data_get($browserReport, 'amendment_policy.unresolved_visible'),
                ],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $browserReport
     * @return array<int, array<string, mixed>>
     */
    private function transferPolicyAuditSteps(array $manifest, PermitApplication $permitApplication, array $browserReport): array
    {
        if (($manifest['resources']['application_type'] ?? null) !== PermitApplicationType::Transfer->value) {
            return [];
        }

        return [
            $this->step(
                'audit-transfer-policy-boundary',
                'Canonical transfer policy boundary remains explicit',
                ['transfer_policy_status' => 'policy_boundary'],
                [
                    'transfer_policy_status' => data_get($permitApplication->metadata, 'transfer_policy_boundary.status'),
                    'unresolved_policy_count' => count(data_get($permitApplication->metadata, 'transfer_policy_boundary.unresolved_policy', [])),
                ],
            ),
            $this->step(
                'audit-browser-transfer-policy-boundary',
                'Browser evidence shows the transfer policy boundary',
                ['transfer_policy_status' => 'policy_boundary'],
                [
                    'transfer_policy_status' => data_get($browserReport, 'transfer_policy.status'),
                    'unresolved_visible' => data_get($browserReport, 'transfer_policy.unresolved_visible'),
                ],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $browserReport
     * @return array<int, array<string, mixed>>
     */
    private function retirementPolicyAuditSteps(array $manifest, PermitApplication $permitApplication, array $browserReport): array
    {
        if (($manifest['resources']['application_type'] ?? null) !== PermitApplicationType::Retirement->value) {
            return [];
        }

        return [
            $this->step(
                'audit-retirement-policy-boundary',
                'Canonical retirement policy boundary remains explicit',
                ['retirement_policy_status' => 'policy_boundary'],
                [
                    'retirement_policy_status' => data_get($permitApplication->metadata, 'retirement_policy_boundary.status'),
                    'unresolved_policy_count' => count(data_get($permitApplication->metadata, 'retirement_policy_boundary.unresolved_policy', [])),
                ],
            ),
            $this->step(
                'audit-browser-retirement-policy-boundary',
                'Browser evidence shows the retirement policy boundary',
                ['retirement_policy_status' => 'policy_boundary'],
                [
                    'retirement_policy_status' => data_get($browserReport, 'retirement_policy.status'),
                    'unresolved_visible' => data_get($browserReport, 'retirement_policy.unresolved_visible'),
                ],
            ),
        ];
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
    private function storyboard(LifecycleScenarioDefinition $scenario, string $runId, PermitApplication $permitApplication, Assessment $assessment, PaymentSchedule $paymentSchedule): array
    {
        $isAmendment = $this->applicationType($scenario) === PermitApplicationType::Amendment;
        $isRenewal = $this->applicationType($scenario) === PermitApplicationType::Renewal;
        $isTransfer = $this->applicationType($scenario) === PermitApplicationType::Transfer;
        $isRetirement = $this->applicationType($scenario) === PermitApplicationType::Retirement;

        return [
            'title' => match (true) {
                $isAmendment => 'Amendment permit lifecycle foundation',
                $isRenewal => 'Renewal permit lifecycle foundation',
                $isTransfer => 'Transfer permit lifecycle foundation',
                $isRetirement => 'Retirement permit lifecycle foundation',
                default => 'Permit application pending payment visibility',
            },
            'summary' => match (true) {
                $isAmendment => 'BPLO staff records an amendment permit application, computes assessment from current persisted fee rules, prepares a payment schedule, and verifies that unresolved amendment policy remains visible.',
                $isRenewal => 'BPLO staff records a renewal permit application, computes assessment from current persisted fee rules, prepares a payment schedule, and verifies that unresolved renewal tax policy remains visible.',
                $isTransfer => 'BPLO staff records a transfer permit application, computes assessment from current persisted fee rules, prepares a payment schedule, and verifies that unresolved transfer policy remains visible.',
                $isRetirement => 'BPLO staff records a retirement permit application, computes assessment from current persisted fee rules, prepares a payment schedule, and verifies that unresolved retirement policy remains visible.',
                default => 'BPLO staff records a disposable application, computes assessment, prepares a payment schedule, and verifies that staff screens show the application ready for collection.',
            },
            'run_id' => $runId,
            'record' => [
                'type' => 'permit_application',
                'id' => $permitApplication->id,
                'application_number' => $permitApplication->application_number,
                'application_type' => $permitApplication->type->value,
                'renewal_policy_status' => data_get($permitApplication->metadata, 'renewal_policy_boundary.status'),
                'amendment_policy_status' => data_get($permitApplication->metadata, 'amendment_policy_boundary.status'),
                'transfer_policy_status' => data_get($permitApplication->metadata, 'transfer_policy_boundary.status'),
                'retirement_policy_status' => data_get($permitApplication->metadata, 'retirement_policy_boundary.status'),
                'assessment_id' => $assessment->id,
                'payment_schedule_id' => $paymentSchedule->id,
            ],
            'frames' => [
                [
                    'title' => match (true) {
                        $isAmendment => 'Staff records amendment application',
                        $isRenewal => 'Staff records renewal application',
                        $isTransfer => 'Staff records transfer application',
                        $isRetirement => 'Staff records retirement application',
                        default => 'Staff records application',
                    },
                    'description' => match (true) {
                        $isAmendment => 'BPLO staff records an amendment application for the scenario business and preserves unresolved amendment policy as explicit evidence.',
                        $isRenewal => 'BPLO staff records a renewal application for the scenario business and preserves unresolved renewal policy as explicit evidence.',
                        $isTransfer => 'BPLO staff records a transfer application for the scenario business and preserves unresolved transfer policy as explicit evidence.',
                        $isRetirement => 'BPLO staff records a retirement application for the scenario business and preserves unresolved retirement policy as explicit evidence.',
                        default => 'BPLO staff records a new business permit application for the scenario business.',
                    },
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
                    'dialogue' => match (true) {
                        $isAmendment => 'The amendment application is pending payment; amended fields, fee basis, and supersession policy remain explicit boundaries.',
                        $isRenewal => 'The renewal application is pending payment; late payment, PIL, and deficiency policy remain explicit boundaries.',
                        $isTransfer => 'The transfer application is pending payment; ownership transfer, location transfer, and legal effect remain explicit boundaries.',
                        $isRetirement => 'The retirement application is pending payment; closure date, final liability, inspection, and legal retirement effect remain explicit boundaries.',
                        default => 'The application is now pending payment; collection and receipt behavior remain separate scenarios.',
                    },
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

    private function storyboardHtml(LifecycleScenarioDefinition $scenario, string $runId, PermitApplication $permitApplication, Assessment $assessment, PaymentSchedule $paymentSchedule): string
    {
        $storyboard = $this->storyboard($scenario, $runId, $permitApplication, $assessment, $paymentSchedule);
        $frames = collect($storyboard['frames'])
            ->map(fn (array $frame): string => '<li><strong>'.e($frame['title']).'</strong><br>'.e($frame['description']).'<br><em>'.e($frame['dialogue']).'</em></li>')
            ->implode('');

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.e($storyboard['title']).'</title></head><body><h1>'.e($storyboard['title']).'</h1><p>'.e($storyboard['summary']).'</p><p>Run ID: '.e($runId).'</p><p>Application: '.e((string) $permitApplication->application_number).'</p><p>Assessment: '.e((string) $assessment->id).'</p><p>Payment schedule: '.e((string) $paymentSchedule->id).'</p><ol>'.$frames.'</ol></body></html>';
    }
}
