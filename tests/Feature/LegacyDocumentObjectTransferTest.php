<?php

use App\Actions\AssessLegacyMigrationReadiness;
use App\Actions\ExecuteLegacyPermitEvidence;
use App\Actions\PlanLegacyPermitEvidence;
use App\Actions\RollbackLegacyPermitEvidence;
use App\Actions\StageLegacyDocumentObjects;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyApplicationMappingProposal;
use App\Models\LegacyDocumentObjectReconciliation;
use App\Models\LegacyDocumentObjectStagingRun;
use App\Models\LegacyImportBatch;
use App\Models\LegacyPermitDocumentMapping;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use Illuminate\Support\Facades\Storage;

/** @return array{batch: LegacyImportBatch, application: PermitApplication, manifest_path: string, storage_reference: string, original_name: string, object_bytes: string} */
function documentTransferFixture(string $suffix, ?string $applicationBusinessId = null): array
{
    Storage::fake('local');
    $source = LegacySource::factory()->create([
        'key' => 'LEGACY-DOCUMENT-'.$suffix,
        'baseline' => 'document-baseline-'.$suffix,
        'archive_checksum' => hash('sha256', 'document-archive-'.$suffix),
    ]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'document-import-'.$suffix,
        'manifest_checksum' => hash('sha256', 'document-import-manifest-'.$suffix),
        'status' => LegacyImportBatchStatus::Staged,
    ]);
    $storageReference = 'storage:'.$suffix.':sensitive-reference';
    $originalName = 'barangay-clearance-'.$suffix.'.pdf';
    $businessPayload = [
        '_id' => 'business-'.$suffix,
        'documents' => [[
            'storageId' => $storageReference,
            'documentType' => 'Barangay Clearance',
            'fileName' => $originalName,
            'uploadedAt' => '2026-08-15T09:30:00+08:00',
            'status' => 'Approved',
        ]],
    ];
    $business = documentTransferRecord($batch, 'businesses', $businessPayload['_id'], $businessPayload);
    $applicationPayload = [
        '_id' => 'application-'.$suffix,
        'businessId' => $applicationBusinessId ?? $business->legacy_id,
        'status' => 'Pending Payment',
    ];
    $applicationRecord = documentTransferRecord($batch, 'applications', $applicationPayload['_id'], $applicationPayload);
    $applicationPlan = LegacyApplicationMappingPlan::factory()->for($batch, 'importBatch')->create([
        'run_reference' => 'document-application-plan-'.$suffix,
        'status' => LegacyMappingPlanStatus::Planned,
        'proposal_count' => 1,
        'ready_count' => 1,
    ]);
    LegacyApplicationMappingProposal::factory()->for($applicationPlan, 'mappingPlan')->for($applicationRecord, 'legacyRecord')->create([
        'status' => LegacyMappingProposalStatus::Ready,
    ]);
    $application = PermitApplication::factory()->create(['legacy_source_id' => $applicationRecord->legacy_id]);
    LegacyApplicationIdMapping::factory()->for($batch, 'importBatch')->for($application)->create([
        'legacy_source_id' => $source->id,
        'dataset_key' => 'applications',
        'legacy_id' => $applicationRecord->legacy_id,
    ]);

    $objectBytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    $objectName = 'object-'.$suffix.'.pdf';
    Storage::disk('local')->put('manifest-input/'.$objectName, $objectBytes);
    $objectPath = Storage::disk('local')->path('manifest-input/'.$objectName);
    $manifest = [
        'schema_version' => StageLegacyDocumentObjects::SchemaVersion,
        'legacy_source_key' => $source->key,
        'legacy_import_batch_run_reference' => $batch->run_reference,
        'legacy_import_manifest_checksum' => $batch->manifest_checksum,
        'objects' => [[
            'business_legacy_id' => $business->legacy_id,
            'document_index' => 0,
            'storage_reference' => $storageReference,
            'file' => basename($objectPath),
            'sha256' => hash('sha256', $objectBytes),
            'size_bytes' => strlen($objectBytes),
            'mime_type' => 'application/pdf',
            'application_legacy_id' => $applicationRecord->legacy_id,
            'decision_authority' => 'Authorized migration test operator',
            'evidence_reference' => 'TEST-DOCUMENT-SCOPE-'.$suffix,
        ]],
    ];
    $manifestPath = dirname($objectPath).'/manifest-'.$suffix.'.json';
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return [
        'batch' => $batch,
        'application' => $application,
        'manifest_path' => $manifestPath,
        'storage_reference' => $storageReference,
        'original_name' => $originalName,
        'object_bytes' => $objectBytes,
    ];
}

/** @param array<string, mixed> $payload */
function documentTransferRecord(LegacyImportBatch $batch, string $dataset, string $legacyId, array $payload): LegacyRecord
{
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

test('a reconciled legacy document transfers once and rolls back without authority claims', function () {
    $fixture = documentTransferFixture('success');
    $staging = app(StageLegacyDocumentObjects::class)->handle($fixture['batch'], $fixture['manifest_path'], 'document-stage-success');
    $stagingRetry = app(StageLegacyDocumentObjects::class)->handle($fixture['batch'], $fixture['manifest_path'], 'document-stage-success');
    $plan = app(PlanLegacyPermitEvidence::class)->handle($fixture['batch'], 'document-plan-success');
    $proposal = $plan->proposals()->where('kind', 'business_supporting_document')->sole();

    expect($stagingRetry->id)->toBe($staging->id)
        ->and($staging->staged_count)->toBe(1)
        ->and($proposal->status)->toBe(LegacyMappingProposalStatus::Ready)
        ->and($proposal->metadata['legacy_document_status_observed'])->toBe('Approved')
        ->and($proposal->metadata['legacy_document_status_authority_migrated'])->toBeFalse()
        ->and($proposal->metadata['documentary_sufficiency_asserted'])->toBeFalse();

    $execution = app(ExecuteLegacyPermitEvidence::class)->handle($plan, [$proposal->id], 'document-execute-success');
    $retry = app(ExecuteLegacyPermitEvidence::class)->handle($plan, [$proposal->id], 'document-execute-success');
    $document = PermitApplicationDocument::query()->sole();
    $mapping = LegacyPermitDocumentMapping::query()->sole();
    $readiness = app(AssessLegacyMigrationReadiness::class)->handle($fixture['batch'], 'document-readiness-success');
    $transferCheck = collect($readiness->checks)->firstWhere('key', 'document_object_transfer_verified');

    expect($retry->id)->toBe($execution->id)
        ->and($execution->created_count)->toBe(1)
        ->and($document->permit_application_id)->toBe($fixture['application']->id)
        ->and($document->uploaded_by_id)->toBeNull()
        ->and($document->source_snapshot['legacy_document_status_authority_migrated'])->toBeFalse()
        ->and($document->source_snapshot['documentary_sufficiency_asserted'])->toBeFalse()
        ->and(Storage::disk('local')->get($document->path))->toBe($fixture['object_bytes'])
        ->and($mapping->metadata['object_checksum'])->toBe(hash('sha256', $fixture['object_bytes']))
        ->and($transferCheck['passed'])->toBeTrue()
        ->and($transferCheck['actual']['checksum_verified_document_objects'])->toBe(1);

    $path = $document->path;
    $rolledBack = app(RollbackLegacyPermitEvidence::class)->handle($execution);
    app(RollbackLegacyPermitEvidence::class)->handle($rolledBack);

    expect(PermitApplicationDocument::query()->count())->toBe(0)
        ->and(LegacyPermitDocumentMapping::query()->count())->toBe(0)
        ->and(Storage::disk('local')->exists($path))->toBeFalse()
        ->and(Storage::disk('local')->exists($staging->reconciliations->sole()->staged_path))->toBeTrue();
});

test('staging refuses checksum and application-scope mismatches without domain writes', function () {
    $checksum = documentTransferFixture('bad-checksum');
    $manifest = json_decode(file_get_contents($checksum['manifest_path']), true, flags: JSON_THROW_ON_ERROR);
    $manifest['objects'][0]['sha256'] = str_repeat('0', 64);
    file_put_contents($checksum['manifest_path'], json_encode($manifest, JSON_THROW_ON_ERROR));

    expect(fn () => app(StageLegacyDocumentObjects::class)->handle($checksum['batch'], $checksum['manifest_path'], 'document-stage-bad-checksum'))
        ->toThrow(RuntimeException::class, 'checksum, size, or MIME type');
    expect(PermitApplicationDocument::query()->count())->toBe(0)
        ->and(LegacyDocumentObjectReconciliation::query()->count())->toBe(0)
        ->and(LegacyDocumentObjectStagingRun::query()->sole()->status->value)->toBe('failed');

    $scope = documentTransferFixture('bad-scope', 'another-business');
    expect(fn () => app(StageLegacyDocumentObjects::class)->handle($scope['batch'], $scope['manifest_path'], 'document-stage-bad-scope'))
        ->toThrow(RuntimeException::class, 'application scope does not match');
});

test('tampered staged and migrated objects fail closed', function () {
    $fixture = documentTransferFixture('tamper');
    $staging = app(StageLegacyDocumentObjects::class)->handle($fixture['batch'], $fixture['manifest_path'], 'document-stage-tamper');
    $plan = app(PlanLegacyPermitEvidence::class)->handle($fixture['batch'], 'document-plan-tamper');
    $proposal = $plan->proposals()->where('kind', 'business_supporting_document')->sole();
    Storage::disk('local')->put($staging->reconciliations->sole()->staged_path, 'tampered');

    expect(fn () => app(ExecuteLegacyPermitEvidence::class)->handle($plan, [$proposal->id], 'document-execute-tamper'))
        ->toThrow(RuntimeException::class);
    expect(PermitApplicationDocument::query()->count())->toBe(0);

    Storage::disk('local')->put($staging->reconciliations->sole()->staged_path, $fixture['object_bytes']);
    $execution = app(ExecuteLegacyPermitEvidence::class)->handle($plan, [$proposal->id], 'document-execute-tamper-retry');
    $document = PermitApplicationDocument::query()->sole();
    Storage::disk('local')->put($document->path, 'tampered');

    expect(fn () => app(RollbackLegacyPermitEvidence::class)->handle($execution))->toThrow(RuntimeException::class);
    expect(PermitApplicationDocument::query()->count())->toBe(1)
        ->and(LegacyPermitDocumentMapping::query()->count())->toBe(1);
});

test('staging command requires confirmation and writes redacted evidence', function () {
    $fixture = documentTransferFixture('command');
    $arguments = [
        'batch' => $fixture['batch']->id,
        'manifest' => $fixture['manifest_path'],
        '--run-id' => 'document-stage-command',
        '--json' => true,
    ];

    $this->artisan('legacy:stage-document-objects', $arguments)->assertFailed();
    $this->artisan('legacy:stage-document-objects', [...$arguments, '--stage' => true, '--confirm-stage' => true])->assertSuccessful();

    $run = LegacyDocumentObjectStagingRun::query()->sole();
    $root = "legacy-migrations/{$fixture['batch']->source->key}/{$fixture['batch']->run_reference}/document-object-staging/{$run->run_reference}";
    $report = Storage::disk('local')->get($root.'/staging.json');
    expect($report)
        ->not->toContain($fixture['storage_reference'], $fixture['original_name'], $fixture['manifest_path'])
        ->and(Storage::disk('local')->exists($root.'/review.md'))->toBeTrue();

    $plan = app(PlanLegacyPermitEvidence::class)->handle($fixture['batch'], 'document-plan-command');
    $proposal = $plan->proposals()->where('kind', 'business_supporting_document')->sole();
    $this->artisan('legacy:execute-permit-evidence', [
        'plan' => $plan->id,
        '--proposal' => [$proposal->id],
        '--run-id' => 'document-execute-command',
        '--execute' => true,
        '--confirm-execute' => true,
        '--json' => true,
    ])->assertSuccessful();
    $executionRoot = "legacy-migrations/{$fixture['batch']->source->key}/{$fixture['batch']->run_reference}/permit-evidence-plans/{$plan->run_reference}/executions/document-execute-command";
    $executionReport = Storage::disk('local')->get($executionRoot.'/execution.json');
    $decoded = json_decode($executionReport, true, flags: JSON_THROW_ON_ERROR);
    expect($executionReport)
        ->not->toContain($fixture['storage_reference'], $fixture['original_name'])
        ->and($decoded['safety']['document_objects_copied'])->toBe(1)
        ->and($decoded['safety']['documentary_sufficiency_asserted'])->toBeFalse();
});

test('document mapping factory preserves one coherent provenance graph', function () {
    $mapping = LegacyPermitDocumentMapping::factory()->create();

    expect($mapping->legacy_record_id)->toBe($mapping->documentReconciliation->legacy_record_id)
        ->and($mapping->legacy_application_id_mapping_id)->toBe($mapping->documentReconciliation->legacy_application_id_mapping_id)
        ->and($mapping->legacy_source_id)->toBe($mapping->legacyRecord->legacy_source_id)
        ->and($mapping->legacy_import_batch_id)->toBe($mapping->legacyRecord->legacy_import_batch_id)
        ->and($mapping->permitApplicationDocument->permit_application_id)->toBe($mapping->applicationMapping->permit_application_id);
});
