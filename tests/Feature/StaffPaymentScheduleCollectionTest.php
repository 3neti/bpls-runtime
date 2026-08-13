<?php

use App\Enums\FeeRuleCategory;
use App\Enums\PaymentScheduleLineStatus;
use App\Enums\PaymentScheduleStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLine;
use App\Models\TreasuryCollection;
use Inertia\Testing\AssertableInertia as Assert;

test('staff users with collection permission can record an over the counter collection', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::RecordCollections,
    ]);

    $schedule = PaymentSchedule::factory()->create([
        'total_amount_cents' => 45_000,
        'paid_amount_cents' => 0,
        'status' => PaymentScheduleStatus::Pending,
    ]);

    $businessTax = PaymentScheduleLine::factory()->for($schedule)->create([
        'code' => 'BUSINESS-TAX',
        'category' => FeeRuleCategory::Tax,
        'amount_cents' => 30_000,
        'paid_amount_cents' => 0,
    ]);

    $permitFee = PaymentScheduleLine::factory()->for($schedule)->create([
        'code' => 'MAYORS-PERMIT',
        'category' => FeeRuleCategory::Fee,
        'amount_cents' => 15_000,
        'paid_amount_cents' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('staff.payment-schedules.collections.store', $schedule), [
            'amount_pesos' => '350.00',
            'method' => 'cash',
            'payer_name' => 'Maria Santos',
            'reference_number' => 'OTC-001',
            'remarks' => 'Counter payment',
        ])
        ->assertRedirect(route('staff.payment-schedules.show', $schedule));

    $collection = TreasuryCollection::query()->sole();

    expect($collection->payment_schedule_id)->toBe($schedule->id)
        ->and($collection->received_by_id)->toBe($user->id)
        ->and($collection->status)->toBe(TreasuryCollectionStatus::PendingReceipt)
        ->and($collection->amount_cents)->toBe(35_000)
        ->and($collection->source_snapshot['policy']['status'])->toBe('pending_receipt')
        ->and($collection->source_snapshot['policy']['note'])->toContain('Receipt numbering')
        ->and($collection->allocations()->count())->toBe(2);

    $businessTax->refresh();
    $permitFee->refresh();
    $schedule->refresh();

    expect($businessTax->paid_amount_cents)->toBe(30_000)
        ->and($businessTax->status)->toBe(PaymentScheduleLineStatus::Paid)
        ->and($permitFee->paid_amount_cents)->toBe(5_000)
        ->and($permitFee->status)->toBe(PaymentScheduleLineStatus::PartiallyPaid)
        ->and($schedule->paid_amount_cents)->toBe(35_000)
        ->and($schedule->status)->toBe(PaymentScheduleStatus::PartiallyPaid);
});

test('recording the final collection marks the schedule paid without issuing a receipt', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::RecordCollections,
    ]);

    $schedule = PaymentSchedule::factory()->create([
        'total_amount_cents' => 15_000,
        'paid_amount_cents' => 5_000,
        'status' => PaymentScheduleStatus::PartiallyPaid,
    ]);

    PaymentScheduleLine::factory()->for($schedule)->create([
        'amount_cents' => 15_000,
        'paid_amount_cents' => 5_000,
        'status' => PaymentScheduleLineStatus::PartiallyPaid,
    ]);

    $this->actingAs($user)
        ->post(route('staff.payment-schedules.collections.store', $schedule), [
            'amount_pesos' => '100.00',
            'method' => 'cash',
        ])
        ->assertRedirect(route('staff.payment-schedules.show', $schedule));

    $schedule->refresh();
    $collection = TreasuryCollection::query()->sole();

    expect($schedule->paid_amount_cents)->toBe(15_000)
        ->and($schedule->status)->toBe(PaymentScheduleStatus::Paid)
        ->and($collection->status)->toBe(TreasuryCollectionStatus::PendingReceipt);
});

test('collections are not allocated to waived schedule lines', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::RecordCollections,
    ]);

    $schedule = PaymentSchedule::factory()->create([
        'total_amount_cents' => 10_000,
        'paid_amount_cents' => 0,
        'status' => PaymentScheduleStatus::Pending,
    ]);

    $waivedLine = PaymentScheduleLine::factory()->for($schedule)->create([
        'code' => 'WAIVED-LINE',
        'amount_cents' => 5_000,
        'paid_amount_cents' => 0,
        'status' => PaymentScheduleLineStatus::Waived,
    ]);

    $payableLine = PaymentScheduleLine::factory()->for($schedule)->create([
        'code' => 'PAYABLE-LINE',
        'amount_cents' => 10_000,
        'paid_amount_cents' => 0,
        'status' => PaymentScheduleLineStatus::Pending,
    ]);

    $this->actingAs($user)
        ->post(route('staff.payment-schedules.collections.store', $schedule), [
            'amount_pesos' => '100.00',
            'method' => 'cash',
        ])
        ->assertRedirect(route('staff.payment-schedules.show', $schedule));

    $collection = TreasuryCollection::query()->sole();

    expect($collection->allocations()->count())->toBe(1)
        ->and($collection->allocations()->sole()->payment_schedule_line_id)->toBe($payableLine->id)
        ->and($waivedLine->refresh()->paid_amount_cents)->toBe(0);
});

test('staff users without collection permission cannot record collections', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPaymentSchedules,
    ]);

    $schedule = PaymentSchedule::factory()->create([
        'total_amount_cents' => 10_000,
    ]);

    $this->actingAs($user)
        ->post(route('staff.payment-schedules.collections.store', $schedule), [
            'amount_pesos' => '10.00',
            'method' => 'cash',
        ])
        ->assertForbidden();

    expect(TreasuryCollection::query()->count())->toBe(0);
});

test('collection amount cannot exceed the schedule balance', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::RecordCollections,
    ]);

    $schedule = PaymentSchedule::factory()->create([
        'total_amount_cents' => 10_000,
        'paid_amount_cents' => 2_500,
    ]);

    $this->actingAs($user)
        ->from(route('staff.payment-schedules.show', $schedule))
        ->post(route('staff.payment-schedules.collections.store', $schedule), [
            'amount_pesos' => '100.00',
            'method' => 'cash',
        ])
        ->assertRedirect(route('staff.payment-schedules.show', $schedule))
        ->assertSessionHasErrors('amount_pesos');

    expect(TreasuryCollection::query()->count())->toBe(0);
});

test('payment schedule review exposes collection permissions and history', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPaymentSchedules,
        UserPermission::ViewCollections,
        UserPermission::RecordCollections,
    ]);

    $schedule = PaymentSchedule::factory()->create([
        'total_amount_cents' => 25_000,
        'paid_amount_cents' => 10_000,
        'status' => PaymentScheduleStatus::PartiallyPaid,
    ]);

    $line = PaymentScheduleLine::factory()->for($schedule)->create([
        'code' => 'APPLICATION-FEE',
        'name' => 'Application Fee',
        'amount_cents' => 25_000,
        'paid_amount_cents' => 10_000,
        'status' => PaymentScheduleLineStatus::PartiallyPaid,
    ]);

    $collection = TreasuryCollection::factory()
        ->for($schedule, 'paymentSchedule')
        ->for($schedule->permitApplication, 'permitApplication')
        ->for($schedule->assessment)
        ->create([
            'amount_cents' => 10_000,
            'method' => 'cash',
        ]);

    $collection->allocations()->create([
        'payment_schedule_line_id' => $line->id,
        'amount_cents' => 10_000,
        'source_snapshot' => [],
    ]);

    $this->actingAs($user)
        ->get(route('staff.payment-schedules.show', $schedule))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payment-schedules/Show')
            ->where('paymentSchedule.collections.0.id', $collection->id)
            ->where('paymentSchedule.collections.0.allocations.0.code', 'APPLICATION-FEE')
            ->where('collectionMethods.0.value', 'cash')
            ->where('can.record_collections', true)
            ->where('can.view_collections', true)
        );
});
