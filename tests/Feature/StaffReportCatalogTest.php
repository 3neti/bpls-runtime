<?php

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authorized staff can open the report catalog', function () {
    $operator = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ], UserRole::Bplo);

    $this->actingAs($operator)
        ->get(route('staff.reports.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->where('auth.can_view_reports', true)
        );
});

test('staff without report visibility permission cannot open the catalog', function () {
    $operator = userWithPermissions([
        UserPermission::AccessStaff,
    ], UserRole::Bplo);

    $this->actingAs($operator)
        ->get(route('staff.reports.index'))
        ->assertForbidden();
});

test('admin can open the report catalog through the runtime role override', function () {
    $adminRole = Role::factory()->create([
        'name' => 'Admin',
        'code' => UserRole::Admin->value,
    ]);
    $admin = User::factory()->create(['role_id' => $adminRole->id]);

    expect($admin->can(UserPermission::ViewReports->value))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('staff.reports.index'))
        ->assertSuccessful();
});
