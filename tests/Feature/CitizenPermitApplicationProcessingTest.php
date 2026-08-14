<?php

use App\Actions\DescribeOnlinePaymentBoundary;
use App\Actions\DescribePaymentPolicyBoundary;
use App\Enums\AssessmentStatus;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('citizens can view authoritative assessment and payment state for an owned application', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $application = PermitApplication::factory()->for($citizen, 'submittedBy')->create([
        'application_number' => 'APP-CITIZEN-TRACKING-001',
        'status' => PermitApplicationStatus::PendingPayment,
    ]);
    $assessment = Assessment::factory()->for($application)->create([
        'sequence' => 2,
        'status' => AssessmentStatus::Computed,
        'total_amount_cents' => 125_075,
        'superseded_at' => null,
    ]);
    $paymentSchedule = PaymentSchedule::factory()
        ->for($application)
        ->for($assessment)
        ->create([
            'sequence' => 1,
            'status' => PaymentScheduleStatus::PartiallyPaid,
            'payment_mode' => 'single',
            'total_amount_cents' => 125_075,
            'paid_amount_cents' => 25_000,
        ]);

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('citizen/permit-applications/Show')
            ->where('permitApplication.display_reference', 'APP-CITIZEN-TRACKING-001')
            ->where('permitApplication.status', PermitApplicationStatus::PendingPayment->value)
            ->where('permitApplication.processing.has_entered_municipal_processing', true)
            ->where('permitApplication.processing.assessment.id', $assessment->id)
            ->where('permitApplication.processing.assessment.sequence', 2)
            ->where('permitApplication.processing.assessment.status', AssessmentStatus::Computed->value)
            ->where('permitApplication.processing.assessment.total_amount_cents', 125_075)
            ->where('permitApplication.processing.payment_schedule.id', $paymentSchedule->id)
            ->where('permitApplication.processing.payment_schedule.status', PaymentScheduleStatus::PartiallyPaid->value)
            ->where('permitApplication.processing.payment_schedule.total_amount_cents', 125_075)
            ->where('permitApplication.processing.payment_schedule.paid_amount_cents', 25_000)
            ->where('permitApplication.processing.payment_schedule.balance_amount_cents', 100_075)
            ->where('permitApplication.processing.payment_schedule.payment_policy_boundary.status', 'policy_boundary')
            ->where('permitApplication.processing.payment_schedule.online_payment_boundary.status', 'blocked')
            ->where('permitApplication.processing.payment_schedule.online_payment_boundary.can_pay_online', false)
            ->where('permitApplication.can_edit', false)
            ->where('permitApplication.can_upload_documents', false)
            ->where('permitApplication.can_view_financials', true)
        );

    expect(app(DescribePaymentPolicyBoundary::class)->handle($paymentSchedule)['status'])
        ->toBe('policy_boundary')
        ->and(app(DescribeOnlinePaymentBoundary::class)->handle($paymentSchedule)['can_pay_online'])
        ->toBeFalse();
});

test('citizen financial state remains hidden without the explicit permission', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);
    $application = PermitApplication::factory()->for($citizen, 'submittedBy')->create([
        'status' => PermitApplicationStatus::PendingPayment,
    ]);
    $assessment = Assessment::factory()->for($application)->create([
        'status' => AssessmentStatus::Computed,
        'total_amount_cents' => 90_000,
    ]);
    PaymentSchedule::factory()->for($application)->for($assessment)->create([
        'total_amount_cents' => 90_000,
    ]);

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permitApplication.processing.has_entered_municipal_processing', true)
            ->where('permitApplication.processing.assessment', null)
            ->where('permitApplication.processing.payment_schedule', null)
            ->where('permitApplication.can_view_financials', false)
        );
});

test('citizen payment state is not paired with a superseded assessment schedule', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $application = PermitApplication::factory()->for($citizen, 'submittedBy')->create([
        'status' => PermitApplicationStatus::PendingPayment,
    ]);
    $supersededAssessment = Assessment::factory()->for($application)->create([
        'sequence' => 1,
        'status' => AssessmentStatus::Computed,
        'total_amount_cents' => 90_000,
        'superseded_at' => now(),
    ]);
    PaymentSchedule::factory()
        ->for($application)
        ->for($supersededAssessment)
        ->create([
            'sequence' => 1,
            'total_amount_cents' => 90_000,
        ]);
    $activeAssessment = Assessment::factory()->for($application)->create([
        'sequence' => 2,
        'status' => AssessmentStatus::Computed,
        'total_amount_cents' => 110_000,
        'superseded_at' => null,
    ]);

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permitApplication.processing.assessment.id', $activeAssessment->id)
            ->where('permitApplication.processing.assessment.total_amount_cents', 110_000)
            ->where('permitApplication.processing.payment_schedule', null)
        );
});

test('citizens cannot view another applicants processing or financial state', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $otherCitizen = User::factory()->create(['role_id' => $citizen->role_id]);
    $application = PermitApplication::factory()->for($otherCitizen, 'submittedBy')->create([
        'status' => PermitApplicationStatus::PendingPayment,
    ]);

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertNotFound();
});
