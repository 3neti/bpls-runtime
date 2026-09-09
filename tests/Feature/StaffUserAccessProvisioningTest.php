<?php

use App\Actions\EnsureBplsInstitution;
use App\Actions\ProvisionLifecycleLaboratoryActors;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAccessAudit;
use Illuminate\Support\Facades\Hash;

function provisioningAdministrator(): User
{
    app(EnsureBplsInstitution::class)->handle();

    return userWithRole(Role::query()->where('code', 'admin')->sole());
}

test('an administrator can provision a staff account with multiple institutional roles', function () {
    $administrator = provisioningAdministrator();

    $this->actingAs($administrator)
        ->post(route('staff.users.store'), [
            'name' => 'Cross-trained Treasury Officer',
            'email' => 'cross-trained@bpls-runtime.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => ['treasury', 'cashier'],
            'reason' => 'Assign approved Treasury and cashier duties.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Staff account provisioned.');

    $user = User::query()->where('email', 'cross-trained@bpls-runtime.test')->sole();

    expect(collect($user->roleCodes())->sort()->values()->all())->toBe(['cashier', 'treasury'])
        ->and($user->hasActiveAccess())->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeTrue();

    $audit = UserAccessAudit::query()->whereBelongsTo($user, 'subject')->sole();
    expect($audit->actor_user_id)->toBe($administrator->id)
        ->and($audit->action)->toBe('staff_account_provisioned')
        ->and($audit->after_state['roles'])->toBe(['cashier', 'treasury']);
});

test('an administrator can change roles and suspend access with an audit reason', function () {
    $administrator = provisioningAdministrator();
    $user = User::factory()->create();
    $user->assignRole(Role::query()->where('code', 'health')->sole());

    $this->actingAs($administrator)
        ->patch(route('staff.users.access.update', $user), [
            'roles' => ['engineering', 'health'],
            'access_status' => 'suspended',
            'reason' => 'Temporary reassignment pending authorization review.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Account access updated.');

    $user->refresh()->load('roles');
    expect(collect($user->roleCodes())->sort()->values()->all())->toBe(['engineering', 'health'])
        ->and($user->hasActiveAccess())->toBeFalse();

    $audit = UserAccessAudit::query()->whereBelongsTo($user, 'subject')->sole();
    expect($audit->reason)->toBe('Temporary reassignment pending authorization review.')
        ->and($audit->before_state['roles'])->toBe(['health'])
        ->and($audit->after_state['access_status'])->toBe('suspended');

    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
});

test('expired access is denied by the authenticated workflow boundary', function () {
    $user = User::factory()->create(['access_expires_at' => now()->subMinute()]);

    expect($user->hasActiveAccess())->toBeFalse();
    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
});

test('an administrator cannot remove their own administrative access', function () {
    $administrator = provisioningAdministrator();

    $this->actingAs($administrator)
        ->from(route('staff.users.index'))
        ->patch(route('staff.users.access.update', $administrator), [
            'roles' => ['bplo'],
            'access_status' => 'active',
            'reason' => 'Attempt self-demotion.',
        ])
        ->assertRedirect(route('staff.users.index'))
        ->assertSessionHasErrors('roles');

    expect($administrator->refresh()->hasRole('admin'))->toBeTrue();
});

test('staff without provisioning authority cannot create accounts', function () {
    app(EnsureBplsInstitution::class)->handle();
    $operator = userWithRole(Role::query()->where('code', 'bplo')->sole());

    $this->actingAs($operator)
        ->post(route('staff.users.store'), [
            'name' => 'Unauthorized Account',
            'email' => 'unauthorized@bpls-runtime.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => ['treasury'],
            'reason' => 'This must be rejected.',
        ])
        ->assertForbidden();

    expect(User::query()->where('email', 'unauthorized@bpls-runtime.test')->exists())->toBeFalse();
});

test('synthetic scenario roles cannot be assigned through staff provisioning', function () {
    $administrator = provisioningAdministrator();
    $scenarioRole = Role::factory()->create([
        'name' => 'scenario-only',
        'code' => 'scenario-only',
        'display_name' => 'Scenario Only',
    ]);

    $this->actingAs($administrator)
        ->post(route('staff.users.store'), [
            'name' => 'Invalid Scenario Assignment',
            'email' => 'scenario-assignment@bpls-runtime.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [$scenarioRole->code],
            'reason' => 'This role is not institutional.',
        ])
        ->assertSessionHasErrors('roles');

    expect(User::query()->where('email', 'scenario-assignment@bpls-runtime.test')->exists())->toBeFalse();
});

test('laboratory actor provisioning creates twelve stable role-specific accounts idempotently', function () {
    config()->set('bpls_installation.seed_laboratory_actors', true);
    config()->set('bpls_installation.laboratory_actor_password', 'password');
    $administrator = provisioningAdministrator();

    $this->actingAs($administrator)
        ->post(route('staff.users.provision-laboratory'), [
            'reason' => 'Prepare the lifecycle laboratory.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', '12 laboratory actors are ready.');

    $definitions = app(ProvisionLifecycleLaboratoryActors::class)->definitions();
    $emails = collect($definitions)->pluck('email');

    expect(User::query()->whereIn('email', $emails)->count())->toBe(12)
        ->and(UserAccessAudit::query()->where('action', 'laboratory_actor_provisioned')->count())->toBe(12);

    foreach ($definitions as $definition) {
        $user = User::query()->where('email', $definition['email'])->sole();
        expect($user->name)->toBe($definition['name'])
            ->and($user->roleCodes())->toBe([$definition['role_code']])
            ->and(Hash::check('password', $user->password))->toBeTrue();
    }

    $firstIds = User::query()->whereIn('email', $emails)->pluck('id', 'email')->all();
    app(ProvisionLifecycleLaboratoryActors::class)->handle();

    expect(User::query()->whereIn('email', $emails)->pluck('id', 'email')->all())->toBe($firstIds)
        ->and(User::query()->whereIn('email', $emails)->count())->toBe(12);
});

test('laboratory actor provisioning obeys the environment switch', function () {
    config()->set('bpls_installation.seed_laboratory_actors', false);

    expect(fn () => app(ProvisionLifecycleLaboratoryActors::class)->handle())
        ->toThrow(RuntimeException::class, 'Lifecycle laboratory actor provisioning is disabled');
});
