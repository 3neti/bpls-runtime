<?php

use App\Actions\BuildCitizenProfile;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('Citizen Profile follows only the explicit owner link and projects multiple businesses without sensitive registry fields', function () {
    $owner = BusinessOwner::factory()->create([
        'name' => 'Linked Registry Owner',
        'email' => 'private-owner@example.test',
        'phone' => 'private-phone',
        'address' => 'private-address',
        'legacy_source_id' => 'private-legacy-owner-id',
        'metadata' => ['tin' => 'private-tin', 'birth_date' => '1990-01-01'],
    ]);
    $citizen = User::factory()->create(['business_owner_id' => $owner->id]);
    $unrelatedCitizen = User::factory()->create();
    $businesses = collect([
        Business::factory()->create([
            'business_owner_id' => $owner->id,
            'name' => 'Alpha Market',
            'legacy_source_id' => 'private-alpha-id',
            'metadata' => ['source_address' => 'private-alpha-address'],
        ]),
        Business::factory()->create([
            'business_owner_id' => $owner->id,
            'name' => 'Beta Kitchen',
            'legacy_source_id' => 'private-beta-id',
            'metadata' => ['source_address' => 'private-beta-address'],
        ]),
    ]);
    $activities = collect(['Retail Trading', 'Food Service'])
        ->map(fn (string $name): LineOfBusiness => LineOfBusiness::factory()->create(['name' => $name]));

    $applications = $businesses->values()->map(function (Business $business, int $index) use ($activities, $unrelatedCitizen): PermitApplication {
        $application = PermitApplication::factory()->create([
            'business_id' => $business->id,
            'submitted_by_id' => $unrelatedCitizen->id,
            'type' => $index === 0 ? PermitApplicationType::Renewal : PermitApplicationType::New,
            'status' => PermitApplicationStatus::PendingPayment,
            'metadata' => ['private_application_fact' => 'must-not-project'],
            'legacy_source_id' => 'private-application-'.$index,
        ]);
        PermitApplicationLine::factory()->create([
            'permit_application_id' => $application->id,
            'line_of_business_id' => $activities[$index]->id,
            'metadata' => ['private_declaration_fact' => 'must-not-project'],
        ]);
        $assessment = Assessment::factory()->create(['permit_application_id' => $application->id]);
        PaymentSchedule::factory()->create([
            'permit_application_id' => $application->id,
            'assessment_id' => $assessment->id,
            'status' => PaymentScheduleStatus::Pending,
            'total_amount_cents' => 122_000 + $index,
            'paid_amount_cents' => $index,
            'source_snapshot' => ['private_financial_fact' => 'must-not-project'],
        ]);

        return $application;
    });

    $submittedByCitizen = PermitApplication::factory()->create([
        'submitted_by_id' => $citizen->id,
    ]);

    $profile = app(BuildCitizenProfile::class)->handle($citizen, includeFinancials: true);
    $encodedProfile = json_encode($profile, JSON_THROW_ON_ERROR);

    expect($profile)
        ->toMatchArray([
            'linked' => true,
            'owner' => ['id' => $owner->id, 'name' => 'Linked Registry Owner'],
        ])
        ->and($profile['businesses'])->toHaveCount(2)
        ->and(array_column($profile['businesses'], 'name'))->toBe(['Alpha Market', 'Beta Kitchen'])
        ->and(data_get($profile, 'businesses.0.permit_applications.0.id'))->toBe($applications[0]->id)
        ->and(data_get($profile, 'businesses.1.permit_applications.0.id'))->toBe($applications[1]->id)
        ->and(data_get($profile, 'businesses.0.permit_applications.0.lines_of_business'))->toBe(['Retail Trading'])
        ->and(data_get($profile, 'businesses.1.permit_applications.0.lines_of_business'))->toBe(['Food Service'])
        ->and(data_get($profile, 'businesses.0.permit_applications.0.payable'))->toBe([
            'status' => 'pending',
            'amount_due_cents' => 122_000,
        ])
        ->and(collect($profile['businesses'])->flatMap(fn (array $business): array => array_column($business['permit_applications'], 'id'))
            ->contains($submittedByCitizen->id))->toBeFalse()
        ->and($encodedProfile)
        ->not->toContain('private-owner@example.test')
        ->not->toContain('private-phone')
        ->not->toContain('private-address')
        ->not->toContain('private-legacy')
        ->not->toContain('private-alpha')
        ->not->toContain('private-beta')
        ->not->toContain('private-application')
        ->not->toContain('private-financial')
        ->not->toContain('private-declaration')
        ->not->toContain('metadata')
        ->not->toContain('legacy_source_id');
});

test('Citizen Profile returns a truthful empty projection for an unlinked account', function () {
    $citizen = User::factory()->create(['business_owner_id' => null]);
    PermitApplication::factory()->create(['submitted_by_id' => $citizen->id]);

    expect(app(BuildCitizenProfile::class)->handle($citizen, includeFinancials: true))->toBe([
        'linked' => false,
        'owner' => null,
        'businesses' => [],
    ]);
});

test('Citizen Profile suppresses payable truth without Citizen financial visibility', function () {
    $application = PermitApplication::factory()->create();
    $citizen = User::factory()->create([
        'business_owner_id' => $application->business->business_owner_id,
    ]);
    $assessment = Assessment::factory()->create(['permit_application_id' => $application->id]);
    PaymentSchedule::factory()->create([
        'permit_application_id' => $application->id,
        'assessment_id' => $assessment->id,
        'total_amount_cents' => 999_999,
    ]);

    $profile = app(BuildCitizenProfile::class)->handle($citizen, includeFinancials: false);

    expect(data_get($profile, 'businesses.0.permit_applications.0.payable'))->toBeNull();
});

test('Citizen Profile surface requires Citizen application visibility and returns the backend projection', function () {
    $application = PermitApplication::factory()->create([
        'type' => PermitApplicationType::Renewal,
        'status' => PermitApplicationStatus::PendingPayment,
    ]);
    $application->business->update(['name' => 'Citizen Surface Business']);
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $citizen->forceFill([
        'business_owner_id' => $application->business->business_owner_id,
    ])->save();

    $this->withoutVite()
        ->actingAs($citizen)
        ->get(route('citizen.profile.show'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('citizen/profile/Show', false)
            ->where('profile.linked', true)
            ->where('profile.businesses.0.name', 'Citizen Surface Business')
            ->where('profile.businesses.0.permit_applications.0.id', $application->id));

    $accessOnlyRole = Role::factory()->create(['code' => 'citizen-access-only']);
    $accessOnlyRole->permissions()->attach(
        Permission::query()->where('code', UserPermission::AccessCitizen->value)->sole(),
    );
    $citizenWithoutVisibility = User::factory()->create(['role_id' => $accessOnlyRole->id]);

    $this->actingAs($citizenWithoutVisibility)
        ->get(route('citizen.profile.show'))
        ->assertForbidden();
});
