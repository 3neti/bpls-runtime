<?php

use App\Enums\FeeRuleCategory;
use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Models\CollectionAllocation;
use App\Models\PaymentScheduleLine;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Inertia\Testing\AssertableInertia as Assert;

test('staff users with report permission can view collections grouped by revenue source', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    $collection = receiptedCollectionForRevenueReport('2026-08-14 09:30:00');
    $businessTaxLine = PaymentScheduleLine::factory()
        ->for($collection->paymentSchedule)
        ->create([
            'code' => 'BUSINESS-TAX',
            'name' => 'Business Tax',
            'category' => FeeRuleCategory::Tax,
            'amount_cents' => 80_000,
        ]);
    $permitFeeLine = PaymentScheduleLine::factory()
        ->for($collection->paymentSchedule)
        ->create([
            'code' => 'PERMIT-FEE',
            'name' => 'Permit Fee',
            'category' => FeeRuleCategory::Fee,
            'amount_cents' => 20_000,
        ]);

    CollectionAllocation::factory()->for($collection)->for($businessTaxLine)->create([
        'amount_cents' => 80_000,
    ]);
    CollectionAllocation::factory()->for($collection)->for($permitFeeLine)->create([
        'amount_cents' => 20_000,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.revenue-sources.index', [
            'date_from' => '2026-08-14',
            'date_to' => '2026-08-14',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/RevenueSources')
            ->where('summary.source_count', 2)
            ->where('summary.allocation_count', 2)
            ->where('summary.total_amount_cents', 100_000)
            ->where('summary.date_basis', 'collection_received_at')
            ->where('rows.0.code', 'PERMIT-FEE')
            ->where('rows.0.amount_cents', 20_000)
            ->where('rows.1.code', 'BUSINESS-TAX')
            ->where('rows.1.amount_cents', 80_000)
        );
});

test('collections by revenue source report filters by category and excludes unsafe states', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    $included = receiptedCollectionForRevenueReport('2026-08-14 10:00:00');
    $taxLine = PaymentScheduleLine::factory()
        ->for($included->paymentSchedule)
        ->create([
            'code' => 'INCLUDED-TAX',
            'name' => 'Included Tax',
            'category' => FeeRuleCategory::Tax,
        ]);
    CollectionAllocation::factory()->for($included)->for($taxLine)->create([
        'amount_cents' => 50_000,
    ]);

    $feeLine = PaymentScheduleLine::factory()
        ->for($included->paymentSchedule)
        ->create([
            'code' => 'EXCLUDED-FEE',
            'name' => 'Excluded Fee',
            'category' => FeeRuleCategory::Fee,
        ]);
    CollectionAllocation::factory()->for($included)->for($feeLine)->create([
        'amount_cents' => 10_000,
    ]);

    $pendingCollection = TreasuryCollection::factory()->create([
        'status' => TreasuryCollectionStatus::PendingReceipt,
        'received_at' => '2026-08-14 11:00:00',
    ]);
    $pendingLine = PaymentScheduleLine::factory()
        ->for($pendingCollection->paymentSchedule)
        ->create([
            'code' => 'PENDING-TAX',
            'category' => FeeRuleCategory::Tax,
        ]);
    CollectionAllocation::factory()->for($pendingCollection)->for($pendingLine)->create([
        'amount_cents' => 90_000,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.revenue-sources.index', [
            'date_from' => '2026-08-14',
            'date_to' => '2026-08-14',
            'category' => FeeRuleCategory::Tax->value,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.source_count', 1)
            ->where('summary.total_amount_cents', 50_000)
            ->where('filters.category', FeeRuleCategory::Tax->value)
            ->where('rows.0.code', 'INCLUDED-TAX')
            ->missing('rows.1')
        );
});

test('staff users without report permission cannot view collections by revenue source', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReceipts,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.revenue-sources.index'))
        ->assertForbidden();
});

test('collections by revenue source report exports matching groups as csv', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    $collection = receiptedCollectionForRevenueReport('2026-08-14 12:15:00');
    $line = PaymentScheduleLine::factory()
        ->for($collection->paymentSchedule)
        ->create([
            'code' => 'CSV-SOURCE',
            'name' => 'CSV Source',
            'category' => FeeRuleCategory::Fee,
        ]);
    CollectionAllocation::factory()->for($collection)->for($line)->create([
        'amount_cents' => 12_345,
    ]);

    $response = $this->actingAs($user)
        ->get(route('staff.reports.revenue-sources.download', [
            'date_from' => '2026-08-14',
            'date_to' => '2026-08-14',
        ]))
        ->assertOk()
        ->assertDownload('collections-by-revenue-source-2026-08-14-to-2026-08-14.csv');

    expect($response->streamedContent())
        ->toContain('Source Code')
        ->toContain('CSV-SOURCE')
        ->toContain('CSV Source')
        ->toContain('123.45');
});

function receiptedCollectionForRevenueReport(string $receivedAt): TreasuryCollection
{
    $collection = TreasuryCollection::factory()->create([
        'status' => TreasuryCollectionStatus::Receipted,
        'received_at' => $receivedAt,
    ]);

    Receipt::factory()
        ->for($collection)
        ->for($collection->paymentSchedule)
        ->for($collection->permitApplication)
        ->for($collection->assessment)
        ->create([
            'status' => ReceiptStatus::Issued,
        ]);

    return $collection;
}
