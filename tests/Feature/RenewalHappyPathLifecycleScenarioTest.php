<?php

use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\Models\Assessment;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\FeeRule;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use Database\Seeders\RevenueCodeFeeCatalogSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

test('Scenario 01 proves a deterministic multi-LOB Renewal becomes an approved payable', function () {
    Storage::fake('local');
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $firstExit = Artisan::call('bpls:lifecycle:run', [
        'scenario' => RenewalHappyPathDefinition::Id,
        '--json' => true,
    ]);
    $first = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $counts = [
        'applications' => PermitApplication::query()->count(),
        'responsibilities' => BusinessPermitEvaluationItem::query()->where('metadata->scenario_id', RenewalHappyPathDefinition::Id)->count(),
        'assessments' => Assessment::query()->count(),
        'schedules' => PaymentSchedule::query()->count(),
        'fee_rules' => FeeRule::query()->count(),
    ];

    $secondExit = Artisan::call('bpls:lifecycle:run', [
        'scenario' => RenewalHappyPathDefinition::Id,
        '--json' => true,
    ]);
    $second = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($firstExit)->toBe(0)
        ->and($secondExit)->toBe(0)
        ->and($first['status'])->toBe('passed')
        ->and($first['application']['type'])->toBe('renewal')
        ->and($first['application']['application_number'])->toBeNull()
        ->and($first['lines_of_business'])->toHaveCount(2)
        ->and($first['responsibilities']['created_count'])->toBe(6)
        ->and($first['responsibilities']['resolved_count'])->toBe(6)
        ->and($first['evaluation']['readiness'])->toBe('ready')
        ->and($first['evaluation']['subtotals']['line_of_business'])->toBe([
            'Scenario 01 Retail Trading' => 33_000,
            'Scenario 01 Food Service' => 54_000,
        ])
        ->and($first['evaluation']['subtotals']['application_wide_amount_cents'])->toBe(35_000)
        ->and($first['evaluation']['grand_total_amount_cents'])->toBe(122_000)
        ->and($first['assessment']['total_amount_cents'])->toBe(122_000)
        ->and($first['assessment']['line_count'])->toBe(7)
        ->and($first['treasurer_decision']['action'])->toBe('approved')
        ->and($first['payment_schedule']['status'])->toBe('pending')
        ->and($first['payable']['status'])->toBe('payable')
        ->and($first['payable']['externally_settled'])->toBeFalse()
        ->and(collect($first['negative_assertions'])->every(fn (array $assertion): bool => $assertion['passed']))->toBeTrue()
        ->and($second)->toBe($first)
        ->and($second['semantic_result_hash'])->toBe($first['semantic_result_hash'])
        ->and(PermitApplication::query()->count())->toBe($counts['applications'])
        ->and(BusinessPermitEvaluationItem::query()->where('metadata->scenario_id', RenewalHappyPathDefinition::Id)->count())->toBe($counts['responsibilities'])
        ->and(Assessment::query()->count())->toBe($counts['assessments'])
        ->and(PaymentSchedule::query()->count())->toBe($counts['schedules'])
        ->and(FeeRule::query()->count())->toBe($counts['fee_rules']);

    $store = new ScenarioArtifactStore(RenewalHappyPathDefinition::Id, RenewalHappyPathDefinition::RunId);
    expect($store->readJson('result.json'))->toBe($second)
        ->and($store->exists('action-trace.json'))->toBeTrue();
});

test('Scenario 01 has compact human output and native discovery', function () {
    Storage::fake('local');
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $this->artisan('bpls:lifecycle:list')
        ->expectsOutputToContain('renewal-happy-path')
        ->assertSuccessful();

    $this->artisan('bpls:lifecycle:run', ['scenario' => RenewalHappyPathDefinition::Id])
        ->expectsOutputToContain('RENEWAL HAPPY PATH: PASS')
        ->expectsOutputToContain('FINANCIAL WORKING PAPER')
        ->expectsOutputToContain('Grand Total: PHP 1,220.00')
        ->expectsOutputToContain('Responsibilities: 6/6 resolved')
        ->assertSuccessful();
});
