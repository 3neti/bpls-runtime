<?php

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * @param  array<int, UserPermission|string>  $permissions
 */
function userWithPermissions(array $permissions, UserRole $roleCode = UserRole::Bplo): User
{
    $role = Role::factory()->create([
        'name' => str($roleCode->value)->replace('_', ' ')->title()->toString(),
        'code' => $roleCode->value,
    ]);

    $role->permissions()->sync(collect($permissions)
        ->map(fn (\App\Enums\UserPermission|string $permission) => Permission::factory()->create([
            'name' => str($permission instanceof UserPermission ? $permission->value : $permission)->replace('.', ' ')->title()->toString(),
            'code' => $permission instanceof UserPermission ? $permission->value : $permission,
        ])->id)
        ->all());

    return User::factory()->create([
        'role_id' => $role->id,
    ]);
}
