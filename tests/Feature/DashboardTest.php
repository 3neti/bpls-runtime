<?php

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
        );
});

test('citizen dashboard receives citizen access without staff navigation access', function () {
    $user = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.can_access_citizen', true)
            ->where('auth.can_access_staff', false)
            ->where('auth.can_view_permit_applications', false)
            ->where('auth.can_view_payment_schedules', false)
            ->where('auth.can_view_reports', false)
        );
});

test('shared navigation permissions project only effective assigned access', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::ViewPaymentSchedules,
        UserPermission::ViewReports,
        UserPermission::ViewUsers,
    ], UserRole::Bplo);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can_access_staff', true)
            ->where('auth.can_access_citizen', false)
            ->where('auth.can_view_permit_applications', true)
            ->where('auth.can_view_payment_schedules', true)
            ->where('auth.can_view_receipts', false)
            ->where('auth.can_view_billing_groups', false)
            ->where('auth.can_view_reports', true)
            ->where('auth.can_view_fee_rules', false)
            ->where('auth.can_view_users', true)
            ->where('auth.can_view_roles', false)
            ->where('auth.can_view_municipality_configuration', false)
        );
});

test('staff access does not escalate into navigation permissions', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
    ], UserRole::Bplo);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can_access_staff', true)
            ->where('auth.can_view_permit_applications', false)
            ->where('auth.can_view_payment_schedules', false)
            ->where('auth.can_view_receipts', false)
            ->where('auth.can_view_billing_groups', false)
            ->where('auth.can_view_reports', false)
            ->where('auth.can_view_fee_rules', false)
            ->where('auth.can_view_users', false)
            ->where('auth.can_view_roles', false)
            ->where('auth.can_view_municipality_configuration', false)
        );
});

test('admin navigation permissions reflect the existing runtime override', function () {
    $adminRole = Role::factory()->create([
        'name' => 'Admin',
        'code' => UserRole::Admin->value,
    ]);
    $admin = User::factory()->create(['role_id' => $adminRole->id]);

    expect($adminRole->permissions()->count())->toBe(0);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can_access_staff', true)
            ->where('auth.can_access_citizen', true)
            ->where('auth.can_view_permit_applications', true)
            ->where('auth.can_view_payment_schedules', true)
            ->where('auth.can_view_receipts', true)
            ->where('auth.can_view_billing_groups', true)
            ->where('auth.can_view_reports', true)
            ->where('auth.can_view_fee_rules', true)
            ->where('auth.can_view_users', true)
            ->where('auth.can_view_roles', true)
            ->where('auth.can_view_municipality_configuration', true)
        );
});
