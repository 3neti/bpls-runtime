<?php

use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreatePaymentScheduleForAssessment;
use App\Actions\RecordAssessmentDecision;
use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentDecisionAction;
use App\Enums\AssessmentStatus;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AssessmentDecision;
use App\Models\AssessmentLine;
use App\Models\FeeRule;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use App\Models\User;
use DomainException;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;

test('an authorized Treasurer records immutable approval of the exact prepared assessment snapshot', function () {
    [$assessmentOfficer, $assessment] = preparedAssessmentFixture();
    $treasurer = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::ApproveAssessments,
    ], UserRole::Treasury);

    $this->actingAs($treasurer)
        ->post(route('staff.assessments.approve', $assessment), [
            'assessment_snapshot_hash' => assessmentSnapshotHash($assessment),
        ])
        ->assertRedirect(route('staff.permit-applications.assessments.show', $assessment));

    $decision = AssessmentDecision::query()->sole();
    $application = $assessment->permitApplication->refresh();

    expect($decision->action)->toBe(AssessmentDecisionAction::Approved)
        ->and($assessment->assessed_by_id)->toBe($assessmentOfficer->id)
        ->and($decision->decided_by_id)->toBe($treasurer->id)
        ->and($decision->decided_by_id)->not->toBe($assessment->assessed_by_id)
        ->and($decision->total_amount_cents)->toBe(45_000)
        ->and($decision->assessment_snapshot_hash)->toHaveLength(64)
        ->and($decision->source_snapshot['assessment_snapshot']['assessment_id'])->toBe($assessment->id)
        ->and($decision->source_snapshot['assessment_snapshot']['total_amount_cents'])->toBe(45_000)
        ->and($decision->source_snapshot['decision']['actor']['user_id'])->toBe($treasurer->id)
        ->and($decision->source_snapshot['decision']['action'])->toBe('approved')
        ->and($decision->source_snapshot['decision']['authorizes_payment_schedule'])->toBeTrue()
        ->and($application->status)->toBe(PermitApplicationStatus::Approval)
        ->and(PaymentSchedule::query()->count())->toBe(0)
        ->and(TreasuryCollection::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0);
});

test('BPLO and citizen users cannot invoke the Treasurer approval route', function () {
    [, $assessment] = preparedAssessmentFixture();
    $bplo = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::AssessPermitApplications,
        UserPermission::PreparePaymentSchedules,
    ]);
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);

    $this->actingAs($bplo)
        ->post(route('staff.assessments.approve', $assessment))
        ->assertForbidden();

    $this->actingAs($citizen)
        ->post(route('staff.assessments.approve', $assessment))
        ->assertForbidden();

    expect(AssessmentDecision::query()->count())->toBe(0);
});

test('the Assessment Officer cannot approve the assessment they prepared', function () {
    [$assessmentOfficer, $assessment] = preparedAssessmentFixture();

    expect(fn () => app(RecordAssessmentDecision::class)->handle(
        $assessment,
        $assessmentOfficer,
        AssessmentDecisionAction::Approved,
    ))->toThrow(
        DomainException::class,
        'The Assessment Officer who prepared the assessment cannot record the Municipal Treasurer decision.',
    );

    expect(AssessmentDecision::query()->count())->toBe(0)
        ->and($assessment->permitApplication->refresh()->status)->toBe(PermitApplicationStatus::Assessment);
});

test('an unapproved or returned assessment cannot create a payment schedule', function () {
    [, $unapprovedAssessment] = preparedAssessmentFixture();

    expect(fn () => app(CreatePaymentScheduleForAssessment::class)->handle($unapprovedAssessment, User::factory()->create()))
        ->toThrow(LogicException::class, 'approved by the Municipal Treasurer');

    [, $returnedAssessment] = preparedAssessmentFixture('APP-RETURNED');
    $treasurer = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ApproveAssessments,
    ], UserRole::Treasury);

    $this->actingAs($treasurer)
        ->post(route('staff.assessments.return-for-correction', $returnedAssessment), [
            'assessment_snapshot_hash' => assessmentSnapshotHash($returnedAssessment),
            'reason' => 'Correct the declared basis before recomputing the amount.',
        ])
        ->assertRedirect(route('staff.permit-applications.assessments.show', $returnedAssessment));

    $decision = $returnedAssessment->decision()->sole();

    expect($decision->action)->toBe(AssessmentDecisionAction::ReturnedForCorrection)
        ->and($decision->reason)->toBe('Correct the declared basis before recomputing the amount.')
        ->and($decision->source_snapshot['decision']['authorizes_payment_schedule'])->toBeFalse()
        ->and($returnedAssessment->permitApplication->refresh()->status)->toBe(PermitApplicationStatus::Assessment)
        ->and(fn () => app(CreatePaymentScheduleForAssessment::class)->handle($returnedAssessment, User::factory()->create()))
        ->toThrow(LogicException::class, 'approved by the Municipal Treasurer');
});

test('approval fails closed if the persisted assessment changes after the decision', function () {
    [, $assessment] = preparedAssessmentFixture();
    $treasurer = User::factory()->create();
    app(RecordAssessmentDecision::class)->handle($assessment, $treasurer, AssessmentDecisionAction::Approved);

    $assessment->lines()->firstOrFail()->update(['amount_cents' => 46_000]);
    $assessment->update(['total_amount_cents' => 46_000]);

    expect(fn () => app(CreatePaymentScheduleForAssessment::class)->handle($assessment->fresh(), User::factory()->create()))
        ->toThrow(LogicException::class, 'no longer matches its Treasurer approval');

    expect(PaymentSchedule::query()->count())->toBe(0);
});

test('a corrected assessment is a new snapshot and does not inherit the prior return decision', function () {
    [, $assessment] = preparedAssessmentFixture();
    app(RecordAssessmentDecision::class)->handle(
        $assessment,
        User::factory()->create(),
        AssessmentDecisionAction::ReturnedForCorrection,
        null,
        'Recompute the assessment.',
    );

    FeeRule::factory()->create([
        'code' => 'CORRECTED-APPLICATION-FEE',
        'scope' => FeeRuleScope::Application,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'amount_cents' => 50_000,
        'effective_from' => '2026-01-01',
    ]);

    $corrected = app(CreateAssessmentForPermitApplication::class)->handle(
        $assessment->permitApplication,
        User::factory()->create(),
    );

    expect($assessment->fresh()->superseded_at)->not->toBeNull()
        ->and($corrected->sequence)->toBe(2)
        ->and($corrected->decision)->toBeNull()
        ->and($corrected->permitApplication->status)->toBe(PermitApplicationStatus::Assessment)
        ->and(fn () => app(CreatePaymentScheduleForAssessment::class)->handle($corrected, User::factory()->create()))
        ->toThrow(LogicException::class, 'approved by the Municipal Treasurer');
});

test('an assessment detail clearly separates preparation from Treasurer decision and payment availability', function () {
    [$assessmentOfficer, $assessment] = preparedAssessmentFixture();
    $treasurer = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::ApproveAssessments,
    ], UserRole::Treasury);

    $this->actingAs($treasurer)
        ->get(route('staff.permit-applications.assessments.show', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Assessments/Show')
            ->where('assessment.assessed_by', $assessmentOfficer->name)
            ->where('assessment.decision', null)
            ->where('assessment.payment_schedule_available', false)
            ->where('can.approve_assessment', true)
        );

    app(RecordAssessmentDecision::class)->handle($assessment, $treasurer, AssessmentDecisionAction::Approved);

    $this->actingAs($treasurer)
        ->get(route('staff.permit-applications.assessments.show', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assessment.assessed_by', $assessmentOfficer->name)
            ->where('assessment.decision.action', 'approved')
            ->where('assessment.decision.decided_by', $treasurer->name)
            ->where('assessment.decision.total_amount_cents', 45_000)
            ->where('assessment.payment_schedule_available', true)
        );
});

test('an assessment snapshot accepts only one immutable Treasurer decision', function () {
    [, $assessment] = preparedAssessmentFixture();
    $treasurer = User::factory()->create();
    $recordDecision = app(RecordAssessmentDecision::class);

    $recordDecision->handle($assessment, $treasurer, AssessmentDecisionAction::Approved);

    expect(fn () => $recordDecision->handle(
        $assessment,
        User::factory()->create(),
        AssessmentDecisionAction::ReturnedForCorrection,
    ))->toThrow(DomainException::class, 'already has an immutable Treasurer decision');
});

test('Treasurer decisions fail safely when the page snapshot is stale or the decision was already recorded', function () {
    [, $assessment] = preparedAssessmentFixture();
    $treasurer = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::ApproveAssessments,
    ], UserRole::Treasury);
    $snapshotHash = assessmentSnapshotHash($assessment);

    $assessment->lines()->firstOrFail()->update(['amount_cents' => 46_000]);
    $assessment->update(['total_amount_cents' => 46_000]);

    $this->from(route('staff.permit-applications.assessments.show', $assessment))
        ->actingAs($treasurer)
        ->post(route('staff.assessments.approve', $assessment), [
            'assessment_snapshot_hash' => $snapshotHash,
        ])
        ->assertRedirect(route('staff.permit-applications.assessments.show', $assessment))
        ->assertSessionHasErrors(['assessment_decision']);

    expect(AssessmentDecision::query()->count())->toBe(0);

    $currentHash = assessmentSnapshotHash($assessment->fresh());

    $this->actingAs($treasurer)
        ->post(route('staff.assessments.approve', $assessment), [
            'assessment_snapshot_hash' => $currentHash,
        ])
        ->assertRedirect(route('staff.permit-applications.assessments.show', $assessment));

    $this->from(route('staff.permit-applications.assessments.show', $assessment))
        ->actingAs($treasurer)
        ->post(route('staff.assessments.return-for-correction', $assessment), [
            'assessment_snapshot_hash' => $currentHash,
            'reason' => 'A stale second decision must not replace approval.',
        ])
        ->assertRedirect(route('staff.permit-applications.assessments.show', $assessment))
        ->assertSessionHasErrors(['assessment_decision']);

    expect(AssessmentDecision::query()->sole()->action)->toBe(AssessmentDecisionAction::Approved);
});

/** @return array{User, Assessment} */
function preparedAssessmentFixture(string $applicationNumber = 'APP-ASSESSMENT-DECISION'): array
{
    $assessmentOfficer = User::factory()->create(['name' => 'Preview Assessment Officer']);
    $application = PermitApplication::factory()->withStatus(PermitApplicationStatus::Assessment)->create([
        'application_number' => $applicationNumber,
        'application_year' => 2026,
        'status' => PermitApplicationStatus::Assessment,
    ]);
    $assessment = Assessment::factory()->for($application)->create([
        'assessed_by_id' => $assessmentOfficer->id,
        'sequence' => 1,
        'status' => AssessmentStatus::Computed,
        'total_amount_cents' => 45_000,
        'superseded_at' => null,
        'source_snapshot' => ['source' => 'persisted assessment decision fixture'],
    ]);
    AssessmentLine::factory()->for($assessment)->create([
        'code' => 'APPLICATION-FEE',
        'name' => 'Application Fee',
        'amount_cents' => 45_000,
    ]);

    return [$assessmentOfficer, $assessment];
}

function assessmentSnapshotHash(Assessment $assessment): string
{
    return app(AssessmentSnapshotFingerprint::class)->hash($assessment);
}
