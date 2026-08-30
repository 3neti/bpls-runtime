<?php

use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathScenario;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\Models\Assessment;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\FeeRule;
use App\Models\LifecycleScenarioSpecimen;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('Scenario 01 proves a deterministic persisted multi-LOB Renewal becomes an approved payable', function () {
    Storage::fake('local');
    Artisan::call('bpls:install');

    $firstExit = Artisan::call('bpls:lifecycle:run', [
        'scenario' => RenewalHappyPathDefinition::Id,
        '--persist' => true,
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
        '--persist' => true,
        '--json' => true,
    ]);
    $second = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($firstExit)->toBe(0)
        ->and($secondExit)->toBe(0)
        ->and($first['status'])->toBe('passed')
        ->and($first['application']['type'])->toBe('renewal')
        ->and($first['application']['application_number'])->toBeNull()
        ->and($first['system_bootstrap']['accepted_business_inspection_fee']['provisional_uat'])->toBeFalse()
        ->and($first['onboarding']['canonical_action'])->toBe('CreatePermitApplication')
        ->and($first['lines_of_business'])->toHaveCount(2)
        ->and($first['application_evaluation_routing']['disposition'])->toBe('projected')
        ->and($first['application_evaluation_routing']['persisted_aggregate_created'])->toBeFalse()
        ->and($first['application_evaluation_routing']['required_work_count'])->toBe(6)
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
        ->and($first['treasury_counter_check']['assessment_id'])->toBe($first['assessment']['id'])
        ->and($first['treasury_counter_check']['evaluation_version_id'])->toBe($first['assessment']['evaluation_version_id'])
        ->and($first['treasury_counter_check']['result'])->toBe('no_correction')
        ->and($first['treasurer_decision']['action'])->toBe('approved')
        ->and($first['payment_schedule']['status'])->toBe('pending')
        ->and($first['payable']['status'])->toBe('payable')
        ->and($first['payable']['externally_settled'])->toBeFalse()
        ->and($first['isolation_inventory'])->toMatchArray([
            'scenario_applications' => 1,
            'scenario_businesses' => 1,
            'scenario_responsibilities' => 6,
            'scenario_evaluation_charges' => 7,
            'current_assessments' => 1,
            'assessment_lines' => 7,
            'treasury_counter_checks' => 1,
            'payment_schedules' => 1,
            'accepted_inspection_fee_rules' => 1,
            'expected_nonaccumulating' => true,
        ])
        ->and(collect($first['negative_assertions'])->every(fn (array $assertion): bool => $assertion['passed']))->toBeTrue()
        ->and($second)->toBe($first)
        ->and($second['semantic_result_hash'])->toBe($first['semantic_result_hash'])
        ->and(PermitApplication::query()->count())->toBe($counts['applications'])
        ->and(BusinessPermitEvaluationItem::query()->where('metadata->scenario_id', RenewalHappyPathDefinition::Id)->count())->toBe($counts['responsibilities'])
        ->and(Assessment::query()->count())->toBe($counts['assessments'])
        ->and(PaymentSchedule::query()->count())->toBe($counts['schedules'])
        ->and(FeeRule::query()->count())->toBe($counts['fee_rules'])
        ->and(LifecycleScenarioSpecimen::query()->count())->toBe(1)
        ->and(User::query()->where('email', 'scenario-01-assessment-officer@example.test')->sole()->can('business_permit_evaluations.view'))->toBeTrue()
        ->and(User::query()->where('email', 'scenario-01-treasury-counter-check@example.test')->sole()->can('business_permit_evaluations.correct_lines_of_business'))->toBeTrue()
        ->and(User::query()->where('email', 'scenario-01-municipal-treasurer@example.test')->sole()->cannot('business_permit_evaluations.counter_check'))->toBeTrue();

    $store = new ScenarioArtifactStore(RenewalHappyPathDefinition::Id, RenewalHappyPathDefinition::RunId);
    expect($store->readJson('result.json'))->toBe($second)
        ->and($store->exists('action-trace.json'))->toBeTrue();
});

test('Scenario 01 has compact human output and native discovery', function () {
    Storage::fake('local');
    Artisan::call('bpls:install');

    $this->artisan('bpls:lifecycle:list')
        ->expectsOutputToContain('renewal-happy-path')
        ->assertSuccessful();

    $this->artisan('bpls:lifecycle:run', ['scenario' => RenewalHappyPathDefinition::Id])
        ->expectsOutputToContain('RENEWAL HAPPY PATH: PASS')
        ->expectsOutputToContain('FINANCIAL WORKING PAPER')
        ->expectsOutputToContain('Grand Total: PHP 1,220.00')
        ->expectsOutputToContain('SYSTEM BOOTSTRAP')
        ->expectsOutputToContain('ONBOARDING')
        ->expectsOutputToContain('APPLICATION EVALUATION ROUTING')
        ->expectsOutputToContain('Routing required work = generated responsibilities exactly · 6')
        ->expectsOutputToContain('6/6 resolved · six departmental amounts are provisional_uat')
        ->expectsOutputToContain('Ready for Assessment before Treasury counter-check')
        ->expectsOutputToContain('completed, no correction')
        ->assertSuccessful();
});

test('Scenario 01 projects its immutable Assessment and suppresses completed role work', function () {
    Artisan::call('bpls:install');

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

test('Scenario 01 is visible through the canonical Citizen owner relationship and projects the complete payable lifecycle', function () {
    Artisan::call('bpls:install');

    $result = app(RenewalHappyPathScenario::class)->run();
    $application = PermitApplication::query()->findOrFail($result['application']['id']);
    $citizen = User::query()->where('email', 'scenario-01-citizen@example.test')->sole();
    $intake = User::query()->where('email', 'scenario-01-intake@example.test')->sole();

    expect($citizen->business_owner_id)->toBe($application->business->business_owner_id)
        ->and($application->submitted_by_id)->toBe($intake->id)
        ->and($application->submitted_by_id)->not->toBe($citizen->id)
        ->and($result['onboarding']['portal_identity'])->toMatchArray([
            'id' => $citizen->id,
            'business_owner_id' => $application->business->business_owner_id,
            'application_submitter_id' => $intake->id,
            'synthetic' => true,
        ]);

    $this->withoutVite()
        ->actingAs($citizen)
        ->get(route('citizen.permit-applications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('citizen/permit-applications/Index', false)
            ->has('permitApplications.data', 1)
            ->where('permitApplications.data.0.id', $application->id)
            ->where('permitApplications.data.0.type', 'renewal')
            ->where('permitApplications.data.0.business_name', 'Scenario 01 Market and Kitchen'));

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('citizen/permit-applications/Show', false)
            ->where('permitApplication.type', 'renewal')
            ->where('permitApplication.lines.0.line_of_business.name', 'Scenario 01 Retail Trading')
            ->where('permitApplication.lines.1.line_of_business.name', 'Scenario 01 Food Service')
            ->where('permitApplication.processing.assessment.total_amount_cents', 122_000)
            ->where('permitApplication.processing.assessment.treasury_counter_check.result', 'no_correction')
            ->where('permitApplication.processing.assessment.treasurer_decision.action', 'approved')
            ->where('permitApplication.processing.payment_schedule.total_amount_cents', 122_000)
            ->where('permitApplication.processing.payment_schedule.balance_amount_cents', 122_000)
            ->where('permitApplication.processing.payment_schedule.status', 'pending')
            ->has('permitApplication.timeline', 7)
            ->where('permitApplication.timeline.1.key', "assessment-computed:{$result['assessment']['id']}")
            ->where('permitApplication.timeline.2.key', "treasury-counter-check:{$result['treasury_counter_check']['id']}")
            ->where('permitApplication.timeline.2.title', 'Treasury counter-check completed — no correction')
            ->where('permitApplication.timeline.3.key', fn (string $key): bool => str_starts_with($key, 'assessment-decision:'))
            ->where('permitApplication.timeline.4.key', "payment-schedule-prepared:{$result['payment_schedule']['id']}"));

    $this->actingAs($intake)
        ->get(route('staff.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Show', false)
            ->where('permitApplication.latest_assessment.total_amount_cents', 122_000)
            ->where('permitApplication.latest_assessment.treasury_counter_check.result', 'no_correction')
            ->where('permitApplication.latest_assessment.treasurer_decision.action', 'approved')
            ->where('permitApplication.latest_payment_schedule.total_amount_cents', 122_000)
            ->where('permitApplication.latest_payment_schedule.paid_amount_cents', 0)
            ->where('permitApplication.timeline.2.key', "treasury-counter-check:{$result['treasury_counter_check']['id']}"));
});
