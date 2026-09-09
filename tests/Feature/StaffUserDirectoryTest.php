<?php

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\BusinessOwner;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authorized staff can search and filter the read-only user directory', function () {
    $operator = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewUsers,
    ], UserRole::Bplo);
    $citizenRole = Role::factory()->create([
        'name' => 'Citizen',
        'code' => UserRole::Citizen->value,
    ]);
    $owner = BusinessOwner::factory()->create(['name' => 'Linked Legal Owner']);
    userWithRole($citizenRole, [
        'name' => 'Maria Citizen',
        'email' => 'maria@example.test',
        'business_owner_id' => $owner->id,
    ]);
    User::factory()->unverified()->create([
        'name' => 'Unassigned Account',
        'email' => 'unassigned@example.test',
    ]);

    $this->actingAs($operator)
        ->get(route('staff.users.index', [
            'q' => 'Maria',
            'role' => UserRole::Citizen->value,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/Access')
            ->where('auth.can_view_users', true)
            ->where('filters.q', 'Maria')
            ->where('filters.role', UserRole::Citizen->value)
            ->where('summary.user_count', 3)
            ->where('summary.verified_user_count', 2)
            ->where('summary.linked_owner_count', 1)
            ->where('summary.unassigned_role_count', 1)
            ->where('summary.role_distribution.bplo', 1)
            ->where('summary.role_distribution.citizen', 1)
            ->where('assignable_roles', fn ($roles): bool => $roles->pluck('value')->contains(UserRole::Bplo->value))
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Maria Citizen')
            ->where('users.data.0.email', 'maria@example.test')
            ->where('users.data.0.roles.0.code', UserRole::Citizen->value)
            ->where('users.data.0.business_owner.name', 'Linked Legal Owner')
            ->missing('users.data.0.password')
        );
});

test('staff without user visibility permission cannot open the directory', function () {
    $operator = userWithPermissions([
        UserPermission::AccessStaff,
    ], UserRole::Bplo);

    $this->actingAs($operator)
        ->get(route('staff.users.index'))
        ->assertForbidden();
});

test('admin can view users through the runtime role override', function () {
    $adminRole = Role::factory()->create([
        'name' => 'Admin',
        'code' => UserRole::Admin->value,
    ]);
    $admin = userWithRole($adminRole);

    expect($adminRole->permissions()->count())->toBe(0)
        ->and($admin->can(UserPermission::ViewUsers->value))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('staff.users.index'))
        ->assertSuccessful();
});

test('user administration presents provisioning and access controls', function () {
    $page = file_get_contents(resource_path('js/pages/users/Access.vue'));

    expect($page)
        ->toContain('Users & Access')
        ->toContain('Provision laboratory actors')
        ->toContain('New staff account')
        ->toContain('Institutional roles')
        ->toContain('Save access');
});
