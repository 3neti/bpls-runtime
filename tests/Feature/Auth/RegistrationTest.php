<?php

use App\Actions\EnsureCitizenRole;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    app(EnsureCitizenRole::class)->handle();
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    expect(auth()->user()->hasRole(UserRole::Citizen))->toBeTrue()
        ->and(auth()->user()->can(UserPermission::AccessCitizen->value))->toBeTrue()
        ->and(auth()->user()->can(UserPermission::CreateOwnPermitApplications->value))->toBeTrue()
        ->and(auth()->user()->can(UserPermission::EditOwnPermitApplications->value))->toBeTrue()
        ->and(auth()->user()->can(UserPermission::UploadOwnPermitApplicationDocuments->value))->toBeTrue()
        ->and(auth()->user()->can(UserPermission::ViewOwnPermitApplications->value))->toBeTrue()
        ->and(auth()->user()->can(UserPermission::ViewOwnPermitApplicationDocuments->value))->toBeTrue()
        ->and(auth()->user()->can(UserPermission::ViewOwnPermitApplicationFinancials->value))->toBeTrue();
});

test('registration assigns the provisioned Citizen role without writing shared authorization', function () {
    $role = app(EnsureCitizenRole::class)->handle();
    $extra = Permission::factory()->create(['code' => 'citizen.preexisting.extra']);
    $role->givePermissionTo($extra);
    $before = registrationAuthorizationFingerprint();
    DB::enableQueryLog();

    $this->post(route('register.store'), registrationInput())->assertRedirect();

    $writes = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => preg_match('/^(insert into|update|delete from) ["`]?((roles|permissions|role_has_permissions)["`]?)(\s|\()/i', $query) === 1,
    );
    DB::disableQueryLog();

    $this->assertAuthenticated();
    expect($writes)->toBeEmpty()
        ->and(registrationAuthorizationFingerprint())->toBe($before)
        ->and(auth()->user()->roles()->sole()->id)->toBe($role->id)
        ->and(auth()->user()->can($extra->code))->toBeTrue();
    $this->assertDatabaseMissing('permissions', ['code' => UserPermission::ViewOwnBusinessPermitEvaluations->value]);
    $this->assertDatabaseMissing('permissions', ['code' => UserPermission::CorrectOwnEvaluationDeclarations->value]);
});

test('registration fails visibly without provisioning a missing Citizen role or orphan user', function () {
    $before = registrationAuthorizationFingerprint();
    $this->from(route('register'))->post(route('register.store'), registrationInput())
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors(['email']);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
    expect(registrationAuthorizationFingerprint())->toBe($before);
});

test('registration fails closed when a required base permission is not assigned', function (UserPermission $permission) {
    $role = app(EnsureCitizenRole::class)->handle();
    $role->revokePermissionTo($permission->value);
    $before = registrationAuthorizationFingerprint();

    $this->post(route('register.store'), registrationInput())->assertSessionHasErrors(['email']);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
    expect(registrationAuthorizationFingerprint())->toBe($before);
})->with([
    UserPermission::AccessCitizen,
    UserPermission::CreateOwnPermitApplications,
    UserPermission::EditOwnPermitApplications,
    UserPermission::SubmitOwnPermitApplications,
    UserPermission::UploadOwnPermitApplicationDocuments,
    UserPermission::ViewOwnPermitApplications,
    UserPermission::ViewOwnPermitApplicationDocuments,
    UserPermission::ViewOwnPermitApplicationFinancials,
]);

/** @return array<string, string> */
function registrationInput(): array
{
    return [
        'name' => 'Registration Test',
        'email' => 'registration@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];
}

function registrationAuthorizationFingerprint(): string
{
    return hash('sha256', json_encode([
        'roles' => Role::query()->orderBy('id')->get()->toArray(),
        'permissions' => Permission::query()->orderBy('id')->get()->toArray(),
        'assignments' => DB::table('role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get()->toArray(),
    ], JSON_THROW_ON_ERROR));
}
