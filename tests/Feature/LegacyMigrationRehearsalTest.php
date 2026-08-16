<?php

use App\Actions\RollbackLegacyMigrationRehearsal;
use App\Actions\VerifyLegacyMigrationRehearsal;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingExecutionStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalAction;
use App\Enums\LegacyMappingProposalStatus;
use App\Enums\LegacyMigrationRehearsalStatus;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyApplicationMappingExecution;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyApplicationMappingProposal;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyFinancialMappingProposal;
use App\Models\LegacyIdMapping;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingExecution;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMappingProposal;
use App\Models\LegacyMigrationRehearsal;
use App\Models\LegacyPermitEvidencePlan;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\Storage;

/** @return array{batch: LegacyImportBatch, registry_execution: LegacyMappingExecution, application_execution: LegacyApplicationMappingExecution, owner: BusinessOwner, business: Business, application: PermitApplication, sensitive_owner: string, sensitive_business: string} */
function migrationRehearsalFixture(string $suffix): array
{
    $sensitiveOwner = 'Sensitive Owner '.$suffix;
    $sensitiveBusiness = 'Sensitive Business '.$suffix;
    $source = LegacySource::factory()->create([
        'key' => 'LEGACY-REHEARSAL-'.$suffix,
        'source_type' => 'synthetic_convex_export',
        'archive_checksum' => hash('sha256', 'rehearsal-archive-'.$suffix),
        'provenance' => ['origin' => 'isolated_test_fixture'],
    ]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'rehearsal-stage-'.$suffix,
        'status' => LegacyImportBatchStatus::Staged,
        'source_record_count' => 3,
        'staged_record_count' => 3,
        'exception_count' => 0,
    ]);
    $ownerRecord = rehearsalRecord($batch, 'business_owners', 'owner-'.$suffix, ['firstName' => $sensitiveOwner]);
    $businessRecord = rehearsalRecord($batch, 'businesses', 'business-'.$suffix, ['ownerId' => $ownerRecord->legacy_id, 'name' => $sensitiveBusiness]);
    $applicationRecord = rehearsalRecord($batch, 'business_permit_applications', 'application-'.$suffix, [
        'businessOwnerId' => $ownerRecord->legacy_id,
        'businessId' => $businessRecord->legacy_id,
        'status' => 'Draft',
        'linesOfBusiness' => [],
    ]);

    $registryPlan = LegacyMappingPlan::factory()->for($batch, 'importBatch')->create([
        'run_reference' => 'registry-plan-'.$suffix,
        'status' => LegacyMappingPlanStatus::Planned,
        'owner_proposal_count' => 1,
        'business_proposal_count' => 1,
        'ready_count' => 2,
        'review_count' => 0,
        'blocked_count' => 0,
        'completed_at' => now(),
    ]);
    $ownerProposal = LegacyMappingProposal::factory()->for($registryPlan, 'mappingPlan')->for($ownerRecord, 'legacyRecord')->create([
        'dataset_key' => 'business_owners',
        'entity_type' => 'business_owner',
        'target_type' => 'business_owner',
        'proposed_action' => LegacyMappingProposalAction::LinkExactLegacyId,
        'status' => LegacyMappingProposalStatus::Ready,
    ]);
    $businessProposal = LegacyMappingProposal::factory()->for($registryPlan, 'mappingPlan')->for($businessRecord, 'legacyRecord')->create([
        'parent_legacy_record_id' => $ownerRecord->id,
        'dataset_key' => 'businesses',
        'entity_type' => 'business',
        'target_type' => 'business',
        'proposed_action' => LegacyMappingProposalAction::LinkExactLegacyId,
        'status' => LegacyMappingProposalStatus::Ready,
    ]);
    $registryIds = [$ownerProposal->id, $businessProposal->id];
    sort($registryIds);
    $registryExecution = LegacyMappingExecution::factory()->for($registryPlan, 'mappingPlan')->create([
        'run_reference' => 'registry-execution-'.$suffix,
        'selection_hash' => rehearsalSelectionHash($registryIds),
        'status' => LegacyMappingExecutionStatus::Completed,
        'selected_count' => 2,
        'linked_count' => 2,
        'mapping_count' => 2,
        'metadata' => rehearsalExecutionMetadata($registryIds),
    ]);
    $owner = BusinessOwner::factory()->create();
    $business = Business::factory()->for($owner, 'owner')->create();
    $ownerMapping = LegacyIdMapping::query()->create([
        'legacy_mapping_execution_id' => $registryExecution->id,
        'legacy_source_id' => $source->id,
        'legacy_import_batch_id' => $batch->id,
        'dataset_key' => 'business_owners',
        'entity_type' => 'business_owner',
        'legacy_id' => $ownerRecord->legacy_id,
        'target_type' => 'business_owner',
        'target_id' => $owner->id,
        'status' => 'mapped',
        'mapping_basis' => 'exact_test_identity',
        'metadata' => ['created_by_execution' => false],
    ]);
    $businessMapping = LegacyIdMapping::query()->create([
        'legacy_mapping_execution_id' => $registryExecution->id,
        'legacy_source_id' => $source->id,
        'legacy_import_batch_id' => $batch->id,
        'dataset_key' => 'businesses',
        'entity_type' => 'business',
        'legacy_id' => $businessRecord->legacy_id,
        'target_type' => 'business',
        'target_id' => $business->id,
        'status' => 'mapped',
        'mapping_basis' => 'exact_test_identity',
        'metadata' => ['created_by_execution' => false],
    ]);

    $applicationPlan = LegacyApplicationMappingPlan::factory()->for($batch, 'importBatch')->create([
        'run_reference' => 'application-plan-'.$suffix,
        'status' => LegacyMappingPlanStatus::Planned,
        'proposal_count' => 1,
        'ready_count' => 1,
        'review_count' => 0,
        'blocked_count' => 0,
        'completed_at' => now(),
    ]);
    $applicationProposal = LegacyApplicationMappingProposal::factory()->for($applicationPlan, 'mappingPlan')->for($applicationRecord, 'legacyRecord')->create([
        'owner_mapping_id' => $ownerMapping->id,
        'business_mapping_id' => $businessMapping->id,
        'proposed_action' => LegacyMappingProposalAction::LinkExactLegacyId,
        'status' => LegacyMappingProposalStatus::Ready,
    ]);
    $applicationIds = [$applicationProposal->id];
    $applicationExecution = LegacyApplicationMappingExecution::factory()->for($applicationPlan, 'mappingPlan')->create([
        'run_reference' => 'application-execution-'.$suffix,
        'selection_hash' => rehearsalSelectionHash($applicationIds),
        'status' => LegacyMappingExecutionStatus::Completed,
        'selected_count' => 1,
        'linked_count' => 1,
        'mapping_count' => 1,
        'completed_at' => now(),
        'metadata' => rehearsalExecutionMetadata($applicationIds),
    ]);
    $application = PermitApplication::factory()->for($business)->create(['legacy_source_id' => $applicationRecord->legacy_id]);
    LegacyApplicationIdMapping::factory()->for($batch, 'importBatch')->for($application)->create([
        'legacy_application_mapping_execution_id' => $applicationExecution->id,
        'legacy_source_id' => $source->id,
        'dataset_key' => 'business_permit_applications',
        'legacy_id' => $applicationRecord->legacy_id,
        'metadata' => ['created_by_execution' => false],
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

    return [
        'batch' => $batch,
        'registry_execution' => $registryExecution,
        'application_execution' => $applicationExecution,
        'owner' => $owner,
        'business' => $business,
        'application' => $application,
        'sensitive_owner' => $sensitiveOwner,
        'sensitive_business' => $sensitiveBusiness,
    ];
}

/** @param array<string, mixed> $payload */
function rehearsalRecord(LegacyImportBatch $batch, string $dataset, string $legacyId, array $payload): LegacyRecord
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

/** @param list<int> $proposalIds */
function rehearsalSelectionHash(array $proposalIds): string
{
    sort($proposalIds);

    return hash('sha256', json_encode($proposalIds, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
}

/** @param list<int> $proposalIds
 * @return array<string, mixed>
 */
function rehearsalExecutionMetadata(array $proposalIds): array
{
    return [
        'proposal_ids' => $proposalIds,
        'external_integrations' => false,
        'notifications' => false,
        'irreversible_actions' => false,
    ];
}

test('exact completed executions verify as one bounded idempotent migration rehearsal', function () {
    $fixture = migrationRehearsalFixture('verified');
    $action = app(VerifyLegacyMigrationRehearsal::class);

    $rehearsal = $action->handle(
        $fixture['batch'],
        $fixture['registry_execution'],
        $fixture['application_execution'],
        null,
        null,
        null,
        'migration-rehearsal-verified',
    );
    $retry = $action->handle(
        $fixture['batch'],
        $fixture['registry_execution'],
        $fixture['application_execution'],
        null,
        null,
        null,
        'migration-rehearsal-verified',
    );
    $checks = collect($rehearsal->checks)->keyBy('key');

    expect($rehearsal->status)->toBe(LegacyMigrationRehearsalStatus::Verified)
        ->and($retry->id)->toBe($rehearsal->id)
        ->and(LegacyMigrationRehearsal::query()->count())->toBe(1)
        ->and($checks['registry_execution']['passed'])->toBeTrue()
        ->and($checks['applications_execution']['passed'])->toBeTrue()
        ->and($checks['declarations_execution']['actual']['applicable'])->toBeFalse()
        ->and($checks['financial_execution']['actual']['applicable'])->toBeFalse()
        ->and($checks['permit_evidence_execution']['actual']['applicable'])->toBeFalse()
        ->and($checks['planning_rehearsal_ready']['passed'])->toBeTrue()
        ->and($checks['authoritative_execution_results']['passed'])->toBeTrue()
        ->and($checks['safety_boundaries']['passed'])->toBeTrue()
        ->and($checks['cutover_boundary_recorded']['actual']['cutover_ready'])->toBeFalse()
        ->and($rehearsal->metadata['domain_logic_duplicated'])->toBeFalse();
});

test('a stable rehearsal run rejects proposal evidence drift', function () {
    $fixture = migrationRehearsalFixture('drift');
    $action = app(VerifyLegacyMigrationRehearsal::class);
    $action->handle(
        $fixture['batch'],
        $fixture['registry_execution'],
        $fixture['application_execution'],
        null,
        null,
        null,
        'migration-rehearsal-drift',
    );
    $fixture['batch']->applicationMappingPlans()->sole()->proposals()->sole()->update([
        'status' => LegacyMappingProposalStatus::Blocked,
    ]);

    expect(fn () => $action->handle(
        $fixture['batch'],
        $fixture['registry_execution'],
        $fixture['application_execution'],
        null,
        null,
        null,
        'migration-rehearsal-drift',
    ))->toThrow(RuntimeException::class, 'different execution or dependency evidence');
});

test('rehearsal fails closed for incomplete selection and unsafe execution metadata', function () {
    $fixture = migrationRehearsalFixture('blocked');
    $fixture['registry_execution']->update([
        'selected_count' => 1,
        'selection_hash' => rehearsalSelectionHash([$fixture['registry_execution']->metadata['proposal_ids'][0]]),
        'metadata' => [
            ...$fixture['registry_execution']->metadata,
            'proposal_ids' => [$fixture['registry_execution']->metadata['proposal_ids'][0]],
            'external_integrations' => true,
        ],
    ]);

    $rehearsal = app(VerifyLegacyMigrationRehearsal::class)->handle(
        $fixture['batch'],
        $fixture['registry_execution'],
        $fixture['application_execution'],
        null,
        null,
        null,
        'migration-rehearsal-blocked',
    );
    $checks = collect($rehearsal->checks)->keyBy('key');

    expect($rehearsal->status)->toBe(LegacyMigrationRehearsalStatus::VerificationFailed)
        ->and($rehearsal->blocked_count)->toBeGreaterThanOrEqual(2)
        ->and($checks['registry_execution']['passed'])->toBeFalse()
        ->and($checks['registry_execution']['actual']['exact_selection'])->toBeFalse()
        ->and($checks['safety_boundaries']['passed'])->toBeFalse();
});

test('a downstream execution becomes mandatory when its latest plan contains ready proposals', function () {
    $fixture = migrationRehearsalFixture('applicable');
    $financialPlan = $fixture['batch']->financialMappingPlans()->sole();
    LegacyFinancialMappingProposal::factory()->for($financialPlan, 'mappingPlan')->create([
        'legacy_record_id' => $fixture['batch']->records()->where('dataset_key', 'business_permit_applications')->sole()->id,
        'status' => LegacyMappingProposalStatus::Ready,
    ]);
    $financialPlan->update([
        'proposal_count' => 1,
        'ready_count' => 1,
    ]);

    $rehearsal = app(VerifyLegacyMigrationRehearsal::class)->handle(
        $fixture['batch'],
        $fixture['registry_execution'],
        $fixture['application_execution'],
        null,
        null,
        null,
        'migration-rehearsal-applicable',
    );
    $check = collect($rehearsal->checks)->firstWhere('key', 'financial_execution');

    expect($rehearsal->status)->toBe(LegacyMigrationRehearsalStatus::VerificationFailed)
        ->and($check['passed'])->toBeFalse()
        ->and($check['actual']['applicable'])->toBeTrue()
        ->and($check['actual']['ready_proposals'])->toBe(1);
});

test('completed execution metadata cannot substitute for missing authoritative mappings', function () {
    $fixture = migrationRehearsalFixture('missing-result');
    LegacyApplicationIdMapping::query()->delete();

    $rehearsal = app(VerifyLegacyMigrationRehearsal::class)->handle(
        $fixture['batch'],
        $fixture['registry_execution'],
        $fixture['application_execution'],
        null,
        null,
        null,
        'migration-rehearsal-missing-result',
    );
    $check = collect($rehearsal->checks)->firstWhere('key', 'authoritative_execution_results');

    expect($rehearsal->status)->toBe(LegacyMigrationRehearsalStatus::VerificationFailed)
        ->and($check['passed'])->toBeFalse()
        ->and($check['actual']['applications'])->toBeFalse();
});

test('rollback composes existing domain rollbacks in reverse order and preserves linked targets', function () {
    $fixture = migrationRehearsalFixture('rollback');
    $rehearsal = app(VerifyLegacyMigrationRehearsal::class)->handle(
        $fixture['batch'],
        $fixture['registry_execution'],
        $fixture['application_execution'],
        null,
        null,
        null,
        'migration-rehearsal-rollback',
    );

    $rolledBack = app(RollbackLegacyMigrationRehearsal::class)->handle($rehearsal);
    $retry = app(RollbackLegacyMigrationRehearsal::class)->handle($rolledBack);

    expect($rolledBack->status)->toBe(LegacyMigrationRehearsalStatus::RolledBack)
        ->and($retry->id)->toBe($rolledBack->id)
        ->and($rolledBack->metadata['rollback_completed_phases'])->toBe(['permit_evidence', 'financial', 'declarations', 'applications', 'registry'])
        ->and($fixture['application']->fresh())->not->toBeNull()
        ->and($fixture['business']->fresh())->not->toBeNull()
        ->and($fixture['owner']->fresh())->not->toBeNull()
        ->and(LegacyApplicationIdMapping::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0)
        ->and($fixture['application_execution']->fresh()->status)->toBe(LegacyMappingExecutionStatus::RolledBack)
        ->and($fixture['registry_execution']->fresh()->status)->toBe(LegacyMappingExecutionStatus::RolledBack);
});

test('a partially completed rehearsal rollback resumes without repeating completed phases', function () {
    $fixture = migrationRehearsalFixture('resume');
    $rehearsal = app(VerifyLegacyMigrationRehearsal::class)->handle(
        $fixture['batch'],
        $fixture['registry_execution'],
        $fixture['application_execution'],
        null,
        null,
        null,
        'migration-rehearsal-resume',
    );
    $fixture['registry_execution']->update(['status' => LegacyMappingExecutionStatus::Executing]);

    expect(fn () => app(RollbackLegacyMigrationRehearsal::class)->handle($rehearsal))
        ->toThrow(RuntimeException::class, 'is not completed and cannot be rolled back');
    $failed = $rehearsal->fresh();
    expect($failed->status)->toBe(LegacyMigrationRehearsalStatus::RollbackFailed)
        ->and($failed->metadata['rollback_completed_phases'])->toBe(['permit_evidence', 'financial', 'declarations', 'applications'])
        ->and($fixture['application_execution']->fresh()->status)->toBe(LegacyMappingExecutionStatus::RolledBack)
        ->and($fixture['registry_execution']->fresh()->status)->toBe(LegacyMappingExecutionStatus::Executing);

    $fixture['registry_execution']->update(['status' => LegacyMappingExecutionStatus::Completed]);
    $rolledBack = app(RollbackLegacyMigrationRehearsal::class)->handle($failed);

    expect($rolledBack->status)->toBe(LegacyMigrationRehearsalStatus::RolledBack)
        ->and($rolledBack->metadata['rollback_completed_phases'])->toBe(['permit_evidence', 'financial', 'declarations', 'applications', 'registry'])
        ->and($fixture['registry_execution']->fresh()->status)->toBe(LegacyMappingExecutionStatus::RolledBack);
});

test('commands require dual confirmation and write redacted rehearsal evidence', function () {
    Storage::fake('local');
    $fixture = migrationRehearsalFixture('command');
    $arguments = [
        'batch' => $fixture['batch']->id,
        '--registry-execution' => $fixture['registry_execution']->id,
        '--application-execution' => $fixture['application_execution']->id,
        '--run-id' => 'migration-rehearsal-command',
        '--json' => true,
    ];

    $this->artisan('legacy:verify-migration-rehearsal', $arguments)->assertFailed();
    $this->artisan('legacy:verify-migration-rehearsal', [...$arguments, '--verify' => true, '--confirm-verify' => true])->assertSuccessful();

    $rehearsal = LegacyMigrationRehearsal::query()->sole();
    $root = "legacy-migrations/{$fixture['batch']->source->key}/{$fixture['batch']->run_reference}/rehearsals/{$rehearsal->run_reference}";
    $report = Storage::disk('local')->get($root.'/rehearsal.json');
    $decoded = json_decode($report, true, flags: JSON_THROW_ON_ERROR);
    expect($report)
        ->not->toContain($fixture['sensitive_owner'], $fixture['sensitive_business'])
        ->and($decoded['safety']['domain_logic_duplicated'])->toBeFalse()
        ->and($decoded['readiness']['cutover_ready'])->toBeFalse()
        ->and(Storage::disk('local')->exists($root.'/review.md'))->toBeTrue();

    $this->artisan('legacy:rollback-migration-rehearsal', ['rehearsal' => $rehearsal->id, '--json' => true])->assertFailed();
    $this->artisan('legacy:rollback-migration-rehearsal', [
        'rehearsal' => $rehearsal->id,
        '--rollback' => true,
        '--confirm-rollback' => true,
        '--json' => true,
    ])->assertSuccessful();
    Storage::disk('local')->assertExists($root.'/rollback.json');
});
