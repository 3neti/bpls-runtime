<?php

use App\Enums\PaymentScheduleStatus;
use App\Enums\StakeholderPreviewPersona;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\CollectionAllocation;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLine;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{PaymentSchedule, TreasuryCollection} */
function receiptCoverageSchedule(int $issued): array
{
    $application = PermitApplication::factory()->create();
    $assessment = Assessment::factory()->for($application)->create(['total_amount_cents' => 397500]);
    $schedule = PaymentSchedule::factory()->for($application, 'permitApplication')->for($assessment)->create([
        'sequence' => 1, 'total_amount_cents' => 397500, 'paid_amount_cents' => 397500, 'status' => PaymentScheduleStatus::Paid,
    ]);
    $collection = TreasuryCollection::factory()->create([
        'payment_schedule_id' => $schedule->id, 'permit_application_id' => $application->id,
        'assessment_id' => $assessment->id, 'amount_cents' => 397500,
    ]);
    foreach ([15000, 100000, 250000, 10000, 12500, 10000] as $index => $amount) {
        $line = PaymentScheduleLine::factory()->for($schedule)->create(['amount_cents' => $amount, 'paid_amount_cents' => $amount]);
        $receipt = $index < $issued ? Receipt::factory()->create([
            'treasury_collection_id' => $collection->id, 'payment_schedule_id' => $schedule->id,
            'permit_application_id' => $application->id, 'assessment_id' => $assessment->id,
            'receipt_group_key' => 'group_'.$index, 'receipt_group_label' => 'Group '.$index, 'amount_cents' => $amount,
        ]) : null;
        CollectionAllocation::factory()->create([
            'treasury_collection_id' => $collection->id, 'payment_schedule_line_id' => $line->id,
            'receipt_group_key' => 'group_'.$index, 'receipt_group_label' => 'Group '.$index,
            'amount_cents' => $amount, 'receipt_id' => $receipt?->id,
        ]);
    }

    return [$schedule, $collection];
}

test('Cashier sees exact schedule receipt coverage without Application access or financial writes', function (int $issued, int $receipted, string $status) {
    [$schedule, $collection] = receiptCoverageSchedule($issued);
    $cashier = userWithPermissions(StakeholderPreviewPersona::Cashier->permissions());
    $before = [$schedule->fresh()->getRawOriginal(), $collection->fresh()->getRawOriginal(), Receipt::all()->toArray(), CollectionAllocation::all()->toArray()];
    $this->actingAs($cashier)->get(route('staff.payment-schedules.show', $schedule))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.view_permit_application', false)
            ->where('receiptReconciliation.required_receipt_group_count', 6)
            ->where('receiptReconciliation.issued_receipt_group_count', $issued)
            ->where('receiptReconciliation.total_receipted_cents', $receipted)
            ->where('receiptReconciliation.unreceipted_amount_cents', 397500 - $receipted)
            ->where('receiptReconciliation.status', $status)
            ->where('receiptReconciliation.totals_reconciled', $issued === 6));
    $this->get(route('staff.permit-applications.show', $schedule->permit_application_id))->assertForbidden();
    $this->get(route('staff.permit-applications.assessments.show', $schedule->assessment_id))->assertForbidden();
    expect([$schedule->fresh()->getRawOriginal(), $collection->fresh()->getRawOriginal(), Receipt::all()->toArray(), CollectionAllocation::all()->toArray()])->toBe($before);
})->with([[0, 0, 'pending_receipts'], [1, 15000, 'pending_receipts'], [6, 397500, 'fully_reconciled']]);

test('receipt coverage remains bound to the requested schedule when a newer schedule exists', function () {
    [$older] = receiptCoverageSchedule(6);
    $assessment = Assessment::factory()->create(['permit_application_id' => $older->permit_application_id, 'sequence' => 2]);
    $newer = PaymentSchedule::factory()->create([
        'permit_application_id' => $older->permit_application_id, 'assessment_id' => $assessment->id,
        'sequence' => 2, 'total_amount_cents' => 10000, 'paid_amount_cents' => 0,
    ]);
    $cashier = userWithPermissions(StakeholderPreviewPersona::Cashier->permissions());
    $this->actingAs($cashier)->get(route('staff.payment-schedules.show', $older))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('receiptReconciliation.total_receipted_cents', 397500)->where('receiptReconciliation.status', 'fully_reconciled'));
    $this->get(route('staff.payment-schedules.show', $newer))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('receiptReconciliation.total_receipted_cents', 0)->where('receiptReconciliation.status', 'awaiting_collection'));
});

test('schedule and collection access alone do not expose receipts and Application link uses effective permission', function (bool $collections) {
    [$schedule] = receiptCoverageSchedule(6);
    $permissions = [UserPermission::AccessStaff, UserPermission::ViewPaymentSchedules, UserPermission::ViewPermitApplications];
    if ($collections) {
        $permissions[] = UserPermission::ViewCollections;
    }
    $viewer = userWithPermissions($permissions);
    $this->actingAs($viewer)->get(route('staff.payment-schedules.show', $schedule))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('receiptReconciliation', null)->where('can.view_permit_application', true));
})->with([false, true]);

test('Admin runtime override grants the links and receipt summary without stored capability assignments', function () {
    [$schedule] = receiptCoverageSchedule(6);
    $admin = userWithPermissions([], UserRole::Admin);
    expect($admin->getAllPermissions())->toBeEmpty();
    $this->actingAs($admin)->get(route('staff.payment-schedules.show', $schedule))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.view_permit_application', true)->where('receiptReconciliation.status', 'fully_reconciled'));
});
