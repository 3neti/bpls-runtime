<?php

use App\Actions\DescribeOnlinePaymentBoundary;
use App\Actions\DescribePaymentPolicyBoundary;
use App\Actions\DescribePermitVerificationBoundary;
use App\Enums\AssessmentStatus;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitClearanceStatus;
use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\BploRoutingDetermination;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitClearance;
use App\Models\PostPaymentOfficeCertification;
use App\Models\ProvisionalUatPermitCompletion;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Inertia\Testing\AssertableInertia as Assert;

test('released permit is displayed and downloadable only by its portal owner without mutating release evidence', function () {
    $citizen = userWithPermissions([UserPermission::AccessCitizen, UserPermission::ViewOwnPermitApplications], UserRole::Citizen);
    $application = PermitApplication::factory()->create();
    linkPortalUserToApplicationOwner($citizen, $application);
    $completion = ProvisionalUatPermitCompletion::factory()->for($application)->create([
        'status' => 'released_synthetic', 'semantic_classification' => 'synthetic_only',
        'issued_at' => now()->subMinute(), 'released_at' => now(), 'permit_number' => 'BP-2026-TEST',
    ]);
    $before = $completion->fresh()->getRawOriginal();
    $this->actingAs($citizen)->get(route('citizen.permit-applications.show', $application))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('permitApplication.released_permit.number', 'BP-2026-TEST')
        ->where('permitApplication.released_permit.pdf_url', '/citizen/permit-applications/'.$application->id.'/permit.pdf'));
    $url = route('citizen.permit-applications.permit.pdf', $application);
    $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
    $this->get($url.'?download=1')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="business-permit-'.$application->id.'.pdf"');
    expect($completion->fresh()->getRawOriginal())->toBe($before);
    $stranger = userWithRole($citizen->roles()->firstOrFail());
    $this->actingAs($stranger)->get($url)->assertNotFound();
});

test('citizens cannot retrieve or see an unreleased permit PDF', function (bool $issued) {
    $citizen = userWithPermissions([UserPermission::AccessCitizen, UserPermission::ViewOwnPermitApplications], UserRole::Citizen);
    $application = PermitApplication::factory()->create();
    linkPortalUserToApplicationOwner($citizen, $application);
    ProvisionalUatPermitCompletion::factory()->for($application)->create([
        'status' => $issued ? 'issued_synthetic' : 'approved_for_preview_release',
        'semantic_classification' => 'synthetic_only', 'issued_at' => $issued ? now() : null,
        'released_at' => null,
    ]);
    $this->actingAs($citizen)->get(route('citizen.permit-applications.show', $application))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('permitApplication.released_permit', null));
    $this->get(route('citizen.permit-applications.permit.pdf', $application))->assertNotFound();
})->with([true, false]);

test('citizens can view authoritative assessment and payment state for an owned application', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $application = PermitApplication::factory()->withStatus(PermitApplicationStatus::PendingPayment)->for($citizen, 'submittedBy')->create([
        'application_number' => 'APP-CITIZEN-TRACKING-001',
        'status' => PermitApplicationStatus::PendingPayment,
    ]);
    linkPortalUserToApplicationOwner($citizen, $application);
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
            ->has('permitApplication.timeline', 3)
            ->where('permitApplication.timeline.0.key', "application-recorded:{$application->id}")
            ->where('permitApplication.timeline.0.category', 'application')
            ->where('permitApplication.timeline.1.key', "assessment-computed:{$assessment->id}")
            ->where('permitApplication.timeline.1.category', 'assessment')
            ->where('permitApplication.timeline.2.key', "payment-schedule-prepared:{$paymentSchedule->id}")
            ->where('permitApplication.timeline.2.category', 'payment')
            ->missing('permitApplication.timeline.0.actor')
            ->missing('permitApplication.timeline.0.source')
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
    $application = PermitApplication::factory()->withStatus(PermitApplicationStatus::PendingPayment)->for($citizen, 'submittedBy')->create([
        'status' => PermitApplicationStatus::PendingPayment,
    ]);
    linkPortalUserToApplicationOwner($citizen, $application);
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
            ->where('permitApplication.permit_artifact', null)
            ->has('permitApplication.timeline', 1)
            ->where('permitApplication.timeline.0.key', "application-recorded:{$application->id}")
            ->where('permitApplication.can_view_financials', false)
        );
});

test('citizen payment state is not paired with a superseded assessment schedule', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $application = PermitApplication::factory()->withStatus(PermitApplicationStatus::PendingPayment)->for($citizen, 'submittedBy')->create([
        'status' => PermitApplicationStatus::PendingPayment,
    ]);
    linkPortalUserToApplicationOwner($citizen, $application);
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

test('citizens can view collection receipt clearance and authority review evidence', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $application = PermitApplication::factory()->withStatus(PermitApplicationStatus::PendingPayment)->for($citizen, 'submittedBy')->create([
        'application_number' => 'APP-CITIZEN-AUTHORITY-001',
        'status' => PermitApplicationStatus::PendingPayment,
    ]);
    linkPortalUserToApplicationOwner($citizen, $application);
    $assessment = Assessment::factory()->for($application)->create([
        'status' => AssessmentStatus::Computed,
        'total_amount_cents' => 150_000,
        'superseded_at' => null,
    ]);
    $paymentSchedule = PaymentSchedule::factory()
        ->for($application)
        ->for($assessment)
        ->create([
            'status' => PaymentScheduleStatus::Paid,
            'total_amount_cents' => 150_000,
            'paid_amount_cents' => 150_000,
        ]);
    $collection = TreasuryCollection::factory()
        ->for($application)
        ->for($assessment)
        ->for($paymentSchedule)
        ->create([
            'status' => TreasuryCollectionStatus::Receipted,
            'amount_cents' => 150_000,
        ]);
    $receipt = Receipt::factory()
        ->for($collection, 'treasuryCollection')
        ->for($application)
        ->for($assessment)
        ->for($paymentSchedule)
        ->create([
            'status' => ReceiptStatus::Issued,
            'receipt_number' => 'OR-CITIZEN-001',
            'amount_cents' => 150_000,
        ]);

    foreach (['bplo_review', 'treasury_payment', 'release_authority'] as $code) {
        PermitClearance::factory()->for($application)->create([
            'code' => $code,
            'status' => PermitClearanceStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    $verification = app(DescribePermitVerificationBoundary::class)->handle($application);

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permitApplication.processing.payment_schedule.status', PaymentScheduleStatus::Paid->value)
            ->where('permitApplication.processing.payment_schedule.balance_amount_cents', 0)
            ->where('permitApplication.processing.collection.id', $collection->id)
            ->where('permitApplication.processing.collection.status', TreasuryCollectionStatus::Receipted->value)
            ->where('permitApplication.processing.collection.receipt.id', $receipt->id)
            ->where('permitApplication.processing.collection.receipt.receipt_number', 'OR-CITIZEN-001')
            ->where('permitApplication.processing.collection.receipt.status', ReceiptStatus::Issued->value)
            ->where('permitApplication.processing.clearance_summary.completed', 3)
            ->where('permitApplication.processing.clearance_summary.total', 3)
            ->where('permitApplication.processing.clearance_summary.all_completed', true)
            ->where('permitApplication.processing.authority_review.ready_for_authority_review', true)
            ->where('permitApplication.processing.authority_review.can_release', false)
            ->where('permitApplication.processing.authority_review.status', 'ready_for_authority_review')
            ->where('permitApplication.permit_artifact.label', "Mayor's Permit Preview")
            ->where('permitApplication.permit_artifact.status', 'not_issued')
            ->where('permitApplication.permit_artifact.available', false)
            ->where('permitApplication.permit_artifact.ready_for_authority_review', true)
            ->where('permitApplication.permit_artifact.can_issue', false)
            ->where('permitApplication.permit_artifact.can_release', false)
            ->where('permitApplication.permit_artifact.can_make_legally_effective', false)
            ->where('permitApplication.permit_artifact.verification_reference', $verification['reference'])
            ->where('permitApplication.permit_artifact.verification_status', 'artifact_only')
            ->where('permitApplication.permit_artifact.verification_view_url', $verification['view_url'])
            ->missing('permitApplication.permit_artifact.permit_pdf_url')
        );
});

test('citizens cannot view another applicants processing or financial state', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $otherCitizen = userWithRole($citizen->primaryRole());
    $application = PermitApplication::factory()->withStatus(PermitApplicationStatus::PendingPayment)->for($otherCitizen, 'submittedBy')->create([
        'status' => PermitApplicationStatus::PendingPayment,
    ]);

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertNotFound();
});

test('released synthetic permits replace stale payment and clearance projections for citizens', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $application = PermitApplication::factory()->withStatus(PermitApplicationStatus::PendingPayment)->for($citizen, 'submittedBy')->create([
        'status' => PermitApplicationStatus::PendingPayment,
        'metadata' => [
            'lifecycle_cleanroom' => [
                'semantic_classification' => 'synthetic_only',
            ],
        ],
    ]);
    linkPortalUserToApplicationOwner($citizen, $application);
    $routing = BploRoutingDetermination::factory()->for($application)->create();
    $assessment = Assessment::factory()->for($application)->create([
        'status' => AssessmentStatus::Computed,
        'total_amount_cents' => 417_500,
        'superseded_at' => null,
    ]);
    $paymentSchedule = PaymentSchedule::factory()->for($application)->for($assessment)->create([
        'status' => PaymentScheduleStatus::Paid,
        'total_amount_cents' => 417_500,
        'paid_amount_cents' => 417_500,
    ]);
    $collection = TreasuryCollection::factory()->for($application)->for($assessment)->for($paymentSchedule)->create([
        'status' => TreasuryCollectionStatus::Receipted,
        'amount_cents' => 417_500,
    ]);
    $receipt = Receipt::factory()
        ->for($collection, 'treasuryCollection')
        ->for($application)
        ->for($assessment)
        ->for($paymentSchedule)
        ->create([
            'status' => ReceiptStatus::Issued,
            'amount_cents' => 417_500,
        ]);

    foreach (['assessor', 'engineering', 'health', 'menro'] as $office) {
        PostPaymentOfficeCertification::query()->create([
            'permit_application_id' => $application->id,
            'bplo_routing_determination_id' => $routing->id,
            'receipt_id' => $receipt->id,
            'certified_by_id' => null,
            'office_code' => $office,
            'office_label' => str($office)->headline()->toString(),
            'routing_work_ids' => [],
            'status' => 'completed',
            'result' => 'certified',
            'remarks' => null,
            'evidence' => [],
            'certified_at' => now(),
            'semantic_classification' => 'synthetic_only',
            'production_authority' => false,
        ]);
    }

    ProvisionalUatPermitCompletion::factory()->for($application)->create([
        'issued_at' => now()->subMinute(),
        'released_at' => now(),
        'permit_number' => 'BP-2026-0004',
        'semantic_classification' => 'synthetic_only',
    ]);

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permitApplications.data.0.id', $application->id)
            ->where('permitApplications.data.0.status', PermitApplicationStatus::Released->value)
        );

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permitApplication.status', PermitApplicationStatus::Released->value)
            ->where('permitApplication.processing.application_status', PermitApplicationStatus::Released->value)
            ->where('permitApplication.processing.current_stage', PermitApplicationStatus::Released->value)
            ->where('permitApplication.processing.clearance_summary.completed', 4)
            ->where('permitApplication.processing.clearance_summary.total', 4)
            ->where('permitApplication.processing.clearance_summary.all_completed', true)
            ->where('permitApplication.processing.statement', 'The synthetic Business Permit was issued and separately released. Its public verification reference is available below.')
        );

    $page = file_get_contents(resource_path('js/pages/citizen/permit-applications/Show.vue'));

    expect($page)->toContain("permitApplication.processing.current_stage === 'released'")
        ->and($page)->toContain("!['issued', 'released'].includes(");
});
