<?php

use App\Enums\AssessmentStatus;
use App\Enums\FeeRuleCategory;
use App\Enums\PaymentScheduleLineStatus;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
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

test('citizen payment detail requires authentication and financial permission', function () {
    $schedule = PaymentSchedule::factory()->create();

    $this->get(route('citizen.payment-schedules.show', $schedule))
        ->assertRedirect(route('login'));

    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);
    $application = PermitApplication::factory()->for($citizen, 'submittedBy')->create();
    $ownedSchedule = PaymentSchedule::factory()->for($application, 'permitApplication')->create();

    $this->actingAs($citizen)
        ->get(route('citizen.payment-schedules.show', $ownedSchedule))
        ->assertForbidden();
});

test('citizens can inspect authoritative payment evidence for an owned application', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $owner = BusinessOwner::factory()->create(['name' => 'Citizen Payment Owner']);
    $business = Business::factory()->for($owner, 'owner')->create([
        'name' => 'Citizen Payment Trading',
        'trade_name' => 'Citizen Payment Shop',
    ]);
    $application = PermitApplication::factory()
        ->for($citizen, 'submittedBy')
        ->for($business)
        ->create([
            'application_number' => 'APP-CITIZEN-PAYMENT-001',
            'status' => PermitApplicationStatus::PendingPayment,
            'application_year' => 2026,
        ]);
    $assessment = Assessment::factory()->for($application)->create([
        'sequence' => 2,
        'status' => AssessmentStatus::Computed,
        'total_amount_cents' => 250_000,
    ]);
    $schedule = PaymentSchedule::factory()
        ->for($application, 'permitApplication')
        ->for($assessment)
        ->create([
            'sequence' => 3,
            'status' => PaymentScheduleStatus::Paid,
            'payment_mode' => 'single',
            'total_amount_cents' => 250_000,
            'paid_amount_cents' => 250_000,
        ]);
    $line = PaymentScheduleLine::factory()->for($schedule)->create([
        'code' => 'CITIZEN-PERMIT-FEE',
        'name' => 'Citizen Permit Fee',
        'category' => FeeRuleCategory::Fee,
        'status' => PaymentScheduleLineStatus::Paid,
        'amount_cents' => 250_000,
        'paid_amount_cents' => 250_000,
    ]);
    $collection = TreasuryCollection::factory()->create([
        'payment_schedule_id' => $schedule->id,
        'permit_application_id' => $application->id,
        'assessment_id' => $assessment->id,
        'status' => TreasuryCollectionStatus::Receipted,
        'amount_cents' => 250_000,
        'payer_name' => 'Sensitive Payer Name',
        'reference_number' => 'SENSITIVE-REFERENCE',
    ]);
    CollectionAllocation::factory()->create([
        'treasury_collection_id' => $collection->id,
        'payment_schedule_line_id' => $line->id,
        'amount_cents' => 250_000,
    ]);
    $receipt = Receipt::factory()->create([
        'treasury_collection_id' => $collection->id,
        'payment_schedule_id' => $schedule->id,
        'permit_application_id' => $application->id,
        'assessment_id' => $assessment->id,
        'status' => ReceiptStatus::Issued,
        'numbering_authority' => 'manual',
        'receipt_number' => 'OR-CITIZEN-PAYMENT-001',
        'amount_cents' => 250_000,
    ]);

    $this->actingAs($citizen)
        ->get(route('citizen.payment-schedules.show', $schedule))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('citizen/payment-schedules/Show')
            ->where('paymentSchedule.id', $schedule->id)
            ->where('paymentSchedule.sequence', 3)
            ->where('paymentSchedule.status', PaymentScheduleStatus::Paid->value)
            ->where('paymentSchedule.total_amount_cents', 250_000)
            ->where('paymentSchedule.paid_amount_cents', 250_000)
            ->where('paymentSchedule.balance_amount_cents', 0)
            ->where('paymentSchedule.assessment.id', $assessment->id)
            ->where('paymentSchedule.permit_application.id', $application->id)
            ->where('paymentSchedule.permit_application.application_number', 'APP-CITIZEN-PAYMENT-001')
            ->where('paymentSchedule.lines.0.code', 'CITIZEN-PERMIT-FEE')
            ->where('paymentSchedule.lines.0.amount_cents', 250_000)
            ->where('paymentSchedule.collections.0.id', $collection->id)
            ->where('paymentSchedule.collections.0.allocations.0.code', 'CITIZEN-PERMIT-FEE')
            ->where('paymentSchedule.collections.0.receipt.id', $receipt->id)
            ->where('paymentSchedule.collections.0.receipt.receipt_number', 'OR-CITIZEN-PAYMENT-001')
            ->where('paymentSchedule.payment_policy_boundary.status', 'policy_boundary')
            ->where('paymentSchedule.online_payment_boundary.status', 'blocked')
            ->where('paymentSchedule.online_payment_boundary.can_pay_online', false)
            ->where('paymentSchedule.artifact_statement', fn (string $statement): bool => str_contains($statement, 'does not execute payment'))
            ->missing('paymentSchedule.prepared_by')
            ->missing('paymentSchedule.collections.0.payer_name')
            ->missing('paymentSchedule.collections.0.reference_number')
            ->missing('paymentSchedule.collections.0.received_by')
            ->missing('paymentSchedule.collections.0.receipt.issued_by')
            ->missing('paymentSchedule.collections.0.receipt.pdf_url')
        );
});

test('citizen payment detail does not reveal another applicants schedule', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $otherCitizen = User::factory()->create(['role_id' => $citizen->role_id]);
    $otherApplication = PermitApplication::factory()->for($otherCitizen, 'submittedBy')->create();
    $otherSchedule = PaymentSchedule::factory()->for($otherApplication, 'permitApplication')->create();

    $this->actingAs($citizen)
        ->get(route('citizen.payment-schedules.show', $otherSchedule))
        ->assertNotFound();
});
