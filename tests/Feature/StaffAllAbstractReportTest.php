<?php

use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('all abstract exposes its complete treasury contract while refusing partial output', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);

    $this->actingAs($user)
        ->get(route('staff.reports.all-abstract.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/AllAbstract')
            ->where('status', 'blocked')
            ->where('can_generate', false)
            ->where('can_export', false)
            ->where('row_count', 0)
            ->where('report.key', 'all_abstract_of_collection')
            ->where('base_columns', fn ($columns): bool => $columns->count() === 5)
            ->where('coverage', fn ($coverage): bool => $coverage->count() === 5)
            ->where('coverage.0.status', 'available')
            ->where('coverage.1.status', 'not_implemented')
            ->where('coverage.2.status', 'not_implemented')
            ->where('coverage.3.status', 'not_implemented')
            ->where('reconciliation_controls', fn ($controls): bool => $controls->count() === 7)
            ->where('reconciliation_controls.5.status', 'not_collected')
            ->where('completeness_boundary.partial_report_may_be_labeled_all', false)
            ->missing('rows.0')
        );

    expect(Route::has('staff.reports.all-abstract.download'))->toBeFalse();
});

test('all abstract does not mislabel a real permit receipt as complete treasury coverage', function () {
    $collection = TreasuryCollection::factory()->create([
        'status' => TreasuryCollectionStatus::Receipted,
        'received_at' => '2026-08-16 09:30:00',
    ]);
    Receipt::factory()
        ->for($collection)
        ->for($collection->paymentSchedule)
        ->for($collection->permitApplication)
        ->for($collection->assessment)
        ->create([
            'receipt_number' => 'ABSTRACT-PERMIT-ONLY-001',
            'status' => ReceiptStatus::Issued,
        ]);
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);

    $this->actingAs($user)
        ->get(route('staff.reports.all-abstract.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('row_count', 0)
            ->where('can_generate', false)
            ->where('completeness_boundary.all_sources_available', false)
            ->where('completeness_boundary.partial_report_may_be_labeled_all', false)
            ->missing('rows.0')
        );
});

test('all abstract requires report permission', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReceipts]);

    $this->actingAs($user)
        ->get(route('staff.reports.all-abstract.index'))
        ->assertForbidden();
});
