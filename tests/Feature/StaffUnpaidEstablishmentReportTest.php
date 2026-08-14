<?php

use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use Inertia\Testing\AssertableInertia as Assert;

test('staff users with report permission can view unpaid establishments', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    unpaidEstablishmentReportSchedule([
        'business_name' => 'Alpha Unpaid Store',
        'owner_name' => 'Ana Owner',
        'application_number' => 'APP-UNPAID-001',
        'type' => PermitApplicationType::New,
        'year' => 2026,
        'total_amount_cents' => 125_000,
        'paid_amount_cents' => 0,
        'status' => PaymentScheduleStatus::Pending,
        'line_of_business' => 'Retail Store',
    ]);

    unpaidEstablishmentReportSchedule([
        'business_name' => 'Beta Partial Services',
        'owner_name' => 'Berto Owner',
        'application_number' => 'APP-UNPAID-002',
        'type' => PermitApplicationType::Renewal,
        'year' => 2026,
        'total_amount_cents' => 100_000,
        'paid_amount_cents' => 25_000,
        'status' => PaymentScheduleStatus::PartiallyPaid,
        'line_of_business' => 'Repair Services',
    ]);

    paidEstablishmentExcludedFromUnpaidReport();

    $this->actingAs($user)
        ->get(route('staff.reports.unpaid-establishments.index', [
            'year' => 2026,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/UnpaidEstablishments')
            ->where('summary.row_count', 2)
            ->where('summary.business_count', 2)
            ->where('summary.total_amount_cents', 225_000)
            ->where('summary.paid_amount_cents', 25_000)
            ->where('summary.outstanding_amount_cents', 200_000)
            ->where('summary.partially_paid_count', 1)
            ->where('summary.scope', 'Pending and partially paid permit payment schedules for the selected application year.')
            ->where('rows.0.business_name', 'Alpha Unpaid Store')
            ->where('rows.0.outstanding_amount_cents', 125_000)
            ->where('rows.1.business_name', 'Beta Partial Services')
            ->where('rows.1.outstanding_amount_cents', 75_000)
        );
});

test('unpaid establishments report filters by status type and search', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    unpaidEstablishmentReportSchedule([
        'business_name' => 'Included Partial Renewal',
        'owner_name' => 'Rina Owner',
        'application_number' => 'APP-INCLUDED-PARTIAL',
        'type' => PermitApplicationType::Renewal,
        'year' => 2026,
        'total_amount_cents' => 88_000,
        'paid_amount_cents' => 11_000,
        'status' => PaymentScheduleStatus::PartiallyPaid,
        'line_of_business' => 'Grocery',
        'barangay' => 'Taway',
    ]);

    unpaidEstablishmentReportSchedule([
        'business_name' => 'Wrong Status Pending',
        'owner_name' => 'Pending Owner',
        'application_number' => 'APP-WRONG-STATUS',
        'type' => PermitApplicationType::Renewal,
        'year' => 2026,
        'total_amount_cents' => 44_000,
        'paid_amount_cents' => 0,
        'status' => PaymentScheduleStatus::Pending,
        'line_of_business' => 'Hardware',
        'barangay' => 'Taway',
    ]);

    unpaidEstablishmentReportSchedule([
        'business_name' => 'Wrong Year Partial',
        'owner_name' => 'Year Owner',
        'application_number' => 'APP-WRONG-YEAR',
        'type' => PermitApplicationType::Renewal,
        'year' => 2025,
        'total_amount_cents' => 77_000,
        'paid_amount_cents' => 12_000,
        'status' => PaymentScheduleStatus::PartiallyPaid,
        'line_of_business' => 'Pharmacy',
        'barangay' => 'Taway',
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.unpaid-establishments.index', [
            'year' => 2026,
            'type' => PermitApplicationType::Renewal->value,
            'status' => PaymentScheduleStatus::PartiallyPaid->value,
            'q' => 'taway',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.row_count', 1)
            ->where('summary.outstanding_amount_cents', 77_000)
            ->where('filters.type', PermitApplicationType::Renewal->value)
            ->where('filters.status', PaymentScheduleStatus::PartiallyPaid->value)
            ->where('filters.q', 'taway')
            ->where('rows.0.business_name', 'Included Partial Renewal')
            ->missing('rows.1')
        );
});

test('staff users without report permission cannot view unpaid establishments', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReceipts,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.unpaid-establishments.index'))
        ->assertForbidden();
});

test('unpaid establishments report exports matching rows as csv', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    unpaidEstablishmentReportSchedule([
        'business_name' => 'CSV Unpaid Establishment',
        'owner_name' => 'CSV Owner',
        'application_number' => 'APP-CSV-UNPAID',
        'type' => PermitApplicationType::New,
        'year' => 2026,
        'total_amount_cents' => 54_321,
        'paid_amount_cents' => 12_345,
        'status' => PaymentScheduleStatus::PartiallyPaid,
        'line_of_business' => 'Food Service',
    ]);

    $response = $this->actingAs($user)
        ->get(route('staff.reports.unpaid-establishments.download', [
            'year' => 2026,
        ]))
        ->assertOk()
        ->assertDownload('unpaid-establishments-2026.csv');

    expect($response->streamedContent())
        ->toContain('Application Number')
        ->toContain('APP-CSV-UNPAID')
        ->toContain('CSV Unpaid Establishment')
        ->toContain('Food Service')
        ->toContain('419.76');
});

/**
 * @param  array{
 *     business_name: string,
 *     owner_name: string,
 *     application_number: string,
 *     type: PermitApplicationType,
 *     year: int,
 *     total_amount_cents: int,
 *     paid_amount_cents: int,
 *     status: PaymentScheduleStatus,
 *     line_of_business: string,
 *     barangay?: string
 * }  $attributes
 */
function unpaidEstablishmentReportSchedule(array $attributes): PaymentSchedule
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
            'total_amount_cents' => $attributes['total_amount_cents'],
        ]);
    $schedule = PaymentSchedule::factory()
        ->for($permitApplication)
        ->for($assessment)
        ->create([
            'status' => $attributes['status'],
            'total_amount_cents' => $attributes['total_amount_cents'],
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

function paidEstablishmentExcludedFromUnpaidReport(): PaymentSchedule
{
    $permitApplication = PermitApplication::factory()->create([
        'application_number' => 'APP-PAID-EXCLUDED',
        'application_year' => 2026,
    ]);
    $assessment = Assessment::factory()
        ->for($permitApplication)
        ->create([
            'total_amount_cents' => 10_000,
        ]);

    return PaymentSchedule::factory()
        ->for($permitApplication)
        ->for($assessment)
        ->create([
            'status' => PaymentScheduleStatus::Paid,
            'total_amount_cents' => 10_000,
            'paid_amount_cents' => 10_000,
        ]);
}
