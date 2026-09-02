<?php

use App\Actions\ExecutePersistedLifecycleScenario;
use App\Assessment\AssessmentComponent;
use App\Assessment\AssessmentComponentProjector;
use App\Assessment\AssessmentComposition;
use App\Enums\AssessmentComponentScope;
use App\Enums\AssessmentComponentType;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\Models\Assessment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

test('canonical 2025 and 2026 assessments project exact-once deterministic BPLS components', function (string $scenarioId): void {
    Storage::fake('local');
    Artisan::call('bpls:install');
    app(ExecutePersistedLifecycleScenario::class)->handle($scenarioId);

    $assessment = Assessment::query()->with('lines')->sole();
    $components = app(AssessmentComponentProjector::class)->fromAssessment($assessment);
    $ordered = app(AssessmentComposition::class)->ordered($components);

    expect($components)->toHaveCount(7)
        ->and($components->where('type', AssessmentComponentType::PaperlessPaymentOrder))->toHaveCount(6)
        ->and($components->where('type', AssessmentComponentType::GovernedFee))->toHaveCount(1)
        ->and($components->pluck('exactOnceKey')->unique())->toHaveCount(7)
        ->and($ordered->pluck('exactOnceKey')->all())->toBe($ordered->pluck('exactOnceKey')->sort()->values()->all())
        ->and(app(AssessmentComposition::class)->totalAmountCents($components))->toBe(122_000)
        ->and($components->whereNotNull('lineOfBusinessId')->groupBy('lineOfBusinessId')->map->sum('amountCents')->values()->sort()->values()->all())->toBe([33_000, 54_000])
        ->and($components->where('scope', AssessmentComponentScope::Application)->sum('amountCents'))->toBe(35_000)
        ->and($components->every(fn (AssessmentComponent $component): bool => $component->roundingInstruction === 'none_fixed_minor_units'))->toBeTrue()
        ->and($components->every(fn (AssessmentComponent $component): bool => data_get($component->immutableProjection(), 'currency') === 'PHP'))->toBeTrue();
})->with([
    '2025 New' => NewApplicationHappyPathDefinition::Id,
    '2026 Renewal' => RenewalHappyPathDefinition::Id,
]);

test('component composition refuses duplicate sources and uncommissioned percentage arithmetic', function (): void {
    $component = new AssessmentComponent(
        key: 'MRC-3A-04-BUSINESS-INSPECTION',
        type: AssessmentComponentType::GovernedFee,
        scope: AssessmentComponentScope::Application,
        permitApplicationLineId: null,
        lineOfBusinessId: null,
        sourceType: 'fee_rule',
        sourceId: '1:application',
        exactOnceKey: 'fee_rule:1:application',
        responsibleOffice: null,
        policyVersion: 'fee_rule:1:2025-01-01:2025-12-31',
        amountCents: 35_000,
        orderingPhase: 100,
        percentageBaseKeys: [],
        roundingInstruction: 'none_fixed_minor_units',
        explanationSnapshot: ['basis' => 'accepted fixed cents'],
    );

    expect(fn () => app(AssessmentComposition::class)->ordered([$component, $component]))
        ->toThrow(LogicException::class, 'exact-once');

    $percentage = new AssessmentComponent(
        key: 'UNCOMMISSIONED-PERCENTAGE',
        type: AssessmentComponentType::Surcharge,
        scope: AssessmentComponentScope::Application,
        permitApplicationLineId: null,
        lineOfBusinessId: null,
        sourceType: 'uncommissioned_policy',
        sourceId: 'percentage-example',
        exactOnceKey: 'uncommissioned_policy:percentage-example',
        responsibleOffice: null,
        policyVersion: 'uncommissioned',
        amountCents: 0,
        orderingPhase: 200,
        percentageBaseKeys: [$component->exactOnceKey],
        roundingInstruction: 'unresolved',
        explanationSnapshot: ['status' => 'blocked'],
    );

    expect(fn () => app(AssessmentComposition::class)->ordered([$component, $percentage]))
        ->toThrow(UnsupportedAssessmentPolicy::class, 'blocked until its rate, explicit base, ordering, and rounding policy are commissioned');
});
