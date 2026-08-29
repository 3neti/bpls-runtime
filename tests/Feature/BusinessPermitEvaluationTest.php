<?php

use App\Actions\CompleteBusinessPermitEvaluationResponsibility;
use App\Actions\CorrectEvaluationLinesOfBusiness;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreatePaymentScheduleForAssessment;
use App\Actions\DefineBusinessPermitEvaluationItem;
use App\Actions\InitializeBusinessPermitEvaluation;
use App\Actions\PrepareBusinessPermitEvaluatorUatDataset;
use App\Actions\RecordAssessmentDecision;
use App\Actions\RecordBusinessPermitEvaluationCounterCheck;
use App\Actions\RefreshBusinessPermitEvaluation;
use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentDecisionAction;
use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationItemType;
use App\Enums\BusinessPermitEvaluationSource;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRulePublicationSource;
use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\StakeholderPreviewPersona;
use App\Enums\UserPermission;
use App\Evaluation\BusinessPermitEvaluationReadiness;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use App\Models\Business;
use App\Models\BusinessPermitEvaluationItemRevision;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\User;

function evaluationFixture(): array
{
    $actor = User::factory()->create();
    $business = Business::factory()->create();
    $retail = LineOfBusiness::factory()->create(['code' => 'RETAIL', 'name' => 'Retail']);
    $application = PermitApplication::factory()->for($business)->create([
        'submitted_by_id' => $actor->id,
        'status' => PermitApplicationStatus::Assessment,
        'submitted_at' => now(),
        'application_year' => 2026,
    ]);
    $line = PermitApplicationLine::factory()->for($application)->for($retail)->create([
        'declared_gross_sales_cents' => 500_000,
        'capital_investment_cents' => 250_000,
    ]);
    $evaluation = app(InitializeBusinessPermitEvaluation::class)->handle($application, $actor);

    return compact('actor', 'business', 'retail', 'application', 'line', 'evaluation');
}

it('creates a durable evaluation without overwriting the applicant declaration or business registry', function () {
    $fixture = evaluationFixture();
    $projection = app(BusinessPermitEvaluationResolver::class)->resolve($fixture['evaluation']->fresh());

    expect($fixture['evaluation']->versions()->count())->toBe(1)
        ->and($projection['fingerprint_current'])->toBeTrue()
        ->and($projection['resolved_line_of_business_ids'])->toBe([$fixture['retail']->id])
        ->and($projection['items'][0]['item_type'])->toBe('fact')
        ->and($projection['items'][0]['action'])->toBe('declaration')
        ->and($projection['items'][0]['revision_history'])->toHaveCount(1)
        ->and($fixture['application']->fresh()->business_id)->toBe($fixture['business']->id)
        ->and($fixture['business']->fresh()->name)->toBe($fixture['business']->name);
});

it('rejects blank charges, preserves accepted zero, and segregates provisional UAT from commissioned readiness', function () {
    $fixture = evaluationFixture();
    $define = app(DefineBusinessPermitEvaluationItem::class);

    expect(fn () => $define->handle(
        $fixture['evaluation'],
        'engineering.charge',
        BusinessPermitEvaluationItemType::Charge,
        'engineering',
        true,
        true,
        BusinessPermitEvaluationApplicability::Applicable,
        null,
        BusinessPermitEvaluationSource::ProvisionalUat,
        $fixture['actor'],
    ))->toThrow(LogicException::class, 'Undefined is not zero');

    $item = $define->handle(
        $fixture['evaluation'],
        'engineering.charge',
        BusinessPermitEvaluationItemType::Charge,
        'engineering',
        true,
        false,
        BusinessPermitEvaluationApplicability::Applicable,
        ['amount_cents' => 0],
        BusinessPermitEvaluationSource::ProvisionalUat,
        $fixture['actor'],
        metadata: ['label' => 'Engineering charge'],
    );

    app(RecordBusinessPermitEvaluationCounterCheck::class)->handle($fixture['evaluation']->fresh(), $fixture['actor']);
    $commissioned = app(BusinessPermitEvaluationReadiness::class)->forAssessment($fixture['evaluation']->fresh(), 'commissioned');
    $uat = app(BusinessPermitEvaluationReadiness::class)->forAssessment($fixture['evaluation']->fresh(), 'provisional_uat');

    expect($item->revisions()->latest()->first()->value['amount_cents'])->toBe(0)
        ->and($commissioned['ready'])->toBeFalse()
        ->and(implode(' ', $commissioned['issues']))->toContain('accepted commissioned source')
        ->and($uat['ready'])->toBeTrue();
});

it('preserves default and resolved amounts with idempotent optimistic office confirmation', function () {
    $fixture = evaluationFixture();
    $engineer = User::factory()->create();
    $item = app(DefineBusinessPermitEvaluationItem::class)->handle(
        $fixture['evaluation'],
        'engineering.review',
        BusinessPermitEvaluationItemType::Charge,
        'engineering',
        true,
        true,
        BusinessPermitEvaluationApplicability::Applicable,
        ['amount_cents' => 12_500, 'inspection' => ['required' => true, 'completed' => false]],
        BusinessPermitEvaluationSource::ProvisionalUat,
        $fixture['actor'],
        metadata: ['label' => 'Engineering evaluation', 'authorized_actor_id' => $engineer->id, 'inspection_required' => true],
    );
    $current = $fixture['evaluation']->fresh()->currentVersion;
    $commandKey = 'engineering-confirm-'.$item->id;
    $value = ['amount_cents' => 15_000, 'inspection' => ['required' => true, 'mode' => 'physical', 'completed' => true, 'findings' => 'Synthetic UAT finding']];
    $complete = app(CompleteBusinessPermitEvaluationResponsibility::class);

    $first = $complete->handle($item, $engineer, BusinessPermitEvaluationApplicability::Applicable, $value, BusinessPermitEvaluationSource::ProvisionalUat, 'Synthetic UAT adjustment.', $current->sequence, $current->fingerprint, $commandKey);
    $retry = $complete->handle($item, $engineer, BusinessPermitEvaluationApplicability::Applicable, $value, BusinessPermitEvaluationSource::ProvisionalUat, 'Synthetic UAT adjustment.', $current->sequence, $current->fingerprint, $commandKey);
    $projection = app(BusinessPermitEvaluationResolver::class)->resolve($fixture['evaluation']->fresh());
    $resolved = collect($projection['items'])->firstWhere('key', 'engineering.review');

    expect($retry->id)->toBe($first->id)
        ->and(BusinessPermitEvaluationItemRevision::query()->where('idempotency_key', $commandKey)->count())->toBe(1)
        ->and($resolved['default_value']['amount_cents'])->toBe(12_500)
        ->and($resolved['value']['amount_cents'])->toBe(15_000)
        ->and($resolved['action'])->toBe('correction')
        ->and($resolved['revision_history'])->toHaveCount(2);

    expect(fn () => $complete->handle(
        $item,
        $engineer,
        BusinessPermitEvaluationApplicability::Applicable,
        $value,
        BusinessPermitEvaluationSource::ProvisionalUat,
        'Stale attempt.',
        $current->sequence,
        $current->fingerprint,
        'different-stale-command',
    ))->toThrow(LogicException::class, 'Evaluation changed');
});

it('records Treasury LOB determination as a new version and leaves the original declaration and Business untouched', function () {
    $fixture = evaluationFixture();
    $restaurant = LineOfBusiness::factory()->create(['code' => 'RESTAURANT', 'name' => 'Restaurant']);
    $beforeBusiness = $fixture['business']->getAttributes();
    $current = $fixture['evaluation']->fresh()->currentVersion;
    $beforeProjection = app(BusinessPermitEvaluationResolver::class)->resolve($fixture['evaluation']->fresh());

    app(CorrectEvaluationLinesOfBusiness::class)->handle(
        $fixture['evaluation'],
        [$fixture['retail']->id, $restaurant->id],
        $fixture['actor'],
        'Synthetic UAT counter-check identified an additional activity.',
        $current->sequence,
        $current->fingerprint,
        'treasury-lob-'.$fixture['evaluation']->id,
    );

    $projection = app(BusinessPermitEvaluationResolver::class)->resolve($fixture['evaluation']->fresh());
    $lineItem = collect($projection['items'])->firstWhere('key', BusinessPermitEvaluationResolver::APPLICANT_LINES_ITEM_KEY);

    expect($fixture['evaluation']->versions()->count())->toBe(2)
        ->and($projection['current_fingerprint'])->not->toBe($beforeProjection['current_fingerprint'])
        ->and($projection['total_amount_cents'])->toBe($beforeProjection['total_amount_cents'])
        ->and($projection['resolved_line_of_business_ids'])->toBe([$fixture['retail']->id, $restaurant->id])
        ->and($lineItem['revision_history'][0]['action'])->toBe('declaration')
        ->and($lineItem['revision_history'][0]['value']['line_of_business_ids'])->toBe([$fixture['retail']->id])
        ->and($lineItem['action'])->toBe('authorized_determination')
        ->and($lineItem['reason'])->toContain('additional activity')
        ->and($fixture['application']->fresh()->lines()->count())->toBe(1)
        ->and($fixture['business']->fresh()->getAttributes())->toMatchArray($beforeBusiness);
});

it('binds one exact ready evaluation version to an idempotent assessment with canonical rule parity', function () {
    $fixture = evaluationFixture();
    FeeRule::factory()->create([
        'code' => 'PERMIT-FEE',
        'name' => 'Permit fee',
        'scope' => FeeRuleScope::Application,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'basis' => 'none',
        'amount_cents' => 25_000,
        'effective_from' => '2026-01-01',
    ]);
    app(RefreshBusinessPermitEvaluation::class)->handle($fixture['evaluation'], $fixture['actor']);
    app(RecordBusinessPermitEvaluationCounterCheck::class)->handle($fixture['evaluation']->fresh(), $fixture['actor']);

    $first = app(CreateAssessmentForPermitApplication::class)->handle($fixture['application']->fresh(), $fixture['actor']);
    $retry = app(CreateAssessmentForPermitApplication::class)->handle($fixture['application']->fresh(), $fixture['actor']);
    $current = $fixture['evaluation']->fresh()->currentVersion;

    expect($retry->id)->toBe($first->id)
        ->and($first->business_permit_evaluation_version_id)->toBe($current->id)
        ->and($first->business_permit_evaluation_fingerprint)->toBe($current->fingerprint)
        ->and($first->lines)->toHaveCount(1)
        ->and($first->lines->first()->fee_rule_id)->not->toBeNull()
        ->and($first->lines->first()->business_permit_evaluation_item_id)->toBeNull()
        ->and($first->total_amount_cents)->toBe(25_000)
        ->and(Assessment::query()->count())->toBe(1);
});

it('maps each governed and human-resolved charge exactly once without duplicate pricing paths', function () {
    $fixture = evaluationFixture();
    $feeRule = FeeRule::factory()->create([
        'code' => 'CANONICAL-RULE',
        'name' => 'Canonical rule charge',
        'scope' => FeeRuleScope::Application,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'basis' => 'none',
        'amount_cents' => 20_000,
        'effective_from' => '2026-01-01',
    ]);
    app(RefreshBusinessPermitEvaluation::class)->handle($fixture['evaluation'], $fixture['actor']);

    $item = app(DefineBusinessPermitEvaluationItem::class)->handle(
        $fixture['evaluation']->fresh(),
        'engineering.resolved-charge',
        BusinessPermitEvaluationItemType::Charge,
        'engineering',
        true,
        false,
        BusinessPermitEvaluationApplicability::Applicable,
        ['amount_cents' => 7_500],
        BusinessPermitEvaluationSource::GovernedOfficeProcedure,
        $fixture['actor'],
        metadata: ['label' => 'Resolved Engineering charge'],
    );
    app(RecordBusinessPermitEvaluationCounterCheck::class)->handle($fixture['evaluation']->fresh(), $fixture['actor']);

    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($fixture['application']->fresh(), $fixture['actor']);

    expect($assessment->lines)->toHaveCount(2)
        ->and($assessment->lines->where('fee_rule_id', $feeRule->id))->toHaveCount(1)
        ->and($assessment->lines->where('business_permit_evaluation_item_id', $item->id))->toHaveCount(1)
        ->and($assessment->lines->whereNotNull('fee_rule_id')->first()->business_permit_evaluation_item_id)->toBeNull()
        ->and($assessment->lines->whereNotNull('business_permit_evaluation_item_id')->first()->fee_rule_id)->toBeNull()
        ->and($assessment->total_amount_cents)->toBe(27_500);

    expect(fn () => app(DefineBusinessPermitEvaluationItem::class)->handle(
        $fixture['evaluation']->fresh(),
        'duplicate.rule-path',
        BusinessPermitEvaluationItemType::Charge,
        'engineering',
        true,
        false,
        BusinessPermitEvaluationApplicability::Applicable,
        ['amount_cents' => 20_000],
        BusinessPermitEvaluationSource::GovernedOfficeProcedure,
        $fixture['actor'],
        metadata: ['fee_rule_id' => $feeRule->id],
    ))->toThrow(LogicException::class, 'cannot also be defined as a human Evaluation charge');
});

it('detects rule drift, refreshes dynamic dependencies, and supersedes an unscheduled Assessment without mutating it', function () {
    $fixture = evaluationFixture();
    $feeRule = FeeRule::factory()->create([
        'code' => 'DYNAMIC-FEE',
        'scope' => FeeRuleScope::Application,
        'amount_cents' => 10_000,
        'effective_from' => '2026-01-01',
    ]);
    app(RefreshBusinessPermitEvaluation::class)->handle($fixture['evaluation'], $fixture['actor']);
    app(RecordBusinessPermitEvaluationCounterCheck::class)->handle($fixture['evaluation']->fresh(), $fixture['actor']);
    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($fixture['application']->fresh(), $fixture['actor']);

    $feeRule->update(['amount_cents' => 11_000]);
    $stale = app(BusinessPermitEvaluationReadiness::class)->forAssessment($fixture['evaluation']->fresh());
    expect($stale['ready'])->toBeFalse()->and(implode(' ', $stale['issues']))->toContain('fingerprint is stale');

    app(RefreshBusinessPermitEvaluation::class)->handle($fixture['evaluation']->fresh(), $fixture['actor']);

    expect($assessment->refresh()->superseded_at)->not->toBeNull()
        ->and($assessment->total_amount_cents)->toBe(10_000)
        ->and($fixture['evaluation']->fresh()->currentVersion->counterCheck)->toBeNull();
});

it('hard-stops every evaluation change after a Payment Schedule exists', function () {
    $fixture = evaluationFixture();
    $assessment = Assessment::factory()->for($fixture['application'])->create();
    PaymentSchedule::factory()->create([
        'permit_application_id' => $fixture['application']->id,
        'assessment_id' => $assessment->id,
    ]);

    expect(fn () => app(RefreshBusinessPermitEvaluation::class)->handle($fixture['evaluation'], $fixture['actor']))
        ->toThrow(LogicException::class, 'cannot change after a Payment Schedule exists');
});

it('keeps pre-Evaluator historical and operational applications compatible without synthetic Evaluation history', function () {
    $application = PermitApplication::factory()->create(['application_year' => 2026]);
    FeeRule::factory()->create(['code' => 'LEGACY-COMPAT', 'amount_cents' => 5_000, 'effective_from' => '2026-01-01']);

    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application);

    expect($application->businessPermitEvaluation()->exists())->toBeFalse()
        ->and($assessment->business_permit_evaluation_version_id)->toBeNull()
        ->and($assessment->business_permit_evaluation_fingerprint)->toBeNull()
        ->and($assessment->total_amount_cents)->toBe(5_000);
});

it('keeps Treasury counter-check authority separate from Municipal Treasurer exact-snapshot approval', function () {
    expect(StakeholderPreviewPersona::Treasury->permissions())
        ->toContain(UserPermission::CounterCheckBusinessPermitEvaluations, UserPermission::CorrectEvaluationLinesOfBusiness)
        ->not->toContain(UserPermission::ApproveAssessments)
        ->and(StakeholderPreviewPersona::MunicipalTreasurer->permissions())
        ->toContain(UserPermission::ApproveAssessments)
        ->not->toContain(UserPermission::CounterCheckBusinessPermitEvaluations, UserPermission::CorrectEvaluationLinesOfBusiness);
});

it('prepares an idempotent substantial provisional UAT Evaluator inventory with distinct responsibilities', function () {
    configureBusinessPermitEvaluatorPreviewSafety();
    $actors = businessPermitEvaluatorPreviewActors();
    $prepare = app(PrepareBusinessPermitEvaluatorUatDataset::class);

    $first = $prepare->handle('evaluator-test-run', $actors);
    $retry = $prepare->handle('evaluator-test-run', $actors);

    expect($first['semantic_classification'])->toBe('provisional_uat')
        ->and($first['production_liability'])->toBeFalse()
        ->and($first['cases'])->toHaveCount(14)
        ->and(array_keys($first['cases']))->toContain(
            'financial-working-paper',
            'awaiting-engineering',
            'awaiting-health',
            'office-confirms-default',
            'office-override',
            'accepted-not-applicable',
            'ready-for-assessment',
            'assessment-prepared',
            'treasury-lob-reopens',
            'fresh-reassessment',
            'treasurer-approved',
            'returned-for-correction',
            'payment-locked',
        )
        ->and($retry)->toBe($first);

    $locked = PermitApplication::query()->findOrFail($first['cases']['payment-locked']['permit_application_id']);
    $reopened = PermitApplication::query()->findOrFail($first['cases']['treasury-lob-reopens']['permit_application_id']);
    $workingPaper = PermitApplication::query()->findOrFail($first['cases']['financial-working-paper']['permit_application_id']);

    expect($locked->paymentSchedules()->count())->toBe(1)
        ->and($locked->status)->toBe(PermitApplicationStatus::PendingPayment)
        ->and($reopened->assessments()->whereNotNull('superseded_at')->count())->toBe(1)
        ->and($reopened->businessPermitEvaluation->items()->where('responsible_party', 'health')->exists())->toBeTrue()
        ->and($workingPaper->businessPermitEvaluation->items()->count())->toBe(10)
        ->and($workingPaper->businessPermitEvaluation->items()->where('item_type', 'charge')->count())->toBe(9);
});

it('operates one assessment-slip-shaped multi-LOB working paper through reassessment approval and payment lock', function () {
    configureBusinessPermitEvaluatorPreviewSafety();
    $actors = businessPermitEvaluatorPreviewActors();
    $inventory = app(PrepareBusinessPermitEvaluatorUatDataset::class)->handle('working-paper-run', $actors);
    $application = PermitApplication::query()->findOrFail($inventory['cases']['financial-working-paper']['permit_application_id']);
    $evaluation = $application->businessPermitEvaluation;
    $resolver = app(BusinessPermitEvaluationResolver::class);
    $complete = app(CompleteBusinessPermitEvaluationResponsibility::class);

    $initial = $resolver->resolve($evaluation->fresh());
    $officeCharges = collect($initial['items'])->where('item_type', 'charge');
    $applicableOfficeCharges = $officeCharges->where('applicability', 'applicable');

    expect($initial['projected_charges'])->toHaveCount(1)
        ->and($application->type)->toBe(PermitApplicationType::Renewal)
        ->and($initial['projected_charges'][0]['amount_cents'])->toBe(10_000)
        ->and($officeCharges)->toHaveCount(9)
        ->and($applicableOfficeCharges)->toHaveCount(7)
        ->and($applicableOfficeCharges->pluck('responsible_party')->sort()->values()->all())->toBe([
            'assessor',
            'assessor',
            'assessor',
            'engineering',
            'engineering',
            'health',
            'menro',
        ])
        ->and($applicableOfficeCharges->every(
            fn (array $item): bool => is_int(data_get($item, 'default_value.amount_cents'))
                && data_get($item, 'default_value.amount_cents') >= 0
                && $item['resolution'] === 'awaiting_responsible_confirmation'
                && $item['default_source_classification'] === 'provisional_uat',
        ))->toBeTrue()
        ->and($initial['total_amount_cents'])->toBe(10_000);

    $record = function (
        string $key,
        User $actor,
        BusinessPermitEvaluationApplicability $applicability,
        int $amount,
        ?string $reason,
        bool $inspectionCompleted,
    ) use ($evaluation, $complete): void {
        $item = $evaluation->items()->where('key', $key)->sole();
        $version = $evaluation->fresh()->currentVersion;

        $complete->handle(
            $item,
            $actor,
            $applicability,
            [
                'amount_cents' => $amount,
                'inspection' => [
                    'required' => (bool) data_get($item->metadata, 'inspection_required'),
                    'mode' => 'document_review',
                    'completed' => $inspectionCompleted,
                    'findings' => $reason,
                ],
            ],
            BusinessPermitEvaluationSource::ProvisionalUat,
            $reason,
            $version->sequence,
            $version->fingerprint,
            'working-paper-'.$key.'-'.$applicability->value,
        );
    };

    $record('retail.business-tax.charge', $actors['assessor'], BusinessPermitEvaluationApplicability::Applicable, 14_300, 'Assessor confirmed the synthetic Retail Business Tax proposal.', true);
    $record('retail.mayors-permit.charge', $actors['engineering'], BusinessPermitEvaluationApplicability::Applicable, 8_200, 'Engineering corrected the synthetic Mayor permit proposal after inspection.', true);
    $mayorsPermit = collect($resolver->resolve($evaluation->fresh())['items'])->firstWhere('key', 'retail.mayors-permit.charge');
    expect(data_get($mayorsPermit, 'default_value.amount_cents'))->toBe(7_600)
        ->and(data_get($mayorsPermit, 'value.amount_cents'))->toBe(8_200)
        ->and($mayorsPermit['action'])->toBe('correction')
        ->and($mayorsPermit['reason'])->toBe('Engineering corrected the synthetic Mayor permit proposal after inspection.');

    $record('retail.weight-measure.charge', $actors['assessor'], BusinessPermitEvaluationApplicability::NotApplicable, 3_400, 'Weight and Measure is legitimately not applicable to this synthetic Retail activity.', true);
    $record('repair.business-tax.charge', $actors['assessor'], BusinessPermitEvaluationApplicability::Applicable, 16_800, 'Assessor confirmed the synthetic Repair Business Tax proposal.', true);
    $record('repair.occupation-fee.charge', $actors['engineering'], BusinessPermitEvaluationApplicability::Applicable, 6_900, 'Engineering confirmed the synthetic Occupation Fee proposal.', true);
    $record('repair.solid-waste.charge', $actors['menro'], BusinessPermitEvaluationApplicability::Applicable, 5_200, 'MENRO confirmed the synthetic Solid Waste proposal.', true);
    $record('repair.sanitary-permit.charge', $actors['health'], BusinessPermitEvaluationApplicability::Applicable, 4_600, 'Health confirmed the synthetic Sanitary Permit proposal.', true);

    $afterOffices = $resolver->resolve($evaluation->fresh());
    $weightMeasure = collect($afterOffices['items'])->firstWhere('key', 'retail.weight-measure.charge');
    expect($afterOffices['total_amount_cents'])->toBe(66_000)
        ->and($weightMeasure['applicability'])->toBe('not_applicable')
        ->and($weightMeasure['resolution'])->toBe('resolved')
        ->and(data_get($afterOffices, 'financial_working_paper.line_sections.0.subtotal_amount_cents'))->toBe(22_500)
        ->and(data_get($afterOffices, 'financial_working_paper.line_sections.1.subtotal_amount_cents'))->toBe(33_500)
        ->and(data_get($afterOffices, 'financial_working_paper.application_subtotal_amount_cents'))->toBe(10_000)
        ->and(data_get($afterOffices, 'financial_working_paper.grand_total_amount_cents'))->toBe(66_000);

    app(RecordBusinessPermitEvaluationCounterCheck::class)->handle($evaluation->fresh(), $actors['treasury']);
    expect(app(BusinessPermitEvaluationReadiness::class)->forAssessment($evaluation->fresh(), 'provisional_uat')['ready'])->toBeTrue();

    $firstAssessment = app(CreateAssessmentForPermitApplication::class)->handle($application->fresh(), $actors['assessment_officer']);
    expect($firstAssessment->total_amount_cents)->toBe(66_000)
        ->and($firstAssessment->lines)->toHaveCount(7)
        ->and($firstAssessment->lines->sum('amount_cents'))->toBe(66_000)
        ->and($firstAssessment->lines->pluck('business_permit_evaluation_item_id')->filter()->unique())->toHaveCount(6)
        ->and($firstAssessment->lines->whereNotNull('line_of_business_id'))->toHaveCount(6);

    $retailId = LineOfBusiness::query()->where('code', 'EVAL-UAT-RETAIL')->sole()->id;
    $repairId = LineOfBusiness::query()->where('code', 'EVAL-UAT-REPAIR')->sole()->id;
    $restaurantId = LineOfBusiness::query()->where('code', 'EVAL-UAT-RESTAURANT')->sole()->id;
    $beforeCorrection = $evaluation->fresh()->currentVersion;
    app(CorrectEvaluationLinesOfBusiness::class)->handle(
        $evaluation,
        [$retailId, $repairId, $restaurantId],
        $actors['treasury'],
        'Treasury identified Restaurant in addition to the preserved Retail declaration.',
        $beforeCorrection->sequence,
        $beforeCorrection->fingerprint,
        'working-paper-lob-correction',
    );

    $versionCountAfterCorrection = $evaluation->versions()->count();
    app(CorrectEvaluationLinesOfBusiness::class)->handle(
        $evaluation,
        [$retailId, $repairId, $restaurantId],
        $actors['treasury'],
        'Treasury identified Restaurant in addition to the preserved Retail declaration.',
        $beforeCorrection->sequence,
        $beforeCorrection->fingerprint,
        'working-paper-lob-correction',
    );

    $reopened = $resolver->resolve($evaluation->fresh());
    $restaurantHealth = collect($reopened['items'])->firstWhere('key', 'restaurant.health-certificate.charge');
    expect(data_get($reopened, 'application.declared_lines.0.line_of_business_id'))->toBe($retailId)
        ->and($reopened['resolved_line_of_business_ids'])->toBe([$retailId, $repairId, $restaurantId])
        ->and($evaluation->fresh()->currentVersion->sequence)->toBe($beforeCorrection->sequence + 2)
        ->and($evaluation->versions()->count())->toBe($versionCountAfterCorrection)
        ->and($firstAssessment->fresh()->superseded_at)->not->toBeNull()
        ->and($restaurantHealth['applicability'])->toBe('applicable')
        ->and($restaurantHealth['resolution'])->toBe('awaiting_responsible_confirmation')
        ->and(data_get($restaurantHealth, 'default_value.amount_cents'))->toBe(8_700)
        ->and($reopened['total_amount_cents'])->toBe(66_000)
        ->and(data_get($reopened, 'financial_working_paper.grand_total_available'))->toBeFalse()
        ->and(data_get($reopened, 'financial_working_paper.grand_total_amount_cents'))->toBeNull();

    $record('restaurant.health-certificate.charge', $actors['health'], BusinessPermitEvaluationApplicability::Applicable, 8_700, 'Health completed the synthetic Restaurant Health Certificate review.', true);
    $record('restaurant.sanitary-permit.charge', $actors['health'], BusinessPermitEvaluationApplicability::Applicable, 6_100, 'Health completed the synthetic Restaurant Sanitary Permit review.', true);
    app(RecordBusinessPermitEvaluationCounterCheck::class)->handle($evaluation->fresh(), $actors['treasury']);
    $resolved = $resolver->resolve($evaluation->fresh());
    $freshAssessment = app(CreateAssessmentForPermitApplication::class)->handle($application->fresh(), $actors['assessment_officer']);

    expect($resolved['total_amount_cents'])->toBe(80_800)
        ->and(data_get($resolved, 'financial_working_paper.line_sections.2.subtotal_amount_cents'))->toBe(14_800)
        ->and(data_get($resolved, 'financial_working_paper.grand_total_amount_cents'))->toBe(80_800)
        ->and($freshAssessment->total_amount_cents)->toBe(80_800)
        ->and($freshAssessment->lines->sum('amount_cents'))->toBe(80_800)
        ->and($freshAssessment->lines)->toHaveCount(9)
        ->and($freshAssessment->lines->pluck('business_permit_evaluation_item_id')->filter()->unique())->toHaveCount(8)
        ->and($freshAssessment->business_permit_evaluation_version_id)->toBe($evaluation->fresh()->currentVersion->id)
        ->and($freshAssessment->business_permit_evaluation_fingerprint)->toBe($evaluation->fresh()->currentVersion->fingerprint);

    $decision = app(RecordAssessmentDecision::class)->handle(
        $freshAssessment,
        $actors['municipal_treasurer'],
        AssessmentDecisionAction::Approved,
        app(AssessmentSnapshotFingerprint::class)->hash($freshAssessment),
    );
    $schedule = app(CreatePaymentScheduleForAssessment::class)->handle($freshAssessment, $actors['assessment_officer']);

    expect($decision->action)->toBe(AssessmentDecisionAction::Approved)
        ->and($decision->assessment_snapshot_hash)->toBe(app(AssessmentSnapshotFingerprint::class)->hash($freshAssessment->fresh()))
        ->and($schedule->total_amount_cents)->toBe(80_800)
        ->and($application->fresh()->status)->toBe(PermitApplicationStatus::PendingPayment);

    $engineering = $evaluation->items()->where('key', 'retail.mayors-permit.charge')->sole();
    $lockedVersion = $evaluation->fresh()->currentVersion;
    expect(fn () => $complete->handle(
        $engineering,
        $actors['engineering'],
        BusinessPermitEvaluationApplicability::Applicable,
        ['amount_cents' => 8_200, 'inspection' => ['required' => true, 'completed' => true]],
        BusinessPermitEvaluationSource::ProvisionalUat,
        'Attempted mutation after scheduling.',
        $lockedVersion->sequence,
        $lockedVersion->fingerprint,
        'working-paper-payment-lock',
    ))->toThrow(LogicException::class, 'cannot change after a Payment Schedule exists');
});

it('normalizes legacy preview pricing across three persistent runs without changing historical snapshots or municipal rules', function () {
    configureBusinessPermitEvaluatorPreviewSafety();

    $legacyRules = collect(['first-run', 'second-run'])->map(fn (string $runId): FeeRule => FeeRule::factory()->create([
        'code' => 'EVAL-UAT-BASE-'.$runId,
        'name' => 'Evaluator UAT base proposal',
        'scope' => FeeRuleScope::Application,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'basis' => 'none',
        'amount_cents' => 10_000,
        'effective_from' => '2099-01-01',
        'effective_until' => '2099-12-31',
        'is_active' => true,
        'metadata' => [
            'semantic_classification' => 'provisional_uat',
            'uat_run_id' => $runId,
            'production_liability' => false,
        ],
    ]));
    $historicalAssessment = Assessment::factory()->create(['total_amount_cents' => 10_000]);
    $historicalLine = AssessmentLine::factory()->for($historicalAssessment)->create([
        'fee_rule_id' => $legacyRules->first()->id,
        'code' => $legacyRules->first()->code,
        'name' => $legacyRules->first()->name,
        'amount_cents' => 10_000,
        'rule_snapshot' => [
            'code' => $legacyRules->first()->code,
            'amount_cents' => 10_000,
            'semantic_classification' => 'provisional_uat',
            'uat_run_id' => 'first-run',
        ],
    ]);
    $historicalSnapshot = $historicalLine->rule_snapshot;
    $municipalRule = FeeRule::factory()->create([
        'code' => 'MUNICIPAL-ACCEPTED-UNCHANGED',
        'effective_from' => '2026-01-01',
        'effective_until' => '2026-12-31',
        'metadata' => [
            'semantic_classification' => 'accepted_municipal_authority',
            'price_list_source_classification' => 'accepted_municipal_authority',
        ],
    ]);
    $municipalState = $municipalRule->refresh()->getAttributes();
    $prepare = app(PrepareBusinessPermitEvaluatorUatDataset::class);
    $actors = businessPermitEvaluatorPreviewActors();
    $preparedAssessments = collect();
    $goldenWorkingPaperTotals = collect();

    foreach (['normalized-run-one', 'normalized-run-two', 'normalized-run-three'] as $runId) {
        $inventory = $prepare->handle($runId, $actors);
        $application = PermitApplication::query()
            ->whereKey($inventory['cases']['assessment-prepared']['permit_application_id'])
            ->sole();
        $assessment = $application->assessments()->whereNull('superseded_at')->with('lines')->sole();
        $preparedAssessments->push($assessment);
        $goldenApplication = PermitApplication::query()
            ->whereKey($inventory['cases']['financial-working-paper']['permit_application_id'])
            ->sole();
        $goldenProjection = app(BusinessPermitEvaluationResolver::class)->resolve($goldenApplication->businessPermitEvaluation);
        $goldenWorkingPaperTotals->push($goldenProjection['total_amount_cents']);

        expect($assessment->total_amount_cents)->toBe(10_000)
            ->and($assessment->lines)->toHaveCount(1)
            ->and($assessment->lines->sole()->code)->toBe('EVAL-UAT-BASE')
            ->and($inventory['pricing_fixture'])->toBe([
                'stable_code' => 'EVAL-UAT-BASE',
                'active_rule_count' => 1,
                'inactive_legacy_rule_count' => 2,
            ])
            ->and($goldenApplication->businessPermitEvaluation->items()->where('item_type', 'charge')->count())->toBe(9)
            ->and($goldenProjection['projected_charges'])->toHaveCount(1)
            ->and($goldenProjection['total_amount_cents'])->toBe(10_000)
            ->and(data_get($goldenProjection, 'financial_working_paper.grand_total_available'))->toBeFalse()
            ->and(FeeRule::query()->where('code', 'EVAL-UAT-BASE')->where('is_active', true)->count())->toBe(1);
    }

    $stableRule = FeeRule::query()->where('code', 'EVAL-UAT-BASE')->sole();

    expect($legacyRules->map->refresh()->pluck('is_active')->all())->toBe([false, false])
        ->and(FeeRule::query()->where('code', 'like', 'EVAL-UAT-BASE%')->where('is_active', true)->count())->toBe(1)
        ->and(data_get($stableRule->metadata, 'fixture_family'))->toBe('evaluator_uat_base')
        ->and(data_get($stableRule->metadata, 'latest_uat_run_id'))->toBe('normalized-run-three')
        ->and(FeeRulePublicationSource::forRule($stableRule))->toBe(FeeRulePublicationSource::ProvisionalUat)
        ->and(FeeRulePublicationSource::forRule($stableRule)->mayPublishExactAmount())->toBeFalse()
        ->and($historicalAssessment->fresh()->total_amount_cents)->toBe(10_000)
        ->and($historicalLine->fresh()->fee_rule_id)->toBe($legacyRules->first()->id)
        ->and($historicalLine->rule_snapshot)->toBe($historicalSnapshot)
        ->and($preparedAssessments->first()->fresh()->total_amount_cents)->toBe(10_000)
        ->and($preparedAssessments->first()->lines()->sole()->rule_snapshot['code'])->toBe('EVAL-UAT-BASE')
        ->and($goldenWorkingPaperTotals->all())->toBe([10_000, 10_000, 10_000])
        ->and($municipalRule->fresh()->getAttributes())->toBe($municipalState);
});

it('refuses evaluator fixture mutation outside preview mode', function () {
    config()->set('stakeholder_preview.mode', false);
    $feeRuleCount = FeeRule::query()->count();
    $applicationCount = PermitApplication::query()->count();

    expect(fn () => app(PrepareBusinessPermitEvaluatorUatDataset::class)->handle(
        'non-preview-run',
        businessPermitEvaluatorPreviewActors(),
    ))->toThrow(RuntimeException::class, 'outside the canonical stakeholder preview')
        ->and(FeeRule::query()->count())->toBe($feeRuleCount)
        ->and(PermitApplication::query()->count())->toBe($applicationCount);
});

it('fails closed without changing an accepted rule that occupies the stable preview identity', function () {
    configureBusinessPermitEvaluatorPreviewSafety();
    $acceptedRule = FeeRule::factory()->create([
        'code' => 'EVAL-UAT-BASE',
        'effective_from' => '2099-01-01',
        'metadata' => [
            'semantic_classification' => 'accepted_municipal_authority',
            'price_list_source_classification' => 'accepted_municipal_authority',
        ],
    ]);
    $acceptedState = $acceptedRule->refresh()->getAttributes();

    expect(fn () => app(PrepareBusinessPermitEvaluatorUatDataset::class)->handle(
        'identity-collision-run',
        businessPermitEvaluatorPreviewActors(),
    ))->toThrow(RuntimeException::class, 'occupied by a non-preview rule')
        ->and($acceptedRule->fresh()->getAttributes())->toBe($acceptedState)
        ->and(PermitApplication::query()->where('metadata->business_permit_evaluation->uat_run_id', 'identity-collision-run')->exists())->toBeFalse();
});

/**
 * @return array{
 *     citizen: User,
 *     assessment_officer: User,
 *     treasury: User,
 *     municipal_treasurer: User,
 *     engineering: User,
 *     mpdo: User,
 *     assessor: User,
 *     health: User,
 *     menro: User
 * }
 */
function businessPermitEvaluatorPreviewActors(): array
{
    return [
        'citizen' => User::factory()->create(),
        'assessment_officer' => User::factory()->create(),
        'treasury' => User::factory()->create(),
        'municipal_treasurer' => User::factory()->create(),
        'engineering' => User::factory()->create(),
        'mpdo' => User::factory()->create(),
        'assessor' => User::factory()->create(),
        'health' => User::factory()->create(),
        'menro' => User::factory()->create(),
    ];
}

function configureBusinessPermitEvaluatorPreviewSafety(): void
{
    config()->set([
        'stakeholder_preview.mode' => true,
        'stakeholder_preview.profile' => 'stakeholder_preview_weekend_v1',
        'stakeholder_preview.data_classification' => 'synthetic_only',
        'stakeholder_preview.pii_mode' => 'synthetic_only',
        'stakeholder_preview.production_migration_enabled' => false,
        'stakeholder_preview.production_integrations' => 'disabled',
    ]);
}
