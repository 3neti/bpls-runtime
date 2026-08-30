<?php

use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathScenario;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\Models\Assessment;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\FeeRule;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\User;
use Database\Seeders\RevenueCodeFeeCatalogSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

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
        ->and(FeeRule::query()->count())->toBe($counts['fee_rules'])
        ->and(User::query()->where('email', 'scenario-01-assessment-officer@example.test')->sole()->can('business_permit_evaluations.view'))->toBeTrue()
        ->and(User::query()->where('email', 'scenario-01-treasury-counter-check@example.test')->sole()->can('business_permit_evaluations.correct_lines_of_business'))->toBeTrue()
        ->and(User::query()->where('email', 'scenario-01-municipal-treasurer@example.test')->sole()->cannot('business_permit_evaluations.counter_check'))->toBeTrue();

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

test('Scenario 01 projects its immutable Assessment and suppresses completed role work', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $result = app(RenewalHappyPathScenario::class)->run();
    $assessment = Assessment::query()->findOrFail($result['assessment']['id']);
    $assessmentOfficer = User::query()->where('email', 'scenario-01-assessment-officer@example.test')->sole();

    $this->withoutVite()
        ->actingAs($assessmentOfficer)
        ->get(route('staff.permit-applications.assessments.show', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Assessments/Show', false)
            ->where('assessment.display_status', 'Approved · Payable')
            ->where('assessment.financial_working_paper.line_sections.0.line_of_business_name', 'Scenario 01 Retail Trading')
            ->where('assessment.financial_working_paper.line_sections.0.subtotal_amount_cents', 33_000)
            ->where('assessment.financial_working_paper.line_sections.1.line_of_business_name', 'Scenario 01 Food Service')
            ->where('assessment.financial_working_paper.line_sections.1.subtotal_amount_cents', 54_000)
            ->where('assessment.financial_working_paper.application_subtotal_amount_cents', 35_000)
            ->where('assessment.financial_working_paper.grand_total_amount_cents', 122_000)
            ->where('assessment.financial_working_paper.grouped_total_amount_cents', 122_000)
            ->where('assessment.financial_working_paper.reconciles', true)
            ->where('assessment.treasury_counter_check.checked_by', 'Scenario 01 Treasury Counter-checker')
            ->where('assessment.decision.action', 'approved')
            ->where('assessment.decision.total_amount_cents', 122_000)
            ->where('assessment.latest_payment_schedule.status', 'pending')
            ->where('assessment.latest_payment_schedule.total_amount_cents', 122_000));

    foreach ([
        'scenario-01-health@example.test' => 'department_responsibilities',
        'scenario-01-treasury-counter-check@example.test' => 'treasury_counter_check',
        'scenario-01-municipal-treasurer@example.test' => 'treasurer_approval',
        'scenario-01-assessment-officer@example.test' => 'assessment_preparation',
    ] as $email => $surface) {
        $user = User::query()->where('email', $email)->sole();

        $this->actingAs($user)
            ->get(route('staff.permit-applications.assessments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workSurface.id', $surface)
                ->where('workSurface.count', 0)
                ->has('permitApplications.data', 0));
    }
});
