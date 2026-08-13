<?php

use App\Enums\FeeRuleCategory;
use App\Enums\PaymentScheduleLineStatus;
use App\Enums\PaymentScheduleStatus;
use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\CollectionAllocation;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLine;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('staff users with view receipt permission can view receipt detail evidence', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReceipts,
    ]);

    $issuedBy = User::factory()->create(['name' => 'Local Assessor']);
    $receivedBy = User::factory()->create(['name' => 'Cashier One']);
    $owner = BusinessOwner::factory()->create([
        'name' => 'Codex Owner',
        'email' => 'owner@example.test',
        'phone' => '555-0199',
        'address' => 'Owner Address',
    ]);
    $business = Business::factory()->for($owner, 'owner')->create([
        'name' => 'Codex Quantity Store',
        'trade_name' => 'Quantity Store',
        'registration_number' => 'BN-9001',
        'address' => 'Ipil Public Market',
        'barangay' => 'Poblacion',
    ]);
    $permitApplication = PermitApplication::factory()->for($business)->create([
        'application_number' => 'LOCAL-PERMIT',
        'application_year' => 2026,
    ]);
    $assessment = Assessment::factory()->for($permitApplication)->create([
        'sequence' => 1,
        'total_amount_cents' => 42_000,
    ]);
    $paymentSchedule = PaymentSchedule::factory()
        ->for($permitApplication, 'permitApplication')
        ->for($assessment)
        ->create([
            'sequence' => 2,
            'status' => PaymentScheduleStatus::PartiallyPaid,
            'payment_mode' => 'single',
            'total_amount_cents' => 42_000,
            'paid_amount_cents' => 12_500,
        ]);
    $paymentLine = PaymentScheduleLine::factory()->for($paymentSchedule)->create([
        'code' => 'MAYOR-PERMIT',
        'name' => 'Mayor Permit Fee',
        'category' => FeeRuleCategory::Fee,
        'status' => PaymentScheduleLineStatus::PartiallyPaid,
        'amount_cents' => 42_000,
        'paid_amount_cents' => 12_500,
    ]);
    $collection = TreasuryCollection::factory()
        ->for($paymentSchedule)
        ->for($permitApplication)
        ->for($assessment)
        ->for($receivedBy, 'receivedBy')
        ->create([
            'status' => TreasuryCollectionStatus::Receipted,
            'amount_cents' => 12_500,
            'payer_name' => 'Codex Browser Payer',
            'reference_number' => 'CASH-REF',
        ]);

    CollectionAllocation::factory()
        ->for($collection)
        ->for($paymentLine)
        ->create([
            'amount_cents' => 12_500,
        ]);

    $receipt = Receipt::factory()
        ->for($collection)
        ->for($paymentSchedule)
        ->for($permitApplication)
        ->for($assessment)
        ->for($issuedBy, 'issuedBy')
        ->create([
            'status' => ReceiptStatus::Issued,
            'receipt_number' => 'LOCAL-OR-001',
            'amount_cents' => 12_500,
            'remarks' => 'Manual receipt observed during local verification.',
            'source_snapshot' => [
                'policy' => [
                    'numbering_mode' => 'manual',
                    'note' => 'Automatic receipt numbering authority remains unresolved.',
                ],
            ],
        ]);

    $this->actingAs($user)
        ->get(route('staff.receipts.show', $receipt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('receipts/Show')
            ->where('receipt.id', $receipt->id)
            ->where('receipt.receipt_number', 'LOCAL-OR-001')
            ->where('receipt.issued_by', 'Local Assessor')
            ->where('receipt.collection.payer_name', 'Codex Browser Payer')
            ->where('receipt.collection.received_by', 'Cashier One')
            ->where('receipt.payment_schedule.id', $paymentSchedule->id)
            ->where('receipt.permit_application.application_number', 'LOCAL-PERMIT')
            ->where('receipt.business.name', 'Codex Quantity Store')
            ->where('receipt.business.owner.name', 'Codex Owner')
            ->where('receipt.allocations.0.code', 'MAYOR-PERMIT')
            ->where('receipt.allocations.0.amount_cents', 12_500)
            ->where('policyGaps.0', 'Automatic receipt numbering authority remains unresolved.')
            ->where('policyGaps.1', 'This is a print-friendly receipt view, not the final official PDF layout.')
        );
});

test('staff users without view receipt permission cannot view receipt details', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewCollections,
    ]);

    $receipt = Receipt::factory()->create();

    $this->actingAs($user)
        ->get(route('staff.receipts.show', $receipt))
        ->assertForbidden();
});
