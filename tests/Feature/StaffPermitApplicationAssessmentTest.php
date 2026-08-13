<?php

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleScope;
use App\Models\Assessment;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected away from staff permit assessments', function () {
    $this->get(route('staff.permit-applications.assessments.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view the staff permit assessment index', function () {
    $user = User::factory()->create();
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
        );
});

test('authenticated users can compute an assessment from the staff surface', function () {
    $user = User::factory()->create();
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

test('authenticated users can review a computed assessment', function () {
    $user = User::factory()->create();
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
