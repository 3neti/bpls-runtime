<?php

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authorized staff can inspect municipality identity and explicit authority status', function () {
    config()->set('municipality.name', 'Municipality of Ipil');
    config()->set('municipality.province', 'Zamboanga Sibugay');
    config()->set('municipality.system_name', 'Business Permit and Licensing System');
    config()->set('municipality.signatories.permit', [
        [
            'role' => 'Municipal Mayor',
            'name' => 'Configured Mayor',
            'title' => 'Municipal Mayor',
            'authority_status' => 'verified',
        ],
        [
            'role' => 'BPLO Officer',
            'name' => 'Unverified BPLO Officer',
            'title' => 'BPLO Officer',
            'authority_status' => 'unverified',
        ],
    ]);
    $operator = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewMunicipalityConfiguration,
    ], UserRole::Bplo);

    $this->actingAs($operator)
        ->get(route('staff.municipality-configuration.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('municipality/Index')
            ->where('auth.can_view_municipality_configuration', true)
            ->where('identity.municipality_name', 'Municipality of Ipil')
            ->where('identity.province', 'Zamboanga Sibugay')
            ->where('identity.system_name', 'Business Permit and Licensing System')
            ->has('permit_signatories', 2)
            ->where('permit_signatories.0.role', 'Municipal Mayor')
            ->where('permit_signatories.0.authority_status', 'verified')
            ->where('permit_signatories.1.authority_status', 'unverified')
            ->where('authority.signatory_count', 2)
            ->where('authority.verified_signatory_count', 1)
            ->where('authority.unverified_signatory_count', 1)
            ->where('authority.all_signatories_verified', false)
            ->where('authority.permit_issuance_authorized', false)
            ->where('source.type', 'runtime_configuration')
            ->where('source.persisted_administration', false)
            ->where('source.read_only', true)
        );
});

test('staff without municipality configuration visibility cannot open the surface', function () {
    $operator = userWithPermissions([
        UserPermission::AccessStaff,
    ], UserRole::Bplo);

    $this->actingAs($operator)
        ->get(route('staff.municipality-configuration.index'))
        ->assertForbidden();
});

test('admin can inspect municipality configuration through the runtime role override', function () {
    $adminRole = Role::factory()->create([
        'name' => 'Admin',
        'code' => UserRole::Admin->value,
    ]);
    $admin = User::factory()->create(['role_id' => $adminRole->id]);

    expect($adminRole->permissions()->count())->toBe(0)
        ->and($admin->can(UserPermission::ViewMunicipalityConfiguration->value))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('staff.municipality-configuration.index'))
        ->assertSuccessful();
});
