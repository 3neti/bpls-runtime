<?php

use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationType;
use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionMethod;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Inertia\Testing\AssertableInertia as Assert;

test('staff users with report permission can view schedule level payment evidence without double counting collections', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    $schedule = paymentSummaryRecord([
        'business_name' => 'Payment Summary Store',
        'owner_name' => 'Payment Summary Owner',
        'application_number' => 'APP-PAYMENT-SUMMARY-001',
        'type' => PermitApplicationType::New,
        'status' => PaymentScheduleStatus::PartiallyPaid,
        'total_amount_cents' => 100_000,
        'paid_amount_cents' => 60_000,
        'collections' => [
            ['amount_cents' => 40_000, 'status' => TreasuryCollectionStatus::Receipted, 'receipt_number' => 'OR-PAYMENT-001'],
            ['amount_cents' => 20_000, 'status' => TreasuryCollectionStatus::PendingReceipt],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.payment-summary.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/PaymentSummary')
            ->where('summary.row_count', 1)
            ->where('summary.partially_paid_count', 1)
            ->where('summary.total_amount_cents', 100_000)
            ->where('summary.paid_amount_cents', 60_000)
            ->where('summary.outstanding_amount_cents', 40_000)
            ->where('summary.receipted_amount_cents', 40_000)
            ->where('summary.pending_receipt_amount_cents', 20_000)
            ->where('summary.grain', 'one_row_per_payment_schedule')
            ->where('rows.0.payment_schedule_id', $schedule->id)
            ->where('rows.0.collection_count', 2)
            ->where('rows.0.collection_amount_cents', 60_000)
            ->where('rows.0.collection_difference_cents', 0)
            ->where('rows.0.receipted_count', 1)
            ->where('rows.0.pending_receipt_count', 1)
            ->where('rows.0.latest_receipt_number', 'OR-PAYMENT-001')
        );
});

test('payment summary filters by schedule status application type and search', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    paymentSummaryRecord([
        'business_name' => 'Included Renewal Pharmacy',
        'owner_name' => 'Included Owner',
        'application_number' => 'APP-INCLUDED-PAYMENT',
        'type' => PermitApplicationType::Renewal,
        'status' => PaymentScheduleStatus::Paid,
        'total_amount_cents' => 75_000,
        'paid_amount_cents' => 75_000,
        'collections' => [
            ['amount_cents' => 75_000, 'status' => TreasuryCollectionStatus::Receipted, 'receipt_number' => 'OR-INCLUDED'],
        ],
    ]);
    paymentSummaryRecord([
        'business_name' => 'Wrong Status Pharmacy',
        'owner_name' => 'Wrong Owner',
        'application_number' => 'APP-WRONG-PAYMENT',
        'type' => PermitApplicationType::Renewal,
        'status' => PaymentScheduleStatus::Pending,
        'total_amount_cents' => 50_000,
        'paid_amount_cents' => 0,
        'collections' => [],
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.payment-summary.index', [
            'year' => 2026,
            'type' => PermitApplicationType::Renewal->value,
            'status' => PaymentScheduleStatus::Paid->value,
            'q' => 'included',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', PaymentScheduleStatus::Paid->value)
            ->where('summary.row_count', 1)
            ->where('summary.paid_count', 1)
            ->where('rows.0.application_number', 'APP-INCLUDED-PAYMENT')
            ->missing('rows.1')
        );
});

test('voided schedules remain visible but do not contribute to active financial totals', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    paymentSummaryRecord([
        'business_name' => 'Voided Payment Store',
        'owner_name' => 'Voided Owner',
        'application_number' => 'APP-VOIDED-PAYMENT',
        'type' => PermitApplicationType::New,
        'status' => PaymentScheduleStatus::Voided,
        'total_amount_cents' => 90_000,
        'paid_amount_cents' => 0,
        'collections' => [],
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.payment-summary.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.row_count', 1)
            ->where('summary.voided_count', 1)
            ->where('summary.total_amount_cents', 0)
            ->where('summary.outstanding_amount_cents', 0)
            ->where('rows.0.is_financially_active', false)
        );
});

test('payment summary requires report permission', function () {
    $unauthorizedUser = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReceipts,
    ]);

    $this->actingAs($unauthorizedUser)
        ->get(route('staff.reports.payment-summary.index'))
        ->assertForbidden();
});

test('payment summary exports matching rows as csv', function () {
    $authorizedUser = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);
    paymentSummaryRecord([
        'business_name' => 'CSV Payment Store',
        'owner_name' => 'CSV Payment Owner',
        'application_number' => 'APP-CSV-PAYMENT',
        'type' => PermitApplicationType::New,
        'status' => PaymentScheduleStatus::Paid,
        'total_amount_cents' => 54_321,
        'paid_amount_cents' => 54_321,
        'collections' => [
            ['amount_cents' => 54_321, 'status' => TreasuryCollectionStatus::Receipted, 'receipt_number' => 'OR-CSV-PAYMENT'],
        ],
    ]);

    $response = $this->actingAs($authorizedUser)
        ->get(route('staff.reports.payment-summary.download', ['year' => 2026]))
        ->assertOk()
        ->assertDownload('payment-summary-2026.csv');

    expect($response->streamedContent())
        ->toContain('Payment Schedule ID')
        ->toContain('APP-CSV-PAYMENT')
        ->toContain('CSV Payment Store')
        ->toContain('543.21')
        ->toContain('OR-CSV-PAYMENT');
});

/**
 * @param  array{
 *     business_name: string,
 *     owner_name: string,
 *     application_number: string,
 *     type: PermitApplicationType,
 *     status: PaymentScheduleStatus,
 *     total_amount_cents: int,
 *     paid_amount_cents: int,
 *     collections: array<int, array{amount_cents: int, status: TreasuryCollectionStatus, receipt_number?: string}>
 * }  $attributes
 */
function paymentSummaryRecord(array $attributes): PaymentSchedule
{
    $owner = BusinessOwner::factory()->create(['name' => $attributes['owner_name']]);
    $business = Business::factory()->for($owner, 'owner')->create(['name' => $attributes['business_name']]);
    $permitApplication = PermitApplication::factory()->for($business)->create([
        'application_number' => $attributes['application_number'],
        'type' => $attributes['type'],
        'application_year' => 2026,
    ]);
    $assessment = Assessment::factory()->for($permitApplication)->create([
        'total_amount_cents' => $attributes['total_amount_cents'],
    ]);
    $schedule = PaymentSchedule::factory()->for($permitApplication)->for($assessment)->create([
        'status' => $attributes['status'],
        'total_amount_cents' => $attributes['total_amount_cents'],
        'paid_amount_cents' => $attributes['paid_amount_cents'],
    ]);

    foreach ($attributes['collections'] as $index => $collectionAttributes) {
        $collection = TreasuryCollection::factory()
            ->for($schedule, 'paymentSchedule')
            ->for($permitApplication)
            ->for($assessment)
            ->create([
                'status' => $collectionAttributes['status'],
                'method' => $index === 0 ? TreasuryCollectionMethod::Cash : TreasuryCollectionMethod::Check,
                'amount_cents' => $collectionAttributes['amount_cents'],
            ]);

        if (isset($collectionAttributes['receipt_number'])) {
            Receipt::factory()
                ->for($collection, 'treasuryCollection')
                ->for($schedule, 'paymentSchedule')
                ->for($permitApplication)
                ->for($assessment)
                ->create([
                    'status' => ReceiptStatus::Issued,
                    'receipt_number' => $collectionAttributes['receipt_number'],
                    'amount_cents' => $collectionAttributes['amount_cents'],
                ]);
        }
    }

    return $schedule;
}
