<?php

use App\Actions\CompleteBusinessPermitEvaluationResponsibility;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\DefineBusinessPermitEvaluationItem;
use App\Actions\DescribeBusinessPermitEvaluation;
use App\Actions\InitializeBusinessPermitEvaluation;
use App\Actions\RecordBusinessPermitEvaluationCounterCheck;
use App\Data\Evaluation\BusinessPermitEvaluationData;
use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationItemType;
use App\Enums\BusinessPermitEvaluationSource;
use App\Enums\PermitApplicationStatus;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\Business;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\User;

/** @return array<string, mixed> */
function contractFixture(): array
{
    $actor = User::factory()->create();
    $business = Business::factory()->create();
    $lineOfBusiness = LineOfBusiness::factory()->create(['name' => 'Retail']);
    $application = PermitApplication::factory()->for($business)->create([
        'submitted_by_id' => $actor->id,
        'status' => PermitApplicationStatus::Assessment,
        'submitted_at' => now(),
    ]);
    PermitApplicationLine::factory()->for($application)->for($lineOfBusiness)->create([
        'declared_gross_sales_cents' => 750_000,
    ]);
    $evaluation = app(InitializeBusinessPermitEvaluation::class)->handle($application, $actor);

    return compact('actor', 'business', 'lineOfBusiness', 'application', 'evaluation');
}

it('projects the exact canonical version, fingerprint, and resolved total — the Data object is not a second source of truth', function () {
    $fixture = contractFixture();
    $canonical = app(BusinessPermitEvaluationResolver::class)->resolve($fixture['evaluation']->fresh());

    $data = app(DescribeBusinessPermitEvaluation::class)->handle($fixture['evaluation']->fresh(), $fixture['actor'], 'internal');

    expect($data)->toBeInstanceOf(BusinessPermitEvaluationData::class)
        ->and($data->version->id)->toBe($canonical['version_id'])
        ->and($data->version->sequence)->toBe($canonical['version_sequence'])
        ->and($data->version->fingerprint)->toBe($canonical['current_fingerprint'])
        ->and($data->version->fingerprint_current)->toBe($canonical['fingerprint_current'])
        ->and($data->current_evaluated_amount_cents)->toBe($canonical['total_amount_cents'])
        ->and($data->pricing_issues)->toBe($canonical['pricing_issues'])
        ->and(count($data->items))->toBe(count($canonical['items']));
});

it('preserves the canonical item taxonomy and keeps default proposal distinct from resolved value', function () {
    $fixture = contractFixture();
    $engineer = User::factory()->create();
    $item = app(DefineBusinessPermitEvaluationItem::class)->handle(
        $fixture['evaluation'],
        'engineering.review',
        BusinessPermitEvaluationItemType::Charge,
        'engineering',
        true,
        true,
        BusinessPermitEvaluationApplicability::Applicable,
        ['amount_cents' => 12_500],
        BusinessPermitEvaluationSource::ProvisionalUat,
        $fixture['actor'],
        metadata: ['label' => 'Engineering evaluation', 'authorized_actor_id' => $engineer->id],
    );
    $current = $fixture['evaluation']->fresh()->currentVersion;
    app(CompleteBusinessPermitEvaluationResponsibility::class)->handle(
        $item,
        $engineer,
        BusinessPermitEvaluationApplicability::Applicable,
        ['amount_cents' => 15_000],
        BusinessPermitEvaluationSource::ProvisionalUat,
        'Synthetic UAT adjustment.',
        $current->sequence,
        $current->fingerprint,
        'contract-test-confirm',
    );

    $data = app(DescribeBusinessPermitEvaluation::class)->handle($fixture['evaluation']->fresh(), $engineer, 'internal');
    $reviewItem = collect($data->items)->firstWhere('key', 'engineering.review');

    expect(collect($data->items)->pluck('item_type')->unique()->all())
        ->each(fn ($type) => $type->toBeIn(['fact', 'determination', 'charge']))
        ->and($reviewItem->default_value['amount_cents'])->toBe(12_500)
        ->and($reviewItem->resolved_value['amount_cents'])->toBe(15_000)
        ->and($reviewItem->default_value)->not->toBe($reviewItem->resolved_value);
});

it('never lets a provisional_uat proposal masquerade as commissioned pricing in the projection', function () {
    $fixture = contractFixture();
    app(DefineBusinessPermitEvaluationItem::class)->handle(
        $fixture['evaluation'],
        'engineering.charge',
        BusinessPermitEvaluationItemType::Charge,
        'engineering',
        true,
        false,
        BusinessPermitEvaluationApplicability::Applicable,
        ['amount_cents' => 12_500],
        BusinessPermitEvaluationSource::ProvisionalUat,
        $fixture['actor'],
        metadata: ['label' => 'Engineering charge'],
    );

    $data = app(DescribeBusinessPermitEvaluation::class)->handle($fixture['evaluation']->fresh(), $fixture['actor'], 'internal');
    $engineeringItem = collect($data->items)->firstWhere('key', 'engineering.charge');

    expect($engineeringItem->source_classification)->toBe('provisional_uat')
        ->and($engineeringItem->default_source_classification)->toBe('provisional_uat')
        ->and(collect($data->projected_charges)->pluck('source_classification'))
        ->each(fn ($classification) => $classification->not->toBe('accepted_municipal_authority'));
});

it('hides internal actor identity and counter-check evidence provenance from the citizen lens', function () {
    $fixture = contractFixture();

    $internal = app(DescribeBusinessPermitEvaluation::class)->handle($fixture['evaluation']->fresh(), $fixture['actor'], 'internal');
    $citizen = app(DescribeBusinessPermitEvaluation::class)->handle($fixture['evaluation']->fresh(), $fixture['actor'], 'citizen');

    $internalRevision = collect($internal->items[0]->history)->first();
    $citizenRevision = collect($citizen->items[0]->history)->first();

    expect($internalRevision->actor_name)->not->toBeNull()
        ->and($citizenRevision->actor_name)->toBeNull()
        ->and($citizen->lens)->toBe('citizen')
        ->and($internal->lens)->toBe('internal');
});

it('traces an Assessment back to the exact Evaluation version and fingerprint that produced it', function () {
    $fixture = contractFixture();
    app(RecordBusinessPermitEvaluationCounterCheck::class)->handle($fixture['evaluation']->fresh(), $fixture['actor']);
    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($fixture['application']->fresh(), $fixture['actor']);

    $data = app(DescribeBusinessPermitEvaluation::class)->handle($fixture['evaluation']->fresh(), $fixture['actor'], 'internal');

    expect($data->latest_assessment)->not->toBeNull()
        ->and($data->latest_assessment->id)->toBe($assessment->id)
        ->and($data->latest_assessment->evaluation_version_id)->toBe($data->version->id)
        ->and($data->latest_assessment->evaluation_fingerprint)->toBe($data->version->fingerprint)
        ->and($data->latest_assessment->consumes_current_evaluation)->toBeTrue();
});

it('does not fabricate Evaluation history for an application that never entered the Evaluator', function () {
    $business = Business::factory()->create();
    $application = PermitApplication::factory()->for($business)->create([
        'status' => PermitApplicationStatus::Assessment,
        'submitted_at' => now(),
    ]);

    expect($application->businessPermitEvaluation()->first())->toBeNull();
});
