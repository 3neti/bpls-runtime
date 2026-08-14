<?php

use App\Actions\DescribeReceiptVoidBoundary;
use App\Actions\RenderReceiptPdf;
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

    $receipt = receiptDocumentFixture();

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
            ->where('receipt.payment_schedule.id', $receipt->payment_schedule_id)
            ->where('receipt.permit_application.application_number', 'LOCAL-PERMIT')
            ->where('receipt.business.name', 'Codex Quantity Store')
            ->where('receipt.business.owner.name', 'Codex Owner')
            ->where('receipt.allocations.0.code', 'MAYOR-PERMIT')
            ->where('receipt.allocations.0.amount_cents', 12_500)
            ->where('receipt.void_boundary.status', 'blocked')
            ->where('receipt.void_boundary.can_void', false)
            ->where('receipt.void_boundary.receipt_status', 'issued')
            ->where('receipt.void_boundary.collection_status', 'receipted')
            ->where('policyGaps.0', 'Automatic receipt numbering authority remains unresolved.')
            ->where('policyGaps.1', 'This is a print-friendly receipt view, not the final official PDF layout.')
            ->where('can.void_receipts', false)
        );
});

test('receipt void boundary descriptor is deterministic and does not authorize voiding', function () {
    $receipt = receiptDocumentFixture();

    $boundary = app(DescribeReceiptVoidBoundary::class)->handle($receipt);

    expect($boundary['reference'])->toStartWith('RVB-'.$receipt->id.'-')
        ->and($boundary['status'])->toBe('blocked')
        ->and($boundary['can_void'])->toBeFalse()
        ->and($boundary['receipt_status'])->toBe('issued')
        ->and($boundary['collection_status'])->toBe('receipted')
        ->and($boundary['policy_note'])->toContain('reconciliation policy remain unresolved')
        ->and(app(DescribeReceiptVoidBoundary::class)->handle($receipt->fresh()))->toBe($boundary);
});

test('staff users with void receipt permission can see the unresolved void policy boundary', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReceipts,
        UserPermission::VoidReceipts,
    ]);

    $receipt = receiptDocumentFixture();

    $this->actingAs($user)
        ->get(route('staff.receipts.show', $receipt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('receipts/Show')
            ->where('receipt.id', $receipt->id)
            ->where('can.void_receipts', true)
            ->where('policyGaps.2', 'Void, reprint, and reconciliation policy remain unresolved.')
        );
});

test('staff users with view receipt permission can open a receipt pdf artifact', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReceipts,
    ]);

    $receipt = receiptDocumentFixture();

    $response = $this->actingAs($user)
        ->get(route('staff.receipts.pdf', $receipt))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="local-or-001.pdf"');

    $pdf = $response->getContent();

    expect($pdf)
        ->toStartWith('%PDF-1.4')
        ->toContain('Business Permit Receipt')
        ->toContain('LOCAL-OR-001')
        ->toContain('PHP 125.00')
        ->toContain('Codex Quantity Store')
        ->toContain('Codex Browser Payer')
        ->toContain('MAYOR-PERMIT')
        ->toContain('Automatic receipt numbering authority remains unresolved.')
        ->toContain('Void, reprint, and reconciliation policy remain unresolved.')
        ->and(pdfPageCount($pdf))->toBe(1);
});

test('receipt pdf output is deterministic for the same persisted receipt facts', function () {
    $receipt = receiptDocumentFixture();

    $renderer = app(RenderReceiptPdf::class);

    expect($renderer->handle($receipt))->toBe($renderer->handle($receipt->fresh()));
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

test('staff users without view receipt permission cannot open receipt pdf artifacts', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewCollections,
    ]);

    $receipt = Receipt::factory()->create();

    $this->actingAs($user)
        ->get(route('staff.receipts.pdf', $receipt))
        ->assertForbidden();
});

test('authorized receipt void attempts are blocked without mutating financial state', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReceipts,
        UserPermission::VoidReceipts,
    ]);

    $receipt = receiptDocumentFixture();
    $collection = $receipt->treasuryCollection;

    $this->actingAs($user)
        ->from(route('staff.receipts.show', $receipt))
        ->post(route('staff.receipts.void', $receipt))
        ->assertRedirectBackWithErrors(['receipt_policy']);

    expect($receipt->refresh()->status)->toBe(ReceiptStatus::Issued)
        ->and($collection->refresh()->status)->toBe(TreasuryCollectionStatus::Receipted);
});

test('staff users without void receipt permission cannot attempt receipt voiding', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReceipts,
    ]);

    $receipt = receiptDocumentFixture();
    $collection = $receipt->treasuryCollection;

    $this->actingAs($user)
        ->post(route('staff.receipts.void', $receipt))
        ->assertForbidden();

    expect($receipt->refresh()->status)->toBe(ReceiptStatus::Issued)
        ->and($collection->refresh()->status)->toBe(TreasuryCollectionStatus::Receipted);
});

function receiptDocumentFixture(): Receipt
{
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

    return Receipt::factory()
        ->for($collection)
        ->for($paymentSchedule)
        ->for($permitApplication)
        ->for($assessment)
        ->for($issuedBy, 'issuedBy')
        ->create([
            'status' => ReceiptStatus::Issued,
            'receipt_number' => 'LOCAL-OR-001',
            'amount_cents' => 12_500,
            'issued_at' => now()->startOfSecond(),
            'remarks' => 'Manual receipt observed during local verification.',
            'source_snapshot' => [
                'policy' => [
                    'numbering_mode' => 'manual',
                    'note' => 'Automatic receipt numbering authority remains unresolved.',
                ],
            ],
        ]);
}

function pdfPageCount(string $pdf): int
{
    preg_match_all('/\/Type \/Page\b/', $pdf, $matches);

    return count($matches[0]);
}
