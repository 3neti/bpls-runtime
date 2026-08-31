<?php

use App\Actions\BuildCitizenBusinessDetail;
use App\Actions\BuildCitizenIdentityDetail;
use App\Enums\AssessmentStatus;
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

test('citizen identity detail exposes only first-class owner facts and all explicitly owned businesses', function () {
    $owner = BusinessOwner::factory()->create([
        'name' => 'Canonical Citizen Owner',
        'email' => 'owner@example.test',
        'phone' => '09171234567',
        'address' => 'Canonical owner address',
        'legacy_source_id' => 'legacy-owner-secret',
        'metadata' => [
            'legacy_birth_date' => '1990-01-01',
            'legacy_barangay_id' => 'secret-barangay-id',
        ],
    ]);
    $citizen = User::factory()->create(['business_owner_id' => $owner->id]);
    Business::factory()->for($owner, 'owner')->create([
        'name' => 'Beta Kitchen',
        'trade_name' => 'Beta',
    ]);
    Business::factory()->for($owner, 'owner')->create([
        'name' => 'Alpha Market',
        'trade_name' => null,
    ]);

    $identity = app(BuildCitizenIdentityDetail::class)->handle($citizen);
    $encodedIdentity = json_encode($identity, JSON_THROW_ON_ERROR);

    expect($identity)->toMatchArray([
        'linked' => true,
        'owner' => [
            'id' => $owner->id,
            'name' => 'Canonical Citizen Owner',
            'email' => 'owner@example.test',
            'phone' => '09171234567',
            'address' => 'Canonical owner address',
        ],
    ])
        ->and(array_column($identity['businesses'], 'name'))->toBe(['Alpha Market', 'Beta Kitchen'])
        ->and($encodedIdentity)
        ->not->toContain('legacy-owner-secret')
        ->not->toContain('1990-01-01')
        ->not->toContain('secret-barangay-id')
        ->not->toContain('metadata')
        ->not->toContain('legacy_source_id');
});

test('business detail follows owner identity rather than submission actor and projects canonical lifecycle truth', function () {
    $owner = BusinessOwner::factory()->create(['name' => 'Linked Owner']);
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $citizen->forceFill(['business_owner_id' => $owner->id])->save();
    $submissionActor = User::factory()->create();
    $business = Business::factory()->for($owner, 'owner')->create([
        'name' => 'Canonical Market and Kitchen',
        'trade_name' => 'Canonical Market',
        'registration_number' => 'CANONICAL-REG-001',
        'address' => 'Canonical business address',
        'barangay' => 'Canonical Barangay',
        'ownership_type' => 'sole-proprietorship',
        'registered_on' => '2025-06-01',
        'legacy_source_id' => 'legacy-business-secret',
        'metadata' => ['legacy_registration_number' => 'legacy-registration-secret'],
    ]);
    $activities = collect(['Retail Trading', 'Food Service'])
        ->map(fn (string $name): LineOfBusiness => LineOfBusiness::factory()->create(['name' => $name]));
    $application = PermitApplication::factory()->for($business)->create([
        'submitted_by_id' => $submissionActor->id,
        'type' => PermitApplicationType::Renewal,
        'status' => PermitApplicationStatus::PendingPayment,
        'application_year' => 2026,
        'metadata' => ['legacy_application_secret' => true],
    ]);
    $activities->each(fn (LineOfBusiness $activity) => PermitApplicationLine::factory()->create([
        'permit_application_id' => $application->id,
        'line_of_business_id' => $activity->id,
    ]));
    $assessment = Assessment::factory()->for($application)->create([
        'sequence' => 1,
        'status' => AssessmentStatus::Computed,
        'total_amount_cents' => 122_000,
    ]);
    $schedule = PaymentSchedule::factory()
        ->for($application, 'permitApplication')
        ->for($assessment)
        ->create([
            'sequence' => 1,
            'status' => PaymentScheduleStatus::Pending,
            'total_amount_cents' => 122_000,
            'paid_amount_cents' => 0,
            'source_snapshot' => ['legacy_financial_secret' => true],
        ]);

    $detail = app(BuildCitizenBusinessDetail::class)->handle($citizen, $business->id, includeFinancials: true);
    $encodedDetail = json_encode($detail, JSON_THROW_ON_ERROR);

    expect($detail)->toMatchArray([
        'id' => $business->id,
        'name' => 'Canonical Market and Kitchen',
        'registration_number' => 'CANONICAL-REG-001',
        'barangay' => 'Canonical Barangay',
        'registered_on' => '2025-06-01',
        'owner' => ['id' => $owner->id, 'name' => 'Linked Owner'],
    ])
        ->and(data_get($detail, 'permit_applications.0.id'))->toBe($application->id)
        ->and(data_get($detail, 'permit_applications.0.type'))->toBe('renewal')
        ->and(data_get($detail, 'permit_applications.0.status'))->toBe('pending_payment')
        ->and(data_get($detail, 'permit_applications.0.lines_of_business.*.name'))->toBe(['Retail Trading', 'Food Service'])
        ->and(data_get($detail, 'permit_applications.0.assessment.total_amount_cents'))->toBe(122_000)
        ->and(data_get($detail, 'permit_applications.0.payable'))->toMatchArray([
            'id' => $schedule->id,
            'status' => 'pending',
            'amount_due_cents' => 122_000,
        ])
        ->and($encodedDetail)
        ->not->toContain('legacy-business-secret')
        ->not->toContain('legacy-registration-secret')
        ->not->toContain('legacy_application_secret')
        ->not->toContain('legacy_financial_secret')
        ->not->toContain('metadata')
        ->not->toContain('legacy_source_id');

    $this->withoutVite()
        ->actingAs($citizen)
        ->get(route('citizen.businesses.show', $business))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('citizen/businesses/Show', false)
            ->where('business.id', $business->id)
            ->where('business.permit_applications.0.assessment.total_amount_cents', 122_000)
            ->where('business.permit_applications.0.payable.amount_due_cents', 122_000));
});

test('citizen detail routes enforce permissions and exact owner isolation for multiple businesses', function () {
    $owner = BusinessOwner::factory()->create();
    $ownedBusinesses = Business::factory()->count(2)->for($owner, 'owner')->create();
    $otherBusiness = Business::factory()->create();
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);
    $citizen->forceFill(['business_owner_id' => $owner->id])->save();

    PermitApplication::factory()->for($otherBusiness)->create(['submitted_by_id' => $citizen->id]);

    $this->withoutVite()
        ->actingAs($citizen)
        ->get(route('citizen.profile.identity.show'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('citizen/profile/Identity', false)
            ->where('identity.linked', true)
            ->has('identity.businesses', 2));

    foreach ($ownedBusinesses as $ownedBusiness) {
        $this->actingAs($citizen)
            ->get(route('citizen.businesses.show', $ownedBusiness))
            ->assertSuccessful();
    }

    $this->actingAs($citizen)
        ->get(route('citizen.businesses.show', $otherBusiness))
        ->assertNotFound();

    $accessOnlyRole = Role::factory()->create(['code' => 'citizen-detail-access-only']);
    $accessOnlyRole->permissions()->attach(
        Permission::query()->where('code', UserPermission::AccessCitizen->value)->sole(),
    );
    $citizenWithoutVisibility = User::factory()->create(['role_id' => $accessOnlyRole->id]);

    $this->actingAs($citizenWithoutVisibility)
        ->get(route('citizen.profile.identity.show'))
        ->assertForbidden();
    $this->actingAs($citizenWithoutVisibility)
        ->get(route('citizen.businesses.show', $ownedBusinesses->first()))
        ->assertForbidden();
});

test('generic unlinked citizen stays empty and cannot gain business visibility through submitted applications', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $business = Business::factory()->create();
    PermitApplication::factory()->for($business)->create(['submitted_by_id' => $citizen->id]);

    expect(app(BuildCitizenIdentityDetail::class)->handle($citizen))->toBe([
        'linked' => false,
        'owner' => null,
        'businesses' => [],
    ]);

    $this->withoutVite()
        ->actingAs($citizen)
        ->get(route('citizen.profile.identity.show'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('citizen/profile/Identity', false)
            ->where('identity.linked', false)
            ->where('identity.owner', null)
            ->has('identity.businesses', 0));

    $this->actingAs($citizen)
        ->get(route('citizen.businesses.show', $business))
        ->assertNotFound();
});

test('business detail suppresses assessment and payable truth without financial visibility', function () {
    $application = PermitApplication::factory()->create();
    $citizen = User::factory()->create([
        'business_owner_id' => $application->business->business_owner_id,
    ]);
    $assessment = Assessment::factory()->for($application)->create(['total_amount_cents' => 999_999]);
    PaymentSchedule::factory()
        ->for($application, 'permitApplication')
        ->for($assessment)
        ->create(['total_amount_cents' => 999_999]);

    $detail = app(BuildCitizenBusinessDetail::class)->handle(
        $citizen,
        $application->business_id,
        includeFinancials: false,
    );

    expect(data_get($detail, 'permit_applications.0.assessment'))->toBeNull()
        ->and(data_get($detail, 'permit_applications.0.payable'))->toBeNull();
});
