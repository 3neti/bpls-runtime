<?php

use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Inertia\Testing\AssertableInertia as Assert;

test('staff users with report permission can view paid establishments', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    paidEstablishmentForReport([
        'business_name' => 'Azucena Trading',
        'owner_name' => 'Maria Santos',
        'application_number' => 'APP-PAID-001',
        'type' => PermitApplicationType::New,
        'year' => 2026,
        'paid_amount_cents' => 125_000,
        'receipt_number' => 'OR-PAID-001',
        'line_of_business' => 'Retail Store',
    ]);

    paidEstablishmentForReport([
        'business_name' => 'Beta Services',
        'owner_name' => 'Ben Cruz',
        'application_number' => 'APP-PAID-002',
        'type' => PermitApplicationType::Renewal,
        'year' => 2026,
        'paid_amount_cents' => 75_000,
        'receipt_number' => 'OR-PAID-002',
        'line_of_business' => 'Consulting Services',
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.paid-establishments.index', [
            'year' => 2026,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/PaidEstablishments')
            ->where('summary.row_count', 2)
            ->where('summary.business_count', 2)
            ->where('summary.paid_amount_cents', 200_000)
            ->where('summary.receipted_count', 2)
            ->where('summary.scope', 'Paid permit payment schedules for the selected application year.')
            ->where('rows.0.business_name', 'Azucena Trading')
            ->where('rows.0.application_number', 'APP-PAID-001')
            ->where('rows.0.receipt_number', 'OR-PAID-001')
            ->where('rows.0.line_of_businesses.0', 'Retail Store')
            ->where('rows.1.business_name', 'Beta Services')
        );
});

test('paid establishments report filters by type and search and excludes unpaid schedules', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    paidEstablishmentForReport([
        'business_name' => 'Included Renewal',
        'owner_name' => 'Rene Owner',
        'application_number' => 'APP-INCLUDED-RENEWAL',
        'type' => PermitApplicationType::Renewal,
        'year' => 2026,
        'paid_amount_cents' => 88_000,
        'receipt_number' => 'OR-INCLUDED-RENEWAL',
        'line_of_business' => 'Grocery',
        'barangay' => 'Taway',
    ]);

    paidEstablishmentForReport([
        'business_name' => 'Wrong Type New',
        'owner_name' => 'Nestor Owner',
        'application_number' => 'APP-WRONG-TYPE',
        'type' => PermitApplicationType::New,
        'year' => 2026,
        'paid_amount_cents' => 44_000,
        'receipt_number' => 'OR-WRONG-TYPE',
        'line_of_business' => 'Hardware',
    ]);

    paidEstablishmentForReport([
        'business_name' => 'Wrong Year Renewal',
        'owner_name' => 'Yna Owner',
        'application_number' => 'APP-WRONG-YEAR',
        'type' => PermitApplicationType::Renewal,
        'year' => 2025,
        'paid_amount_cents' => 33_000,
        'receipt_number' => 'OR-WRONG-YEAR',
        'line_of_business' => 'Pharmacy',
    ]);

    unpaidEstablishmentForReport();

    $this->actingAs($user)
        ->get(route('staff.reports.paid-establishments.index', [
            'year' => 2026,
            'type' => PermitApplicationType::Renewal->value,
            'q' => 'taway',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.row_count', 1)
            ->where('summary.paid_amount_cents', 88_000)
            ->where('filters.type', PermitApplicationType::Renewal->value)
            ->where('filters.q', 'taway')
            ->where('rows.0.business_name', 'Included Renewal')
            ->missing('rows.1')
        );
});

test('staff users without report permission cannot view paid establishments', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReceipts,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.paid-establishments.index'))
        ->assertForbidden();
});

test('paid establishments report exports matching rows as csv', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    paidEstablishmentForReport([
        'business_name' => 'CSV Paid Establishment',
        'owner_name' => 'CSV Owner',
        'application_number' => 'APP-CSV-PAID',
        'type' => PermitApplicationType::New,
        'year' => 2026,
        'paid_amount_cents' => 54_321,
        'receipt_number' => 'OR-CSV-PAID',
        'line_of_business' => 'Food Service',
    ]);

    $response = $this->actingAs($user)
        ->get(route('staff.reports.paid-establishments.download', [
            'year' => 2026,
        ]))
        ->assertOk()
        ->assertDownload('paid-establishments-2026.csv');

    expect($response->streamedContent())
        ->toContain('Application Number')
        ->toContain('APP-CSV-PAID')
        ->toContain('CSV Paid Establishment')
        ->toContain('Food Service')
        ->toContain('543.21');
});

/**
 * @param  array{
 *     business_name: string,
 *     owner_name: string,
 *     application_number: string,
 *     type: PermitApplicationType,
 *     year: int,
 *     paid_amount_cents: int,
 *     receipt_number: string,
 *     line_of_business: string,
 *     barangay?: string
 * }  $attributes
 */
function paidEstablishmentForReport(array $attributes): PaymentSchedule
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
            'total_amount_cents' => $attributes['paid_amount_cents'],
        ]);
    $schedule = PaymentSchedule::factory()
        ->for($permitApplication)
        ->for($assessment)
        ->create([
            'status' => PaymentScheduleStatus::Paid,
            'total_amount_cents' => $attributes['paid_amount_cents'],
            'paid_amount_cents' => $attributes['paid_amount_cents'],
        ]);
    $lineOfBusiness = LineOfBusiness::factory()->create([
        'name' => $attributes['line_of_business'],
    ]);
    PermitApplicationLine::factory()
        ->for($permitApplication)
        ->for($lineOfBusiness)
        ->create();
    $collection = TreasuryCollection::factory()
        ->for($schedule, 'paymentSchedule')
        ->for($permitApplication)
        ->for($assessment)
        ->create([
            'status' => TreasuryCollectionStatus::Receipted,
            'amount_cents' => $attributes['paid_amount_cents'],
        ]);
    Receipt::factory()
        ->for($collection, 'treasuryCollection')
        ->for($schedule, 'paymentSchedule')
        ->for($permitApplication)
        ->for($assessment)
        ->create([
            'status' => ReceiptStatus::Issued,
            'receipt_number' => $attributes['receipt_number'],
            'amount_cents' => $attributes['paid_amount_cents'],
        ]);

    return $schedule;
}

function unpaidEstablishmentForReport(): PaymentSchedule
{
    $permitApplication = PermitApplication::factory()->create([
        'application_number' => 'APP-UNPAID',
        'application_year' => 2026,
    ]);
    $assessment = Assessment::factory()
        ->for($permitApplication)
        ->create();

    return PaymentSchedule::factory()
        ->for($permitApplication)
        ->for($assessment)
        ->create([
            'status' => PaymentScheduleStatus::Pending,
            'total_amount_cents' => 12_000,
            'paid_amount_cents' => 0,
        ]);
}
