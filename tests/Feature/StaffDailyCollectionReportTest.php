<?php

use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Inertia\Testing\AssertableInertia as Assert;

test('staff users with report permission can view daily collections from receipted permit collections', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    $collection = TreasuryCollection::factory()->create([
        'status' => TreasuryCollectionStatus::Receipted,
        'amount_cents' => 42_500,
        'payer_name' => 'Daily Report Payer',
        'reference_number' => 'DAILY-CASH-001',
        'received_at' => '2026-08-14 09:30:00',
    ]);
    $receipt = Receipt::factory()
        ->for($collection)
        ->for($collection->paymentSchedule)
        ->for($collection->permitApplication)
        ->for($collection->assessment)
        ->create([
            'status' => ReceiptStatus::Issued,
            'receipt_number' => 'DAILY-OR-001',
            'amount_cents' => 42_500,
            'issued_at' => '2026-08-14 09:35:00',
        ]);

    $this->actingAs($user)
        ->get(route('staff.reports.daily-collections.index', [
            'date_from' => '2026-08-14',
            'date_to' => '2026-08-14',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/DailyCollections')
            ->where('summary.row_count', 1)
            ->where('summary.total_amount_cents', 42_500)
            ->where('summary.cash_amount_cents', 42_500)
            ->where('summary.manual_receipt_count', 1)
            ->where('summary.date_basis', 'collection_received_at')
            ->where('rows.0.collection_id', $collection->id)
            ->where('rows.0.receipt_id', $receipt->id)
            ->where('rows.0.receipt_number', 'DAILY-OR-001')
            ->where('rows.0.amount_cents', 42_500)
            ->where('rows.0.payer_name', 'Daily Report Payer')
            ->where('rows.0.collection_status', TreasuryCollectionStatus::Receipted->value)
            ->where('rows.0.receipt_status', ReceiptStatus::Issued->value)
        );
});

test('daily collection report filters by received date and excludes unreceipted collections', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    $matching = TreasuryCollection::factory()->create([
        'status' => TreasuryCollectionStatus::Receipted,
        'amount_cents' => 20_000,
        'received_at' => '2026-08-14 10:00:00',
    ]);
    Receipt::factory()
        ->for($matching)
        ->for($matching->paymentSchedule)
        ->for($matching->permitApplication)
        ->for($matching->assessment)
        ->create([
            'status' => ReceiptStatus::Issued,
            'receipt_number' => 'DAILY-OR-INCLUDED',
            'amount_cents' => 20_000,
        ]);

    $outsideRange = TreasuryCollection::factory()->create([
        'status' => TreasuryCollectionStatus::Receipted,
        'amount_cents' => 30_000,
        'received_at' => '2026-08-13 10:00:00',
    ]);
    Receipt::factory()
        ->for($outsideRange)
        ->for($outsideRange->paymentSchedule)
        ->for($outsideRange->permitApplication)
        ->for($outsideRange->assessment)
        ->create([
            'status' => ReceiptStatus::Issued,
            'receipt_number' => 'DAILY-OR-OUTSIDE',
            'amount_cents' => 30_000,
        ]);

    TreasuryCollection::factory()->create([
        'status' => TreasuryCollectionStatus::PendingReceipt,
        'amount_cents' => 40_000,
        'received_at' => '2026-08-14 11:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.daily-collections.index', [
            'date_from' => '2026-08-14',
            'date_to' => '2026-08-14',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.row_count', 1)
            ->where('summary.total_amount_cents', 20_000)
            ->where('rows.0.receipt_number', 'DAILY-OR-INCLUDED')
            ->missing('rows.1')
        );
});

test('staff users without report permission cannot view daily collections', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReceipts,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.daily-collections.index'))
        ->assertForbidden();
});

test('daily collection report exports matching rows as csv', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    $collection = TreasuryCollection::factory()->create([
        'status' => TreasuryCollectionStatus::Receipted,
        'amount_cents' => 12_345,
        'payer_name' => 'CSV Payer',
        'reference_number' => 'CSV-CASH-001',
        'received_at' => '2026-08-14 12:15:00',
    ]);
    Receipt::factory()
        ->for($collection)
        ->for($collection->paymentSchedule)
        ->for($collection->permitApplication)
        ->for($collection->assessment)
        ->create([
            'status' => ReceiptStatus::Issued,
            'receipt_number' => 'DAILY-OR-CSV',
            'amount_cents' => 12_345,
            'issued_at' => '2026-08-14 12:20:00',
        ]);

    $response = $this->actingAs($user)
        ->get(route('staff.reports.daily-collections.download', [
            'date_from' => '2026-08-14',
            'date_to' => '2026-08-14',
        ]))
        ->assertOk()
        ->assertDownload('daily-collections-2026-08-14-to-2026-08-14.csv');

    expect($response->streamedContent())
        ->toContain('Receipt Number')
        ->toContain('DAILY-OR-CSV')
        ->toContain('CSV Payer')
        ->toContain('123.45');
});
