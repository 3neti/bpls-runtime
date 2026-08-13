<?php

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleScope;
use App\Enums\UserPermission;
use App\Models\Assessment;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected away from staff permit assessments', function () {
    $this->get(route('staff.permit-applications.assessments.index'))
        ->assertRedirect(route('login'));
});

test('users without staff access cannot view the staff permit assessment index', function () {
    $this->actingAs(userWithPermissions([]))
        ->get(route('staff.permit-applications.assessments.index'))
        ->assertForbidden();
});

test('staff users with view permission can view the staff permit assessment index', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-2026-00001',
    ]);

    PermitApplicationLine::factory()
        ->for($application)
        ->for(LineOfBusiness::factory()->create(['name' => 'Retail Store']))
        ->create();

    $this->actingAs($user)
        ->get(route('staff.permit-applications.assessments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Assessments/Index')
            ->where('permitApplications.data.0.application_number', 'APP-2026-00001')
            ->where('permitApplications.data.0.line_count', 1)
            ->where('permitApplications.data.0.latest_assessment', null)
            ->where('can.assess_permit_applications', false)
        );
});

test('staff users with assess permission can compute an assessment from the staff surface', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::AssessPermitApplications,
    ]);

    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
    ]);

    FeeRule::factory()->create([
        'code' => 'APPLICATION-FEE',
        'scope' => FeeRuleScope::Application,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'amount_cents' => 25_000,
        'effective_from' => '2026-01-01',
    ]);

    $response = $this->actingAs($user)
        ->post(route('staff.permit-applications.assessments.store', $application));

    $assessment = Assessment::query()->sole();

    $response->assertRedirect(route('staff.permit-applications.assessments.show', $assessment));

    expect($assessment->total_amount_cents)->toBe(25_000);
});

test('staff users without assess permission cannot compute an assessment', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $application = PermitApplication::factory()->create();

    $this->actingAs($user)
        ->post(route('staff.permit-applications.assessments.store', $application))
        ->assertForbidden();
});

test('staff users with view permission can review a computed assessment', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::AssessPermitApplications,
    ]);

    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
    ]);

    FeeRule::factory()->create([
        'code' => 'APPLICATION-FEE',
        'name' => 'Application Fee',
        'scope' => FeeRuleScope::Application,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'amount_cents' => 25_000,
        'effective_from' => '2026-01-01',
    ]);

    $this->actingAs($user)
        ->post(route('staff.permit-applications.assessments.store', $application));

    $assessment = Assessment::query()->sole();

    $this->actingAs($user)
        ->get(route('staff.permit-applications.assessments.show', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Assessments/Show')
            ->where('assessment.total_amount_cents', 25_000)
            ->where('assessment.lines.0.code', 'APPLICATION-FEE')
            ->where('assessment.lines.0.name', 'Application Fee')
        );
});
