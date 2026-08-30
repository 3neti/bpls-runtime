<?php

use App\Enums\StakeholderPreviewPersona;
use App\Models\Assessment;
use App\Models\OfficeChargeContribution;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\TreasuryCollection;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Support\Facades\Artisan;
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
    Artisan::call('bpls:install');

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
            ->has('personas', 14)
            ->where('personas.0.key', 'citizen')
            ->where('personas.13.key', 'releasing'));

    expect($response->getContent())
        ->not->toContain((string) config('stakeholder_preview.password'))
        ->not->toContain('password')
        ->and(PermitApplication::query()->count())->toBe(0)
        ->and(Assessment::query()->count())->toBe(0)
        ->and(TreasuryCollection::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0);
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

test('office workspace presents a bounded queue routed to that office with relevant recorded facts', function () {
    $accounts = createStakeholderPreviewAccounts();
    PermitApplication::factory()->create([
        'submitted_at' => now()->subMinute(),
        'metadata' => [],
    ]);
    $olderSample = PermitApplication::factory()->create([
        'submitted_at' => now()->subMinute(),
        'created_at' => now()->subMinute(),
        'metadata' => [
            'provisional_uat_workflow' => [
                'semantic_classification' => 'provisional_uat',
            ],
        ],
    ]);
    $currentSample = PermitApplication::factory()->create([
        'submitted_at' => now(),
        'created_at' => now(),
        'metadata' => [
            'provisional_uat_workflow' => [
                'semantic_classification' => 'provisional_uat',
            ],
        ],
    ]);
    $currentSample->business->update([
        'business_area_square_meters' => '84.50',
        'occupancy' => 'rented',
        'building_name' => 'Scenario Commerce Building',
    ]);
    $otherOfficeSample = PermitApplication::factory()->create([
        'submitted_at' => now()->addSecond(),
        'created_at' => now()->addSecond(),
        'metadata' => [
            'provisional_uat_workflow' => [
                'semantic_classification' => 'provisional_uat',
            ],
        ],
    ]);
    OfficeChargeContribution::factory()->create([
        'permit_application_id' => $olderSample->id,
        'office_code' => 'engineering',
    ]);
    OfficeChargeContribution::factory()->create([
        'permit_application_id' => $currentSample->id,
        'office_code' => 'engineering',
    ]);
    OfficeChargeContribution::factory()->create([
        'permit_application_id' => $otherOfficeSample->id,
        'office_code' => 'health',
    ]);

    $this->actingAs($accounts[StakeholderPreviewPersona::Engineering->value])
        ->get(route('stakeholder-preview.workflow'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stakeholder-preview/Workflow')
            ->has('applications', 2)
            ->where('applications.0.id', $currentSample->id)
            ->where('applications.0.office_facts.0', [
                'label' => 'Business area',
                'value' => '84.50 m²',
            ])
            ->where('applications.1.id', $olderSample->id));
});

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

    expect($transitions)->toHaveCount(182);

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
    'bplo' => [StakeholderPreviewPersona::Bplo, 2],
    'assessment officer' => [StakeholderPreviewPersona::AssessmentOfficer, 2],
    'treasury' => [StakeholderPreviewPersona::Treasury, 5],
    'municipal treasurer' => [StakeholderPreviewPersona::MunicipalTreasurer, 2],
    'cashier' => [StakeholderPreviewPersona::Cashier, 2],
    'management' => [StakeholderPreviewPersona::Management, 5],
    'engineering' => [StakeholderPreviewPersona::Engineering, 1],
    'mpdo' => [StakeholderPreviewPersona::Mpdo, 1],
    'assessor' => [StakeholderPreviewPersona::Assessor, 1],
    'health' => [StakeholderPreviewPersona::Health, 1],
    'menro' => [StakeholderPreviewPersona::Menro, 1],
    'mayor office' => [StakeholderPreviewPersona::MayorOffice, 1],
    'releasing' => [StakeholderPreviewPersona::Releasing, 1],
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
            'STAKEHOLDER_PREVIEW_PROFILE' => 'stakeholder_preview_weekend_v1',
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
        'stakeholder_preview.profile' => 'stakeholder_preview_weekend_v1',
        'stakeholder_preview.data_classification' => 'synthetic_only',
        'stakeholder_preview.pii_mode' => 'synthetic_only',
        'stakeholder_preview.production_migration_enabled' => false,
        'stakeholder_preview.production_integrations' => 'disabled',
        'stakeholder_preview.password' => 'Stakeholder-Preview-Test-Only-2026',
    ]);
}
