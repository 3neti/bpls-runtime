<?php

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authorized staff sees effective role access and permission catalog drift', function () {
    $operator = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewRoles,
    ], UserRole::Bplo);
    $adminRole = Role::factory()->create([
        'name' => 'Admin',
        'code' => UserRole::Admin->value,
    ]);
    User::factory()->create(['role_id' => $adminRole->id]);
    $unknownPermission = Permission::factory()->create([
        'name' => 'Legacy Unknown',
        'code' => 'legacy.unknown',
    ]);
    $operator->role->permissions()->attach($unknownPermission);

    $this->actingAs($operator)
        ->get(route('staff.roles.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('roles/Index')
            ->where('auth.can_view_roles', true)
            ->where('summary.role_count', 2)
            ->where('summary.assigned_user_count', 2)
            ->where('summary.canonical_permission_count', count(UserPermission::cases()))
            ->where('summary.stored_permission_count', 3)
            ->where('summary.catalog_in_sync', false)
            ->where('summary.unknown_permission_count', 1)
            ->where('catalog_drift.unknown_permission_codes', ['legacy.unknown'])
            ->has('roles', 2)
            ->where('roles.0.code', UserRole::Admin->value)
            ->where('roles.0.access_mode', 'admin_override')
            ->where('roles.0.assigned_permission_count', 0)
            ->where('roles.0.effective_permission_count', count(UserPermission::cases()))
            ->where('roles.0.permissions.0.source', 'admin_override')
            ->where('roles.1.code', UserRole::Bplo->value)
            ->where('roles.1.access_mode', 'assigned_permissions')
            ->where('roles.1.unknown_assigned_permission_codes', ['legacy.unknown'])
            ->where('roles.1.permissions.'.array_search(UserPermission::ViewRoles, UserPermission::cases(), true).'.source', 'assigned')
        );
});

test('staff without role visibility permission cannot open the matrix', function () {
    $operator = userWithPermissions([
        UserPermission::AccessStaff,
    ], UserRole::Bplo);

    $this->actingAs($operator)
        ->get(route('staff.roles.index'))
        ->assertForbidden();
});

test('admin effective access does not depend on assigned permission rows', function () {
    $adminRole = Role::factory()->create([
        'name' => 'Admin',
        'code' => UserRole::Admin->value,
    ]);
    $admin = User::factory()->create(['role_id' => $adminRole->id]);

    expect($adminRole->permissions()->count())->toBe(0)
        ->and($admin->can(UserPermission::ViewRoles->value))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('staff.roles.index'))
        ->assertSuccessful();
});

test('role matrix presents stored and effective access as read-only evidence', function () {
    $page = file_get_contents(resource_path('js/pages/roles/Index.vue'));

    expect($page)
        ->toContain('AdministrationScopePanel')
        ->toContain('Directly assigned access and full system-administrator access are shown separately')
        ->toContain('Creating or editing roles, changing access, assigning users to roles');
});
