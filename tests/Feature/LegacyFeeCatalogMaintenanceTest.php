<?php

use App\Actions\ActivateLegacyUatFeeCatalog;
use App\Actions\CharacterizeLegacyFeeCatalog;
use App\Actions\ReconcileLegacyFeeCatalogCandidate;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\LegacyFeeRuleReconciliationStatus;
use App\Enums\UserPermission;
use App\Models\FeeRule;
use App\Models\LegacyFeeRuleReconciliation;
use App\Models\LegacyImportBatch;
use App\Models\LegacyLineOfBusinessReconciliation;
use App\Models\LegacyRecord;
use App\Models\LineOfBusiness;
use App\Models\RevenueCodeProvision;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/** @param array<string, mixed> $payload */
function legacyFeeCatalogRecord(LegacyImportBatch $batch, string $dataset, string $legacyId, array $payload, int $line = 1): LegacyRecord
{
    $payload = ['_id' => $legacyId, ...$payload];

    return LegacyRecord::factory()->create([
        'legacy_import_batch_id' => $batch->id,
        'legacy_source_id' => $batch->legacy_source_id,
        'dataset_key' => $dataset,
        'entity_type' => str($dataset)->singular()->toString(),
        'legacy_id' => $legacyId,
        'payload' => $payload,
        'payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        'line_number' => $line,
    ]);
}

/** @return array{batch: LegacyImportBatch, actor: User} */
function legacyFeeCatalogFixture(): array
{
    $batch = LegacyImportBatch::factory()->create();
    $group = legacyFeeCatalogRecord($batch, 'groups', 'group-coffee', ['name' => 'S- COFFEE SHOP']);
    $division = legacyFeeCatalogRecord($batch, 'divisions', 'division-services', ['name' => 'SERVICES']);
    $edge = legacyFeeCatalogRecord($batch, 'division_groups', 'edge-services-coffee', [
        'divisionId' => $division->legacy_id,
        'groupId' => $group->legacy_id,
    ]);
    legacyFeeCatalogRecord($batch, 'fees', 'fee-health', [
        'name' => 'Health Certificate',
        'feeType' => 'Constant',
        'feeCategory' => 'Regulatory Fee',
        'amount' => 40,
        'applicationType' => ['New', 'Renewal'],
        'divisionId' => $division->legacy_id,
    ]);
    legacyFeeCatalogRecord($batch, 'fees', 'fee-mayor', [
        'name' => "Mayor's Permit Fee",
        'feeType' => 'Range',
        'feeCategory' => 'Regulatory Fee',
        'amount' => 0,
        'applicationType' => ['Renewal'],
        'groupId' => $group->legacy_id,
        'rangeField' => 'capitalInvestment',
        'ranges' => [
            ['min' => 1, 'max' => 15_000, 'fee' => 300],
            ['min' => 15_001, 'max' => 1_500_000, 'fee' => 500],
        ],
    ], 2);
    legacyFeeCatalogRecord($batch, 'fees', 'fee-occupation', [
        'name' => 'Occupation Fee',
        'feeType' => 'Formula',
        'amount' => 0,
        'applicationType' => ['New', 'Renewal'],
        'formula' => 'numberOfEmployees * 100',
        'divisionId' => $division->legacy_id,
    ], 3);
    legacyFeeCatalogRecord($batch, 'fee_overrides', 'override-health', [
        'feeId' => 'fee-health',
        'divisionGroupId' => $edge->legacy_id,
        'overrideAmount' => 55,
        'reason' => 'Municipal configuration specimen',
    ]);

    return [
        'batch' => $batch,
        'actor' => userWithPermissions([
            UserPermission::AccessStaff,
            UserPermission::ViewFeeRules,
            UserPermission::ManageFeeRules,
        ]),
    ];
}

test('characterizes exact legacy names values applicability ranges and overrides without activating policy', function (): void {
    $fixture = legacyFeeCatalogFixture();
    $feeRulesBefore = FeeRule::query()->count();

    $report = app(CharacterizeLegacyFeeCatalog::class)->handle($fixture['batch']);
    $health = LegacyFeeRuleReconciliation::query()->where('source_legacy_id', 'fee-health')->sole();
    $mayor = LegacyFeeRuleReconciliation::query()->where('source_legacy_id', 'fee-mayor')->sole();
    $occupation = LegacyFeeRuleReconciliation::query()->where('source_legacy_id', 'fee-occupation')->sole();

    expect($report['summary'])->toMatchArray([
        'fee_definition_count' => 3,
        'constant_count' => 1,
        'range_count' => 1,
        'formula_count' => 1,
        'range_band_count' => 2,
        'override_count' => 1,
        'legacy_group_record_count' => 1,
        'line_of_business_candidate_count' => 1,
        'active_fee_rules_created' => 0,
        'financial_records_changed' => 0,
    ])->and($health->metadata)->toMatchArray([
        'name' => 'Health Certificate',
        'amount_minor' => 4_000,
        'division_name' => 'SERVICES',
        'applicable_group_names' => ['S- COFFEE SHOP'],
        'override_count' => 1,
        'executable' => false,
    ])->and($mayor->metadata['ranges'][0])->toMatchArray([
        'minimum_basis_minor' => 100,
        'maximum_basis_minor' => 1_500_000,
        'amount_minor' => 30_000,
    ])->and($occupation->metadata['blockers'])->toContain('formula_semantics_and_rounding_not_implemented')
        ->and(FeeRule::query()->count())->toBe($feeRulesBefore);

    app(CharacterizeLegacyFeeCatalog::class)->handle($fixture['batch']);
    expect(LegacyFeeRuleReconciliation::query()->count())->toBe(3)
        ->and(LegacyLineOfBusinessReconciliation::query()->sole()->metadata)->toMatchArray([
            'name' => 'S- COFFEE SHOP',
            'identity_inferred_from_name' => false,
            'executable' => false,
        ]);
});

test('creates only an inactive blocked Price List proposal and preserves exact legacy value evidence', function (): void {
    $fixture = legacyFeeCatalogFixture();
    app(CharacterizeLegacyFeeCatalog::class)->handle($fixture['batch']);
    $candidate = LegacyFeeRuleReconciliation::query()->where('source_legacy_id', 'fee-mayor')->sole();

    $reviewed = app(ReconcileLegacyFeeCatalogCandidate::class)->handle(
        $candidate,
        'create_proposed',
        null,
        'Use the exact legacy label and bands as a review candidate.',
        $fixture['actor'],
    );
    $feeRule = $reviewed->feeRule;

    expect($reviewed->status->value)->toBe('pending')
        ->and($reviewed->metadata['review_disposition'])->toBe('create_proposed')
        ->and($feeRule)->not->toBeNull()
        ->and($feeRule->name)->toBe("Mayor's Permit Fee")
        ->and($feeRule->is_active)->toBeFalse()
        ->and($feeRule->ranges)->toHaveCount(2)
        ->and($feeRule->currentReconciliation->execution_status)->toBe(FeeRuleExecutionStatus::Blocked)
        ->and($feeRule->auditEvents()->sole()->snapshot)->toMatchArray([
            'active' => false,
            'historical_financial_records_changed' => false,
        ]);
});

test('records mapping and quarantine as non-executable review dispositions', function (): void {
    $fixture = legacyFeeCatalogFixture();
    app(CharacterizeLegacyFeeCatalog::class)->handle($fixture['batch']);
    $existing = FeeRule::factory()->create(['name' => 'Health Certificate']);
    $health = LegacyFeeRuleReconciliation::query()->where('source_legacy_id', 'fee-health')->sole();
    $formula = LegacyFeeRuleReconciliation::query()->where('source_legacy_id', 'fee-occupation')->sole();

    app(ReconcileLegacyFeeCatalogCandidate::class)->handle($health, 'map_existing', $existing->id, 'Exact source-backed review proposal.', $fixture['actor']);
    app(ReconcileLegacyFeeCatalogCandidate::class)->handle($formula, 'quarantine', null, 'Formula execution is not characterized.', $fixture['actor']);

    expect($health->refresh()->fee_rule_id)->toBe($existing->id)
        ->and($health->metadata['executable'])->toBeFalse()
        ->and($formula->refresh()->fee_rule_id)->toBeNull()
        ->and($formula->metadata['review_disposition'])->toBe('quarantine')
        ->and($existing->refresh()->is_active)->toBeTrue();
});

test('presents the candidate register only to Price List viewers without exposing raw source ids', function (): void {
    $fixture = legacyFeeCatalogFixture();
    app(CharacterizeLegacyFeeCatalog::class)->handle($fixture['batch']);

    $this->actingAs(User::factory()->create())
        ->get(route('staff.fee-rules.legacy-candidates.index'))
        ->assertForbidden();

    $this->actingAs($fixture['actor'])
        ->get(route('staff.fee-rules.legacy-candidates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fee-rules/LegacyCandidates')
            ->where('summary.total', 3)
            ->where('summary.executable', 0)
            ->missing('candidates.data.0.source_legacy_id')
            ->has('candidates.data', 3));
});

test('the characterization command writes private aggregate evidence and remains idempotent', function (): void {
    Storage::fake('local');
    $fixture = legacyFeeCatalogFixture();
    $arguments = [
        'batch' => $fixture['batch']->id,
        '--run-id' => 'legacy-fee-catalog-001',
        '--json' => true,
    ];

    $this->artisan('legacy:characterize-fee-catalog', $arguments)->assertSuccessful();
    $this->artisan('legacy:characterize-fee-catalog', $arguments)->assertSuccessful();

    $root = "legacy-migrations/{$fixture['batch']->source->key}/{$fixture['batch']->run_reference}/reconciliation/fee-catalog/legacy-fee-catalog-001";
    Storage::disk('local')->assertExists($root.'/summary.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $report = json_decode(Storage::disk('local')->get($root.'/summary.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($report['summary']['fee_definition_count'])->toBe(3)
        ->and($report['safety'])->toMatchArray([
            'source_ids_exposed' => false,
            'formulas_evaluated' => false,
            'legacy_values_activated' => false,
            'historical_liability_recalculated' => false,
        ]);
});

test('supports normalized many-to-many Lines of Business and explicit office ownership', function (): void {
    $rule = FeeRule::factory()->create();
    $coffee = LineOfBusiness::factory()->create(['name' => 'S- COFFEE SHOP']);
    $restaurant = LineOfBusiness::factory()->create(['name' => 'S- RESTAURANT']);

    $rule->lineOfBusinesses()->attach([
        $coffee->id => ['source' => 'legacy_division_inheritance'],
        $restaurant->id => ['source' => 'legacy_division_inheritance'],
    ]);
    $rule->officeAssignments()->create([
        'office_code' => 'health',
        'office_label' => 'Municipal Health Office',
        'source' => 'municipal_confirmation',
    ]);

    expect($rule->lineOfBusinesses()->pluck('line_of_businesses.name')->all())
        ->toEqualCanonicalizing(['S- COFFEE SHOP', 'S- RESTAURANT'])
        ->and($rule->officeAssignments()->sole()->office_code)->toBe('health');
});

test('activates the scoped migrated Ipil catalogue for UAT and excludes repeated seed and test fixtures', function (): void {
    $fixture = legacyFeeCatalogFixture();
    legacyFeeCatalogRecord($fixture['batch'], 'fees', 'fee-unscoped-seed', [
        'name' => 'Business Permit Fee',
        'feeType' => 'Constant',
        'amount' => 500,
        'applicationType' => ['New'],
    ], 4);
    $testDivision = legacyFeeCatalogRecord($fixture['batch'], 'divisions', 'division-test', ['name' => 'Test Division'], 2);
    legacyFeeCatalogRecord($fixture['batch'], 'fees', 'fee-test', [
        'name' => 'Fee Name Test',
        'feeType' => 'Constant',
        'amount' => 500,
        'applicationType' => ['New'],
        'divisionId' => $testDivision->legacy_id,
    ], 5);
    app(CharacterizeLegacyFeeCatalog::class)->handle($fixture['batch']);

    $placeholder = FeeRule::factory()->create(['code' => 'MRC-PLACEHOLDER', 'is_active' => true]);
    $paperReference = FeeRule::factory()->create(['code' => 'LAB-IPIL-ENGINEERING-BUILDING-PERMIT', 'is_active' => true]);
    $ordinanceProvision = RevenueCodeProvision::factory()->create();

    $result = app(ActivateLegacyUatFeeCatalog::class)->handle(
        $fixture['batch'],
        '2025-01-01',
        $fixture['actor'],
    );

    $health = LegacyFeeRuleReconciliation::query()->where('source_legacy_id', 'fee-health')->sole();
    $formula = LegacyFeeRuleReconciliation::query()->where('source_legacy_id', 'fee-occupation')->sole();
    $unscoped = LegacyFeeRuleReconciliation::query()->where('source_legacy_id', 'fee-unscoped-seed')->sole();
    $test = LegacyFeeRuleReconciliation::query()->where('source_legacy_id', 'fee-test')->sole();

    expect($result)->toMatchArray([
        'activated_fee_rule_count' => 3,
        'excluded_unscoped_seed_count' => 1,
        'excluded_test_fixture_count' => 1,
        'deactivated_placeholder_count' => 1,
        'superseded_ordinance_provision_count' => 1,
        'health_fee_count' => 1,
    ])->and($placeholder->refresh()->is_active)->toBeFalse()
        ->and($paperReference->refresh()->is_active)->toBeTrue()
        ->and($ordinanceProvision->refresh()->metadata['catalog_status'])->toBe('superseded_by_ipil_legacy_uat_v1')
        ->and($health->refresh()->status)->toBe(LegacyFeeRuleReconciliationStatus::Accepted)
        ->and($health->feeRule->is_active)->toBeTrue()
        ->and($health->feeRule->metadata['price_list_source_classification'])->toBe('migrated_legacy_uat')
        ->and($health->feeRule->officeAssignments()->sole()->office_code)->toBe('health')
        ->and($health->feeRule->lineOfBusinesses()->pluck('line_of_businesses.name')->all())->toBe(['S- COFFEE SHOP'])
        ->and($formula->refresh()->feeRule->metadata['manual_amount_required'])->toBeTrue()
        ->and($formula->feeRule->currentReconciliation->execution_status)->toBe(FeeRuleExecutionStatus::Executable)
        ->and($unscoped->status)->toBe(LegacyFeeRuleReconciliationStatus::Rejected)
        ->and($test->status)->toBe(LegacyFeeRuleReconciliationStatus::Rejected);
});
