<?php

use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Inertia\Testing\AssertableInertia as Assert;

test('staff users with issue receipt permission can issue a manual receipt for a pending collection', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::IssueReceipts,
    ]);

    $collection = TreasuryCollection::factory()->create([
        'status' => TreasuryCollectionStatus::PendingReceipt,
        'amount_cents' => 12_500,
    ]);

    $this->actingAs($user)
        ->post(route('staff.collections.receipt.store', $collection), [
            'receipt_number' => 'OR-000001',
        ])
        ->assertRedirect(route('staff.payment-schedules.show', $collection->payment_schedule_id));

    $receipt = Receipt::query()->sole();

    expect($receipt->treasury_collection_id)->toBe($collection->id)
        ->and($receipt->issued_by_id)->toBe($user->id)
        ->and($receipt->status)->toBe(ReceiptStatus::Issued)
        ->and($receipt->numbering_authority)->toBe('manual')
        ->and($receipt->receipt_number)->toBe('OR-000001')
        ->and($receipt->amount_cents)->toBe(12_500)
        ->and($receipt->source_snapshot['policy']['numbering_mode'])->toBe('manual')
        ->and($receipt->source_snapshot['policy']['note'])->toContain('Automatic receipt numbering authority');

    expect($collection->refresh()->status)->toBe(TreasuryCollectionStatus::Receipted);
});

test('staff users without issue receipt permission cannot issue receipts', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewCollections,
    ]);

    $collection = TreasuryCollection::factory()->create([
        'status' => TreasuryCollectionStatus::PendingReceipt,
    ]);

    $this->actingAs($user)
        ->post(route('staff.collections.receipt.store', $collection), [
            'receipt_number' => 'OR-000002',
        ])
        ->assertForbidden();

    expect(Receipt::query()->count())->toBe(0)
        ->and($collection->refresh()->status)->toBe(TreasuryCollectionStatus::PendingReceipt);
});

test('manual receipt numbers are unique within the manual numbering authority', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::IssueReceipts,
    ]);

    Receipt::factory()->create([
        'numbering_authority' => 'manual',
        'receipt_number' => 'OR-000003',
    ]);

    $collection = TreasuryCollection::factory()->create([
        'status' => TreasuryCollectionStatus::PendingReceipt,
    ]);

    $this->actingAs($user)
        ->from(route('staff.payment-schedules.show', $collection->payment_schedule_id))
        ->post(route('staff.collections.receipt.store', $collection), [
            'receipt_number' => 'OR-000003',
        ])
        ->assertRedirect(route('staff.payment-schedules.show', $collection->payment_schedule_id))
        ->assertSessionHasErrors('receipt_number');

    expect($collection->refresh()->status)->toBe(TreasuryCollectionStatus::PendingReceipt);
});

test('receipts cannot be issued twice for the same collection', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::IssueReceipts,
    ]);

    $collection = TreasuryCollection::factory()->create([
        'status' => TreasuryCollectionStatus::Receipted,
    ]);

    Receipt::factory()
        ->for($collection)
        ->for($collection->paymentSchedule)
        ->for($collection->permitApplication)
        ->for($collection->assessment)
        ->create([
            'receipt_number' => 'OR-000004',
        ]);

    $this->actingAs($user)
        ->post(route('staff.collections.receipt.store', $collection), [
            'receipt_number' => 'OR-000005',
        ])
        ->assertRedirect(route('staff.payment-schedules.show', $collection->payment_schedule_id));

    expect(Receipt::query()->count())->toBe(1);
});

test('payment schedule review exposes receipt permissions and receipt history', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPaymentSchedules,
        UserPermission::ViewCollections,
        UserPermission::ViewReceipts,
        UserPermission::IssueReceipts,
    ]);

    $collection = TreasuryCollection::factory()->create([
        'status' => TreasuryCollectionStatus::Receipted,
        'amount_cents' => 42_000,
    ]);

    $receipt = Receipt::factory()
        ->for($collection)
        ->for($collection->paymentSchedule)
        ->for($collection->permitApplication)
        ->for($collection->assessment)
        ->create([
            'receipt_number' => 'OR-000006',
            'amount_cents' => 42_000,
        ]);

    $this->actingAs($user)
        ->get(route('staff.payment-schedules.show', $collection->paymentSchedule))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payment-schedules/Show')
            ->where('paymentSchedule.collections.0.receipt.id', $receipt->id)
            ->where('paymentSchedule.collections.0.receipt.receipt_number', 'OR-000006')
            ->where('can.issue_receipts', true)
            ->where('can.view_receipts', true)
        );
});
