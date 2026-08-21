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
    User::factory()->create([
        'name' => 'Maria Citizen',
        'email' => 'maria@example.test',
        'role_id' => $citizenRole->id,
        'business_owner_id' => $owner->id,
    ]);
    User::factory()->unverified()->create([
        'name' => 'Unassigned Account',
        'email' => 'unassigned@example.test',
        'role_id' => null,
    ]);

    $this->actingAs($operator)
        ->get(route('staff.users.index', [
            'q' => 'Maria',
            'role' => UserRole::Citizen->value,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/Index')
            ->where('auth.can_view_users', true)
            ->where('filters.q', 'Maria')
            ->where('filters.role', UserRole::Citizen->value)
            ->where('summary.user_count', 3)
            ->where('summary.verified_user_count', 2)
            ->where('summary.linked_owner_count', 1)
            ->where('summary.unassigned_role_count', 1)
            ->where('summary.role_distribution.bplo', 1)
            ->where('summary.role_distribution.citizen', 1)
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Maria Citizen')
            ->where('users.data.0.email', 'maria@example.test')
            ->where('users.data.0.role.code', UserRole::Citizen->value)
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
    $admin = User::factory()->create(['role_id' => $adminRole->id]);

    expect($adminRole->permissions()->count())->toBe(0)
        ->and($admin->can(UserPermission::ViewUsers->value))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('staff.users.index'))
        ->assertSuccessful();
});

test('user directory presents its read-only identity boundary', function () {
    $page = file_get_contents(resource_path('js/pages/users/Index.vue'));
    $scopePanel = file_get_contents(resource_path('js/components/administration/AdministrationScopePanel.vue'));

    expect($page)
        ->toContain('AdministrationScopePanel')
        ->toContain('An account, a legal business-owner identity, and the person who submitted an application are separate records')
        ->toContain('Creating accounts, changing roles, activating or deactivating access, and resetting passwords')
        ->and($scopePanel)
        ->toContain('Available now')
        ->toContain('What this means')
        ->toContain('Unavailable here');
});
