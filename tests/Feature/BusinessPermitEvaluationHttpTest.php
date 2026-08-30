<?php

use App\Actions\DefineBusinessPermitEvaluationItem;
use App\Actions\InitializeBusinessPermitEvaluation;
use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationItemType;
use App\Enums\BusinessPermitEvaluationSource;
use App\Enums\PermitApplicationStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function httpEvaluationFixture(?User $submitter = null): array
{
    $submitter ??= User::factory()->create();
    $business = Business::factory()->create();
    $lineOfBusiness = LineOfBusiness::factory()->create(['name' => 'Retail']);
    $application = PermitApplication::factory()->for($business)->create([
        'submitted_by_id' => $submitter->id,
        'status' => PermitApplicationStatus::Assessment,
        'submitted_at' => now(),
    ]);
    if ($submitter->hasRole(UserRole::Citizen)) {
        linkPortalUserToApplicationOwner($submitter, $application);
    }
    PermitApplicationLine::factory()->for($application)->for($lineOfBusiness)->create();
    $evaluation = app(InitializeBusinessPermitEvaluation::class)->handle($application, $submitter);

    return compact('submitter', 'business', 'lineOfBusiness', 'application', 'evaluation');
}

it('renders the same typed Evaluator product surface through an authorized staff lens', function () {
    $fixture = httpEvaluationFixture();
    $viewer = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewBusinessPermitEvaluations,
    ]);
    $this->withoutVite();

    $this->actingAs($viewer)
        ->get(route('staff.permit-applications.evaluation.show', $fixture['application']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('business-permit-evaluations/Show', false)
            ->where('evaluation.application.id', $fixture['application']->id)
            ->where('evaluation.lens', 'internal')
            ->where('evaluation.applicant_declaration.0.line_of_business_name', 'Retail')
            ->where('evaluation.version.fingerprint_current', true)
            ->where('can.correct_lines_of_business', false));
});

it('refuses Evaluator visibility without the dedicated permission', function () {
    $fixture = httpEvaluationFixture();
    $viewer = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewPermitApplications]);

    $this->actingAs($viewer)
        ->get(route('staff.permit-applications.evaluation.show', $fixture['application']))
        ->assertForbidden();
});

it('authorizes Treasury LOB correction through optimistic concurrency and preserves registry facts', function () {
    $fixture = httpEvaluationFixture();
    $restaurant = LineOfBusiness::factory()->create(['name' => 'Restaurant']);
    $treasury = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewBusinessPermitEvaluations,
        UserPermission::CorrectEvaluationLinesOfBusiness,
    ], UserRole::Treasury);
    $version = $fixture['evaluation']->fresh()->currentVersion;
    $businessAttributes = $fixture['business']->getAttributes();

    $this->actingAs($treasury)
        ->post(route('staff.permit-applications.evaluation.lines-of-business.correct', $fixture['application']), [
            'line_of_business_ids' => [$fixture['lineOfBusiness']->id, $restaurant->id],
            'reason' => 'Synthetic authorized additional activity determination.',
            'expected_version_sequence' => $version->sequence,
            'expected_fingerprint' => $version->fingerprint,
            'idempotency_key' => 'http-treasury-correction',
        ])
        ->assertSessionHasNoErrors();

    expect($fixture['evaluation']->versions()->count())->toBe(2)
        ->and($fixture['application']->fresh()->lines()->count())->toBe(1)
        ->and($fixture['business']->fresh()->getAttributes())->toMatchArray($businessAttributes);

    $this->actingAs($treasury)
        ->post(route('staff.permit-applications.evaluation.lines-of-business.correct', $fixture['application']), [
            'line_of_business_ids' => [$fixture['lineOfBusiness']->id],
            'reason' => 'Stale attempt.',
            'expected_version_sequence' => $version->sequence,
            'expected_fingerprint' => $version->fingerprint,
            'idempotency_key' => 'http-stale-correction',
        ])
        ->assertSessionHasErrors('evaluation');

    expect($fixture['evaluation']->versions()->count())->toBe(2);
});

it('lets the assigned concerned office complete only its own provisional responsibility', function () {
    $fixture = httpEvaluationFixture();
    $engineering = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::ViewBusinessPermitEvaluations,
        UserPermission::ContributeBusinessPermitEvaluations,
    ]);
    $otherOffice = User::factory()->create(['role_id' => $engineering->role_id]);
    $item = app(DefineBusinessPermitEvaluationItem::class)->handle(
        $fixture['evaluation'],
        'engineering.charge',
        BusinessPermitEvaluationItemType::Charge,
        'engineering',
        true,
        true,
        BusinessPermitEvaluationApplicability::Applicable,
        ['amount_cents' => 12_500],
        BusinessPermitEvaluationSource::ProvisionalUat,
        $fixture['submitter'],
        metadata: [
            'label' => 'Engineering evaluation',
            'authorized_actor_id' => $engineering->id,
            'inspection_required' => true,
            'line_of_business_id' => $fixture['lineOfBusiness']->id,
            'department_selection_reason' => 'Retail requires an Engineering inspection in this synthetic test.',
        ],
    );
    $version = $fixture['evaluation']->fresh()->currentVersion;
    $payload = [
        'expected_version_sequence' => $version->sequence,
        'expected_fingerprint' => $version->fingerprint,
        'idempotency_key' => 'engineering-http-complete',
        'applicability' => 'applicable',
        'amount_cents' => 12_500,
        'inspection_mode' => 'physical',
        'inspection_completed' => true,
        'findings' => 'Synthetic UAT inspection complete.',
    ];

    $this->actingAs($engineering)
        ->get(route('staff.permit-applications.assessments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Assessments/Index')
            ->where('workSurface.id', 'department_responsibilities')
            ->where('workSurface.count', 1)
            ->where('permitApplications.data.0.work_items.0.label', 'Engineering evaluation')
            ->where('permitApplications.data.0.work_items.0.line_of_business', 'Retail')
            ->where('permitApplications.data.0.work_items.0.reason', 'Retail requires an Engineering inspection in this synthetic test.'));

    $this->actingAs($otherOffice)
        ->post(route('staff.permit-applications.evaluation.items.confirm', [$fixture['application'], $item]), $payload)
        ->assertSessionHasErrors('evaluation');

    $this->actingAs($engineering)
        ->post(route('staff.permit-applications.evaluation.items.confirm', [$fixture['application'], $item]), $payload)
        ->assertSessionHasNoErrors();

    expect($item->revisions()->count())->toBe(2);

    $this->actingAs($engineering)
        ->get(route('staff.permit-applications.assessments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workSurface.id', 'department_responsibilities')
            ->where('workSurface.count', 0)
            ->has('permitApplications.data', 0));
});

it('uses the same Evaluator surface for the owning Citizen and rejects another Citizen', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnBusinessPermitEvaluations,
        UserPermission::CorrectOwnEvaluationDeclarations,
    ], UserRole::Citizen);
    $fixture = httpEvaluationFixture($citizen);
    $otherCitizen = User::factory()->create(['role_id' => $citizen->role_id]);
    $this->withoutVite();

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.evaluation.show', $fixture['application']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('business-permit-evaluations/Show', false)
            ->where('evaluation.lens', 'citizen')
            ->where('can.correct_lines_of_business', true));

    $this->actingAs($otherCitizen)
        ->get(route('citizen.permit-applications.evaluation.show', $fixture['application']))
        ->assertForbidden();
});
