<?php

use App\Actions\AssessLegacyMigrationReadiness;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingExecutionStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMigrationReadinessStatus;
use App\Enums\MigrationExceptionSeverity;
use App\Enums\MigrationExceptionStatus;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingExecution;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMigrationException;
use App\Models\LegacyMigrationReadinessAssessment;
use App\Models\LegacyPermitEvidencePlan;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\Storage;

/** @return array{source: LegacySource, batch: LegacyImportBatch} */
function readinessBatch(string $suffix, bool $completePlans = true): array
{
    $source = LegacySource::factory()->create([
        'key' => 'LEGACY-READINESS-'.$suffix,
        'source_type' => 'synthetic_convex_export',
        'archive_checksum' => hash('sha256', 'readiness-archive-'.$suffix),
        'provenance' => ['origin' => 'isolated_test_fixture', 'operator_note' => 'Sensitive note must not leak'],
    ]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'readiness-staging-'.$suffix,
        'status' => LegacyImportBatchStatus::Staged,
        'source_record_count' => 3,
        'staged_record_count' => 3,
        'exception_count' => 0,
    ]);
    readinessRecord($batch, 'business_owners', 'owner-'.$suffix, ['firstName' => 'Sensitive Owner']);
    readinessRecord($batch, 'businesses', 'business-'.$suffix, ['ownerId' => 'owner-'.$suffix, 'name' => 'Sensitive Business']);
    readinessRecord($batch, 'business_permit_applications', 'application-'.$suffix, [
        'businessOwnerId' => 'owner-'.$suffix,
        'businessId' => 'business-'.$suffix,
        'status' => 'Draft',
        'linesOfBusiness' => [],
    ]);

    if ($completePlans) {
        readinessPlans($batch);
    }

    return compact('source', 'batch');
}

/** @param array<string, mixed> $payload */
function readinessRecord(LegacyImportBatch $batch, string $dataset, string $legacyId, array $payload): LegacyRecord
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

function readinessPlans(LegacyImportBatch $batch): void
{
    LegacyMappingPlan::factory()->for($batch, 'importBatch')->create([
        'status' => LegacyMappingPlanStatus::Planned,
        'owner_proposal_count' => 1,
        'business_proposal_count' => 1,
        'ready_count' => 2,
        'review_count' => 0,
        'blocked_count' => 0,
        'completed_at' => now(),
    ]);
    LegacyApplicationMappingPlan::factory()->for($batch, 'importBatch')->create([
        'status' => LegacyMappingPlanStatus::Planned,
        'proposal_count' => 1,
        'ready_count' => 1,
        'review_count' => 0,
        'blocked_count' => 0,
        'completed_at' => now(),
    ]);
    LegacyFinancialMappingPlan::factory()->for($batch, 'importBatch')->create([
        'status' => LegacyMappingPlanStatus::Planned,
        'proposal_count' => 0,
        'ready_count' => 0,
        'review_count' => 0,
        'blocked_count' => 0,
        'completed_at' => now(),
    ]);
    LegacyPermitEvidencePlan::factory()->for($batch, 'importBatch')->create([
        'status' => LegacyMappingPlanStatus::Planned,
        'proposal_count' => 0,
        'ready_count' => 0,
        'review_count' => 0,
        'blocked_count' => 0,
        'completed_at' => now(),
    ]);
}

test('complete planning evidence can pass rehearsal while cutover remains explicitly blocked', function () {
    $fixture = readinessBatch('rehearsal');

    $assessment = app(AssessLegacyMigrationReadiness::class)->handle($fixture['batch'], 'readiness-rehearsal-001');
    $checks = collect($assessment->checks)->keyBy('key');

    expect($assessment->status)->toBe(LegacyMigrationReadinessStatus::RehearsalReady)
        ->and($assessment->rehearsal_ready)->toBeTrue()
        ->and($assessment->cutover_ready)->toBeFalse()
        ->and($checks['core_datasets_present']['passed'])->toBeTrue()
        ->and($checks['registry_plan_ready']['passed'])->toBeTrue()
        ->and($checks['declaration_plan_ready']['actual']['applicable'])->toBeFalse()
        ->and($checks['production_export_provenance']['passed'])->toBeFalse()
        ->and($checks['remaining_domain_execution_paths']['passed'])->toBeFalse()
        ->and($checks['municipal_cutover_authorization']['passed'])->toBeFalse()
        ->and($assessment->metadata['migration_execution'])->toBeFalse()
        ->and(BusinessOwner::query()->count())->toBe(0)
        ->and(Business::query()->count())->toBe(0)
        ->and(PermitApplication::query()->count())->toBe(0);
});

test('missing and unresolved plans block rehearsal with exact diagnostic checks', function () {
    $fixture = readinessBatch('missing-plans', false);
    LegacyMigrationException::factory()->for($fixture['batch'], 'importBatch')->create([
        'severity' => MigrationExceptionSeverity::Error,
        'status' => MigrationExceptionStatus::Open,
        'code' => 'unresolved-sensitive-reference',
    ]);

    $assessment = app(AssessLegacyMigrationReadiness::class)->handle($fixture['batch'], 'readiness-missing-plans');
    $checks = collect($assessment->checks)->keyBy('key');

    expect($assessment->status)->toBe(LegacyMigrationReadinessStatus::Blocked)
        ->and($assessment->rehearsal_ready)->toBeFalse()
        ->and($checks['migration_exceptions_resolved']['passed'])->toBeFalse()
        ->and($checks['migration_exceptions_resolved']['actual']['open_errors'])->toBe(1)
        ->and($checks['registry_plan_ready']['passed'])->toBeFalse()
        ->and($checks['application_plan_ready']['passed'])->toBeFalse()
        ->and($checks['financial_plan_ready']['passed'])->toBeFalse()
        ->and($checks['permit_evidence_plan_ready']['passed'])->toBeFalse();
});

test('review or blocked proposals prevent a rehearsal readiness claim', function () {
    $fixture = readinessBatch('review');
    $plan = $fixture['batch']->applicationMappingPlans()->sole();
    $plan->update([
        'status' => LegacyMappingPlanStatus::PlannedWithExceptions,
        'ready_count' => 0,
        'review_count' => 1,
    ]);

    $assessment = app(AssessLegacyMigrationReadiness::class)->handle($fixture['batch'], 'readiness-review');
    $check = collect($assessment->checks)->firstWhere('key', 'application_plan_ready');

    expect($assessment->rehearsal_ready)->toBeFalse()
        ->and($check['passed'])->toBeFalse()
        ->and($check['actual']['review_required'])->toBe(1);
});

test('staged document references independently block cutover object transfer without blocking planning rehearsal', function () {
    $fixture = readinessBatch('documents');
    $business = $fixture['batch']->records()->where('dataset_key', 'businesses')->sole();
    $payload = [
        ...$business->payload,
        'documents' => [[
            'storageId' => 'sensitive-storage-object',
            'documentType' => 'DTI Certificate',
            'fileName' => 'sensitive-document.pdf',
            'uploadedAt' => '2026-08-16T08:00:00+08:00',
        ]],
    ];
    $business->update([
        'payload' => $payload,
        'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
    ]);

    $assessment = app(AssessLegacyMigrationReadiness::class)->handle($fixture['batch'], 'readiness-documents');
    $check = collect($assessment->checks)->firstWhere('key', 'document_object_transfer_verified');

    expect($assessment->rehearsal_ready)->toBeTrue()
        ->and($assessment->cutover_ready)->toBeFalse()
        ->and($check['passed'])->toBeFalse()
        ->and($check['actual'])->toMatchArray([
            'staged_document_metadata_records' => 1,
            'object_transfer_verified' => false,
        ]);
});

test('stable readiness runs are idempotent and reject changed evidence', function () {
    $fixture = readinessBatch('stable');
    $action = app(AssessLegacyMigrationReadiness::class);

    $first = $action->handle($fixture['batch'], 'readiness-stable-001');
    $second = $action->handle($fixture['batch'], 'readiness-stable-001');

    expect($second->id)->toBe($first->id)
        ->and(LegacyMigrationReadinessAssessment::query()->count())->toBe(1);

    LegacyMappingExecution::factory()->for($fixture['batch']->mappingPlans()->sole(), 'mappingPlan')->create([
        'status' => LegacyMappingExecutionStatus::Completed,
    ]);

    expect(fn () => $action->handle($fixture['batch'], 'readiness-stable-001'))
        ->toThrow(RuntimeException::class, 'different evidence');
});

test('command preserves redacted evidence and fails a requested cutover gate', function () {
    Storage::fake('local');
    $fixture = readinessBatch('command');

    $this->artisan('legacy:assess-readiness', [
        'batch' => $fixture['batch']->id,
        '--run-id' => 'readiness-command-001',
        '--gate' => 'cutover',
        '--json' => true,
    ])->assertFailed();

    $root = "legacy-migrations/{$fixture['source']->key}/{$fixture['batch']->run_reference}/readiness-assessments/readiness-command-001";
    Storage::disk('local')->assertExists($root.'/readiness-report.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $report = Storage::disk('local')->get($root.'/readiness-report.json');
    $decoded = json_decode($report, true, flags: JSON_THROW_ON_ERROR);

    expect($report)->not->toContain('Sensitive Owner', 'Sensitive Business', 'Sensitive note must not leak')
        ->and($decoded['result']['rehearsal_ready'])->toBeTrue()
        ->and($decoded['result']['cutover_ready'])->toBeFalse()
        ->and($decoded['safety'])->toMatchArray([
            'assessment_only' => true,
            'migration_executed' => false,
            'external_calls' => false,
            'production_mutation' => false,
            'cutover_authorized' => false,
            'domain_writes' => false,
        ]);
});

test('rehearsal gate succeeds only when every rehearsal check passes', function () {
    Storage::fake('local');
    $fixture = readinessBatch('rehearsal-command');

    $this->artisan('legacy:assess-readiness', [
        'batch' => $fixture['batch']->id,
        '--run-id' => 'readiness-rehearsal-command',
        '--gate' => 'rehearsal',
        '--json' => true,
    ])->assertSuccessful();
});
