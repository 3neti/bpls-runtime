<?php

use App\Actions\ProvisionLifecycleLaboratoryActors;
use App\Enums\StakeholderPreviewPersona;
use App\Models\LifecycleCleanroomRun;
use App\Models\Role;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    config()->set([
        'stakeholder_preview.mode' => true,
        'stakeholder_preview.profile' => StakeholderPreviewSafety::Profile,
        'stakeholder_preview.data_classification' => 'synthetic_only',
        'stakeholder_preview.pii_mode' => 'synthetic_only',
        'stakeholder_preview.production_migration_enabled' => false,
        'stakeholder_preview.production_integrations' => 'disabled',
    ]);
    Route::middleware('web')->group(base_path('routes/web.php'));
    Route::getRoutes()->refreshNameLookups();
    Route::getRoutes()->refreshActionLookups();
    Artisan::call('bpls:install');
});

test('all ordinary walkthrough identities keep normal access without engineering presentation even in an active cleanroom', function () {
    $management = User::query()->where('email', StakeholderPreviewPersona::Management->approvedEmail())->sole();
    $actors = [];

    foreach (app(ProvisionLifecycleLaboratoryActors::class)->definitions() as $key => $definition) {
        $user = User::factory()->create(['name' => $definition['name'], 'email' => 'walkthrough-'.$definition['email']]);
        $role = Role::query()->where('code', $definition['role_code'])->sole();
        $user->assignRole($role);
        $actors[$key] = ['label' => $definition['name'], 'user_id' => $user->id, 'role_id' => $role->id];
    }

    $run = LifecycleCleanroomRun::factory()->for($management, 'startedBy')->create([
        'actor_manifest' => ['actors' => $actors],
    ]);
    $before = $run->fresh()->getRawOriginal();

    foreach ($actors as $key => $actor) {
        $user = User::query()->findOrFail($actor['user_id']);
        $permissionCodes = $user->getAllPermissions()->pluck('code')->sort()->values()->all();
        $this->actingAs($user)->get(route('dashboard'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stakeholder_preview.enabled', true)
                ->where('stakeholder_preview.show_engineering_controls', false)
                ->where('stakeholder_preview.current_persona', null)
                ->where('stakeholder_preview.cleanroom_actor', null)
                ->where('stakeholder_preview.personas', [])
                ->where('stakeholder_preview.what_to_try', [])
                ->where('auth.can_access_staff', $key !== 'citizen')
                ->where('auth.can_access_citizen', $key === 'citizen'));
        expect($user->fresh()->getAllPermissions()->pluck('code')->sort()->values()->all())->toBe($permissionCodes);
    }

    expect($run->fresh()->getRawOriginal())->toBe($before);
});

test('exact approved preview operators retain engineering presentation and laboratory access', function () {
    foreach (StakeholderPreviewPersona::cases() as $persona) {
        $user = User::query()->where('email', $persona->approvedEmail())->sole();
        $this->actingAs($user)->get(route('dashboard'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stakeholder_preview.show_engineering_controls', true)
                ->where('stakeholder_preview.current_persona', $persona->value)
                ->has('stakeholder_preview.personas', count(StakeholderPreviewPersona::cases())));
    }

    $management = User::query()->where('email', StakeholderPreviewPersona::Management->approvedEmail())->sole();
    $this->actingAs($management)->get(route('stakeholder-preview.lifecycle-laboratory.index'))->assertSuccessful();
});

test('engineering presentation does not infer preview identity from role or email alone', function () {
    $persona = StakeholderPreviewPersona::Bplo;
    $user = User::query()->where('email', $persona->approvedEmail())->sole();
    $user->update(['name' => 'Ordinary BPLO account']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stakeholder_preview.show_engineering_controls', false)
            ->where('stakeholder_preview.cleanroom_actor', null));
});

test('hiding engineering presentation preserves the restricted legacy safety classification', function () {
    config()->set([
        'stakeholder_preview.profile' => StakeholderPreviewSafety::AuthorizedLegacyReviewProfile,
        'stakeholder_preview.data_classification' => 'authorized_legacy_review',
        'stakeholder_preview.pii_mode' => 'restricted',
        'stakeholder_preview.legacy_lab_specimen_bundle' => '/private/example',
        'stakeholder_preview.legacy_lab_specimen_pool_sha256' => str_repeat('a', 64),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stakeholder_preview.show_engineering_controls', false)
            ->where('stakeholder_preview.authorized_legacy_review', true)
            ->where('stakeholder_preview.access', 'private'));
});
