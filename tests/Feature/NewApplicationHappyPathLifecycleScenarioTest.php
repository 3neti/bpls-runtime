<?php

use App\Actions\InspectBplsInstallation;
use App\Enums\StakeholderPreviewPersona;
use App\Enums\UserPermission;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\Models\Assessment;
use App\Models\BusinessOwner;
use App\Models\LifecycleScenarioSpecimen;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('Scenario 02 certifies the empty-to-first-business Citizen lifecycle and rolls back by default', function () {
    Storage::fake('local');
    Artisan::call('bpls:install');

    expect(Artisan::call('bpls:lifecycle:run', [
        'scenario' => NewApplicationHappyPathDefinition::Id,
        '--json' => true,
    ]))->toBe(0);

    $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($result['status'])->toBe('passed')
        ->and($result['application']['type'])->toBe('new')
        ->and($result['application']['application_number'])->toBeNull()
        ->and($result['application']['tracking_reference'])->toStartWith('SUB-')
        ->and($result['onboarding']['empty_to_first_business'])->toBe([
            'before' => ['business_owner' => false, 'businesses' => 0, 'applications' => 0],
            'after' => ['business_owner' => true, 'businesses' => 1, 'applications' => 1],
            'passed' => true,
        ])
        ->and($result['onboarding']['portal_identity']['id'])->toBe($result['onboarding']['portal_identity']['application_submitter_id'])
        ->and($result['responsibilities']['created_count'])->toBe(6)
        ->and($result['responsibilities']['resolved_count'])->toBe(6)
        ->and($result['evaluation']['grand_total_amount_cents'])->toBe(122_000)
        ->and($result['assessment']['line_count'])->toBe(7)
        ->and($result['assessment']['total_amount_cents'])->toBe(122_000)
        ->and($result['treasury_counter_check']['result'])->toBe('no_correction')
        ->and($result['treasurer_decision']['action'])->toBe('approved')
        ->and($result['payment_schedule']['status'])->toBe('pending')
        ->and($result['payable']['amount_cents'])->toBe(122_000)
        ->and(PermitApplication::query()->count())->toBe(0)
        ->and(Assessment::query()->count())->toBe(0)
        ->and(PaymentSchedule::query()->count())->toBe(0)
        ->and(LifecycleScenarioSpecimen::query()->count())->toBe(0);
});

test('Scenario 02 persist is idempotent, explicitly owned, and visible through its own Citizen profile', function () {
    Storage::fake('local');
    Artisan::call('bpls:install');

    expect(Artisan::call('bpls:lifecycle:run', [
        'scenario' => NewApplicationHappyPathDefinition::Id,
        '--persist' => true,
        '--json' => true,
    ]))->toBe(0);
    $first = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect(Artisan::call('bpls:lifecycle:run', [
        'scenario' => NewApplicationHappyPathDefinition::Id,
        '--persist' => true,
        '--json' => true,
    ]))->toBe(0);
    $second = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $application = PermitApplication::query()->with('business')->sole();
    $specimen = LifecycleScenarioSpecimen::query()->sole();
    $scenarioCitizen = User::query()->where('email', 'scenario-02-citizen@example.test')->sole();
    $previewCitizen = User::query()->where('email', StakeholderPreviewPersona::Citizen->approvedEmail())->sole();

    expect($second)->toBe($first)
        ->and($specimen->scenario_id)->toBe(NewApplicationHappyPathDefinition::Id)
        ->and($specimen->scenario_revision)->toBe(NewApplicationHappyPathDefinition::Revision)
        ->and($specimen->permit_application_id)->toBe($application->id)
        ->and($specimen->owned_resource_manifest['permit_application_ids'])->toBe([$application->id])
        ->and($specimen->owned_resource_manifest['business_ids'])->toBe([$application->business_id])
        ->and($specimen->owned_resource_manifest['business_owner_ids'])->toBe([$application->business->business_owner_id])
        ->and($specimen->owned_resource_manifest['database_notification_ids'])->toHaveCount(1)
        ->and($specimen->owned_resource_manifest['semantic_classification'])->toBe('synthetic_only')
        ->and($scenarioCitizen->business_owner_id)->toBe($application->business->business_owner_id)
        ->and($application->submitted_by_id)->toBe($scenarioCitizen->id)
        ->and($previewCitizen->business_owner_id)->toBeNull()
        ->and($scenarioCitizen->can(UserPermission::CreateOwnPermitApplications->value))->toBeTrue()
        ->and($scenarioCitizen->can(UserPermission::EditOwnPermitApplications->value))->toBeTrue()
        ->and($scenarioCitizen->can(UserPermission::SubmitOwnPermitApplications->value))->toBeTrue();

    $this->withoutVite()
        ->actingAs($scenarioCitizen)
        ->get(route('citizen.profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.owner.name', 'Scenario 02 Synthetic Owner')
            ->has('profile.businesses', 1)
            ->where('profile.businesses.0.name', 'Scenario 02 Market and Kitchen')
            ->where('profile.businesses.0.permit_applications.0.type', 'new')
            ->where('profile.businesses.0.permit_applications.0.status', 'pending_payment')
            ->where('profile.businesses.0.permit_applications.0.lines_of_business', [
                'Scenario 02 Retail Trading',
                'Scenario 02 Food Service',
            ])
            ->where('profile.businesses.0.permit_applications.0.payable', [
                'status' => 'pending',
                'amount_due_cents' => 122_000,
            ]));

    $this->actingAs($previewCitizen)
        ->get(route('citizen.profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.linked', false)
            ->where('profile.owner', null)
            ->has('profile.businesses', 0));

    $this->actingAs($scenarioCitizen)
        ->get(route('citizen.permit-applications.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Create', false)
            ->where('registry.owner.id', $application->business->business_owner_id)
            ->where('registry.businesses.0.id', $application->business_id)
            ->has('lineOfBusinesses', 1)
            ->where('lineOfBusinesses.0.code', 'MRC-2A-02-B-WHOLESALE-RETAIL'));

    $this->actingAs($previewCitizen)
        ->get(route('citizen.permit-applications.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('registry.linked', false)
            ->has('registry.businesses', 0)
            ->has('lineOfBusinesses', 1)
            ->where('lineOfBusinesses.0.code', 'MRC-2A-02-B-WHOLESALE-RETAIL'));
});

test('Scenario 01 and Scenario 02 persisted specimens coexist in either order with disjoint explicit ownership', function (string $firstScenario, string $secondScenario) {
    Storage::fake('local');
    Artisan::call('bpls:install');

    expect(Artisan::call('bpls:lifecycle:run', [
        'scenario' => $firstScenario,
        '--persist' => true,
        '--json' => true,
    ]))->toBe(0);
    $first = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect(Artisan::call('bpls:lifecycle:run', [
        'scenario' => $secondScenario,
        '--persist' => true,
        '--json' => true,
    ]))->toBe(0);
    $second = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    $specimens = LifecycleScenarioSpecimen::query()->orderBy('scenario_id')->get();
    $applications = PermitApplication::query()->with(['assessments', 'paymentSchedules'])->get();
    $inspection = app(InspectBplsInstallation::class)->handle();

    expect($specimens)->toHaveCount(2)
        ->and($specimens->pluck('scenario_id')->all())->toBe([
            NewApplicationHappyPathDefinition::Id,
            RenewalHappyPathDefinition::Id,
        ])
        ->and($applications)->toHaveCount(2)
        ->and($applications->flatMap->assessments->pluck('total_amount_cents')->all())->toBe([122_000, 122_000])
        ->and($applications->flatMap->paymentSchedules->pluck('total_amount_cents')->all())->toBe([122_000, 122_000])
        ->and($inspection['integrity']['pass'])->toBeTrue()
        ->and($inspection['price_list']['synthetic_uat_exact_published_count'])->toBe(0)
        ->and(LineOfBusiness::query()->availableToMunicipalCatalog()->pluck('code')->all())->toBe(['MRC-2A-02-B-WHOLESALE-RETAIL']);

    $manifests = $specimens->pluck('owned_resource_manifest');
    foreach ($manifests->first() as $key => $firstIds) {
        if (! str_ends_with((string) $key, '_ids') || ! is_array($firstIds)) {
            continue;
        }

        expect(array_intersect($firstIds, $manifests->last()[$key] ?? []))->toBe([]);
    }

    foreach ([[$firstScenario, $first], [$secondScenario, $second]] as [$scenarioId, $original]) {
        expect(Artisan::call('bpls:lifecycle:run', [
            'scenario' => $scenarioId,
            '--persist' => true,
            '--json' => true,
        ]))->toBe(0)
            ->and(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR))->toBe($original);
    }
})->with([
    'renewal then new application' => [RenewalHappyPathDefinition::Id, NewApplicationHappyPathDefinition::Id],
    'new application then renewal' => [NewApplicationHappyPathDefinition::Id, RenewalHappyPathDefinition::Id],
]);

test('a second persisted specimen refuses unrelated residue without deleting or claiming it', function () {
    Storage::fake('local');
    Artisan::call('bpls:install');
    Artisan::call('bpls:lifecycle:run', [
        'scenario' => RenewalHappyPathDefinition::Id,
        '--persist' => true,
        '--json' => true,
    ]);
    $unrelatedOwner = BusinessOwner::factory()->create(['name' => 'Protected unrelated owner']);

    expect(Artisan::call('bpls:lifecycle:run', [
        'scenario' => NewApplicationHappyPathDefinition::Id,
        '--persist' => true,
        '--json' => true,
    ]))->toBe(1)
        ->and($unrelatedOwner->fresh())->not->toBeNull()
        ->and(LifecycleScenarioSpecimen::query()->count())->toBe(1)
        ->and(PermitApplication::query()->count())->toBe(1);
});

test('Scenario 02 discovery is canonical and Scenario 01 semantic certification remains unchanged', function () {
    Storage::fake('local');
    Artisan::call('bpls:install');

    $this->artisan('bpls:lifecycle:list')
        ->expectsOutputToContain(RenewalHappyPathDefinition::Id)
        ->expectsOutputToContain(NewApplicationHappyPathDefinition::Id)
        ->assertSuccessful();

    expect(Artisan::call('bpls:lifecycle:run', [
        'scenario' => RenewalHappyPathDefinition::Id,
        '--json' => true,
    ]))->toBe(0);

    $scenario01 = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($scenario01['semantic_result_hash'])->toBe('9ce435c29f1fa0122381f15cf5bd002652cc95539078d07a689476e8064c6783')
        ->and($scenario01['evaluation']['grand_total_amount_cents'])->toBe(122_000);
});
