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
        UserPermission::EditOwnPermitApplications,
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
        UserPermission::EditOwnPermitApplications,
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
        UserPermission::EditOwnPermitApplications,
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
        ->and($application->submitted_at)->toBeNull()
        ->and($application->type)->toBe(PermitApplicationType::New)
        ->and($application->status)->toBe(PermitApplicationStatus::Draft)
        ->and($application->submitted_by_id)->toBe($citizen->id)
        ->and($citizen->refresh()->business_owner_id)->toBe($application->business->business_owner_id)
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

test('citizens can create a draft for an existing linked business without mutating registry facts', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::CreateOwnPermitApplications,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);
    $owner = BusinessOwner::factory()->create(['name' => 'Durable Legal Owner']);
    $business = Business::factory()->for($owner, 'owner')->create(['name' => 'Durable Registry Business']);
    $citizen->forceFill(['business_owner_id' => $owner->id])->save();
    $lineOfBusiness = LineOfBusiness::factory()->create();

    $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.store'), citizenPermitDraftPayload([
            'business_id' => $business->id,
            'owner_name' => 'Must Not Replace Owner',
            'business_name' => 'Must Not Replace Business',
            'lines' => [[
                'line_of_business_id' => $lineOfBusiness->id,
                'declared_gross_sales_pesos' => '1000.00',
                'capital_investment_pesos' => '500.00',
                'quantity' => 1,
            ]],
        ]))
        ->assertRedirect();

    $application = PermitApplication::query()->whereBelongsTo($citizen, 'submittedBy')->sole();

    expect($application->business_id)->toBe($business->id)
        ->and($application->submitted_at)->toBeNull()
        ->and($application->application_number)->toBeNull()
        ->and($owner->refresh()->name)->toBe('Durable Legal Owner')
        ->and($business->refresh()->name)->toBe('Durable Registry Business')
        ->and(BusinessOwner::query()->count())->toBe(1)
        ->and(Business::query()->count())->toBe(1);
});

test('citizen existing-business selection is server scoped to the linked legal owner', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::CreateOwnPermitApplications,
    ], UserRole::Citizen);
    $owner = BusinessOwner::factory()->create();
    $ownedBusiness = Business::factory()->for($owner, 'owner')->create([
        'name' => 'Owned Registry Business',
    ]);
    $otherBusiness = Business::factory()->create([
        'name' => 'Other Owner Business',
    ]);
    $citizen->forceFill(['business_owner_id' => $owner->id])->save();
    $lineOfBusiness = LineOfBusiness::factory()->create();

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Create')
            ->has('registry.businesses', 1)
            ->where('registry.businesses.0.id', $ownedBusiness->id)
            ->where('registry.businesses.0.name', 'Owned Registry Business')
        );

    $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.store'), citizenPermitDraftPayload([
            'business_id' => $otherBusiness->id,
            'lines' => [[
                'line_of_business_id' => $lineOfBusiness->id,
                'declared_gross_sales_pesos' => '1000.00',
                'capital_investment_pesos' => '500.00',
                'quantity' => 1,
            ]],
        ]))
        ->assertSessionHasErrors('business_id');

    expect(PermitApplication::query()->count())->toBe(0)
        ->and($ownedBusiness->refresh()->business_owner_id)->toBe($owner->id)
        ->and($otherBusiness->refresh()->business_owner_id)->not->toBe($owner->id);
});

test('citizens can edit an owned draft and atomically replace its activities', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::CreateOwnPermitApplications,
        UserPermission::EditOwnPermitApplications,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);
    $retail = LineOfBusiness::factory()->create(['code' => 'EDIT-RETAIL']);
    $services = LineOfBusiness::factory()->create(['code' => 'EDIT-SERVICES']);

    $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.store'), citizenPermitDraftPayload([
            'lines' => [[
                'line_of_business_id' => $retail->id,
                'declared_gross_sales_pesos' => '1000.00',
                'capital_investment_pesos' => '500.00',
                'quantity' => 1,
            ]],
        ]))
        ->assertRedirect();

    $application = PermitApplication::query()->whereBelongsTo($citizen, 'submittedBy')->sole();
    $citizen->refresh();
    $draftVersion = $application->updated_at->toIso8601String();

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.edit', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Create')
            ->where('draft.id', $application->id)
            ->where('draft.draft_version', $draftVersion)
            ->where('draft.business_name', 'Citizen Trading')
            ->where('draft.lines.0.line_of_business_id', $retail->id)
            ->where('draft.lines.0.declared_gross_sales_pesos', '1000.00')
        );

    $response = $this->actingAs($citizen)
        ->patch(route('citizen.permit-applications.update', $application), citizenPermitDraftPayload([
            'draft_version' => $draftVersion,
            'business_name' => 'Citizen Trading Must Remain Unchanged',
            'lines' => [
                [
                    'line_of_business_id' => $retail->id,
                    'declared_gross_sales_pesos' => '1250.50',
                    'capital_investment_pesos' => '600.25',
                    'quantity' => 2,
                    'started_on' => '2020-01-15',
                ],
                [
                    'line_of_business_id' => $services->id,
                    'declared_gross_sales_pesos' => '450.75',
                    'capital_investment_pesos' => '150.50',
                    'quantity' => 3,
                    'started_on' => '2021-06-01',
                ],
            ],
        ]));

    $application->refresh()->load(['business', 'lines.lineOfBusiness']);

    $response->assertRedirect(route('citizen.permit-applications.show', $application));
    expect($application->business->name)->toBe('Citizen Trading')
        ->and($application->status)->toBe(PermitApplicationStatus::Draft)
        ->and($application->application_number)->toBeNull()
        ->and($application->assessments()->count())->toBe(0)
        ->and($application->submitted_by_id)->toBe($citizen->id)
        ->and($application->lines)->toHaveCount(2)
        ->and($application->lines[0]->lineOfBusiness->code)->toBe('EDIT-RETAIL')
        ->and($application->lines[0]->declared_gross_sales_cents)->toBe(125_050)
        ->and($application->lines[1]->lineOfBusiness->code)->toBe('EDIT-SERVICES')
        ->and($application->lines[1]->quantity)->toBe(3);
});

test('citizen draft editing is ownership and permission scoped', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::EditOwnPermitApplications,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);
    $otherCitizen = User::factory()->create(['role_id' => $citizen->role_id]);
    $application = PermitApplication::factory()->for($otherCitizen, 'submittedBy')->create([
        'application_number' => null,
        'status' => PermitApplicationStatus::Draft,
        'type' => PermitApplicationType::New,
    ]);
    PermitApplicationLine::factory()->for($application)->create();

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.edit', $application))
        ->assertNotFound();

    $citizen->role->permissions()
        ->where('code', UserPermission::EditOwnPermitApplications->value)
        ->detach();
    $citizenWithoutEditPermission = User::factory()->create([
        'role_id' => $citizen->role_id,
    ]);
    $ownedApplication = PermitApplication::factory()->for($citizenWithoutEditPermission, 'submittedBy')->create([
        'application_number' => null,
        'status' => PermitApplicationStatus::Draft,
        'type' => PermitApplicationType::New,
    ]);

    $this->actingAs($citizenWithoutEditPermission)
        ->get(route('citizen.permit-applications.edit', $ownedApplication))
        ->assertForbidden();
});

test('invalid citizen draft edits preserve all existing records', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::EditOwnPermitApplications,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);
    $lineOfBusiness = LineOfBusiness::factory()->create();
    $application = PermitApplication::factory()->for($citizen, 'submittedBy')->create([
        'application_number' => null,
        'status' => PermitApplicationStatus::Draft,
        'type' => PermitApplicationType::New,
    ]);
    linkPortalUserToApplicationOwner($citizen, $application);
    $line = PermitApplicationLine::factory()->for($application)->for($lineOfBusiness)->create([
        'declared_gross_sales_cents' => 100_000,
    ]);
    $originalBusinessName = $application->business->name;

    $this->actingAs($citizen)
        ->patch(route('citizen.permit-applications.update', $application), citizenPermitDraftPayload([
            'draft_version' => $application->updated_at->toIso8601String(),
            'business_name' => 'Must Not Persist',
            'lines' => [],
        ]))
        ->assertSessionHasErrors('lines');

    expect($application->business->refresh()->name)->toBe($originalBusinessName)
        ->and($application->lines()->count())->toBe(1)
        ->and($line->refresh()->declared_gross_sales_cents)->toBe(100_000);
});

test('citizen draft editing preserves shared registry records while updating declarations', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::EditOwnPermitApplications,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);
    $lineOfBusiness = LineOfBusiness::factory()->create();
    $application = PermitApplication::factory()->for($citizen, 'submittedBy')->create([
        'application_number' => null,
        'status' => PermitApplicationStatus::Draft,
        'type' => PermitApplicationType::New,
    ]);
    linkPortalUserToApplicationOwner($citizen, $application);
    $line = PermitApplicationLine::factory()->for($application)->for($lineOfBusiness)->create([
        'declared_gross_sales_cents' => 100_000,
    ]);
    PermitApplication::factory()->for($application->business)->create();
    Business::factory()->for($application->business->owner, 'owner')->create();
    $originalBusinessName = $application->business->name;

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.edit', $application))
        ->assertOk();

    $this->actingAs($citizen)
        ->patch(route('citizen.permit-applications.update', $application), citizenPermitDraftPayload([
            'draft_version' => $application->updated_at->toIso8601String(),
            'business_name' => 'Must Not Persist',
            'lines' => [[
                'line_of_business_id' => $lineOfBusiness->id,
                'declared_gross_sales_pesos' => '9999.99',
                'capital_investment_pesos' => '500.00',
                'quantity' => 1,
            ]],
        ]))
        ->assertRedirect(route('citizen.permit-applications.show', $application));

    expect($application->business->refresh()->name)->toBe($originalBusinessName)
        ->and(PermitApplicationLine::query()->whereKey($line)->exists())->toBeFalse()
        ->and($application->lines()->sole()->declared_gross_sales_cents)->toBe(999_999);
});

test('citizen draft update refuses stale or municipally processed records without mutation', function (array $applicationOverrides, bool $stale): void {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::EditOwnPermitApplications,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);
    $lineOfBusiness = LineOfBusiness::factory()->create();
    $applicationFactory = PermitApplication::factory()->for($citizen, 'submittedBy');
    if (($applicationOverrides['status'] ?? PermitApplicationStatus::Draft) !== PermitApplicationStatus::Draft) {
        $applicationFactory = $applicationFactory->withStatus($applicationOverrides['status']);
    }
    $application = $applicationFactory->create([
        'application_number' => null,
        'status' => PermitApplicationStatus::Draft,
        'type' => PermitApplicationType::New,
        ...$applicationOverrides,
    ]);
    linkPortalUserToApplicationOwner($citizen, $application);
    $line = PermitApplicationLine::factory()->for($application)->for($lineOfBusiness)->create([
        'declared_gross_sales_cents' => 100_000,
    ]);
    $draftVersion = $stale
        ? $application->updated_at->subSecond()->toIso8601String()
        : $application->updated_at->toIso8601String();

    $this->actingAs($citizen)
        ->patch(route('citizen.permit-applications.update', $application), citizenPermitDraftPayload([
            'draft_version' => $draftVersion,
            'business_name' => 'Must Not Persist',
            'lines' => [[
                'line_of_business_id' => $lineOfBusiness->id,
                'declared_gross_sales_pesos' => '9999.99',
                'capital_investment_pesos' => '500.00',
                'quantity' => 1,
            ]],
        ]))
        ->assertSessionHasErrors('draft');

    expect($application->business->refresh()->name)->not->toBe('Must Not Persist')
        ->and($line->refresh()->declared_gross_sales_cents)->toBe(100_000);
})->with([
    'stale browser version' => [[], true],
    'officially numbered application' => [['application_number' => 'APP-PROCESSED-001'], false],
    'non-draft application' => [['status' => PermitApplicationStatus::PendingPayment], false],
]);

test('citizen application lists and details are scoped to the authenticated portal owner', function () {
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
    linkPortalUserToApplicationOwner($citizen, $ownedApplication);
    linkPortalUserToApplicationOwner($otherCitizen, $otherApplication);
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
    'unapproved laboratory fixture' => [
        ['lab_fixture_id' => 'legacy-ipil-forged'],
        'lab_fixture_id',
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
