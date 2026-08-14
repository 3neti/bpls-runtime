<?php

use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('citizen permit routes require authentication and citizen permissions', function () {
    $this->get(route('citizen.permit-applications.index'))
        ->assertRedirect(route('login'));

    $staff = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::CreatePermitApplications,
    ]);

    $this->actingAs($staff)
        ->get(route('citizen.permit-applications.index'))
        ->assertForbidden();

    $citizenWithoutCreatePermission = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);

    $this->actingAs($citizenWithoutCreatePermission)
        ->get(route('citizen.permit-applications.create'))
        ->assertForbidden();
});

test('shared navigation capabilities do not imply citizen access', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can_access_staff', false)
            ->where('auth.can_access_citizen', false)
        );
});

test('citizens can open a new permit draft with their identity prefilled', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::CreateOwnPermitApplications,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);
    $lineOfBusiness = LineOfBusiness::factory()->create([
        'name' => 'Retail Store',
        'code' => 'CITIZEN-RETAIL',
        'is_active' => true,
    ]);

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Create')
            ->where('intakeAudience', 'citizen')
            ->where('currentApplicationYear', now()->year)
            ->where('applicationTypes', [[
                'label' => 'New',
                'value' => PermitApplicationType::New->value,
            ]])
            ->where('lineOfBusinesses.0.id', $lineOfBusiness->id)
            ->where('applicant.name', $citizen->name)
            ->where('applicant.email', $citizen->email)
        );

    $this->actingAs($citizen)
        ->get(route('staff.permit-applications.create'))
        ->assertForbidden();
});

test('citizens can save an owned new permit draft with multiple activities', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::CreateOwnPermitApplications,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);
    $retail = LineOfBusiness::factory()->create([
        'name' => 'Retail Store',
        'code' => 'CITIZEN-MULTI-RETAIL',
    ]);
    $repair = LineOfBusiness::factory()->create([
        'name' => 'Repair Services',
        'code' => 'CITIZEN-MULTI-REPAIR',
    ]);

    $response = $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.store'), citizenPermitDraftPayload([
            'lines' => [
                [
                    'line_of_business_id' => $retail->id,
                    'declared_gross_sales_pesos' => '125000.50',
                    'capital_investment_pesos' => '75000.25',
                    'quantity' => 1,
                    'started_on' => '2020-01-15',
                ],
                [
                    'line_of_business_id' => $repair->id,
                    'declared_gross_sales_pesos' => '45000.75',
                    'capital_investment_pesos' => '15000.50',
                    'quantity' => 2,
                    'started_on' => '2021-06-01',
                ],
            ],
        ]));

    $application = PermitApplication::query()
        ->with('lines.lineOfBusiness')
        ->whereBelongsTo($citizen, 'submittedBy')
        ->sole();

    $response->assertRedirect(route('citizen.permit-applications.show', $application));

    expect($application->application_number)->toBeNull()
        ->and($application->type)->toBe(PermitApplicationType::New)
        ->and($application->status)->toBe(PermitApplicationStatus::Draft)
        ->and($application->submitted_by_id)->toBe($citizen->id)
        ->and($application->assessments()->count())->toBe(0)
        ->and($application->lines)->toHaveCount(2)
        ->and($application->lines[0]->lineOfBusiness->code)->toBe('CITIZEN-MULTI-RETAIL')
        ->and($application->lines[0]->declared_gross_sales_cents)->toBe(12_500_050)
        ->and($application->lines[1]->lineOfBusiness->code)->toBe('CITIZEN-MULTI-REPAIR')
        ->and($application->lines[1]->capital_investment_cents)->toBe(1_500_050);

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('citizen/permit-applications/Show')
            ->where('permitApplication.id', $application->id)
            ->where('permitApplication.display_reference', 'Draft #'.$application->id)
            ->where('permitApplication.status', PermitApplicationStatus::Draft->value)
            ->where('permitApplication.draft_boundary.is_draft', true)
            ->where('permitApplication.draft_boundary.assessment_started', false)
            ->where('permitApplication.draft_boundary.official_application_number_assigned', false)
            ->has('permitApplication.lines', 2)
            ->where('permitApplication.lines.0.line_of_business.code', 'CITIZEN-MULTI-RETAIL')
            ->where('permitApplication.lines.1.line_of_business.code', 'CITIZEN-MULTI-REPAIR')
        );
});

test('citizen application lists and details are scoped to the authenticated submitter', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);
    $otherCitizen = User::factory()->create([
        'role_id' => $citizen->role_id,
    ]);
    $ownedApplication = PermitApplication::factory()->for($citizen, 'submittedBy')->create([
        'application_number' => null,
        'status' => PermitApplicationStatus::Draft,
    ]);
    $otherApplication = PermitApplication::factory()->for($otherCitizen, 'submittedBy')->create([
        'application_number' => null,
        'status' => PermitApplicationStatus::Draft,
    ]);
    PermitApplicationLine::factory()->for($ownedApplication)->create();
    PermitApplicationLine::factory()->for($otherApplication)->create();

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('citizen/permit-applications/Index')
            ->has('permitApplications.data', 1)
            ->where('permitApplications.data.0.id', $ownedApplication->id)
        );

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $otherApplication))
        ->assertNotFound();
});

test('citizen intake refuses official numbers and policy-sensitive application types', function (array $overrides, string $error): void {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::CreateOwnPermitApplications,
    ], UserRole::Citizen);
    $lineOfBusiness = LineOfBusiness::factory()->create();

    $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.store'), citizenPermitDraftPayload([
            'lines' => [[
                'line_of_business_id' => $lineOfBusiness->id,
                'declared_gross_sales_pesos' => '1000.00',
                'capital_investment_pesos' => '500.00',
                'quantity' => 1,
            ]],
            ...$overrides,
        ]))
        ->assertSessionHasErrors($error);

    expect(PermitApplication::query()->count())->toBe(0)
        ->and(Business::query()->count())->toBe(0)
        ->and(BusinessOwner::query()->count())->toBe(0);
})->with([
    'official application number' => [
        ['application_number' => 'APP-CITIZEN-NOT-ALLOWED'],
        'application_number',
    ],
    'renewal application' => [
        ['type' => PermitApplicationType::Renewal->value],
        'type',
    ],
    'future application year' => [
        ['application_year' => now()->year + 1],
        'application_year',
    ],
]);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function citizenPermitDraftPayload(array $overrides = []): array
{
    return [
        'owner_name' => 'Citizen Applicant',
        'owner_email' => 'citizen@example.test',
        'owner_phone' => '09171234567',
        'owner_address' => 'Poblacion, Ipil',
        'business_name' => 'Citizen Trading',
        'trade_name' => 'Citizen Store',
        'business_address' => 'Market Road',
        'barangay' => 'Poblacion',
        'type' => PermitApplicationType::New->value,
        'application_year' => now()->year,
        ...$overrides,
    ];
}
