<?php

use App\Actions\RecordAssessmentDecision;
use App\Enums\AssessmentDecisionAction;
use App\Enums\AssessmentStatus;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\UserPermission;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('staff users with prepare permission can prepare a payment schedule from an assessment', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::PreparePaymentSchedules,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-PAY-001',
        'status' => PermitApplicationStatus::Assessment,
    ]);

    $assessment = Assessment::factory()->for($application)->create([
        'status' => AssessmentStatus::Computed,
        'sequence' => 1,
        'total_amount_cents' => 45_000,
        'superseded_at' => null,
    ]);

    AssessmentLine::factory()->for($assessment)->create([
        'code' => 'BUSINESS-TAX',
        'name' => 'Business Tax',
        'category' => FeeRuleCategory::Tax,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'amount_cents' => 30_000,
    ]);

    AssessmentLine::factory()->for($assessment)->create([
        'code' => 'MAYORS-PERMIT',
        'name' => "Mayor's Permit Fee",
        'category' => FeeRuleCategory::Fee,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'amount_cents' => 15_000,
    ]);

    $approver = User::factory()->create();
    app(RecordAssessmentDecision::class)->handle($assessment, $approver, AssessmentDecisionAction::Approved);

    $response = $this->actingAs($user)
        ->post(route('staff.assessments.payment-schedule.store', $assessment));

    $schedule = PaymentSchedule::query()->sole();
    $application->refresh();

    $response->assertRedirect(route('staff.payment-schedules.show', $schedule));

    expect($schedule->assessment_id)->toBe($assessment->id)
        ->and($schedule->permit_application_id)->toBe($application->id)
        ->and($schedule->prepared_by_id)->toBe($user->id)
        ->and($schedule->payment_mode)->toBe('single')
        ->and($schedule->total_amount_cents)->toBe(45_000)
        ->and($schedule->paid_amount_cents)->toBe(0)
        ->and($schedule->source_snapshot['policy']['due_on'])->toBeNull()
        ->and($schedule->source_snapshot['treasurer_approval']['approved_by_id'])->toBe($approver->id)
        ->and($schedule->lines()->count())->toBe(2)
        ->and($schedule->lines()->where('code', 'BUSINESS-TAX')->sole()->amount_cents)->toBe(30_000)
        ->and($schedule->lines()->where('code', 'MAYORS-PERMIT')->sole()->amount_cents)->toBe(15_000)
        ->and($application->status)->toBe(PermitApplicationStatus::PendingPayment)
        ->and($application->metadata['status_history'][0]['from'])->toBe(PermitApplicationStatus::Assessment->value)
        ->and($application->metadata['status_history'][0]['to'])->toBe(PermitApplicationStatus::Approval->value)
        ->and($application->metadata['status_history'][1]['from'])->toBe(PermitApplicationStatus::Approval->value)
        ->and($application->metadata['status_history'][1]['to'])->toBe(PermitApplicationStatus::PendingPayment->value);
});

test('preparing a payment schedule is idempotent for an assessment', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::PreparePaymentSchedules,
    ]);

    $application = PermitApplication::factory()->create([
        'status' => PermitApplicationStatus::Assessment,
    ]);
    $assessment = Assessment::factory()->for($application)->create([
        'status' => AssessmentStatus::Computed,
        'total_amount_cents' => 10_000,
    ]);

    AssessmentLine::factory()->for($assessment)->create([
        'amount_cents' => 10_000,
    ]);

    app(RecordAssessmentDecision::class)->handle(
        $assessment,
        User::factory()->create(),
        AssessmentDecisionAction::Approved,
    );

    $this->actingAs($user)
        ->post(route('staff.assessments.payment-schedule.store', $assessment))
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('staff.assessments.payment-schedule.store', $assessment))
        ->assertRedirect();

    expect(PaymentSchedule::query()->count())->toBe(1)
        ->and(PaymentSchedule::query()->sole()->lines()->count())->toBe(1)
        ->and($assessment->permitApplication->refresh()->metadata['status_history'])->toHaveCount(2);
});

test('staff users without prepare permission cannot prepare a payment schedule', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPaymentSchedules,
    ]);

    $assessment = Assessment::factory()->create([
        'status' => AssessmentStatus::Computed,
    ]);

    $this->actingAs($user)
        ->post(route('staff.assessments.payment-schedule.store', $assessment))
        ->assertForbidden();
});

test('staff users with view permission can review a payment schedule', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPaymentSchedules,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-PAY-002',
    ]);

    $assessment = Assessment::factory()->for($application)->create([
        'status' => AssessmentStatus::Computed,
        'sequence' => 2,
    ]);

    $schedule = PaymentSchedule::factory()->for($application, 'permitApplication')->for($assessment)->create([
        'sequence' => 1,
        'total_amount_cents' => 25_000,
    ]);

    $schedule->lines()->create([
        'assessment_line_id' => null,
        'code' => 'APPLICATION-FEE',
        'name' => 'Application Fee',
        'category' => FeeRuleCategory::Fee,
        'status' => 'pending',
        'amount_cents' => 25_000,
        'paid_amount_cents' => 0,
        'source_snapshot' => [],
    ]);

    $this->actingAs($user)
        ->get(route('staff.payment-schedules.show', $schedule))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payment-schedules/Show')
            ->where('paymentSchedule.total_amount_cents', 25_000)
            ->where('paymentSchedule.permit_application.application_number', 'APP-PAY-002')
            ->where('paymentSchedule.lines.0.code', 'APPLICATION-FEE')
            ->where('paymentSchedule.payment_policy_boundary.status', 'policy_boundary')
            ->where('paymentSchedule.payment_policy_boundary.can_calculate_surcharge', false)
            ->where('paymentSchedule.payment_policy_boundary.can_calculate_interest', false)
            ->where('paymentSchedule.payment_policy_boundary.can_validate_pil', false)
            ->where('paymentSchedule.payment_policy_boundary.can_calculate_deficiency_tax', false)
            ->where('paymentSchedule.payment_policy_boundary.can_split_installments', false)
            ->where('paymentSchedule.payment_policy_boundary.can_assign_statutory_due_dates', false)
            ->where('paymentSchedule.payment_policy_boundary.supported_payment_modes.0', 'single')
            ->where('paymentSchedule.payment_policy_boundary.software_knows.assessment_lines_are_snapshotted', true)
            ->where('paymentSchedule.payment_policy_boundary.unresolved_policy.0', 'annual, semiannual, and quarterly payment splitting rules')
            ->where('paymentSchedule.online_payment_boundary.status', 'blocked')
            ->where('paymentSchedule.online_payment_boundary.can_pay_online', false)
            ->where('paymentSchedule.online_payment_boundary.can_reconcile_online', false)
            ->where('paymentSchedule.online_payment_boundary.software_knows.gateway_adapter_is_not_configured', true)
            ->where('paymentSchedule.online_payment_boundary.software_knows.reconciliation_policy_is_not_resolved', true)
        );
});

test('staff users with view permission can search and filter payment schedule queue', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPaymentSchedules,
    ]);

    $owner = BusinessOwner::factory()->create(['name' => 'Queue Owner Alpha']);
    $business = Business::factory()->for($owner, 'owner')->create([
        'name' => 'Queue Hardware Alpha',
        'trade_name' => 'Alpha Tools',
        'registration_number' => 'QUEUE-BN-ALPHA',
    ]);
    $application = PermitApplication::factory()->for($business)->create([
        'application_number' => 'APP-QUEUE-ALPHA',
        'application_year' => 2026,
    ]);
    $assessment = Assessment::factory()->for($application)->create([
        'sequence' => 4,
        'status' => AssessmentStatus::Computed,
    ]);
    $matchingSchedule = PaymentSchedule::factory()
        ->for($application, 'permitApplication')
        ->for($assessment)
        ->create([
            'sequence' => 7,
            'status' => PaymentScheduleStatus::Paid,
            'total_amount_cents' => 50_000,
            'paid_amount_cents' => 50_000,
        ]);
    PaymentSchedule::factory()->create([
        'status' => PaymentScheduleStatus::Pending,
        'total_amount_cents' => 10_000,
    ]);

    $this->actingAs($user)
        ->get(route('staff.payment-schedules.index', [
            'q' => 'QUEUE-ALPHA',
            'status' => PaymentScheduleStatus::Paid->value,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payment-schedules/Index')
            ->where('filters.q', 'QUEUE-ALPHA')
            ->where('filters.status', PaymentScheduleStatus::Paid->value)
            ->where('paymentSchedules.data.0.id', $matchingSchedule->id)
            ->where('paymentSchedules.data.0.permit_application.application_number', 'APP-QUEUE-ALPHA')
            ->where('paymentSchedules.data.0.permit_application.business_name', 'Queue Hardware Alpha')
            ->where('paymentSchedules.data.0.status', PaymentScheduleStatus::Paid->value)
            ->where('paymentSchedules.data.0.paid_amount_cents', 50_000)
        );
});

test('payment schedule queue rejects invalid status filters', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPaymentSchedules,
    ]);

    $this->actingAs($user)
        ->from(route('staff.payment-schedules.index'))
        ->get(route('staff.payment-schedules.index', ['status' => 'settled']))
        ->assertRedirect(route('staff.payment-schedules.index'))
        ->assertSessionHasErrors('status');
});

test('staff users without view permission cannot open payment schedule queue', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::PreparePaymentSchedules,
    ]);

    $this->actingAs($user)
        ->get(route('staff.payment-schedules.index'))
        ->assertForbidden();
});

test('assessment review exposes payment schedule permission state', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::PreparePaymentSchedules,
        UserPermission::ViewPaymentSchedules,
    ]);

    $assessment = Assessment::factory()->create([
        'status' => AssessmentStatus::Computed,
        'total_amount_cents' => 10_000,
    ]);

    $schedule = PaymentSchedule::factory()
        ->for($assessment->permitApplication, 'permitApplication')
        ->for($assessment)
        ->create([
            'total_amount_cents' => 10_000,
        ]);

    $this->actingAs($user)
        ->get(route('staff.permit-applications.assessments.show', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Assessments/Show')
            ->where('assessment.latest_payment_schedule.id', $schedule->id)
            ->where('can.prepare_payment_schedule', true)
            ->where('can.view_payment_schedules', true)
        );
});

test('permit application review exposes latest payment schedule state', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-PAY-003',
        'status' => PermitApplicationStatus::PendingPayment,
    ]);

    $assessment = Assessment::factory()->for($application)->create([
        'status' => AssessmentStatus::Computed,
        'sequence' => 1,
        'total_amount_cents' => 25_000,
    ]);

    $schedule = PaymentSchedule::factory()->for($application, 'permitApplication')->for($assessment)->create([
        'sequence' => 1,
        'total_amount_cents' => 25_000,
        'paid_amount_cents' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('staff.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Show')
            ->where('permitApplication.status', 'pending_payment')
            ->where('permitApplication.latest_payment_schedule.id', $schedule->id)
            ->where('permitApplication.latest_payment_schedule.status', 'pending')
        );
});
