<?php

use App\Actions\AssessLegacyMigrationReadiness;
use App\Actions\ExecuteLegacyPermitEvidence;
use App\Actions\PlanLegacyPermitEvidence;
use App\Actions\RollbackLegacyPermitEvidence;
use App\Enums\LegacyClearanceTypeReconciliationStatus;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingExecutionStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Enums\PermitClearanceStatus;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyApplicationMappingProposal;
use App\Models\LegacyClearanceTypeReconciliation;
use App\Models\LegacyImportBatch;
use App\Models\LegacyPermitClearanceMapping;
use App\Models\LegacyPermitEvidenceExecution;
use App\Models\LegacyPermitEvidencePlan;
use App\Models\LegacyPermitEvidenceProposal;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\PermitClearance;
use Illuminate\Support\Facades\Storage;

/** @return array{source: LegacySource, batch: LegacyImportBatch, application: PermitApplication, application_record: LegacyRecord, application_mapping: LegacyApplicationIdMapping, reconciliation: LegacyClearanceTypeReconciliation, clearance_record: LegacyRecord, plan: LegacyPermitEvidencePlan, proposal: LegacyPermitEvidenceProposal} */
function executablePermitEvidencePlan(string $suffix): array
{
    $source = LegacySource::factory()->create([
        'key' => 'LEGACY-PERMIT-EXECUTION-'.$suffix,
        'baseline' => 'permit-execution-baseline-'.$suffix,
        'archive_checksum' => hash('sha256', 'permit-execution-archive-'.$suffix),
    ]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'permit-execution-staging-'.$suffix,
        'status' => LegacyImportBatchStatus::Staged,
    ]);
    $applicationPayload = ['_id' => 'application-'.$suffix, 'status' => 'Pending Payment'];
    $applicationRecord = legacyPermitExecutionRecord($batch, 'applications', $applicationPayload['_id'], $applicationPayload);
    $applicationPlan = LegacyApplicationMappingPlan::factory()->for($batch, 'importBatch')->create([
        'run_reference' => 'application-plan-'.$suffix,
        'status' => LegacyMappingPlanStatus::Planned,
        'proposal_count' => 1,
        'ready_count' => 1,
    ]);
    LegacyApplicationMappingProposal::factory()->for($applicationPlan, 'mappingPlan')->for($applicationRecord, 'legacyRecord')->create([
        'status' => LegacyMappingProposalStatus::Ready,
    ]);
    $application = PermitApplication::factory()->create(['legacy_source_id' => $applicationRecord->legacy_id]);
    $applicationMapping = LegacyApplicationIdMapping::query()->create([
        'legacy_source_id' => $source->id,
        'legacy_import_batch_id' => $batch->id,
        'permit_application_id' => $application->id,
        'dataset_key' => 'applications',
        'legacy_id' => $applicationRecord->legacy_id,
        'status' => 'mapped',
        'mapping_basis' => 'fixture',
        'metadata' => ['fixture' => true],
    ]);
    $reconciliation = LegacyClearanceTypeReconciliation::query()->create([
        'legacy_source_id' => $source->id,
        'source_dataset' => 'clearance_types',
        'source_legacy_id' => 'clearance-type-'.$suffix,
        'target_code' => 'sanitary',
        'target_label' => 'Sanitary Clearance',
        'status' => LegacyClearanceTypeReconciliationStatus::Accepted,
        'decision_authority' => 'Municipal test authority',
        'evidence_reference' => 'TEST-CLEARANCE-'.$suffix,
        'decided_at' => now(),
    ]);
    $clearancePayload = [
        '_id' => 'clearance-'.$suffix,
        'applicationId' => $applicationRecord->legacy_id,
        'clearanceTypeId' => $reconciliation->source_legacy_id,
        'isCompleted' => false,
        'assignedAt' => '2026-08-16T09:00:00+08:00',
    ];
    $clearanceRecord = legacyPermitExecutionRecord($batch, 'permit_clearances', $clearancePayload['_id'], $clearancePayload);
    $plan = app(PlanLegacyPermitEvidence::class)->handle($batch, 'permit-evidence-plan-'.$suffix);

    return [
        'source' => $source,
        'batch' => $batch,
        'application' => $application,
        'application_record' => $applicationRecord,
        'application_mapping' => $applicationMapping,
        'reconciliation' => $reconciliation,
        'clearance_record' => $clearanceRecord,
        'plan' => $plan,
        'proposal' => $plan->proposals()->where('kind', 'clearance')->sole(),
    ];
}

/** @param array<string, mixed> $payload */
function legacyPermitExecutionRecord(LegacyImportBatch $batch, string $dataset, string $legacyId, array $payload): LegacyRecord
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

test('a selected ready pending clearance executes once without asserting completion or permit authority', function () {
    $fixture = executablePermitEvidencePlan('create');
    $action = app(ExecuteLegacyPermitEvidence::class);

    $execution = $action->handle($fixture['plan'], [$fixture['proposal']->id], 'permit-evidence-execution-create');
    $retry = $action->handle($fixture['plan'], [$fixture['proposal']->id], 'permit-evidence-execution-create');
    $clearance = PermitClearance::query()->sole();
    $readiness = app(AssessLegacyMigrationReadiness::class)->handle($fixture['batch'], 'permit-evidence-readiness-create');
    $executionCheck = collect($readiness->checks)->firstWhere('key', 'remaining_domain_execution_paths');

    expect($execution)
        ->status->toBe(LegacyMappingExecutionStatus::Completed)
        ->created_count->toBe(1)
        ->linked_count->toBe(0)
        ->reused_count->toBe(0)
        ->mapping_count->toBe(1)
        ->and($retry->id)->toBe($execution->id)
        ->and(LegacyPermitEvidenceExecution::query()->count())->toBe(1)
        ->and(LegacyPermitClearanceMapping::query()->count())->toBe(1)
        ->and($clearance->permit_application_id)->toBe($fixture['application']->id)
        ->and($clearance->code)->toBe('sanitary')
        ->and($clearance->status)->toBe(PermitClearanceStatus::Pending)
        ->and($clearance->completed_by_id)->toBeNull()
        ->and($clearance->completed_at)->toBeNull()
        ->and($clearance->legacy_source_id)->toBe($fixture['clearance_record']->legacy_id)
        ->and($clearance->source_snapshot['completion_authority_asserted'])->toBeFalse()
        ->and($executionCheck['actual']['permit_evidence_execution'])->toBeTrue()
        ->and($executionCheck['actual']['mapped_pending_clearances'])->toBe(1)
        ->and($executionCheck['actual']['unresolved_permit_evidence_proposals'])->toBe(0)
        ->and(PermitApplicationDocument::query()->count())->toBe(0);
});

test('an exact pre-existing pending clearance is linked and preserved by rollback', function () {
    $fixture = executablePermitEvidencePlan('link');
    $existing = PermitClearance::factory()->for($fixture['application'], 'permitApplication')->create([
        'code' => 'sanitary',
        'label' => 'Sanitary Clearance',
        'status' => PermitClearanceStatus::Pending,
        'legacy_source_id' => $fixture['clearance_record']->legacy_id,
    ]);

    $execution = app(ExecuteLegacyPermitEvidence::class)->handle(
        $fixture['plan'],
        [$fixture['proposal']->id],
        'permit-evidence-execution-link',
    );

    expect($execution)
        ->created_count->toBe(0)
        ->linked_count->toBe(1)
        ->mapping_count->toBe(1)
        ->and(PermitClearance::query()->count())->toBe(1);

    $rolledBack = app(RollbackLegacyPermitEvidence::class)->handle($execution);
    $retry = app(RollbackLegacyPermitEvidence::class)->handle($rolledBack);

    expect($rolledBack->status)->toBe(LegacyMappingExecutionStatus::RolledBack)
        ->and($retry->id)->toBe($execution->id)
        ->and(PermitClearance::query()->whereKey($existing->id)->exists())->toBeTrue()
        ->and(LegacyPermitClearanceMapping::query()->count())->toBe(0);
});

test('rollback removes only unchanged clearances created by the execution', function () {
    $fixture = executablePermitEvidencePlan('rollback');
    $execution = app(ExecuteLegacyPermitEvidence::class)->handle(
        $fixture['plan'],
        [$fixture['proposal']->id],
        'permit-evidence-execution-rollback',
    );

    $rolledBack = app(RollbackLegacyPermitEvidence::class)->handle($execution);

    expect($rolledBack->status)->toBe(LegacyMappingExecutionStatus::RolledBack)
        ->and(PermitClearance::query()->count())->toBe(0)
        ->and(LegacyPermitClearanceMapping::query()->count())->toBe(0)
        ->and($rolledBack->metadata['pre_existing_targets_deleted'])->toBeFalse();
});

test('rollback refuses a clearance changed by subsequent municipal operation', function () {
    $fixture = executablePermitEvidencePlan('changed');
    $execution = app(ExecuteLegacyPermitEvidence::class)->handle(
        $fixture['plan'],
        [$fixture['proposal']->id],
        'permit-evidence-execution-changed',
    );
    PermitClearance::query()->sole()->update(['remarks' => 'Operational review started']);

    expect(fn () => app(RollbackLegacyPermitEvidence::class)->handle($execution))
        ->toThrow(RuntimeException::class, 'changed after migration; rollback refused')
        ->and($execution->fresh()->status)->toBe(LegacyMappingExecutionStatus::Completed);
});

test('execution fails closed for authority claims completed clearances and dependency drift', function () {
    $authorityFixture = executablePermitEvidencePlan('authority');
    $authorityFixture['proposal']->update([
        'kind' => 'permit_authority_claim',
        'status' => LegacyMappingProposalStatus::Ready,
    ]);
    expect(fn () => app(ExecuteLegacyPermitEvidence::class)->handle(
        $authorityFixture['plan'],
        [$authorityFixture['proposal']->id],
        'permit-evidence-execution-authority',
    ))->toThrow(RuntimeException::class, 'is authority-bearing or unsupported');

    $completedFixture = executablePermitEvidencePlan('completed');
    $completedFixture['proposal']->update(['metadata' => [...$completedFixture['proposal']->metadata, 'completed' => true]]);
    expect(fn () => app(ExecuteLegacyPermitEvidence::class)->handle(
        $completedFixture['plan'],
        [$completedFixture['proposal']->id],
        'permit-evidence-execution-completed',
    ))->toThrow(RuntimeException::class, 'asserts completion authority');

    $driftFixture = executablePermitEvidencePlan('drift');
    $driftFixture['reconciliation']->update(['evidence_reference' => 'CHANGED-EVIDENCE']);
    expect(fn () => app(ExecuteLegacyPermitEvidence::class)->handle(
        $driftFixture['plan'],
        [$driftFixture['proposal']->id],
        'permit-evidence-execution-drift',
    ))->toThrow(RuntimeException::class, 'no longer matches its dependency snapshot');
});

test('execution requires one exact application mapping and rejects conflicting clearance identity', function () {
    $mappingFixture = executablePermitEvidencePlan('mapping');
    $mappingFixture['application_mapping']->delete();
    expect(fn () => app(ExecuteLegacyPermitEvidence::class)->handle(
        $mappingFixture['plan'],
        [$mappingFixture['proposal']->id],
        'permit-evidence-execution-mapping',
    ))->toThrow(RuntimeException::class, 'requires one exact accepted application mapping');

    $collisionFixture = executablePermitEvidencePlan('collision');
    PermitClearance::factory()->for($collisionFixture['application'], 'permitApplication')->create([
        'code' => 'sanitary',
        'label' => 'Sanitary Clearance',
        'status' => PermitClearanceStatus::Pending,
        'legacy_source_id' => 'different-legacy-clearance',
    ]);
    expect(fn () => app(ExecuteLegacyPermitEvidence::class)->handle(
        $collisionFixture['plan'],
        [$collisionFixture['proposal']->id],
        'permit-evidence-execution-collision',
    ))->toThrow(RuntimeException::class, 'conflicts with proposal');
});

test('commands require dual confirmation and write redacted execution and rollback evidence', function () {
    Storage::fake('local');
    $fixture = executablePermitEvidencePlan('command');
    $arguments = [
        'plan' => $fixture['plan']->id,
        '--proposal' => [$fixture['proposal']->id],
        '--run-id' => 'permit-evidence-execution-command',
        '--json' => true,
    ];

    $this->artisan('legacy:execute-permit-evidence', $arguments)->assertFailed();
    $this->artisan('legacy:execute-permit-evidence', [...$arguments, '--execute' => true, '--confirm-execute' => true])->assertSuccessful();

    $execution = LegacyPermitEvidenceExecution::query()->sole();
    $root = 'legacy-migrations/LEGACY-PERMIT-EXECUTION-command/permit-execution-staging-command/permit-evidence-plans/permit-evidence-plan-command/executions/permit-evidence-execution-command';
    Storage::disk('local')->assertExists($root.'/execution.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $report = Storage::disk('local')->get($root.'/execution.json');
    $decoded = json_decode($report, true, flags: JSON_THROW_ON_ERROR);

    expect($report)
        ->not->toContain($fixture['clearance_record']->legacy_id, $fixture['application_record']->legacy_id)
        ->and($decoded['safety'])->toMatchArray([
            'supported_kinds' => ['clearance', 'business_supporting_document'],
            'completed_clearances_created' => false,
            'document_objects_copied' => 0,
            'permit_artifacts_created' => false,
            'issuance_authorized' => false,
            'release_authorized' => false,
            'legal_effect_asserted' => false,
            'external_integrations' => false,
        ]);

    $this->artisan('legacy:rollback-permit-evidence', ['execution' => $execution->id, '--json' => true])->assertFailed();
    $this->artisan('legacy:rollback-permit-evidence', [
        'execution' => $execution->id,
        '--rollback' => true,
        '--confirm-rollback' => true,
        '--json' => true,
    ])->assertSuccessful();
    Storage::disk('local')->assertExists($root.'/rollback.json');
});

test('permit clearance mapping factory creates one coherent provenance graph', function () {
    $mapping = LegacyPermitClearanceMapping::factory()->create();

    expect($mapping->legacy_source_id)->toBe($mapping->legacyRecord->legacy_source_id)
        ->and($mapping->legacy_import_batch_id)->toBe($mapping->legacyRecord->legacy_import_batch_id)
        ->and($mapping->applicationMapping->legacy_source_id)->toBe($mapping->legacy_source_id)
        ->and($mapping->applicationMapping->legacy_import_batch_id)->toBe($mapping->legacy_import_batch_id)
        ->and($mapping->clearanceReconciliation->legacy_source_id)->toBe($mapping->legacy_source_id)
        ->and($mapping->permitClearance->permit_application_id)->toBe($mapping->applicationMapping->permit_application_id)
        ->and($mapping->permitClearance->code)->toBe($mapping->clearanceReconciliation->target_code)
        ->and($mapping->permitClearance->legacy_source_id)->toBe($mapping->legacy_id);
});
