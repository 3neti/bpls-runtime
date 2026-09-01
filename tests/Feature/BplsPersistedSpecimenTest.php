<?php

use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\Models\Assessment;
use App\Models\BusinessOwner;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\FeeRule;
use App\Models\LifecycleScenarioSpecimen;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

test('certification run executes canonical lifecycle and leaves the installed baseline empty', function () {
    Storage::fake('local');
    Artisan::call('bpls:install');

    expect(Artisan::call('bpls:lifecycle:run', [
        'scenario' => RenewalHappyPathDefinition::Id,
        '--json' => true,
    ]))->toBe(0);
    $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($result['assessment']['total_amount_cents'])->toBe(122_000)
        ->and($result['treasury_counter_check']['result'])->toBe('no_correction')
        ->and($result['treasurer_decision']['action'])->toBe('approved')
        ->and($result['payment_schedule']['total_amount_cents'])->toBe(122_000)
        ->and(PermitApplication::query()->count())->toBe(0)
        ->and(Assessment::query()->count())->toBe(0)
        ->and(PaymentSchedule::query()->count())->toBe(0)
        ->and(LifecycleScenarioSpecimen::query()->count())->toBe(0);
});

test('persist leaves exactly one harness-owned standalone Renewal specimen and reruns idempotently', function () {
    Storage::fake('local');
    Artisan::call('bpls:install');

    expect(Artisan::call('bpls:lifecycle:run', [
        'scenario' => RenewalHappyPathDefinition::Id,
        '--persist' => true,
        '--json' => true,
    ]))->toBe(0);
    $first = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $counts = [
        PermitApplication::query()->count(),
        BusinessPermitEvaluationItem::query()->count(),
        Assessment::query()->count(),
        PaymentSchedule::query()->count(),
    ];

    expect(Artisan::call('bpls:lifecycle:run', [
        'scenario' => RenewalHappyPathDefinition::Id,
        '--persist' => true,
        '--json' => true,
    ]))->toBe(0);
    $second = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $specimen = LifecycleScenarioSpecimen::query()->sole();
    $application = PermitApplication::query()->with('business')->sole();
    $citizen = User::query()->where('email', 'scenario-citizen@example.test')->sole();
    $intake = User::query()->where('email', 'scenario-02-intake@example.test')->sole();

    expect($second)->toBe($first)
        ->and($specimen->scenario_id)->toBe(RenewalHappyPathDefinition::Id)
        ->and($specimen->scenario_revision)->toBe(RenewalHappyPathDefinition::Revision)
        ->and($specimen->permit_application_id)->toBe($application->id)
        ->and($specimen->semantic_result_hash)->toBe($first['semantic_result_hash'])
        ->and($specimen->owned_resource_manifest['permit_application_ids'])->toBe([$application->id])
        ->and($specimen->owned_resource_manifest['semantic_classification'])->toBe('synthetic_only')
        ->and($citizen->business_owner_id)->toBe($application->business->business_owner_id)
        ->and($application->submitted_by_id)->toBe($intake->id)
        ->and($application->submitted_by_id)->not->toBe($citizen->id)
        ->and($counts)->toBe([1, 7, 1, 1])
        ->and([
            PermitApplication::query()->count(),
            BusinessPermitEvaluationItem::query()->count(),
            Assessment::query()->count(),
            PaymentSchedule::query()->count(),
        ])->toBe($counts)
        ->and(FeeRule::query()->whereIn('amount_cents', [24_000, 9_000, 31_000, 9_500, 6_500, 7_000])->count())->toBe(0)
        ->and($first['evaluation']['grand_total_amount_cents'])->toBe(122_000)
        ->and($first['assessment']['total_amount_cents'])->toBe(122_000)
        ->and($first['payable']['amount_cents'])->toBe(122_000);
});

test('persist refuses unrelated transactions on first creation and never deletes unrelated records', function () {
    Storage::fake('local');
    Artisan::call('bpls:install');
    $unrelatedOwner = BusinessOwner::factory()->create(['name' => 'Unrelated local record']);

    expect(Artisan::call('bpls:lifecycle:run', [
        'scenario' => RenewalHappyPathDefinition::Id,
        '--persist' => true,
        '--json' => true,
    ]))->toBe(1)
        ->and($unrelatedOwner->fresh())->not->toBeNull()
        ->and(PermitApplication::query()->count())->toBe(0)
        ->and(LifecycleScenarioSpecimen::query()->count())->toBe(0);

    $unrelatedOwner->delete();
    Artisan::call('bpls:lifecycle:run', [
        'scenario' => RenewalHappyPathDefinition::Id,
        '--persist' => true,
        '--json' => true,
    ]);
    $preservedAfterSpecimen = BusinessOwner::factory()->create(['name' => 'Preserved after specimen']);
    Artisan::call('bpls:lifecycle:run', [
        'scenario' => RenewalHappyPathDefinition::Id,
        '--persist' => true,
        '--json' => true,
    ]);

    expect($preservedAfterSpecimen->fresh())->not->toBeNull()
        ->and(PermitApplication::query()->count())->toBe(1)
        ->and(LifecycleScenarioSpecimen::query()->count())->toBe(1);
});
