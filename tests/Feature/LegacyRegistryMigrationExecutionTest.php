<?php

use App\Actions\ExecuteLegacyRegistryMigration;
use App\Actions\PlanLegacyRegistryMigration;
use App\Actions\RollbackLegacyRegistryMigration;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingExecutionStatus;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyIdMapping;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingExecution;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMappingProposal;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\Storage;

/** @return array{batch: LegacyImportBatch, owner: LegacyRecord, business: LegacyRecord} */
function createExecutableRegistryBatch(string $suffix = '001'): array
{
    $source = LegacySource::factory()->create([
        'key' => 'LEGACY-EXECUTION-'.$suffix,
        'baseline' => 'execution-baseline-'.$suffix,
        'archive_checksum' => hash('sha256', 'execution-archive-'.$suffix),
    ]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'registry-execution-staging-'.$suffix,
        'status' => LegacyImportBatchStatus::Staged,
        'source_record_count' => 2,
        'staged_record_count' => 2,
        'exception_count' => 0,
        'mapping_count' => 0,
    ]);
    $ownerPayload = [
        '_id' => 'owner-execution-'.$suffix,
        'firstName' => 'Execution',
        'lastName' => 'Owner '.$suffix,
        'email' => 'execution.'.$suffix.'@example.test',
    ];
    $businessPayload = [
        '_id' => 'business-execution-'.$suffix,
        'ownerId' => 'owner-execution-'.$suffix,
        'name' => 'Execution Business '.$suffix,
        'registrationNumber' => 'EXEC-'.$suffix,
    ];
    $owner = createExecutionRecord($batch, 'business_owners', 'business_owner', $ownerPayload);
    $business = createExecutionRecord($batch, 'businesses', 'business', $businessPayload);

    return compact('batch', 'owner', 'business');
}

/** @param array<string, mixed> $payload */
function createExecutionRecord(LegacyImportBatch $batch, string $dataset, string $entity, array $payload): LegacyRecord
{
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    return LegacyRecord::query()->create([
        'legacy_import_batch_id' => $batch->id,
        'legacy_source_id' => $batch->legacy_source_id,
        'dataset_key' => $dataset,
        'entity_type' => $entity,
        'legacy_id' => $payload['_id'],
        'payload' => $payload,
        'payload_hash' => hash('sha256', $encoded),
        'status' => 'staged',
        'line_number' => $batch->records()->where('dataset_key', $dataset)->count() + 1,
    ]);
}

/** @return array{plan: LegacyMappingPlan, owner: LegacyMappingProposal, business: LegacyMappingProposal} */
function createExecutableRegistryPlan(string $suffix = '001'): array
{
    $fixture = createExecutableRegistryBatch($suffix);
    $plan = app(PlanLegacyRegistryMigration::class)->handle($fixture['batch'], 'registry-execution-plan-'.$suffix);

    return [
        'plan' => $plan,
        'owner' => $plan->proposals->firstWhere('target_type', 'business_owner'),
        'business' => $plan->proposals->firstWhere('target_type', 'business'),
    ];
}

test('selected create proposals execute once and rollback removes only their unchanged records', function () {
    $fixture = createExecutableRegistryPlan('create');
    $ids = [$fixture['owner']->id, $fixture['business']->id];
    $action = app(ExecuteLegacyRegistryMigration::class);

    $execution = $action->handle($fixture['plan'], $ids, 'registry-execution-create-001');
    $retry = $action->handle($fixture['plan'], array_reverse($ids), 'registry-execution-create-001');
    $owner = BusinessOwner::query()->sole();
    $business = Business::query()->sole();

    expect($execution)
        ->status->toBe(LegacyMappingExecutionStatus::Completed)
        ->created_count->toBe(2)
        ->mapping_count->toBe(2)
        ->and($retry->id)->toBe($execution->id)
        ->and(LegacyMappingExecution::query()->count())->toBe(1)
        ->and(LegacyIdMapping::query()->count())->toBe(2)
        ->and($business->business_owner_id)->toBe($owner->id);

    $rolledBack = app(RollbackLegacyRegistryMigration::class)->handle($execution);
    $rollbackRetry = app(RollbackLegacyRegistryMigration::class)->handle($rolledBack);

    expect($rolledBack->status)->toBe(LegacyMappingExecutionStatus::RolledBack)
        ->and($rollbackRetry->id)->toBe($execution->id)
        ->and(BusinessOwner::query()->count())->toBe(0)
        ->and(Business::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0);
});

test('exact legacy links create mappings but rollback preserves pre-existing targets', function () {
    $existingOwner = BusinessOwner::factory()->create(['legacy_source_id' => 'owner-exact-link']);
    $existingBusiness = Business::factory()->for($existingOwner, 'owner')->create(['legacy_source_id' => 'business-exact-link']);
    $source = LegacySource::factory()->create(['key' => 'LEGACY-EXACT-LINK']);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'exact-link-staging',
        'status' => LegacyImportBatchStatus::Staged,
        'source_record_count' => 2,
        'staged_record_count' => 2,
    ]);
    createExecutionRecord($batch, 'business_owners', 'business_owner', [
        '_id' => 'owner-exact-link',
        'firstName' => 'Exact',
        'lastName' => 'Owner',
    ]);
    createExecutionRecord($batch, 'businesses', 'business', [
        '_id' => 'business-exact-link',
        'ownerId' => 'owner-exact-link',
        'name' => 'Exact Business',
    ]);
    $plan = app(PlanLegacyRegistryMigration::class)->handle($batch, 'exact-link-plan');

    $execution = app(ExecuteLegacyRegistryMigration::class)->handle($plan, $plan->proposals->pluck('id')->all(), 'exact-link-execution');

    expect($execution)->created_count->toBe(0)
        ->linked_count->toBe(2)
        ->and(LegacyIdMapping::query()->count())->toBe(2);

    app(RollbackLegacyRegistryMigration::class)->handle($execution);

    expect(BusinessOwner::query()->whereKey($existingOwner->id)->exists())->toBeTrue()
        ->and(Business::query()->whereKey($existingBusiness->id)->exists())->toBeTrue()
        ->and(LegacyIdMapping::query()->count())->toBe(0);
});

test('execution refuses a staged payload changed after planning', function () {
    $fixture = createExecutableRegistryPlan('tamper');
    $record = $fixture['owner']->legacyRecord;
    $record->update(['payload' => [...$record->payload, 'lastName' => 'Changed']]);

    expect(fn () => app(ExecuteLegacyRegistryMigration::class)->handle(
        $fixture['plan'],
        [$fixture['owner']->id],
        'registry-execution-tamper-001',
    ))->toThrow(RuntimeException::class, 'no longer matches its staged projection')
        ->and(BusinessOwner::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0);
});

test('execution refuses a registry changed after planning', function () {
    $fixture = createExecutableRegistryPlan('registry-drift');
    BusinessOwner::factory()->create();

    expect(fn () => app(ExecuteLegacyRegistryMigration::class)->handle(
        $fixture['plan'],
        [$fixture['owner']->id],
        'registry-execution-drift-001',
    ))->toThrow(RuntimeException::class, 'no longer matches the current registry snapshot')
        ->and(LegacyMappingExecution::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0);
});

test('rollback refuses to delete a created target changed after execution', function () {
    $fixture = createExecutableRegistryPlan('changed');
    $execution = app(ExecuteLegacyRegistryMigration::class)->handle(
        $fixture['plan'],
        [$fixture['owner']->id],
        'registry-execution-changed-001',
    );
    $owner = BusinessOwner::query()->sole();
    $owner->update(['name' => 'Post Migration Registry Change']);

    expect(fn () => app(RollbackLegacyRegistryMigration::class)->handle($execution))
        ->toThrow(RuntimeException::class, 'changed after migration; rollback refused')
        ->and(BusinessOwner::query()->whereKey($owner->id)->exists())->toBeTrue()
        ->and(LegacyIdMapping::query()->count())->toBe(1)
        ->and($execution->fresh()->status)->toBe(LegacyMappingExecutionStatus::Completed);
});

test('execution refuses proposals that are not ready', function () {
    $fixture = createExecutableRegistryPlan('not-ready');
    $fixture['owner']->update(['status' => 'review_required', 'proposed_action' => 'review']);

    expect(fn () => app(ExecuteLegacyRegistryMigration::class)->handle(
        $fixture['plan'],
        [$fixture['owner']->id],
        'registry-execution-not-ready-001',
    ))->toThrow(RuntimeException::class, 'is not ready and cannot execute')
        ->and(LegacyMappingExecution::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0);
});

test('rollback refuses created businesses that acquired permit applications', function () {
    $fixture = createExecutableRegistryPlan('dependency');
    $execution = app(ExecuteLegacyRegistryMigration::class)->handle(
        $fixture['plan'],
        [$fixture['owner']->id, $fixture['business']->id],
        'registry-execution-dependency-001',
    );
    $business = Business::query()->sole();
    PermitApplication::factory()->for($business)->create();

    expect(fn () => app(RollbackLegacyRegistryMigration::class)->handle($execution))
        ->toThrow(RuntimeException::class, 'has permit applications; rollback refused')
        ->and(Business::query()->whereKey($business->id)->exists())->toBeTrue()
        ->and(LegacyIdMapping::query()->count())->toBe(2)
        ->and($execution->fresh()->status)->toBe(LegacyMappingExecutionStatus::Completed);
});

test('commands require exact selection and dual confirmations and write redacted evidence', function () {
    Storage::fake('local');
    $fixture = createExecutableRegistryPlan('command');
    $arguments = [
        'plan' => $fixture['plan']->id,
        '--proposal' => [$fixture['owner']->id, $fixture['business']->id],
        '--run-id' => 'registry-execution-command-001',
        '--json' => true,
    ];

    $this->artisan('legacy:execute-registry', $arguments)->assertFailed();
    $this->artisan('legacy:execute-registry', [...$arguments, '--execute' => true, '--confirm-execute' => true])->assertSuccessful();

    $execution = LegacyMappingExecution::query()->sole();
    $root = 'legacy-migrations/LEGACY-EXECUTION-command/registry-execution-staging-command/mapping-plans/registry-execution-plan-command/executions/registry-execution-command-001';
    Storage::disk('local')->assertExists($root.'/execution.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $executionReport = Storage::disk('local')->get($root.'/execution.json');

    expect($executionReport)
        ->not->toContain('execution.command@example.test')
        ->not->toContain('owner-execution-command')
        ->not->toContain('business-execution-command');

    $this->artisan('legacy:rollback-registry', ['execution' => $execution->id, '--json' => true])->assertFailed();
    $this->artisan('legacy:rollback-registry', [
        'execution' => $execution->id,
        '--rollback' => true,
        '--confirm-rollback' => true,
        '--json' => true,
    ])->assertSuccessful();
    Storage::disk('local')->assertExists($root.'/rollback.json');
});
