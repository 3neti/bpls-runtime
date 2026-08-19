<?php

use App\Enums\StakeholderPreviewPersona;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    configureStakeholderPreviewSafety();
    Route::middleware('web')->group(base_path('routes/web.php'));
    Route::getRoutes()->refreshNameLookups();
    Route::getRoutes()->refreshActionLookups();
});

test('safe preview configuration registers an intentional launcher without credentials', function () {
    createStakeholderPreviewAccounts();

    expect(Route::has('stakeholder-preview.enter'))
        ->toBeTrue()
        ->and(Route::has('stakeholder-preview.switch'))
        ->toBeTrue()
        ->and(Route::has('stakeholder-preview.walkthrough'))
        ->toBeTrue();

    $response = $this->get('/');

    $response->assertSuccessful()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertInertia(fn (Assert $page) => $page
            ->component('stakeholder-preview/Launcher')
            ->has('personas', 4)
            ->where('personas.0.key', 'citizen')
            ->where('personas.3.key', 'management'));

    expect($response->getContent())
        ->not->toContain((string) config('stakeholder_preview.password'))
        ->not->toContain('password');
});

test('launcher remains unavailable until the complete exact synthetic account set is ready', function () {
    createStakeholderPreviewAccounts();
    User::query()->where('email', StakeholderPreviewPersona::Treasury->approvedEmail())->delete();

    $this->get('/')->assertNotFound();
    $this->get('/stakeholder-preview/walkthrough')->assertNotFound();
    $this->post('/stakeholder-preview/enter/citizen')->assertNotFound();
});

test('one click entry authenticates each exact synthetic account through the web guard', function (StakeholderPreviewPersona $persona) {
    $accounts = createStakeholderPreviewAccounts();
    $oldSessionId = session()->getId();

    $this->withSession(['preview_test_marker' => 'must disappear'])
        ->post(route('stakeholder-preview.enter', $persona))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($accounts[$persona->value]);
    expect(session('preview_test_marker'))
        ->toBeNull()
        ->and(session()->getId())->not->toBe($oldSessionId);
})->with(StakeholderPreviewPersona::cases());

test('role switching works in every ordered direction and retains normal authorization', function () {
    $accounts = createStakeholderPreviewAccounts();
    $transitions = [];

    foreach (StakeholderPreviewPersona::cases() as $source) {
        foreach (StakeholderPreviewPersona::cases() as $target) {
            if ($source !== $target) {
                $transitions[] = [$source, $target];
            }
        }
    }

    expect($transitions)->toHaveCount(12);

    foreach ($transitions as [$source, $target]) {
        $this->actingAs($accounts[$source->value]);
        $oldSessionId = session()->getId();

        $this->withSession(['preview_test_marker' => $source->value])
            ->post(route('stakeholder-preview.switch', $target))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($accounts[$target->value]);
        expect(session('preview_test_marker'))
            ->toBeNull()
            ->and(session()->getId())->not->toBe($oldSessionId);
    }

    $this->actingAs($accounts['citizen'])
        ->get(route('staff.users.index'))
        ->assertForbidden();
});

test('arbitrary persona and user injection is refused', function (string $persona) {
    createStakeholderPreviewAccounts();

    $this->post('/stakeholder-preview/enter/'.$persona)->assertNotFound();
})->with([
    'numeric user id' => '1',
    'unknown persona' => 'administrator',
    'email-shaped value' => 'stakeholder.preview.management%40example.test',
]);

test('entry fails closed when the exact synthetic identity is altered', function (array $changes) {
    $accounts = createStakeholderPreviewAccounts();
    $management = $accounts['management'];

    if (isset($changes['permission'])) {
        $management->role?->permissions()->detach(
            Permission::query()->where('code', $changes['permission'])->value('id'),
        );
    } else {
        $management->forceFill($changes)->save();
    }

    $this->post(route('stakeholder-preview.enter', StakeholderPreviewPersona::Management))
        ->assertNotFound();
    $this->assertGuest();
})->with([
    'wrong name' => [['name' => 'Municipal Administrator']],
    'two factor enabled' => [['two_factor_secret' => 'synthetic-secret']],
    'permission removed' => [['permission' => 'users.view']],
]);

test('preview context exposes only authorized real guidance and a persistent banner signal', function (StakeholderPreviewPersona $persona, int $expectedCount) {
    $accounts = createStakeholderPreviewAccounts();

    $response = $this->actingAs($accounts[$persona->value])->get(route('dashboard'));

    $response->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('stakeholder_preview.enabled', true)
        ->where('stakeholder_preview.current_persona', $persona->value)
        ->has('stakeholder_preview.what_to_try', $expectedCount));

    $guidance = app(StakeholderPreviewSafety::class)->guidanceFor($accounts[$persona->value]);

    expect($guidance)->toHaveCount($expectedCount);
    foreach ($guidance as $item) {
        expect($item['href'])->toStartWith('/')->not->toContain('http');
    }
})->with([
    'citizen' => [StakeholderPreviewPersona::Citizen, 3],
    'bplo' => [StakeholderPreviewPersona::Bplo, 3],
    'treasury' => [StakeholderPreviewPersona::Treasury, 4],
    'management' => [StakeholderPreviewPersona::Management, 5],
]);

test('logout ends the preview session and returns to the launcher', function () {
    $accounts = createStakeholderPreviewAccounts();

    $this->actingAs($accounts['citizen'])
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
    $this->get('/')->assertInertia(fn (Assert $page) => $page->component('stakeholder-preview/Launcher'));
});

test('the production environment refuses preview even when every preview flag is enabled', function () {
    app()->detectEnvironment(fn (): string => 'production');

    expect(app(StakeholderPreviewSafety::class)->isEnabled())->toBeFalse();

    $this->artisan('lifecycle:prepare-stakeholder-preview', [
        '--phase' => 'prepare',
    ])->assertFailed();
});

test('preview routes are not registered by a fresh production bootstrap even when the preview flag is true', function () {
    $result = Process::path(base_path())
        ->env([
            'APP_ENV' => 'production',
            'STAKEHOLDER_PREVIEW_MODE' => 'true',
            'STAKEHOLDER_PREVIEW_PROFILE' => 'stakeholder_preview_cycle_4',
            'STAKEHOLDER_PREVIEW_DATA_CLASSIFICATION' => 'synthetic_only',
            'STAKEHOLDER_PREVIEW_PII_MODE' => 'synthetic_only',
            'STAKEHOLDER_PREVIEW_PRODUCTION_MIGRATION_ENABLED' => 'false',
            'STAKEHOLDER_PREVIEW_PRODUCTION_INTEGRATIONS' => 'disabled',
        ])
        ->run(['php', 'artisan', 'route:list', '--json']);

    expect($result->successful())->toBeTrue()
        ->and($result->output())->not->toContain('stakeholder-preview/enter')
        ->and($result->output())->not->toContain('stakeholder-preview/switch')
        ->and($result->output())->not->toContain('stakeholder-preview/walkthrough');
});

test('a false preview flag refuses the launcher in a non production environment', function () {
    config()->set('stakeholder_preview.mode', false);

    expect(app(StakeholderPreviewSafety::class)->isEnabled())->toBeFalse();

    $this->post('/stakeholder-preview/enter/citizen')->assertNotFound();
    $this->get('/')->assertHeaderMissing('X-Robots-Tag');
});

/** @return array<string, User> */
function createStakeholderPreviewAccounts(): array
{
    return collect(StakeholderPreviewPersona::cases())
        ->mapWithKeys(function (StakeholderPreviewPersona $persona): array {
            $role = Role::factory()->create([
                'code' => $persona->roleCode(),
                'name' => 'Preview '.$persona->label(),
            ]);
            $role->permissions()->sync(collect($persona->permissions())
                ->map(fn ($permission) => Permission::query()->firstOrCreate(
                    ['code' => $permission->value],
                    ['name' => str($permission->value)->headline()->toString()],
                )->id)
                ->all());

            $user = User::factory()->create([
                'name' => $persona->accountName(),
                'email' => $persona->approvedEmail(),
                'email_verified_at' => now(),
                'role_id' => $role->id,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ]);

            return [$persona->value => $user];
        })
        ->all();
}

function configureStakeholderPreviewSafety(): void
{
    config()->set([
        'stakeholder_preview.mode' => true,
        'stakeholder_preview.profile' => 'stakeholder_preview_cycle_4',
        'stakeholder_preview.data_classification' => 'synthetic_only',
        'stakeholder_preview.pii_mode' => 'synthetic_only',
        'stakeholder_preview.production_migration_enabled' => false,
        'stakeholder_preview.production_integrations' => 'disabled',
        'stakeholder_preview.password' => 'Stakeholder-Preview-Test-Only-2026',
    ]);
}
