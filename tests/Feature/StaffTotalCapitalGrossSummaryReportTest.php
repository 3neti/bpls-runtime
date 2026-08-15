<?php

use App\Enums\PaymentScheduleStatus;
use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Inertia\Testing\AssertableInertia as Assert;

test('capital gross summary qualifies by collection date and reports lifetime figures once per application', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);
    $record = capitalGrossSummaryRecord([
        'owner_name' => 'Alpha Owner',
        'business_name' => 'Alpha Trading',
        'application_number' => 'APP-CAPITAL-GROSS-001',
        'schedule_total_cents' => 100_000,
        'lines' => [
            ['capital_cents' => 120_000, 'gross_cents' => 300_000],
            ['capital_cents' => 80_050, 'gross_cents' => 200_075],
        ],
        'collections' => [
            ['amount_cents' => 30_000, 'received_at' => '2026-08-15 09:00:00', 'receipt_number' => 'OR-CAPITAL-001'],
            ['amount_cents' => 20_000, 'received_at' => '2026-08-16 10:00:00', 'receipt_number' => 'OR-CAPITAL-002'],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.total-capital-gross-summary.index', [
            'date_from' => '2026-08-15',
            'date_to' => '2026-08-15',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/TotalCapitalGrossSummary')
            ->where('summary.row_count', 1)
            ->where('summary.capital_investment_cents', 200_050)
            ->where('summary.gross_sales_cents', 500_075)
            ->where('summary.payment_amount_cents', 50_000)
            ->where('summary.remaining_balance_cents', 50_000)
            ->where('summary.partial_count', 1)
            ->where('summary.financial_scope', 'lifetime_issued_receipted_collections')
            ->where('rows.0.application_id', $record['application']->id)
            ->where('rows.0.owner_name', 'Alpha Owner')
            ->where('rows.0.business_name', 'Alpha Trading')
            ->where('rows.0.capital_investment_cents', 200_050)
            ->where('rows.0.gross_sales_cents', 500_075)
            ->where('rows.0.latest_receipt_number', 'OR-CAPITAL-002')
            ->where('rows.0.latest_payment_date', '2026-08-16')
            ->where('rows.0.payment_amount_cents', 50_000)
            ->where('rows.0.remaining_balance_cents', 50_000)
            ->where('rows.0.payment_status', 'Partial')
            ->missing('rows.1')
        );
});

test('capital gross summary excludes applications without issued receipted evidence in range', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);
    capitalGrossSummaryRecord([
        'owner_name' => 'Pending Owner',
        'business_name' => 'Pending Receipt Store',
        'application_number' => 'APP-CAPITAL-PENDING',
        'schedule_total_cents' => 40_000,
        'lines' => [['capital_cents' => 20_000, 'gross_cents' => 30_000]],
        'collections' => [[
            'amount_cents' => 40_000,
            'received_at' => '2026-08-15 09:00:00',
            'receipt_number' => null,
            'collection_status' => TreasuryCollectionStatus::PendingReceipt,
        ]],
    ]);
    capitalGrossSummaryRecord([
        'owner_name' => 'Voided Owner',
        'business_name' => 'Voided Receipt Store',
        'application_number' => 'APP-CAPITAL-VOIDED',
        'schedule_total_cents' => 50_000,
        'lines' => [['capital_cents' => 25_000, 'gross_cents' => 35_000]],
        'collections' => [[
            'amount_cents' => 50_000,
            'received_at' => '2026-08-15 10:00:00',
            'receipt_number' => 'OR-CAPITAL-VOIDED',
            'receipt_status' => ReceiptStatus::Voided,
        ]],
    ]);
    capitalGrossSummaryRecord([
        'owner_name' => 'Outside Owner',
        'business_name' => 'Outside Range Store',
        'application_number' => 'APP-CAPITAL-OUTSIDE',
        'schedule_total_cents' => 60_000,
        'lines' => [['capital_cents' => 30_000, 'gross_cents' => 40_000]],
        'collections' => [[
            'amount_cents' => 60_000,
            'received_at' => '2026-08-14 23:59:59',
            'receipt_number' => 'OR-CAPITAL-OUTSIDE',
        ]],
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.total-capital-gross-summary.index', [
            'date_from' => '2026-08-15',
            'date_to' => '2026-08-15',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.row_count', 0)
            ->where('summary.payment_amount_cents', 0)
            ->missing('rows.0')
        );
});

test('capital gross summary marks fully collected persisted liability as completed', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);
    capitalGrossSummaryRecord([
        'owner_name' => 'Complete Owner',
        'business_name' => 'Complete Store',
        'application_number' => 'APP-CAPITAL-COMPLETE',
        'schedule_total_cents' => 75_000,
        'lines' => [['capital_cents' => 100_000, 'gross_cents' => 150_000]],
        'collections' => [[
            'amount_cents' => 75_000,
            'received_at' => '2026-08-15 11:00:00',
            'receipt_number' => 'OR-CAPITAL-COMPLETE',
        ]],
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.total-capital-gross-summary.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.completed_count', 1)
            ->where('summary.partial_count', 0)
            ->where('rows.0.remaining_balance_cents', 0)
            ->where('rows.0.payment_status', 'Completed')
        );
});

test('capital gross summary rejects an inverted date range', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);

    $this->actingAs($user)
        ->get(route('staff.reports.total-capital-gross-summary.index', [
            'date_from' => '2026-08-16',
            'date_to' => '2026-08-15',
        ]))
        ->assertSessionHasErrors('date_to');
});

test('capital gross summary requires report permission', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReceipts]);

    $this->actingAs($user)
        ->get(route('staff.reports.total-capital-gross-summary.index'))
        ->assertForbidden();
});

test('capital gross summary exports full rows and totals as csv', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);
    capitalGrossSummaryRecord([
        'owner_name' => 'CSV Owner',
        'business_name' => 'CSV Capital Store',
        'application_number' => 'APP-CAPITAL-CSV',
        'schedule_total_cents' => 65_432,
        'lines' => [['capital_cents' => 125_050, 'gross_cents' => 225_075]],
        'collections' => [[
            'amount_cents' => 65_432,
            'received_at' => '2026-08-15 09:00:00',
            'receipt_number' => 'OR-CAPITAL-CSV',
        ]],
    ]);

    $response = $this->actingAs($user)
        ->get(route('staff.reports.total-capital-gross-summary.download', [
            'date_from' => '2026-08-15',
            'date_to' => '2026-08-15',
        ]))
        ->assertOk()
        ->assertDownload('total-capital-gross-summary-2026-08-15-to-2026-08-15.csv');

    expect($response->streamedContent())
        ->toContain('Owner Name')
        ->toContain('CSV Owner')
        ->toContain('CSV Capital Store')
        ->toContain('1250.50')
        ->toContain('2250.75')
        ->toContain('OR-CAPITAL-CSV')
        ->toContain('TOTAL');
});

/**
 * @param  array{
 *     owner_name: string,
 *     business_name: string,
 *     application_number: string,
 *     schedule_total_cents: int,
 *     lines: array<int, array{capital_cents: int, gross_cents: int}>,
 *     collections: array<int, array{
 *         amount_cents: int,
 *         received_at: string,
 *         receipt_number: string|null,
 *         collection_status?: TreasuryCollectionStatus,
 *         receipt_status?: ReceiptStatus
 *     }>
 * }  $attributes
 * @return array{application: PermitApplication, schedule: PaymentSchedule}
 */
function capitalGrossSummaryRecord(array $attributes): array
{
    $owner = BusinessOwner::factory()->create(['name' => $attributes['owner_name']]);
    $business = Business::factory()->for($owner, 'owner')->create(['name' => $attributes['business_name']]);
    $application = PermitApplication::factory()->for($business)->create([
        'application_number' => $attributes['application_number'],
        'application_year' => 2026,
    ]);

    foreach ($attributes['lines'] as $line) {
        PermitApplicationLine::factory()->for($application)->create([
            'capital_investment_cents' => $line['capital_cents'],
            'declared_gross_sales_cents' => $line['gross_cents'],
        ]);
    }

    $assessment = Assessment::factory()->for($application)->create([
        'total_amount_cents' => $attributes['schedule_total_cents'],
    ]);
    $schedule = PaymentSchedule::factory()->for($application)->for($assessment)->create([
        'status' => PaymentScheduleStatus::PartiallyPaid,
        'total_amount_cents' => $attributes['schedule_total_cents'],
        'paid_amount_cents' => collect($attributes['collections'])->sum('amount_cents'),
    ]);

    foreach ($attributes['collections'] as $collectionAttributes) {
        $collection = TreasuryCollection::factory()
            ->for($schedule, 'paymentSchedule')
            ->for($application)
            ->for($assessment)
            ->create([
                'status' => $collectionAttributes['collection_status'] ?? TreasuryCollectionStatus::Receipted,
                'amount_cents' => $collectionAttributes['amount_cents'],
                'received_at' => $collectionAttributes['received_at'],
            ]);

        if ($collectionAttributes['receipt_number'] !== null) {
            Receipt::factory()
                ->for($collection, 'treasuryCollection')
                ->for($schedule, 'paymentSchedule')
                ->for($application)
                ->for($assessment)
                ->create([
                    'status' => $collectionAttributes['receipt_status'] ?? ReceiptStatus::Issued,
                    'receipt_number' => $collectionAttributes['receipt_number'],
                    'amount_cents' => $collectionAttributes['amount_cents'],
                    'issued_at' => $collectionAttributes['received_at'],
                ]);
        }
    }

    return ['application' => $application, 'schedule' => $schedule];
}
