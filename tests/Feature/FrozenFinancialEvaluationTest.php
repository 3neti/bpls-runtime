<?php

use App\Actions\AssignTreasuryLinesOfBusiness;
use App\Actions\BuildMunicipalWorkInbox;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\RecordAssessmentDecision;
use App\Actions\RecordBusinessPermitEvaluationCounterCheck;
use App\Assessment\AssessmentCounterCheckReadiness;
use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentDecisionAction;
use App\Enums\AssessmentStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\UserPermission;
use App\Evaluation\FrozenFinancialEvaluation;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\Assessment;
use App\Models\InstitutionalPosition;
use App\Models\InstitutionalPositionAssignment;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Support/TreasuryEnterpriseFixture.php';

function frozenEvaluationActor(array $permissions): User
{
    $role = Role::factory()->create();
    $role->permissions()->sync(collect($permissions)->map(fn (UserPermission $permission) => Permission::firstOrCreate(
        ['name' => $permission->value, 'guard_name' => 'web'],
        ['code' => $permission->value, 'display_name' => $permission->value],
    )->id));

    return userWithRole($role);
}

test('ordinary Treasury confirmation freezes inputs and Assessment counter-check consumes exact immutable artifacts', function (): void {
    [$application, $treasury, $selection] = enterpriseUatFixture();
    app(AssignTreasuryLinesOfBusiness::class)->handle($application, [$selection], $treasury);
    $version = $application->businessPermitEvaluation()->sole()->currentVersion;
    $frozen = app(FrozenFinancialEvaluation::class)->read($version);
    expect($frozen['report']['total']['minor'])->toBe(417500)
        ->and($frozen['treasury_assignments'][0]['enterprise_determination']['classification'])->toBe('Small')
        ->and($frozen['paperless_payment_order_ids'])->toHaveCount(4);
    $assessor = frozenEvaluationActor([UserPermission::AccessStaff, UserPermission::AssessPermitApplications, UserPermission::ViewPermitApplications]);
    $checker = frozenEvaluationActor([UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::CounterCheckBusinessPermitEvaluations]);
    $treasurer = frozenEvaluationActor([UserPermission::ApproveAssessments]);
    $application->treasuryLineOfBusinessAssignments()->sole()->items()->update(['default_amount_cents' => 999999, 'determined_amount_cents' => 999999]);
    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application, $assessor);
    $hash = app(AssessmentSnapshotFingerprint::class)->hash($assessment);
    $role = $checker->primaryRole();
    $role->update(['code' => 'treasury']);
    $position = InstitutionalPosition::factory()->create(['capability_role_id' => $role->id]);
    InstitutionalPositionAssignment::create(['user_id' => $checker->id, 'institutional_position_id' => $position->id, 'status' => 'active', 'assigned_at' => now(), 'reason' => 'Synthetic counter-check regression']);
    $inbox = app(BuildMunicipalWorkInbox::class)->handle($checker)['items'];
    expect($inbox->where('task_type', 'treasury_counter_check'))->toHaveCount(1);
    expect($assessment->business_permit_evaluation_version_id)->toBe($version->id)
        ->and($assessment->fresh()->business_permit_evaluation_fingerprint)->toBe($version->fingerprint)
        ->and($assessment->total_amount_cents)->toBe(417500)
        ->and($assessment->price_report_snapshot)->toBe($frozen['report'])
        ->and(app(AssessmentSnapshotFingerprint::class)->hash($assessment->fresh()))->toBe($hash);
    test()->actingAs($checker)->get('/staff/assessments/'.$assessment->id)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('assessment.counter_check_state', 'awaiting_counter_check')
        ->where('assessment.business_permit_evaluation.version_id', $version->id)
        ->where('can.approve_assessment', false));
    expect(fn () => app(RecordAssessmentDecision::class)->handle($assessment, $treasurer, AssessmentDecisionAction::Approved))->toThrow(DomainException::class, 'counter-check');
    expect(fn () => app(RecordBusinessPermitEvaluationCounterCheck::class)->handle($assessment, User::factory()->create()))->toThrow(LogicException::class, 'authorized');
    test()->actingAs($checker)->get('/staff/permit-applications/'.$application->id.'/evaluation')->assertOk();
    test()->actingAs(User::factory()->create())->post('/staff/permit-applications/'.$application->id.'/evaluation/counter-check', [])->assertForbidden();
    $this->actingAs($checker)->post(route('staff.permit-applications.evaluation.counter-check', $application), [
        'assessment_id' => $assessment->id, 'expected_version_sequence' => $version->sequence, 'expected_fingerprint' => $version->fingerprint,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $check = $assessment->treasuryCounterCheck()->sole();
    expect($check->assessment_id)->toBe($assessment->id)->and($check->business_permit_evaluation_version_id)->toBe($version->id)
        ->and($check->assessment_snapshot_hash)->toBe($hash)
        ->and(app(RecordBusinessPermitEvaluationCounterCheck::class)->handle($assessment, $checker)->id)->toBe($check->id)
        ->and(app(AssessmentCounterCheckReadiness::class)->state($assessment->fresh()))->toBe('checked');
    app(RecordAssessmentDecision::class)->handle($assessment, $treasurer, AssessmentDecisionAction::Approved, $hash);
    expect($assessment->fresh()->decision->action)->toBe(AssessmentDecisionAction::Approved)
        ->and(app(AssessmentSnapshotFingerprint::class)->hash($assessment->fresh()))->toBe($hash);
    expect(fn () => $assessment->update(['business_permit_evaluation_version_id' => null]))->toThrow(LogicException::class, 'immutable');
    expect(fn () => $version->update(['fingerprint' => str_repeat('a', 64)]))->toThrow(LogicException::class, 'immutable');
});

test('commissioned Assessment fails closed without a frozen financial Evaluation', function (): void {
    [$application] = enterpriseUatFixture();
    expect(fn () => app(CreateAssessmentForPermitApplication::class)->handle($application))->toThrow(UnsupportedAssessmentPolicy::class, 'Evaluation');
    expect($application->assessments()->count())->toBe(0);
});

test('financial Assessment rejects mismatched identity amount or fingerprint', function (string $field): void {
    [$application, $actor, $selection] = enterpriseUatFixture();
    app(AssignTreasuryLinesOfBusiness::class)->handle($application, [$selection], $actor);
    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application);
    $attributes = $assessment->getAttributes();
    unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);
    $candidate = new Assessment;
    $candidate->setRawAttributes($attributes);
    if ($field === 'application') {
        $candidate->permit_application_id = PermitApplication::factory()->create()->id;
    } elseif ($field === 'amount') {
        $candidate->total_amount_cents = 1;
    } else {
        $candidate->business_permit_evaluation_fingerprint = str_repeat('a', 64);
    }
    expect(fn () => app(FrozenFinancialEvaluation::class)->assertAssessment($candidate))->toThrow(LogicException::class);
    expect(fn () => $candidate->save())->toThrow(LogicException::class);
})->with(['application', 'amount', 'fingerprint']);

test('retained unbound Assessment is incomplete and never grants Treasurer approval or a guessed binding', function (): void {
    $application = PermitApplication::factory()->withStatus(PermitApplicationStatus::Assessment)->create();
    $assessment = Assessment::factory()->for($application)->create(['status' => AssessmentStatus::Computed, 'total_amount_cents' => 417500]);
    $application->update(['metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => true]]]);
    $before = app(AssessmentSnapshotFingerprint::class)->hash($assessment);
    $actor = frozenEvaluationActor([UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ApproveAssessments]);
    test()->actingAs($actor)->get('/staff/assessments/'.$assessment->id)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('assessment.counter_check_state', 'incomplete')
        ->where('assessment.display_status', 'Incomplete · Evaluation binding unavailable')
        ->where('assessment.business_permit_evaluation', null)
        ->where('can.approve_assessment', false));
    expect(fn () => app(RecordAssessmentDecision::class)->handle($assessment->fresh(), $actor, AssessmentDecisionAction::Approved))->toThrow(DomainException::class, 'counter-check');
    expect($application->businessPermitEvaluation()->count())->toBe(0)
        ->and(app(AssessmentSnapshotFingerprint::class)->hash($assessment->fresh()))->toBe($before);
});
