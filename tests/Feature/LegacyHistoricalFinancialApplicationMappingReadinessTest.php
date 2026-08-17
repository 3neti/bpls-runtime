<?php

use App\Actions\CharacterizeLegacyHistoricalFinancialApplicationMappings;
use App\Actions\PlanLegacyFinancialDependencies;
use App\Actions\PlanLegacyRegistryMigration;
use App\Enums\LegacyImportBatchStatus;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyIdMapping;
use App\Models\LegacyImportBatch;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use Illuminate\Support\Facades\Storage;

/** @param array<string, mixed> $payload */
function mappingReadinessRecord(LegacyImportBatch $batch, string $dataset, string $legacyId, array $payload): LegacyRecord
{
    $payload = ['_id' => $legacyId, ...$payload];

    return LegacyRecord::query()->create([
        'legacy_import_batch_id' => $batch->id,
        'legacy_source_id' => $batch->legacy_source_id,
        'dataset_key' => $dataset,
        'entity_type' => str($dataset)->singular()->toString(),
        'legacy_id' => $legacyId,
        'payload' => $payload,
        'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        'status' => 'staged',
        'line_number' => $batch->records()->count() + 1,
    ]);
}

/**
 * @param  array<string, mixed>  $owner
 * @param  array<string, mixed>  $business
 * @param  array<string, mixed>  $application
 */
function addMappingReadinessCandidate(
    LegacyImportBatch $registryBatch,
    LegacyImportBatch $financialBatch,
    string $key,
    array $owner = [],
    array $business = [],
    array $application = [],
): void {
    $ownerId = 'owner-'.$key;
    $businessId = 'business-'.$key;
    $ownerPayload = [
        'ownerType' => 'Individual',
        'firstName' => 'Owner '.$key,
        'lastName' => 'Example',
        ...$owner,
    ];
    $businessPayload = [
        'ownerId' => $ownerId,
        'name' => 'Business '.$key,
        ...$business,
    ];
    $applicationPayload = [
        'businessOwnerId' => $ownerId,
        'businessId' => $businessId,
        'applicationNumber' => 'APP-'.$key,
        'status' => 'Assessment',
        'permitApplicationType' => 'New',
        'submittedAt' => '2026-01-10T08:00:00+08:00',
        'linesOfBusiness' => [],
        ...$application,
    ];

    foreach ([$registryBatch, $financialBatch] as $batch) {
        mappingReadinessRecord($batch, 'business_owners', $ownerId, $ownerPayload);
        mappingReadinessRecord($batch, 'businesses', $businessId, $businessPayload);
    }
    mappingReadinessRecord($registryBatch, 'business_permit_applications', 'application-'.$key, $applicationPayload);
    mappingReadinessRecord($financialBatch, 'business_permit_applications', 'application-'.$key, $applicationPayload);
    mappingReadinessRecord($financialBatch, 'payment_schedules', 'schedule-'.$key, [
        'applicationId' => 'application-'.$key,
        'sectionNumber' => 1,
        'dueDate' => '2026-01-20',
        'status' => 'pending',
        'fees' => [[
            'feeName' => 'Historical fee '.$key,
            'feeCategory' => 'Fee',
            'originalAmount' => 100,
            'sectionAmount' => 100,
        ]],
        'surcharge' => 0,
        'penalty' => 0,
        'totalAmount' => 100,
        'paidAmount' => 0,
    ]);
}

/** @return array{registry: LegacyImportBatch, financial: LegacyImportBatch} */
function mappingReadinessBatches(string $suffix): array
{
    $source = LegacySource::factory()->create([
        'key' => 'MAPPING-READINESS-'.$suffix,
        'archive_checksum' => hash('sha256', 'mapping-readiness-'.$suffix),
    ]);

    return [
        'registry' => LegacyImportBatch::factory()->for($source, 'source')->create([
            'run_reference' => 'mapping-readiness-registry-'.$suffix,
            'manifest_checksum' => hash('sha256', 'registry-'.$suffix),
            'status' => LegacyImportBatchStatus::Staged,
        ]),
        'financial' => LegacyImportBatch::factory()->for($source, 'source')->create([
            'run_reference' => 'mapping-readiness-financial-'.$suffix,
            'manifest_checksum' => hash('sha256', 'financial-'.$suffix),
            'status' => LegacyImportBatchStatus::Staged,
        ]),
    ];
}

test('strict preservation candidates are classified by exact identity evidence without accepting mappings', function () {
    $batches = mappingReadinessBatches('taxonomy');
    addMappingReadinessCandidate($batches['registry'], $batches['financial'], 'exact');
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'crosswalk',
        business: ['categoryId' => 'legacy-category'],
        application: ['linesOfBusiness' => [['businessCategory' => 'Legacy category']]],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'group',
        owner: ['ownerType' => 'Group', 'groupName' => 'Historical group'],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'collision',
        owner: ['email' => 'shared@example.test'],
    );
    foreach ([$batches['registry'], $batches['financial']] as $batch) {
        mappingReadinessRecord($batch, 'business_owners', 'owner-collision-decoy', [
            'ownerType' => 'Individual',
            'firstName' => 'Collision Decoy',
            'lastName' => 'Example',
            'email' => 'shared@example.test',
        ]);
    }

    $registryPlan = app(PlanLegacyRegistryMigration::class)->handle($batches['registry'], 'registry-taxonomy');
    $financialPlan = app(PlanLegacyFinancialDependencies::class)->handle($batches['financial'], 'financial-taxonomy');
    $beforeRecordCount = LegacyRecord::query()->count();
    $result = app(CharacterizeLegacyHistoricalFinancialApplicationMappings::class)->handle($financialPlan, $registryPlan);

    expect($result['report']['summary'])
        ->strict_preservation_candidate_count->toBe(4)
        ->deterministic_identity_chain_count->toBe(2)
        ->accepted_mapping_count->toBe(0)
        ->and($result['report']['summary']['classification_counts'])->toMatchArray([
            'deterministic_exact_mapping_candidate' => 1,
            'human_identity_reconciliation' => 1,
            'reference_data_crosswalk_only' => 1,
            'registry_policy_reconciliation' => 1,
        ])
        ->and($result['cohort'])->toHaveCount(2)
        ->and(collect($result['cohort'])->pluck('proposed_mapping_status')->sort()->values()->all())->toBe([
            'pending_mapping_acceptance',
            'pending_reference_data_and_mapping_acceptance',
        ])
        ->and(LegacyApplicationIdMapping::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0)
        ->and(LegacyRecord::query()->count())->toBe($beforeRecordCount)
        ->and($result['report']['safety'])->toMatchArray([
            'read_only_characterization' => true,
            'accepted_mappings_created' => false,
            'production_mutation' => false,
            'migration_executed' => false,
        ]);
});

test('characterization refuses cross-batch payload drift and reports structural reference breaks', function () {
    $batches = mappingReadinessBatches('drift');
    addMappingReadinessCandidate($batches['registry'], $batches['financial'], 'drift');
    $registryOwner = $batches['registry']->records()->where('dataset_key', 'business_owners')->sole();
    $registryOwner->update(['payload_hash' => hash('sha256', 'different-owner-payload')]);

    $registryPlan = app(PlanLegacyRegistryMigration::class)->handle($batches['registry'], 'registry-drift');
    $financialPlan = app(PlanLegacyFinancialDependencies::class)->handle($batches['financial'], 'financial-drift');
    $result = app(CharacterizeLegacyHistoricalFinancialApplicationMappings::class)->handle($financialPlan, $registryPlan);
    $candidate = $result['candidates'][0];

    expect($candidate['classification'])->toBe('structural_reference_break')
        ->and($candidate['structural_reasons'])->toContain('owner_payload_differs_between_bound_batches')
        ->and($result['cohort'])->toBe([]);
});

test('command writes immutable payload-safe evidence and stable retry does not create mappings', function () {
    Storage::fake('local');
    $batches = mappingReadinessBatches('command');
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'sensitive-source-id',
        owner: ['email' => 'private@example.test'],
    );
    $registryPlan = app(PlanLegacyRegistryMigration::class)->handle($batches['registry'], 'registry-command');
    $financialPlan = app(PlanLegacyFinancialDependencies::class)->handle($batches['financial'], 'financial-command');
    $command = 'legacy:characterize-historical-financial-application-mappings';
    $arguments = [
        'financial-plan' => $financialPlan->id,
        'registry-plan' => $registryPlan->id,
        '--run-id' => 'mapping-readiness-command-001',
        '--json' => true,
    ];

    $this->artisan($command, $arguments)->assertSuccessful();
    $this->artisan($command, $arguments)->assertSuccessful();

    $root = "legacy-migrations/{$batches['financial']->source->key}/{$batches['financial']->run_reference}/reconciliation/historical-financial-application-mapping-readiness/mapping-readiness-command-001";
    Storage::disk('local')->assertExists($root.'/summary.json');
    Storage::disk('local')->assertExists($root.'/proposed-mappings.jsonl');
    Storage::disk('local')->assertExists($root.'/recommended-first-cohort.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $evidence = Storage::disk('local')->get($root.'/summary.json')
        .Storage::disk('local')->get($root.'/proposed-mappings.jsonl')
        .Storage::disk('local')->get($root.'/recommended-first-cohort.json');

    expect($evidence)->not->toContain('private@example.test')
        ->not->toContain('application-sensitive-source-id')
        ->not->toContain('owner-sensitive-source-id')
        ->not->toContain('business-sensitive-source-id')
        ->and(LegacyApplicationIdMapping::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0);
});
