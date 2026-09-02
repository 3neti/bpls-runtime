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
use App\Models\BusinessPermitEvaluationItem;
use App\Models\FeeRule;
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
    $routing = $application->bploRoutingDetermination()->create([
        'determined_by_id' => $actor->id,
        'situational_context' => 'Test-only explicit BPLO routing fixture.',
        'application_facts_snapshot' => ['applicant_declaration_preserved' => true],
        'determined_at' => now(),
    ]);
    $routing->works()->create([
        'office_code' => 'engineering',
        'office_label' => 'Engineering',
        'situational_reason' => 'Test-only situational selection.',
        'required_work' => 'Test-only office work.',
        'context_snapshot' => ['automatic_lob_rule' => false],
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
    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($fixture['application']->fresh(), $fixture['actor']);
    app(RecordBusinessPermitEvaluationCounterCheck::class)->handle($assessment, $fixture['actor']);

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

/**
 * The exact serialized shape the Evaluator frontend consumes, and the
 * backend anchor for the hand-maintained TypeScript mirror in
 * `resources/js/types/business-permit-evaluation.ts`.
 *
 * Field *names* are the contract; presentation copy is not. Each list is
 * declaration-ordered, so an accidental rename, removal, or reordering of
 * a contract field fails here instead of silently breaking the frontend.
 *
 * @return array<string, array<int, string>>
 */
function evaluationContractKeys(): array
{
    return [
        'root' => [
            'id',
            'version',
            'status_label',
            'application',
            'applicant_declaration',
            'municipal_resolved_lines',
            'items',
            'projected_charges',
            'financial_working_paper',
            'current_evaluated_amount_cents',
            'pricing_issues',
            'readiness',
            'my_item_ids',
            'latest_assessment',
            'financial_lock',
            'lens',
        ],
        'version' => ['id', 'sequence', 'fingerprint', 'fingerprint_current', 'treasury_counter_check'],
        'treasury_counter_check' => ['assessment_id', 'assessment_snapshot_hash', 'result', 'checked_at', 'checked_by', 'reason', 'evidence_provenance'],
        'application' => ['id', 'application_number', 'tracking_reference', 'business_name', 'owner_name', 'type', 'year'],
        'applicant_declaration' => ['line_of_business_id', 'line_of_business_name', 'declared_gross_sales_cents', 'capital_investment_cents', 'quantity'],
        'municipal_resolved_lines' => ['id', 'name'],
        'items' => [
            'id',
            'key',
            'label',
            'line_of_business_id',
            'line_of_business_name',
            'department_selection_reason',
            'item_type',
            'responsible_party',
            'is_required',
            'requires_confirmation',
            'is_mine',
            'applicability',
            'resolution',
            'action',
            'default_value',
            'default_source_classification',
            'resolved_value',
            'source_classification',
            'reason',
            'occurred_at',
            'inspection_required',
            'history',
        ],
        'history' => ['version_sequence', 'action', 'applicability', 'value', 'source_classification', 'actor_name', 'reason', 'occurred_at'],
        'projected_charges' => ['key', 'fee_rule_id', 'code', 'name', 'amount_cents', 'basis', 'basis_amount_cents', 'legal_basis', 'source_classification'],
        'financial_working_paper' => [
            'line_sections',
            'application_charges',
            'application_subtotal_amount_cents',
            'required_unresolved_charge_count',
            'grand_total_available',
            'grand_total_amount_cents',
        ],
        'working_paper_line' => ['line_of_business_id', 'permit_application_line_id', 'line_of_business_name', 'charges', 'subtotal_amount_cents'],
        'working_paper_charge' => [
            'identity',
            'source_type',
            'evaluation_item_id',
            'fee_rule_id',
            'scope',
            'permit_application_line_id',
            'line_of_business_id',
            'code',
            'label',
            'responsible_party',
            'proposal_amount_cents',
            'resolved_amount_cents',
            'applicability',
            'resolution',
            'source_classification',
            'action',
            'reason',
            'included_in_subtotal',
            'included_in_grand_total',
        ],
        'readiness' => ['commissioned', 'provisional_uat'],
        'readiness_outcome' => ['ready', 'issues'],
        'latest_assessment' => [
            'id',
            'sequence',
            'total_amount_cents',
            'superseded',
            'decision',
            'evaluation_version_id',
            'evaluation_fingerprint',
            'consumes_current_evaluation',
        ],
    ];
}

/**
 * One serialized Evaluation item, resolved by its canonical key.
 *
 * @param  array<string, mixed>  $serialized
 * @return array<string, mixed>
 */
function contractItem(array $serialized, string $key): array
{
    /** @var array<int, array<string, mixed>> $items */
    $items = $serialized['items'];

    foreach ($items as $item) {
        if ($item['key'] === $key) {
            return $item;
        }
    }

    throw new RuntimeException("The serialized Evaluation contract is missing item [{$key}].");
}

/**
 * A fixture that populates every branch of the contract at once: a
 * governed FeeRule projection, an office charge item with provenance, a
 * recorded Treasury counter-check, and a prepared Assessment.
 *
 * @return array{item: BusinessPermitEvaluationItem, officer: User, serialized: array<string, mixed>}
 */
function fullyPopulatedContract(): array
{
    $fixture = contractFixture();
    FeeRule::factory()->create([
        'code' => 'CONTRACT-BASE',
        'name' => 'Contract fixture base proposal',
        'legal_basis' => 'Contract fixture ordinance reference',
    ]);
    $officer = User::factory()->create();
    $item = app(DefineBusinessPermitEvaluationItem::class)->handle(
        $fixture['evaluation'],
        'engineering.charge',
        BusinessPermitEvaluationItemType::Charge,
        'engineering',
        true,
        true,
        BusinessPermitEvaluationApplicability::Applicable,
        ['amount_cents' => 12_500, 'inspection' => ['required' => false, 'completed' => false]],
        BusinessPermitEvaluationSource::GovernedOfficeProcedure,
        $fixture['actor'],
        'Contract fixture office proposal.',
        ['label' => 'Engineering evaluation charge', 'authorized_actor_id' => $officer->id],
    );
    $version = $fixture['evaluation']->fresh()->currentVersion;
    app(CompleteBusinessPermitEvaluationResponsibility::class)->handle(
        $item,
        $officer,
        BusinessPermitEvaluationApplicability::Applicable,
        ['amount_cents' => 12_500],
        BusinessPermitEvaluationSource::GovernedOfficeProcedure,
        'Contract fixture office confirmation.',
        $version->sequence,
        $version->fingerprint,
        'contract-serialization-confirm',
    );
    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($fixture['application']->fresh(), $fixture['actor']);
    app(RecordBusinessPermitEvaluationCounterCheck::class)->handle($assessment, $fixture['actor']);

    return [
        'item' => $item,
        'officer' => $officer,
        'serialized' => app(DescribeBusinessPermitEvaluation::class)
            ->handle($fixture['evaluation']->fresh(), $officer, 'internal')
            ->toArray(),
    ];
}

it('serializes the complete typed contract, including every nested Data object key', function () {
    $context = fullyPopulatedContract();
    $data = $context['serialized'];
    $keys = evaluationContractKeys();
    $chargeItem = contractItem($data, 'engineering.charge');

    expect(array_keys($data))->toBe($keys['root'])
        ->and(array_keys($data['version']))->toBe($keys['version'])
        ->and(array_keys($data['version']['treasury_counter_check']))->toBe($keys['treasury_counter_check'])
        ->and(array_keys($data['application']))->toBe($keys['application'])
        ->and($data['applicant_declaration'])->not->toBeEmpty()
        ->and(array_keys($data['applicant_declaration'][0]))->toBe($keys['applicant_declaration'])
        ->and($data['municipal_resolved_lines'])->not->toBeEmpty()
        ->and(array_keys($data['municipal_resolved_lines'][0]))->toBe($keys['municipal_resolved_lines'])
        ->and($data['items'])->not->toBeEmpty()
        ->and($data['projected_charges'])->not->toBeEmpty()
        ->and(array_keys($data['projected_charges'][0]))->toBe($keys['projected_charges'])
        ->and(array_keys($data['financial_working_paper']))->toBe($keys['financial_working_paper'])
        ->and($data['financial_working_paper']['application_charges'])->not->toBeEmpty()
        ->and(array_keys($data['financial_working_paper']['application_charges'][0]))->toBe($keys['working_paper_charge'])
        ->and($data['financial_working_paper']['line_sections'])->not->toBeEmpty()
        ->and(array_keys($data['financial_working_paper']['line_sections'][0]))->toBe($keys['working_paper_line'])
        ->and(array_keys($data['readiness']))->toBe($keys['readiness'])
        ->and(array_keys($data['readiness']['commissioned']))->toBe($keys['readiness_outcome'])
        ->and(array_keys($data['readiness']['provisional_uat']))->toBe($keys['readiness_outcome'])
        ->and($data['latest_assessment'])->not->toBeNull()
        ->and(array_keys($data['latest_assessment']))->toBe($keys['latest_assessment']);

    /** @var array<int, array<string, mixed>> $items */
    $items = $data['items'];

    foreach ($items as $item) {
        expect(array_keys($item))->toBe($keys['items']);
    }

    /** @var array<int, array<string, mixed>> $history */
    $history = $chargeItem['history'];

    expect($history)->not->toBeEmpty()
        ->and(array_keys($history[0]))->toBe($keys['history']);
});

it('keeps the serialized contract value types stable without freezing presentation copy', function () {
    $context = fullyPopulatedContract();
    $data = $context['serialized'];
    $chargeItem = contractItem($data, 'engineering.charge');

    expect($data['id'])->toBeInt()
        ->and($data['version']['sequence'])->toBeInt()
        ->and($data['version']['fingerprint'])->toBeString()
        ->and(mb_strlen((string) $data['version']['fingerprint']))->toBe(64)
        ->and($data['version']['fingerprint_current'])->toBeTrue()
        ->and($data['status_label'])->toBeString()
        ->and(mb_strlen((string) $data['status_label']))->toBeGreaterThan(0)
        ->and($data['current_evaluated_amount_cents'])->toBeInt()
        ->and($data['pricing_issues'])->toBeArray()
        ->and($data['readiness']['commissioned']['ready'])->toBeBool()
        ->and($data['readiness']['commissioned']['issues'])->toBeArray()
        ->and($data['my_item_ids'])->toContain($context['item']->id)
        ->and($data['financial_lock'])->toBeFalse()
        ->and($data['lens'])->toBe('internal')
        ->and($data['latest_assessment']['consumes_current_evaluation'])->toBeTrue();

    // The Board invariant: a system proposal and a resolved municipal value
    // stay two separate serialized fields and are never collapsed.
    expect($chargeItem['default_value'])->toBeArray()
        ->and($chargeItem['resolved_value'])->toBeArray()
        ->and($chargeItem['default_source_classification'])->toBe('governed_office_procedure')
        ->and($chargeItem['resolution'])->toBe('resolved');
});

it('keeps the hand-maintained TypeScript mirror aligned with every serialized contract key', function () {
    $mirror = file_get_contents(base_path('resources/js/types/business-permit-evaluation.ts'));

    expect($mirror)->toBeString();

    foreach (evaluationContractKeys() as $group => $groupKeys) {
        foreach ($groupKeys as $key) {
            expect(str_contains((string) $mirror, $key.':'))
                ->toBeTrue("The TypeScript mirror is missing the [{$group}] contract field [{$key}].");
        }
    }
});
