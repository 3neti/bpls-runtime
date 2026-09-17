<?php

use App\Actions\EnsureCitizenRole;
use App\Enums\UserPermission;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('home is informational even in the restricted review profile without prepared accounts', function () {
    config([
        'stakeholder_preview.mode' => true,
        'stakeholder_preview.profile' => StakeholderPreviewSafety::AuthorizedLegacyReviewProfile,
        'stakeholder_preview.data_classification' => 'authorized_legacy_review',
        'stakeholder_preview.pii_mode' => 'restricted',
        'stakeholder_preview.production_migration_enabled' => false,
        'stakeholder_preview.production_integrations' => 'disabled',
        'stakeholder_preview.legacy_lab_specimen_bundle' => base64_encode('[]'),
        'stakeholder_preview.legacy_lab_specimen_pool_sha256' => hash('sha256', '[]'),
    ]);
    expect(app(StakeholderPreviewSafety::class)->isEnabled())->toBeTrue();
    Route::middleware('web')->group(base_path('routes/web.php'));
    Route::getRoutes()->refreshNameLookups();

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')->where('isNonProduction', true)
        ->missing('personas')->missing('citizenSpecimens'));
    $this->get(route('services-and-fees.index'))->assertRedirect(route('login'));
    $this->get('/permits/verify/999999/not-a-real-code')->assertRedirect(route('login'));
    $this->get(route('stakeholder-preview.index'))->assertRedirect(route('login'));
});

test('apply and continue preserve their destination through ordinary login', function (string $destination) {
    $user = userWithRole(app(EnsureCitizenRole::class)->handle());
    $this->get(route($destination))->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe(route($destination));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route($destination));
    $this->get(route($destination))->assertOk();
    $this->get(route('staff.work.index'))->assertForbidden();
})->with(['citizen.permit-applications.create', 'citizen.permit-applications.index']);

test('ordinary citizen and staff home responses do not advertise engineering controls', function (bool $staff) {
    $user = $staff
        ? userWithPermissions([UserPermission::AccessStaff])
        : userWithRole(app(EnsureCitizenRole::class)->handle());
    $this->actingAs($user)->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')->missing('personas')->missing('citizenSpecimens')
        ->where('auth.can_access_staff', $staff));
    $this->get(route('dashboard'))->assertOk();
})->with([false, true]);

test('staff uses the same login without receiving a citizen role or engineering identity', function () {
    $staff = userWithPermissions([UserPermission::AccessStaff]);
    $this->get(route('login'))->assertOk();
    $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('auth.can_access_staff', true)->where('auth.can_access_citizen', false));
    expect(app(StakeholderPreviewSafety::class)->personaFor($staff))->toBeNull();
});

test('citizen registration preserves the intended Apply destination and cannot provision staff', function () {
    app(EnsureCitizenRole::class)->handle();
    $this->get(route('citizen.permit-applications.create'))->assertRedirect(route('login'));
    $this->get(route('register'))->assertOk();
    $this->post(route('register.store'), [
        'name' => 'Synthetic Home Applicant',
        'email' => 'home-applicant@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roles' => ['admin'],
    ])->assertRedirect(route('citizen.permit-applications.create'));
    expect(auth()->user()->can('citizen.access'))->toBeTrue()
        ->and(auth()->user()->can('staff.access'))->toBeFalse();
});

test('inactive accounts cannot enter application routes from the public home', function (string $status) {
    $attributes = $status === 'suspended' ? ['access_status' => 'suspended'] : ['access_expires_at' => now()->subDay()];
    $user = userWithRole(app(EnsureCitizenRole::class)->handle(), $attributes);
    $this->actingAs($user)->get(route('home'))->assertOk();
    $this->get(route('citizen.permit-applications.create'))->assertForbidden();
    $this->get(route('citizen.permit-applications.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertForbidden();
})->with(['suspended', 'expired']);

test('public fees and existing auth screens remain reachable without introducing permit search', function () {
    foreach (['services-and-fees.index', 'login', 'register', 'password.request'] as $name) {
        $this->get(route($name))->assertOk();
    }
    $this->get('/permits/verify')->assertNotFound();
    $this->get('/permits/verify/999999/not-a-real-code')->assertNotFound();
});
