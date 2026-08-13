<?php

use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected away from staff permit applications', function () {
    $this->get(route('staff.permit-applications.index'))
        ->assertRedirect(route('login'));
});

test('users without staff access cannot view staff permit applications', function () {
    $this->actingAs(userWithPermissions([]))
        ->get(route('staff.permit-applications.index'))
        ->assertForbidden();
});

test('staff users with view permission can list permit applications', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-2026-00010',
    ]);

    PermitApplicationLine::factory()
        ->for($application)
        ->for(LineOfBusiness::factory()->create(['name' => 'Retail Store']))
        ->create([
            'declared_gross_sales_cents' => 125_000_00,
        ]);

    $this->actingAs($user)
        ->get(route('staff.permit-applications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Index')
            ->where('permitApplications.data.0.application_number', 'APP-2026-00010')
            ->where('permitApplications.data.0.business.name', $application->business->name)
            ->where('permitApplications.data.0.lines.0.line_of_business.name', 'Retail Store')
            ->where('can.create_permit_applications', false)
            ->where('can.assess_permit_applications', false)
        );
});

test('staff users with create permission can view the intake form', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::CreatePermitApplications,
    ]);

    LineOfBusiness::factory()->create([
        'name' => 'Restaurant',
        'code' => 'RESTAURANT',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('staff.permit-applications.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Create')
            ->where('currentApplicationYear', now()->year)
            ->where('applicationTypes.0.value', PermitApplicationType::New->value)
            ->where('lineOfBusinesses.0.name', 'Restaurant')
        );
});

test('staff users with create permission can record a permit application', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::CreatePermitApplications,
        UserPermission::ViewPermitApplications,
    ]);

    $lineOfBusiness = LineOfBusiness::factory()->create([
        'name' => 'Retail Store',
        'code' => 'RETAIL',
    ]);

    $response = $this->actingAs($user)
        ->post(route('staff.permit-applications.store'), [
            'owner_name' => 'Ana Cruz',
            'owner_email' => 'ana@example.test',
            'owner_phone' => '09171234567',
            'owner_address' => 'Poblacion, Ipil',
            'business_name' => 'Ana Trading',
            'trade_name' => 'Ana Store',
            'registration_number' => 'DTI-12345',
            'business_address' => 'Market Road',
            'barangay' => 'Poblacion',
            'application_number' => 'APP-2026-00011',
            'type' => PermitApplicationType::New->value,
            'application_year' => 2026,
            'line_of_business_id' => $lineOfBusiness->id,
            'declared_gross_sales_pesos' => '1234.50',
            'capital_investment_pesos' => '5000.75',
            'quantity' => 2,
        ]);

    $application = PermitApplication::query()
        ->where('application_number', 'APP-2026-00011')
        ->sole();

    $response->assertRedirect(route('staff.permit-applications.show', $application));

    expect(BusinessOwner::query()->where('name', 'Ana Cruz')->exists())->toBeTrue()
        ->and(Business::query()->where('name', 'Ana Trading')->exists())->toBeTrue()
        ->and($application->submitted_by_id)->toBe($user->id)
        ->and($application->lines()->sole()->declared_gross_sales_cents)->toBe(123_450)
        ->and($application->lines()->sole()->capital_investment_cents)->toBe(500_075)
        ->and($application->lines()->sole()->quantity)->toBe(2);
});

test('staff users with view permission can review a permit application', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-2026-00012',
    ]);

    PermitApplicationLine::factory()
        ->for($application)
        ->for(LineOfBusiness::factory()->create(['name' => 'Restaurant']))
        ->create();

    $this->actingAs($user)
        ->get(route('staff.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Show')
            ->where('permitApplication.application_number', 'APP-2026-00012')
            ->where('permitApplication.lines.0.line_of_business.name', 'Restaurant')
            ->where('can.assess_permit_applications', false)
        );
});

test('staff users without create permission cannot record a permit application', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $lineOfBusiness = LineOfBusiness::factory()->create();

    $this->actingAs($user)
        ->post(route('staff.permit-applications.store'), [
            'owner_name' => 'Ana Cruz',
            'business_name' => 'Ana Trading',
            'type' => PermitApplicationType::New->value,
            'application_year' => 2026,
            'line_of_business_id' => $lineOfBusiness->id,
            'declared_gross_sales_pesos' => '100.00',
            'capital_investment_pesos' => '100.00',
            'quantity' => 1,
        ])
        ->assertForbidden();
});
