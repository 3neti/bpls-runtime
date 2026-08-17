<?php

use App\Actions\BuildLegacyFinancialFormulaReconciliation;
use App\Actions\ProposeLegacyClearanceTypeReconciliations;
use App\Enums\LegacyImportBatchStatus;
use App\Models\LegacyClearanceTypeReconciliation;
use App\Models\LegacyImportBatch;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** @var list<string> $productionReconciliationArchives */
$productionReconciliationArchives = [];

/** @return array{source: LegacySource, batch: LegacyImportBatch} */
function productionReconciliationBatch(string $suffix): array
{
    $source = LegacySource::factory()->create([
        'key' => 'PRODUCTION-RECONCILIATION-'.$suffix,
        'archive_checksum' => hash('sha256', 'snapshot-'.$suffix),
    ]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'production-reconciliation-'.$suffix,
        'manifest_checksum' => hash('sha256', 'manifest-'.$suffix),
        'status' => LegacyImportBatchStatus::StagedWithExceptions,
        'completed_at' => '2026-08-17T12:00:00+08:00',
    ]);

    return compact('source', 'batch');
}

/** @param array<string, mixed> $payload */
function productionReconciliationRecord(LegacyImportBatch $batch, string $dataset, string $legacyId, array $payload): LegacyRecord
{
    $payload = ['_id' => $legacyId, ...$payload];

    return LegacyRecord::query()->create([
        'legacy_import_batch_id' => $batch->id,
        'legacy_source_id' => $batch->legacy_source_id,
        'dataset_key' => $dataset,
        'entity_type' => str($dataset)->singular()->toString(),
        'legacy_id' => $legacyId,
        'payload' => $payload,
        'payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        'status' => 'staged',
        'line_number' => $batch->records()->count() + 1,
    ]);
}

function productionReconciliationLegacyArchive(): string
{
    global $productionReconciliationArchives;

    $path = storage_path('framework/testing/production-reconciliation-'.Str::uuid().'.zip');
    File::ensureDirectoryExists(dirname($path));
    $zip = new ZipArchive;
    expect($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    expect($zip->addFromString(
        'bpls-system-main/apps/admin/lib/utils/fee-calculator.ts',
        'const result = Function(`"use strict"; return (${expression})`)(); return Math.max(0, result); return 0;',
    ))->toBeTrue();
    expect($zip->addFromString(
        'bpls-system-main/apps/admin/lib/utils/surcharge-penalty-calculator.ts',
        'const result = Function(`"use strict"; return (${expression})`)(); const due = daysDiff >= 0; const month = daysDiff / 30; roundTo2Decimals(result);',
    ))->toBeTrue();
    expect($zip->addFromString(
        'bpls-system-main/packages/backend/convex/paymentSchedules.ts',
        'feeId?: Id<"fees">; Math.round((fee.totalAmount / sectionCount) * 100) / 100; Math.round(sectionFees.reduce(() => 0) * 100) / 100; roundedPaid >= roundedTotal;',
    ))->toBeTrue();
    expect($zip->close())->toBeTrue();
    $productionReconciliationArchives[] = $path;

    return $path;
}

afterEach(function (): void {
    global $productionReconciliationArchives;

    foreach ($productionReconciliationArchives ?? [] as $path) {
        File::delete($path);
    }
    $productionReconciliationArchives = [];
});

test('clearance analysis proposes exact source-backed candidates without accepting mappings', function () {
    $fixture = productionReconciliationBatch('clearances');
    foreach ([
        ['target-fire', 'Bureau of Fire Protection', 'BFP', 'BFP Clearance Certificate'],
        ['target-sanitary', 'Sanitary Department', 'Sanitary', 'Sanitary Permit'],
    ] as [$id, $name, $shortName, $certificateName]) {
        productionReconciliationRecord($fixture['batch'], 'clearance_types', $id, compact('name', 'shortName', 'certificateName'));
    }
    foreach ([
        ['clearance-1', 'missing-fire', 'Bureau of Fire Protection', 'BFP', 'BFP Clearance Certificate'],
        ['clearance-2', 'missing-fire', 'Bureau of Fire Protection', 'BFP', 'BFP Clearance Certificate'],
        ['clearance-3', 'missing-sanitary', 'Sanitary Department', 'Sanitary', 'Sanitary Permit'],
        ['clearance-existing', 'target-fire', 'Bureau of Fire Protection', 'BFP', 'BFP Clearance Certificate'],
    ] as [$id, $clearanceTypeId, $clearanceName, $clearanceShortName, $certificateName]) {
        productionReconciliationRecord($fixture['batch'], 'permit_clearances', $id, compact('clearanceTypeId', 'clearanceName', 'clearanceShortName', 'certificateName'));
    }

    $report = app(ProposeLegacyClearanceTypeReconciliations::class)
        ->handle($fixture['batch']->load('source'), 'clearance-proposal-001');

    expect($report['result'])->toMatchArray([
        'missing_source_identifier_count' => 2,
        'affected_record_count' => 3,
        'exact_candidate_count' => 2,
        'unresolved_candidate_count' => 0,
        'accepted_count' => 0,
    ])->and(collect($report['proposals'])->pluck('basis')->unique()->all())->toBe(['exact_three_field_denormalized_match'])
        ->and(collect($report['proposals'])->pluck('municipal_decision_status')->unique()->all())->toBe(['pending'])
        ->and($report['safety'])->toMatchArray([
            'normalized_name_matching' => false,
            'similarity_matching' => false,
            'reconciliation_rows_created' => false,
            'domain_writes' => false,
            'migration_executed' => false,
        ])
        ->and(LegacyClearanceTypeReconciliation::query()->count())->toBe(0);
});

test('clearance proposal command writes stable private evidence without leaking identifiers to stdout', function () {
    Storage::fake('local');
    $fixture = productionReconciliationBatch('clearance-command');
    productionReconciliationRecord($fixture['batch'], 'clearance_types', 'target-sensitive', [
        'name' => 'Sanitary Department',
        'shortName' => 'Sanitary',
        'certificateName' => 'Sanitary Permit',
    ]);
    productionReconciliationRecord($fixture['batch'], 'permit_clearances', 'clearance-sensitive', [
        'clearanceTypeId' => 'missing-sensitive',
        'clearanceName' => 'Sanitary Department',
        'clearanceShortName' => 'Sanitary',
        'certificateName' => 'Sanitary Permit',
    ]);

    $arguments = ['batch' => $fixture['batch']->id, '--run-id' => 'clearance-command-001', '--json' => true];
    $this->artisan('legacy:propose-clearance-reconciliations', $arguments)
        ->doesntExpectOutputToContain('missing-sensitive')
        ->doesntExpectOutputToContain('target-sensitive')
        ->assertSuccessful();
    $this->artisan('legacy:propose-clearance-reconciliations', $arguments)->assertSuccessful();

    $root = "legacy-migrations/{$fixture['source']->key}/{$fixture['batch']->run_reference}/reconciliation/clearance-types/clearance-command-001";
    Storage::disk('local')->assertExists($root.'/proposal.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    expect(json_decode(Storage::disk('local')->get($root.'/proposal.json'), true, flags: JSON_THROW_ON_ERROR)['result']['accepted_count'])->toBe(0);
});

test('financial analysis characterizes configuration and refuses name-only historical attribution', function () {
    $fixture = productionReconciliationBatch('financial');
    productionReconciliationRecord($fixture['batch'], 'fees', 'fee-formula', [
        'name' => 'Formula Fee',
        'feeType' => 'Formula',
        'feeCategory' => 'Tax',
        'formula' => 'grossSales * 0.01 + numberOfTables',
        'applicationType' => ['New'],
    ]);
    productionReconciliationRecord($fixture['batch'], 'fees', 'fee-range', [
        'name' => 'Range Fee',
        'feeType' => 'Range',
        'feeCategory' => 'Regulatory Fee',
        'rangeField' => 'businessArea',
        'ranges' => [['min' => 0, 'max' => 100, 'fee' => 350]],
    ]);
    productionReconciliationRecord($fixture['batch'], 'fee_overrides', 'override-1', [
        'feeId' => 'fee-formula',
        'divisionGroupId' => 'division-group-1',
        'overrideAmount' => 500,
    ]);
    productionReconciliationRecord($fixture['batch'], 'payment_schedules', 'schedule-1', [
        'fees' => [
            ['feeName' => 'Formula Fee', 'feeCategory' => 'Tax', 'originalAmount' => 100, 'sectionAmount' => 100],
            ['feeName' => 'Range Fee', 'feeCategory' => 'Regulatory Fee', 'originalAmount' => 350, 'sectionAmount' => 350, 'isEdited' => true],
        ],
    ]);
    productionReconciliationRecord($fixture['batch'], 'payments', 'payment-1', ['amount' => 450, 'status' => 'completed']);
    productionReconciliationRecord($fixture['batch'], 'unitsOfMeasurement', 'uom-1', ['variableName' => 'numberOfTables', 'quantity' => 2]);
    productionReconciliationRecord($fixture['batch'], 'surcharge_penalty_config', 'config-1', [
        'isActive' => true,
        'surchargeFormula' => 'periodFee * 0.25',
        'penaltyFormula' => 'periodFee * 0.02 * monthsMissed',
    ]);
    $archive = productionReconciliationLegacyArchive();

    $report = app(BuildLegacyFinancialFormulaReconciliation::class)
        ->handle($fixture['batch']->load('source'), 'financial-reconciliation-001', $archive);
    $formulaFee = collect($report['fee_matrix'])->firstWhere('source_fee_id', 'fee-formula');

    expect($report['summary'])->toMatchArray([
        'fee_definition_count' => 2,
        'formula_bearing_fee_count' => 1,
        'range_based_fee_count' => 1,
        'fee_override_count' => 1,
        'payment_schedule_count' => 1,
        'payment_count' => 1,
        'schedule_fee_item_count' => 2,
        'schedule_fee_item_with_exact_fee_id_count' => 0,
        'schedule_fee_distinct_name_count' => 2,
        'schedule_fee_edited_item_count' => 1,
        'historical_formula_attribution_status' => 'blocked_missing_exact_fee_identity',
        'accepted_interpretation_count' => 0,
        'executable_policy_count' => 0,
    ])->and($formulaFee['configuration']['recognized_variables'])->toContain('grossSales', 'numberOfTables')
        ->and($formulaFee['reconciliation'])->toMatchArray([
            'historical_exact_fee_link_count' => 0,
            'historical_name_signal_count' => 1,
            'name_signal_establishes_identity' => false,
            'accepted_interpretation' => false,
            'executable_policy' => false,
        ])->and($report['safety'])->toMatchArray([
            'formulas_evaluated' => false,
            'historical_liability_recalculated' => false,
            'fee_identity_inferred_from_name' => false,
            'financial_domain_writes' => false,
            'migration_executed' => false,
        ]);
});

test('financial reconciliation command writes stable private evidence and no liability', function () {
    Storage::fake('local');
    $fixture = productionReconciliationBatch('financial-command');
    productionReconciliationRecord($fixture['batch'], 'fees', 'fee-sensitive', [
        'name' => 'Inspection Fee',
        'feeType' => 'Constant',
        'feeCategory' => 'Regulatory Fee',
        'amount' => 350,
    ]);
    productionReconciliationRecord($fixture['batch'], 'fee_overrides', 'override-empty', []);
    productionReconciliationRecord($fixture['batch'], 'payment_schedules', 'schedule-empty', ['fees' => []]);
    productionReconciliationRecord($fixture['batch'], 'payments', 'payment-empty', []);
    productionReconciliationRecord($fixture['batch'], 'unitsOfMeasurement', 'uom-empty', ['variableName' => 'numberOfTables']);
    productionReconciliationRecord($fixture['batch'], 'surcharge_penalty_config', 'config-empty', []);
    $archive = productionReconciliationLegacyArchive();
    $arguments = [
        'batch' => $fixture['batch']->id,
        '--run-id' => 'financial-command-001',
        '--legacy-archive' => $archive,
        '--json' => true,
    ];

    $this->artisan('legacy:build-financial-reconciliation', $arguments)
        ->doesntExpectOutputToContain('fee-sensitive')
        ->assertSuccessful();
    $this->artisan('legacy:build-financial-reconciliation', $arguments)->assertSuccessful();

    $root = "legacy-migrations/{$fixture['source']->key}/{$fixture['batch']->run_reference}/reconciliation/financial-formulas/financial-command-001";
    Storage::disk('local')->assertExists($root.'/financial-reconciliation.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $report = json_decode(Storage::disk('local')->get($root.'/financial-reconciliation.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($report['safety'])->toMatchArray([
        'formulas_evaluated' => false,
        'historical_liability_recalculated' => false,
        'financial_domain_writes' => false,
        'cutover_authorized' => false,
    ]);
});
