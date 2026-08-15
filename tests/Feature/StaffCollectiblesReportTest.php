<?php

use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use Inertia\Testing\AssertableInertia as Assert;

test('staff users can view outstanding balances grouped by application and due date quarter', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    $permitApplication = collectibleApplication([
        'business_name' => 'Quarterly Collectible Store',
        'owner_name' => 'Quarterly Owner',
        'application_number' => 'APP-COLLECTIBLE-001',
        'type' => PermitApplicationType::Renewal,
        'application_year' => 2026,
        'capital_investment_cents' => 500_000,
        'gross_sales_cents' => 2_500_000,
        'schedules' => [
            ['status' => PaymentScheduleStatus::Pending, 'total_amount_cents' => 100_000, 'paid_amount_cents' => 0, 'due_on' => '2026-02-15', 'payment_mode' => 'quarterly'],
            ['status' => PaymentScheduleStatus::PartiallyPaid, 'total_amount_cents' => 80_000, 'paid_amount_cents' => 30_000, 'due_on' => '2026-08-15', 'payment_mode' => 'quarterly'],
            ['status' => PaymentScheduleStatus::Pending, 'total_amount_cents' => 25_000, 'paid_amount_cents' => 0, 'due_on' => null, 'payment_mode' => 'single'],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.collectibles.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/BreakdownOfCollectibles')
            ->where('summary.row_count', 1)
            ->where('summary.schedule_count', 3)
            ->where('summary.q1_amount_cents', 100_000)
            ->where('summary.q2_amount_cents', 0)
            ->where('summary.q3_amount_cents', 50_000)
            ->where('summary.q4_amount_cents', 0)
            ->where('summary.unscheduled_amount_cents', 25_000)
            ->where('summary.total_amount_cents', 175_000)
            ->where('summary.grain', 'one_row_per_permit_application')
            ->where('rows.0.application_id', $permitApplication->id)
            ->where('rows.0.capital_investment_cents', 500_000)
            ->where('rows.0.gross_sales_cents', 2_500_000)
            ->where('rows.0.payment_modes', ['quarterly', 'single'])
            ->where('rows.0.total_amount_cents', 175_000)
        );
});

test('collectibles report uses due year and only falls back to application year for unscheduled balances', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    collectibleApplication([
        'business_name' => 'Included Due Year Store',
        'owner_name' => 'Included Due Owner',
        'application_number' => 'APP-DUE-YEAR',
        'type' => PermitApplicationType::New,
        'application_year' => 2025,
        'schedules' => [
            ['status' => PaymentScheduleStatus::Pending, 'total_amount_cents' => 40_000, 'paid_amount_cents' => 0, 'due_on' => '2026-04-01', 'payment_mode' => 'single'],
        ],
    ]);
    collectibleApplication([
        'business_name' => 'Included Unscheduled Store',
        'owner_name' => 'Included Unscheduled Owner',
        'application_number' => 'APP-UNSCHEDULED-YEAR',
        'type' => PermitApplicationType::New,
        'application_year' => 2026,
        'schedules' => [
            ['status' => PaymentScheduleStatus::Pending, 'total_amount_cents' => 30_000, 'paid_amount_cents' => 0, 'due_on' => null, 'payment_mode' => 'single'],
        ],
    ]);
    collectibleApplication([
        'business_name' => 'Excluded Other Year Store',
        'owner_name' => 'Excluded Owner',
        'application_number' => 'APP-OTHER-YEAR',
        'type' => PermitApplicationType::New,
        'application_year' => 2025,
        'schedules' => [
            ['status' => PaymentScheduleStatus::Pending, 'total_amount_cents' => 90_000, 'paid_amount_cents' => 0, 'due_on' => null, 'payment_mode' => 'single'],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.collectibles.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.row_count', 2)
            ->where('summary.q2_amount_cents', 40_000)
            ->where('summary.unscheduled_amount_cents', 30_000)
            ->where('summary.total_amount_cents', 70_000)
        );
});

test('collectibles report filters applications and excludes paid and voided schedules', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    collectibleApplication([
        'business_name' => 'Included Renewal Collectible',
        'owner_name' => 'Matched Owner',
        'application_number' => 'APP-MATCHED-COLLECTIBLE',
        'type' => PermitApplicationType::Renewal,
        'application_year' => 2026,
        'schedules' => [
            ['status' => PaymentScheduleStatus::Pending, 'total_amount_cents' => 60_000, 'paid_amount_cents' => 0, 'due_on' => null, 'payment_mode' => 'single'],
            ['status' => PaymentScheduleStatus::Paid, 'total_amount_cents' => 40_000, 'paid_amount_cents' => 40_000, 'due_on' => '2026-02-01', 'payment_mode' => 'single'],
            ['status' => PaymentScheduleStatus::Voided, 'total_amount_cents' => 50_000, 'paid_amount_cents' => 0, 'due_on' => '2026-03-01', 'payment_mode' => 'single'],
        ],
    ]);
    collectibleApplication([
        'business_name' => 'Wrong Type Collectible',
        'owner_name' => 'Other Owner',
        'application_number' => 'APP-WRONG-TYPE-COLLECTIBLE',
        'type' => PermitApplicationType::New,
        'application_year' => 2026,
        'schedules' => [
            ['status' => PaymentScheduleStatus::Pending, 'total_amount_cents' => 100_000, 'paid_amount_cents' => 0, 'due_on' => null, 'payment_mode' => 'single'],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.collectibles.index', [
            'year' => 2026,
            'type' => PermitApplicationType::Renewal->value,
            'q' => 'matched',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.row_count', 1)
            ->where('summary.schedule_count', 1)
            ->where('summary.total_amount_cents', 60_000)
            ->where('rows.0.application_number', 'APP-MATCHED-COLLECTIBLE')
            ->missing('rows.1')
        );
});

test('collectibles report requires report permission', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReceipts,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.collectibles.index'))
        ->assertForbidden();
});

test('collectibles report exports the complete quarterly and unscheduled breakdown as csv', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);
    collectibleApplication([
        'business_name' => 'CSV Collectible Store',
        'owner_name' => 'CSV Collectible Owner',
        'application_number' => 'APP-CSV-COLLECTIBLE',
        'type' => PermitApplicationType::New,
        'application_year' => 2026,
        'schedules' => [
            ['status' => PaymentScheduleStatus::Pending, 'total_amount_cents' => 54_321, 'paid_amount_cents' => 0, 'due_on' => null, 'payment_mode' => 'single'],
        ],
    ]);

    $response = $this->actingAs($user)
        ->get(route('staff.reports.collectibles.download', ['year' => 2026]))
        ->assertOk()
        ->assertDownload('breakdown-of-collectibles-2026.csv');

    expect($response->streamedContent())
        ->toContain('Owner / Applicant')
        ->toContain('Unscheduled')
        ->toContain('APP-CSV-COLLECTIBLE')
        ->toContain('CSV Collectible Store')
        ->toContain('543.21');
});

/**
 * @param  array{
 *     business_name: string,
 *     owner_name: string,
 *     application_number: string,
 *     type: PermitApplicationType,
 *     application_year: int,
 *     capital_investment_cents?: int,
 *     gross_sales_cents?: int,
 *     schedules: array<int, array{status: PaymentScheduleStatus, total_amount_cents: int, paid_amount_cents: int, due_on: string|null, payment_mode: string}>
 * }  $attributes
 */
function collectibleApplication(array $attributes): PermitApplication
{
    $owner = BusinessOwner::factory()->create(['name' => $attributes['owner_name']]);
    $business = Business::factory()->for($owner, 'owner')->create([
        'name' => $attributes['business_name'],
        'address' => 'Collectible report address',
        'barangay' => 'Poblacion',
    ]);
    $permitApplication = PermitApplication::factory()->for($business)->create([
        'application_number' => $attributes['application_number'],
        'type' => $attributes['type'],
        'application_year' => $attributes['application_year'],
        'submitted_at' => now(),
    ]);
    PermitApplicationLine::factory()->for($permitApplication)->create([
        'capital_investment_cents' => $attributes['capital_investment_cents'] ?? 100_000,
        'declared_gross_sales_cents' => $attributes['gross_sales_cents'] ?? 200_000,
    ]);

    foreach ($attributes['schedules'] as $index => $scheduleAttributes) {
        $assessment = Assessment::factory()->for($permitApplication)->create([
            'sequence' => $index + 1,
            'total_amount_cents' => $scheduleAttributes['total_amount_cents'],
        ]);
        PaymentSchedule::factory()->for($permitApplication)->for($assessment)->create([
            'sequence' => $index + 1,
            'status' => $scheduleAttributes['status'],
            'payment_mode' => $scheduleAttributes['payment_mode'],
            'due_on' => $scheduleAttributes['due_on'],
            'total_amount_cents' => $scheduleAttributes['total_amount_cents'],
            'paid_amount_cents' => $scheduleAttributes['paid_amount_cents'],
        ]);
    }

    return $permitApplication;
}
