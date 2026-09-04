<?php

use App\Actions\BuildFeeMatrixQuickLook;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\ExecutePersistedLifecycleScenario;
use App\Actions\ProposeFeeRuleRevision;
use App\Assessment\AssessmentSnapshotFingerprint;
use App\Assessment\Price\CanonicalFinancialFingerprint;
use App\Assessment\Price\HistoricalPriceReport;
use App\Assessment\Price\Price;
use App\Data\Assessment\AssessmentContextData;
use App\Data\Assessment\AssessmentPriceComponentInput;
use App\Data\Assessment\AssessmentPriceInput;
use App\Data\Assessment\AssessmentPriceModifierInput;
use App\Enums\FeeRulePublicationSource;
use App\Enums\FeeRuleScope;
use App\Enums\UserPermission;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\Models\Assessment;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use Brick\Money\Money;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function priceComponent(
    string $key,
    int $minor,
    string $currency = 'PHP',
    ?int $lineOfBusinessId = null,
    ?string $lineOfBusinessName = null,
): AssessmentPriceComponentInput {
    $scope = $lineOfBusinessId === null ? 'application' : 'line_of_business';

    return new AssessmentPriceComponentInput(
        key: $key,
        type: 'governed_fee',
        label: str($key)->headline()->toString(),
        scope: $scope,
        permit_application_line_id: $lineOfBusinessId,
        line_of_business_id: $lineOfBusinessId,
        line_of_business_name: $lineOfBusinessName,
        responsible_office: null,
        currency: $currency,
        amount_minor: $minor,
        source_type: 'fee_rule',
        source_identity: $key,
        source_version: 'fee_rule:v1',
        exact_once_key: "fee_rule:{$key}",
        legal_basis: 'Synthetic contract specimen only.',
        explanation: ['canonical_money' => "{$currency}:{$minor}"],
    );
}

/** @param list<AssessmentPriceComponentInput> $components @param list<AssessmentPriceModifierInput> $modifiers */
function priceInput(array $components, array $modifiers = [], string $currency = 'PHP'): AssessmentPriceInput
{
    return new AssessmentPriceInput(
        schema_version: AssessmentPriceInput::Schema,
        currency: $currency,
        assessment_context: new AssessmentContextData(1, 'new', 2026, null, null),
        components: $components,
        modifiers: $modifiers,
        taxes: [],
        composition_policy_version: 'bpls.assessment-composition.fixed-minor-units.v1',
    );
}

it('uses exact Brick Money minor units and a Whitecube-inspired independent Price API', function (): void {
    $money = Money::ofMinor(122_000, 'PHP');
    $price = Price::fromMoney($money, 'Certified scenario');

    expect($price->money()->getCurrency()->getCurrencyCode())->toBe('PHP')
        ->and($price->money()->getMinorAmount()->toInt())->toBe(122_000)
        ->and($price->components())->toHaveCount(1)
        ->and(class_exists('Whitecube\\Price\\Price'))->toBeFalse();
});

it('serializes canonical ISO currency and minor units and fingerprints independently of symbols', function (): void {
    $input = priceInput([priceComponent('inspection', 35_000)]);
    $snapshot = $input->toArray();
    $fingerprints = app(CanonicalFinancialFingerprint::class);

    expect($snapshot)->currency->toBe('PHP')
        ->components->toHaveCount(1)
        ->and($snapshot['components'][0]['amount_minor'])->toBe(35_000)
        ->and(json_encode($snapshot))->not->toContain('₱');

    expect($fingerprints->hash([...$snapshot, 'symbol' => '₱']))
        ->toBe($fingerprints->hash([...$snapshot, 'symbol' => 'PHP']))
        ->toBe($fingerprints->hash($snapshot));
});

it('rejects mixed currencies duplicate exact-once keys and implicit exchange', function (): void {
    expect(fn () => Price::fromInput(priceInput([
        priceComponent('php', 100, 'PHP'),
        priceComponent('usd', 100, 'USD'),
    ])))->toThrow(LogicException::class, 'Mixed currencies');

    $component = priceComponent('duplicate', 100);
    expect(fn () => Price::fromInput(priceInput([$component, $component])))
        ->toThrow(LogicException::class, 'exact-once');
});

it('resolves the synthetic override specimen with a complete modifier chain and exact totals', function (): void {
    $health = priceComponent('health-certificate', 9_500, 'PHP', 2, 'Food Service');
    $modifier = new AssessmentPriceModifierInput(
        key: 'case_override:synthetic-health',
        type: 'case_override',
        target_exact_once_key: $health->exact_once_key,
        currency: 'PHP',
        amount_minor: -2_000,
        reason: 'Synthetic composition proof only.',
        authority: 'Synthetic UAT authority — not municipal policy.',
        actor_id: 1,
        office: 'health',
        occurred_at: '2026-09-04T00:00:00+08:00',
    );
    $price = Price::fromInput(priceInput([
        priceComponent('retail', 33_000, 'PHP', 1, 'Retail Trading'),
        $health,
        priceComponent('other-food', 44_500, 'PHP', 2, 'Food Service'),
        priceComponent('inspection', 35_000),
    ], [$modifier]));
    $resolved = $price->resolve();
    $report = $price->report()->toArray();

    expect($resolved->totalMinor())->toBe(120_000)
        ->and($report['total'])->toBe(['currency' => 'PHP', 'minor' => 120_000])
        ->and($report['modifiers'][0]['amount_minor'])->toBe(-2_000)
        ->and($report['modifiers'][0]['reason'])->toContain('Synthetic')
        ->and(collect($report['components'])->firstWhere('key', 'health-certificate')['scheduled_minor'])->toBe(9_500)
        ->and(collect($report['components'])->firstWhere('key', 'health-certificate')['resolved_minor'])->toBe(7_500);
});

it('keeps the DTO Eloquent-free and the wonderfully dumb engine database-free', function (): void {
    $dtoFiles = collect([
        app_path('Data/Assessment/AssessmentPriceInput.php'),
        app_path('Data/Assessment/AssessmentPriceComponentInput.php'),
        app_path('Data/Assessment/AssessmentPriceModifierInput.php'),
        app_path('Data/Assessment/AssessmentTaxInput.php'),
    ])->map(fn (string $path): string => file_get_contents($path));
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    $report = Price::fromInput(priceInput([priceComponent('no-db', 123)]))->report()->toArray();

    expect($dtoFiles->every(fn (string $source): bool => ! str_contains($source, 'Illuminate\\Database')
        && ! str_contains($source, 'App\\Models')))->toBeTrue()
        ->and($queries)->toBeEmpty()
        ->and($report['total']['minor'])->toBe(123);
});

it('projects both municipal fee families while excluding scenario amounts from shared truth', function (): void {
    $lob = LineOfBusiness::factory()->create(['name' => 'Food Service']);
    $applicationFee = FeeRule::factory()->create([
        'code' => 'APPLICATION-WIDE',
        'name' => 'Business Inspection Fee',
        'scope' => FeeRuleScope::Application,
        'amount_cents' => 35_000,
        'effective_from' => '2026-01-01',
        'metadata' => ['price_list_source_classification' => FeeRulePublicationSource::MunicipalConfirmationRequired->value],
    ]);
    $lobFee = FeeRule::factory()->for($lob)->create([
        'code' => 'HEALTH-CERTIFICATE',
        'scope' => FeeRuleScope::LineOfBusiness,
        'amount_cents' => 50_000,
        'effective_from' => '2026-01-01',
        'metadata' => ['price_list_source_classification' => FeeRulePublicationSource::MunicipalConfirmationRequired->value, 'responsible_office' => 'health'],
    ]);
    FeeRule::factory()->create([
        'code' => 'SCENARIO-ONLY',
        'amount_cents' => 99_999,
        'effective_from' => '2026-01-01',
        'metadata' => null,
    ]);
    $matrix = app(BuildFeeMatrixQuickLook::class)->handle();

    expect(collect($matrix['application_wide'])->pluck('id'))->toContain($applicationFee->id)
        ->and(collect($matrix['line_of_businesses'])->flatMap(fn (array $group): array => $group['fees'])->pluck('id'))->toContain($lobFee->id)
        ->and(json_encode($matrix))->not->toContain('SCENARIO-ONLY')->not->toContain('99999');
});

it('records append-only proposed revisions without execution or historical rewrite', function (): void {
    $actor = userWithPermissions([UserPermission::ManageFeeRules]);
    $rule = FeeRule::factory()->create([
        'amount_cents' => 35_000,
        'effective_from' => '2026-01-01',
    ]);
    $application = PermitApplication::factory()->create(['application_year' => 2026]);
    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application);
    $before = $assessment->price_report_snapshot;

    $revision = app(ProposeFeeRuleRevision::class)->handle(
        $rule,
        40_000,
        '2027-01-01',
        null,
        'Proposed future change.',
        'Draft authority reference for review.',
        $actor,
    );

    expect($revision)->version->toBe(1)->status->toBe('proposed')->activated_at->toBeNull()
        ->and($rule->refresh()->amount_cents)->toBe(35_000)
        ->and($assessment->refresh()->price_report_snapshot)->toBe($before)
        ->and($rule->auditEvents()->count())->toBe(1)
        ->and(fn () => $revision->update(['status' => 'activated']))->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $rule->auditEvents()->firstOrFail()->delete())->toThrow(LogicException::class, 'cannot be deleted');
});

it('keeps the citizen catalog restricted while staff quick look shares the governed FeeRule identity', function (): void {
    $rule = FeeRule::factory()->create([
        'code' => 'INTERNAL-CANDIDATE',
        'amount_cents' => 88_800,
        'effective_from' => '2026-01-01',
        'metadata' => ['price_list_source_classification' => FeeRulePublicationSource::MunicipalConfirmationRequired->value],
    ]);
    $staff = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewFeeRules]);

    $this->actingAs($staff)->getJson(route('staff.fee-matrix.index'))
        ->assertOk()->assertJsonPath('application_wide.0.id', $rule->id);
    $this->get(route('services-and-fees.index'))
        ->assertOk()
        ->assertDontSee('INTERNAL-CANDIDATE')
        ->assertDontSee('88800');
});

it('lets a concerned-office evaluator use only the read-only Fee Matrix', function (): void {
    $rule = FeeRule::factory()->create([
        'code' => 'ASSESSOR-REFERENCE',
        'amount_cents' => 12_300,
        'effective_from' => '2026-01-01',
    ]);
    FeeRule::factory()->create([
        'code' => 'HEALTH-ONLY-REFERENCE',
        'effective_from' => '2026-01-01',
        'metadata' => ['responsible_office' => 'health'],
    ]);
    $assessor = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewBusinessPermitEvaluations,
        UserPermission::ContributeBusinessPermitEvaluations,
    ]);

    $this->actingAs($assessor)
        ->getJson(route('staff.fee-matrix.index', ['office' => 'assessor']))
        ->assertOk()
        ->assertJsonCount(1, 'application_wide')
        ->assertJsonPath('application_wide.0.id', $rule->id);

    $this->actingAs($assessor)
        ->get(route('staff.fee-rules.index'))
        ->assertForbidden();

    $this->actingAs($assessor)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.can_view_fee_matrix', true)
            ->where('auth.can_view_fee_rules', false));
});

it('freezes input and report fingerprints with Assessment line and resolved total parity', function (): void {
    $rule = FeeRule::factory()->create([
        'code' => 'PARITY-FEE',
        'amount_cents' => 12_345,
        'effective_from' => '2026-01-01',
    ]);
    $assessment = app(CreateAssessmentForPermitApplication::class)->handle(
        PermitApplication::factory()->create(['application_year' => 2026]),
    );
    $fingerprint = app(CanonicalFinancialFingerprint::class);

    expect($assessment->currency)->toBe('PHP')
        ->and($assessment->total_amount_cents)->toBe(12_345)
        ->and($assessment->lines->sum('amount_cents'))->toBe(12_345)
        ->and(data_get($assessment->price_report_snapshot, 'total.minor'))->toBe(12_345)
        ->and($assessment->assessment_price_input_fingerprint)->toBe($fingerprint->hash($assessment->assessment_price_input_snapshot))
        ->and($assessment->price_report_fingerprint)->toBe($fingerprint->hash($assessment->price_report_snapshot))
        ->and(app(HistoricalPriceReport::class)->forAssessment($assessment))->toBe($assessment->price_report_snapshot)
        ->and($assessment->lines->sole()->fee_rule_id)->toBe($rule->id);
});

it('keeps certified 2025 New and 2026 Renewal scenarios unchanged at PHP 122000', function (string $scenario): void {
    Storage::fake('local');
    Artisan::call('bpls:install');
    app(ExecutePersistedLifecycleScenario::class)->handle($scenario);
    $assessment = Assessment::query()->sole();

    expect($assessment->total_amount_cents)->toBe(122_000)
        ->and($assessment->lines->sum('amount_cents'))->toBe(122_000)
        ->and(data_get($assessment->price_report_snapshot, 'total'))->toBe(['currency' => 'PHP', 'minor' => 122_000])
        ->and($assessment->assessment_price_input_fingerprint)->toHaveLength(64)
        ->and($assessment->price_report_fingerprint)->toHaveLength(64);
})->with([
    '2025 New' => NewApplicationHappyPathDefinition::Id,
    '2026 Renewal' => RenewalHappyPathDefinition::Id,
]);

it('retains driver-independent Assessment fingerprint parity for SQLite and PostgreSQL amount representations', function (): void {
    $assessment = Assessment::factory()->create(['total_amount_cents' => 12_345]);
    $fingerprint = app(AssessmentSnapshotFingerprint::class);
    $assessment->setAttribute('total_amount_cents', '12345');
    $postgresRepresentation = $fingerprint->hash($assessment);
    $assessment->setAttribute('total_amount_cents', 12_345);

    expect($fingerprint->hash($assessment))->toBe($postgresRepresentation);
});
