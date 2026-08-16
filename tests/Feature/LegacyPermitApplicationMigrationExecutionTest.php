<?php

use App\Actions\ExecuteLegacyPermitApplications;
use App\Actions\PlanLegacyPermitApplications;
use App\Actions\RollbackLegacyPermitApplications;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingExecutionStatus;
use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyApplicationMappingExecution;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyApplicationMappingProposal;
use App\Models\LegacyIdMapping;
use App\Models\LegacyImportBatch;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\PermitApplicationLine;
use App\Models\PermitClearance;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Illuminate\Support\Facades\Storage;

/** @return array{source: LegacySource, batch: LegacyImportBatch, owner: BusinessOwner, business: Business, record: LegacyRecord, plan: LegacyApplicationMappingPlan, proposal: LegacyApplicationMappingProposal} */
function executableApplicationPlan(string $suffix, ?PermitApplication $existing = null, bool $alreadyMapped = false): array
{
    $source = LegacySource::factory()->create([
        'key' => 'LEGACY-APPLICATION-EXECUTION-'.$suffix,
        'baseline' => 'application-execution-baseline-'.$suffix,
        'archive_checksum' => hash('sha256', 'application-execution-archive-'.$suffix),
    ]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'application-execution-staging-'.$suffix,
        'status' => LegacyImportBatchStatus::Staged,
        'source_record_count' => 1,
        'staged_record_count' => 1,
        'exception_count' => 0,
        'mapping_count' => 2,
    ]);
    $owner = BusinessOwner::factory()->create(['legacy_source_id' => 'owner-application-execution-'.$suffix]);
    $business = Business::factory()->for($owner, 'owner')->create(['legacy_source_id' => 'business-application-execution-'.$suffix]);
    applicationExecutionRegistryMapping($batch, 'business_owners', 'business_owner', $owner->legacy_source_id, $owner->id);
    applicationExecutionRegistryMapping($batch, 'businesses', 'business', $business->legacy_source_id, $business->id);
    $payload = [
        '_id' => 'legacy-application-record-'.$suffix,
        'businessOwnerId' => $owner->legacy_source_id,
        'businessId' => $business->legacy_source_id,
        'status' => 'Assessment',
        'permitApplicationType' => 'New',
        'applicationNumber' => 'PRIVATE-BPA-'.$suffix,
        'submittedAt' => '2026-08-01T09:00:00+08:00',
        'linesOfBusiness' => [],
        'applicantName' => 'Sensitive Applicant '.$suffix,
    ];
    $record = LegacyRecord::query()->create([
        'legacy_import_batch_id' => $batch->id,
        'legacy_source_id' => $source->id,
        'dataset_key' => 'applications',
        'entity_type' => 'application',
        'legacy_id' => $payload['_id'],
        'payload' => $payload,
        'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        'status' => 'staged',
        'line_number' => 1,
    ]);

    if ($existing instanceof PermitApplication) {
        $existing->update([
            'business_id' => $business->id,
            'legacy_source_id' => $record->legacy_id,
        ]);

        if ($alreadyMapped) {
            LegacyApplicationIdMapping::query()->create([
                'legacy_source_id' => $source->id,
                'legacy_import_batch_id' => $batch->id,
                'permit_application_id' => $existing->id,
                'dataset_key' => 'applications',
                'legacy_id' => $record->legacy_id,
                'status' => 'mapped',
                'mapping_basis' => 'pre_existing_accepted_mapping',
                'metadata' => ['fixture' => true],
            ]);
        }
    }

    $plan = app(PlanLegacyPermitApplications::class)->handle($batch, 'application-execution-plan-'.$suffix);

    return [
        'source' => $source,
        'batch' => $batch,
        'owner' => $owner,
        'business' => $business,
        'record' => $record,
        'plan' => $plan,
        'proposal' => $plan->proposals->sole(),
    ];
}

function applicationExecutionRegistryMapping(LegacyImportBatch $batch, string $dataset, string $targetType, string $legacyId, int $targetId): LegacyIdMapping
{
    return LegacyIdMapping::query()->create([
        'legacy_source_id' => $batch->legacy_source_id,
        'legacy_import_batch_id' => $batch->id,
        'dataset_key' => $dataset,
        'entity_type' => $targetType,
        'legacy_id' => $legacyId,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'status' => 'mapped',
        'mapping_basis' => 'test_accepted_mapping',
        'metadata' => ['fixture' => true],
    ]);
}

test('a selected ready proposal creates one unnumbered application through an idempotent execution', function () {
    $fixture = executableApplicationPlan('create');
    $action = app(ExecuteLegacyPermitApplications::class);

    $execution = $action->handle($fixture['plan'], [$fixture['proposal']->id], 'application-execution-create-001');
    $retry = $action->handle($fixture['plan'], [$fixture['proposal']->id], 'application-execution-create-001');
    $application = PermitApplication::query()->sole();

    expect($execution)
        ->status->toBe(LegacyMappingExecutionStatus::Completed)
        ->created_count->toBe(1)
        ->linked_count->toBe(0)
        ->mapping_count->toBe(1)
        ->and($retry->id)->toBe($execution->id)
        ->and(LegacyApplicationMappingExecution::query()->count())->toBe(1)
        ->and(LegacyApplicationIdMapping::query()->count())->toBe(1)
        ->and($application->business_id)->toBe($fixture['business']->id)
        ->and($application->application_number)->toBeNull()
        ->and($application->submitted_by_id)->toBeNull()
        ->and($application->legacy_source_id)->toBe($fixture['record']->legacy_id)
        ->and($application->metadata['official_application_number_authority'])->toBe('unresolved')
        ->and($application->metadata['migration']['execution_id'])->toBe($execution->id)
        ->and(PermitApplicationLine::query()->count())->toBe(0)
        ->and(Assessment::query()->count())->toBe(0)
        ->and(PaymentSchedule::query()->count())->toBe(0)
        ->and(TreasuryCollection::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0)
        ->and(PermitClearance::query()->count())->toBe(0)
        ->and(PermitApplicationDocument::query()->count())->toBe(0);

    expect(fn () => $action->handle($fixture['plan'], [$fixture['proposal']->id], 'application-execution-other-run'))
        ->toThrow(RuntimeException::class, 'no longer matches its dependency snapshot');
});

test('one run reference cannot be reused for a different proposal selection', function () {
    $fixture = executableApplicationPlan('selection');
    $action = app(ExecuteLegacyPermitApplications::class);
    $action->handle($fixture['plan'], [$fixture['proposal']->id], 'application-execution-selection-001');

    expect(fn () => $action->handle($fixture['plan'], [$fixture['proposal']->id, 999999], 'application-execution-selection-001'))
        ->toThrow(RuntimeException::class, 'different proposal selection');
});

test('exact legacy links are mapped while rollback preserves the pre-existing application', function () {
    $existing = PermitApplication::factory()->create(['application_number' => 'EXISTING-OFFICIAL-REFERENCE']);
    $fixture = executableApplicationPlan('exact', $existing);
    $execution = app(ExecuteLegacyPermitApplications::class)->handle(
        $fixture['plan'],
        [$fixture['proposal']->id],
        'application-execution-exact-001',
    );

    expect($execution)
        ->created_count->toBe(0)
        ->linked_count->toBe(1)
        ->and($existing->fresh()->application_number)->toBe('EXISTING-OFFICIAL-REFERENCE');

    $rolledBack = app(RollbackLegacyPermitApplications::class)->handle($execution);
    $retry = app(RollbackLegacyPermitApplications::class)->handle($rolledBack);

    expect($rolledBack->status)->toBe(LegacyMappingExecutionStatus::RolledBack)
        ->and($retry->id)->toBe($execution->id)
        ->and(PermitApplication::query()->whereKey($existing->id)->exists())->toBeTrue()
        ->and(LegacyApplicationIdMapping::query()->count())->toBe(0);
});

test('an accepted pre-existing application mapping is reused without duplication', function () {
    $existing = PermitApplication::factory()->create();
    $fixture = executableApplicationPlan('reuse', $existing, true);

    $execution = app(ExecuteLegacyPermitApplications::class)->handle(
        $fixture['plan'],
        [$fixture['proposal']->id],
        'application-execution-reuse-001',
    );

    expect($execution)
        ->created_count->toBe(0)
        ->linked_count->toBe(0)
        ->reused_count->toBe(1)
        ->mapping_count->toBe(0)
        ->and(LegacyApplicationIdMapping::query()->count())->toBe(1)
        ->and(PermitApplication::query()->whereKey($existing->id)->exists())->toBeTrue();
});

test('rollback deletes only an unchanged application created by the execution', function () {
    $fixture = executableApplicationPlan('rollback');
    $execution = app(ExecuteLegacyPermitApplications::class)->handle(
        $fixture['plan'],
        [$fixture['proposal']->id],
        'application-execution-rollback-001',
    );

    $rolledBack = app(RollbackLegacyPermitApplications::class)->handle($execution);

    expect($rolledBack->status)->toBe(LegacyMappingExecutionStatus::RolledBack)
        ->and(PermitApplication::query()->count())->toBe(0)
        ->and(LegacyApplicationIdMapping::query()->count())->toBe(0)
        ->and($rolledBack->metadata['pre_existing_targets_deleted'])->toBeFalse();
});

test('rollback refuses changed applications and applications with downstream records', function () {
    $changedFixture = executableApplicationPlan('changed');
    $changedExecution = app(ExecuteLegacyPermitApplications::class)->handle(
        $changedFixture['plan'],
        [$changedFixture['proposal']->id],
        'application-execution-changed-001',
    );
    $changed = PermitApplication::query()->where('legacy_source_id', $changedFixture['record']->legacy_id)->sole();
    $changed->update(['application_year' => 2025]);

    expect(fn () => app(RollbackLegacyPermitApplications::class)->handle($changedExecution))
        ->toThrow(RuntimeException::class, 'changed after migration; rollback refused');

    $dependencyFixture = executableApplicationPlan('dependency');
    $dependencyExecution = app(ExecuteLegacyPermitApplications::class)->handle(
        $dependencyFixture['plan'],
        [$dependencyFixture['proposal']->id],
        'application-execution-dependency-001',
    );
    $dependency = PermitApplication::query()->where('legacy_source_id', $dependencyFixture['record']->legacy_id)->sole();
    PermitApplicationLine::factory()->for($dependency)->create();

    expect(fn () => app(RollbackLegacyPermitApplications::class)->handle($dependencyExecution))
        ->toThrow(RuntimeException::class, 'has downstream records; rollback refused')
        ->and($dependencyExecution->fresh()->status)->toBe(LegacyMappingExecutionStatus::Completed);
});

test('execution refuses changed source projections ownership drift and non-ready proposals', function () {
    $projectionFixture = executableApplicationPlan('projection-drift');
    $projectionFixture['record']->update([
        'payload' => [...$projectionFixture['record']->payload, 'permitApplicationType' => 'Renewal'],
    ]);

    expect(fn () => app(ExecuteLegacyPermitApplications::class)->handle(
        $projectionFixture['plan'],
        [$projectionFixture['proposal']->id],
        'application-execution-projection-drift',
    ))->toThrow(RuntimeException::class, 'no longer matches its staged projection');

    $ownershipFixture = executableApplicationPlan('ownership-drift');
    $ownershipFixture['business']->update(['business_owner_id' => BusinessOwner::factory()->create()->id]);

    expect(fn () => app(ExecuteLegacyPermitApplications::class)->handle(
        $ownershipFixture['plan'],
        [$ownershipFixture['proposal']->id],
        'application-execution-ownership-drift',
    ))->toThrow(RuntimeException::class, 'no longer agrees with registry ownership');

    $statusFixture = executableApplicationPlan('not-ready');
    $statusFixture['proposal']->update(['status' => 'review_required', 'proposed_action' => 'review']);

    expect(fn () => app(ExecuteLegacyPermitApplications::class)->handle(
        $statusFixture['plan'],
        [$statusFixture['proposal']->id],
        'application-execution-not-ready',
    ))->toThrow(RuntimeException::class, 'is not ready and cannot execute');
});

test('commands require exact dual confirmation and write redacted execution and rollback evidence', function () {
    Storage::fake('local');
    $fixture = executableApplicationPlan('command');
    $arguments = [
        'plan' => $fixture['plan']->id,
        '--proposal' => [$fixture['proposal']->id],
        '--run-id' => 'application-execution-command-001',
        '--json' => true,
    ];

    $this->artisan('legacy:execute-applications', $arguments)->assertFailed();
    $this->artisan('legacy:execute-applications', [...$arguments, '--execute' => true, '--confirm-execute' => true])->assertSuccessful();

    $execution = LegacyApplicationMappingExecution::query()->sole();
    $root = 'legacy-migrations/LEGACY-APPLICATION-EXECUTION-command/application-execution-staging-command/application-mapping-plans/application-execution-plan-command/executions/application-execution-command-001';
    Storage::disk('local')->assertExists($root.'/execution.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $report = Storage::disk('local')->get($root.'/execution.json');
    $decoded = json_decode($report, true, flags: JSON_THROW_ON_ERROR);

    expect($report)
        ->not->toContain('legacy-application-record-command')
        ->not->toContain('PRIVATE-BPA-command')
        ->not->toContain('Sensitive Applicant command')
        ->and($decoded['safety'])->toMatchArray([
            'official_application_numbers_assigned' => false,
            'application_actor_attribution_inferred' => false,
            'downstream_records_created' => false,
            'external_integrations' => false,
        ]);

    $this->artisan('legacy:rollback-applications', ['execution' => $execution->id, '--json' => true])->assertFailed();
    $this->artisan('legacy:rollback-applications', [
        'execution' => $execution->id,
        '--rollback' => true,
        '--confirm-rollback' => true,
        '--json' => true,
    ])->assertSuccessful();
    Storage::disk('local')->assertExists($root.'/rollback.json');
});
