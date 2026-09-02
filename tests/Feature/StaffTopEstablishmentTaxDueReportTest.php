<?php

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use Inertia\Testing\AssertableInertia as Assert;

test('staff users with report permission can view top establishments by tax due', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    topTaxDueReportSchedule([
        'business_name' => 'Beta Tax Store',
        'owner_name' => 'Beta Owner',
        'application_number' => 'APP-TAX-DUE-002',
        'type' => PermitApplicationType::Renewal,
        'year' => 2026,
        'tax_amount_cents' => 150_000,
        'fee_amount_cents' => 10_000,
        'paid_amount_cents' => 25_000,
        'status' => PaymentScheduleStatus::PartiallyPaid,
        'line_of_business' => 'Retail Store',
    ]);

    topTaxDueReportSchedule([
        'business_name' => 'Alpha Tax Services',
        'owner_name' => 'Alpha Owner',
        'application_number' => 'APP-TAX-DUE-001',
        'type' => PermitApplicationType::New,
        'year' => 2026,
        'tax_amount_cents' => 250_000,
        'fee_amount_cents' => 20_000,
        'paid_amount_cents' => 0,
        'status' => PaymentScheduleStatus::Pending,
        'line_of_business' => 'Professional Services',
    ]);

    nonTaxScheduleExcludedFromTopTaxDueReport();

    $this->actingAs($user)
        ->get(route('staff.reports.top-establishments-tax-due.index', [
            'year' => 2026,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/TopEstablishmentsTaxDue')
            ->where('summary.row_count', 2)
            ->where('summary.business_count', 2)
            ->where('summary.tax_due_cents', 400_000)
            ->where('summary.largest_tax_due_cents', 250_000)
            ->where('summary.scope', 'Top establishments by persisted tax assessment lines for the selected application year.')
            ->where('rows.0.business_name', 'Alpha Tax Services')
            ->where('rows.0.tax_due_cents', 250_000)
            ->where('rows.0.tax_codes.0', 'TEST-BUSINESS-TAX')
            ->where('rows.1.business_name', 'Beta Tax Store')
            ->where('rows.1.tax_due_cents', 150_000)
        );
});

test('top establishments by tax due filters by type search and limit', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    topTaxDueReportSchedule([
        'business_name' => 'Included High Tax Renewal',
        'owner_name' => 'Rina Owner',
        'application_number' => 'APP-INCLUDED-TAX',
        'type' => PermitApplicationType::Renewal,
        'year' => 2026,
        'tax_amount_cents' => 330_000,
        'fee_amount_cents' => 11_000,
        'paid_amount_cents' => 0,
        'status' => PaymentScheduleStatus::Pending,
        'line_of_business' => 'Grocery',
        'barangay' => 'Taway',
    ]);

    topTaxDueReportSchedule([
        'business_name' => 'Included Lower Tax Renewal',
        'owner_name' => 'Lower Owner',
        'application_number' => 'APP-LOWER-TAX',
        'type' => PermitApplicationType::Renewal,
        'year' => 2026,
        'tax_amount_cents' => 220_000,
        'fee_amount_cents' => 9_000,
        'paid_amount_cents' => 0,
        'status' => PaymentScheduleStatus::Pending,
        'line_of_business' => 'Hardware',
        'barangay' => 'Taway',
    ]);

    topTaxDueReportSchedule([
        'business_name' => 'Wrong Type Tax',
        'owner_name' => 'Wrong Owner',
        'application_number' => 'APP-WRONG-TYPE-TAX',
        'type' => PermitApplicationType::New,
        'year' => 2026,
        'tax_amount_cents' => 440_000,
        'fee_amount_cents' => 5_000,
        'paid_amount_cents' => 0,
        'status' => PaymentScheduleStatus::Pending,
        'line_of_business' => 'Pharmacy',
        'barangay' => 'Taway',
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.top-establishments-tax-due.index', [
            'year' => 2026,
            'type' => PermitApplicationType::Renewal->value,
            'q' => 'taway',
            'limit' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.row_count', 1)
            ->where('summary.tax_due_cents', 330_000)
            ->where('filters.type', PermitApplicationType::Renewal->value)
            ->where('filters.q', 'taway')
            ->where('filters.limit', 1)
            ->where('rows.0.business_name', 'Included High Tax Renewal')
            ->missing('rows.1')
        );
});

test('staff users without report permission cannot view top establishments by tax due', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReceipts,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.top-establishments-tax-due.index'))
        ->assertForbidden();
});

test('top establishments by tax due exports matching rows as csv', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    topTaxDueReportSchedule([
        'business_name' => 'CSV Top Tax Establishment',
        'owner_name' => 'CSV Owner',
        'application_number' => 'APP-CSV-TOP-TAX',
        'type' => PermitApplicationType::New,
        'year' => 2026,
        'tax_amount_cents' => 54_321,
        'fee_amount_cents' => 12_345,
        'paid_amount_cents' => 0,
        'status' => PaymentScheduleStatus::Pending,
        'line_of_business' => 'Food Service',
    ]);

    $response = $this->actingAs($user)
        ->get(route('staff.reports.top-establishments-tax-due.download', [
            'year' => 2026,
        ]))
        ->assertOk()
        ->assertDownload('top-establishments-tax-due-2026.csv');

    expect($response->streamedContent())
        ->toContain('Rank')
        ->toContain('APP-CSV-TOP-TAX')
        ->toContain('CSV Top Tax Establishment')
        ->toContain('TEST-BUSINESS-TAX')
        ->toContain('543.21');
});

/**
 * @param  array{
 *     business_name: string,
 *     owner_name: string,
 *     application_number: string,
 *     type: PermitApplicationType,
 *     year: int,
 *     tax_amount_cents: int,
 *     fee_amount_cents: int,
 *     paid_amount_cents: int,
 *     status: PaymentScheduleStatus,
 *     line_of_business: string,
 *     barangay?: string
 * }  $attributes
 */
function topTaxDueReportSchedule(array $attributes): PaymentSchedule
{
    $owner = BusinessOwner::factory()->create([
        'name' => $attributes['owner_name'],
    ]);
    $business = Business::factory()
        ->for($owner, 'owner')
        ->create([
            'name' => $attributes['business_name'],
            'trade_name' => $attributes['business_name'].' Trade',
            'barangay' => $attributes['barangay'] ?? 'Poblacion',
        ]);
    $permitApplication = PermitApplication::factory()
        ->withStatus(PermitApplicationStatus::PendingPayment)
        ->for($business)
        ->create([
            'application_number' => $attributes['application_number'],
            'type' => $attributes['type'],
            'status' => PermitApplicationStatus::PendingPayment,
            'application_year' => $attributes['year'],
        ]);
    $assessment = Assessment::factory()
        ->for($permitApplication)
        ->create([
            'total_amount_cents' => $attributes['tax_amount_cents'] + $attributes['fee_amount_cents'],
        ]);

    AssessmentLine::factory()
        ->for($assessment)
        ->create([
            'code' => 'TEST-BUSINESS-TAX',
            'name' => 'Test Business Tax',
            'category' => FeeRuleCategory::Tax,
            'calculation_type' => FeeRuleCalculationType::Fixed,
            'basis' => 'declared_gross_sales',
            'amount_cents' => $attributes['tax_amount_cents'],
        ]);

    AssessmentLine::factory()
        ->for($assessment)
        ->create([
            'code' => 'TEST-PERMIT-FEE',
            'name' => 'Test Permit Fee',
            'category' => FeeRuleCategory::Fee,
            'calculation_type' => FeeRuleCalculationType::Fixed,
            'basis' => 'none',
            'amount_cents' => $attributes['fee_amount_cents'],
        ]);

    $schedule = PaymentSchedule::factory()
        ->for($permitApplication)
        ->for($assessment)
        ->create([
            'status' => $attributes['status'],
            'total_amount_cents' => $attributes['tax_amount_cents'] + $attributes['fee_amount_cents'],
            'paid_amount_cents' => $attributes['paid_amount_cents'],
        ]);
    $lineOfBusiness = LineOfBusiness::factory()->create([
        'name' => $attributes['line_of_business'],
    ]);
    PermitApplicationLine::factory()
        ->for($permitApplication)
        ->for($lineOfBusiness)
        ->create();

    return $schedule;
}

function nonTaxScheduleExcludedFromTopTaxDueReport(): PaymentSchedule
{
    $permitApplication = PermitApplication::factory()->create([
        'application_number' => 'APP-NON-TAX-EXCLUDED',
        'application_year' => 2026,
    ]);
    $assessment = Assessment::factory()
        ->for($permitApplication)
        ->create([
            'total_amount_cents' => 10_000,
        ]);
    AssessmentLine::factory()
        ->for($assessment)
        ->create([
            'code' => 'TEST-NON-TAX-FEE',
            'category' => FeeRuleCategory::Fee,
            'amount_cents' => 10_000,
        ]);

    return PaymentSchedule::factory()
        ->for($permitApplication)
        ->for($assessment)
        ->create([
            'status' => PaymentScheduleStatus::Pending,
            'total_amount_cents' => 10_000,
            'paid_amount_cents' => 0,
        ]);
}
