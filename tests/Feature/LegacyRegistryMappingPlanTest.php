<?php

use App\Actions\PlanLegacyRegistryMigration;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalAction;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyIdMapping;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMappingProposal;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{source: LegacySource, batch: LegacyImportBatch}
 */
function createRegistryPlanningBatch(string $runReference = 'registry-staging-001'): array
{
    $source = LegacySource::factory()->create([
        'key' => 'LEGACY-REGISTRY-PLAN-TEST',
        'title' => 'Legacy registry planning fixture',
        'baseline' => 'registry-plan-test-baseline',
        'archive_checksum' => hash('sha256', 'registry-plan-test-archive'),
    ]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => $runReference,
        'status' => LegacyImportBatchStatus::Staged,
        'source_record_count' => 0,
        'staged_record_count' => 0,
        'exception_count' => 0,
        'mapping_count' => 0,
    ]);

    return compact('source', 'batch');
}

/** @param array<string, mixed> $payload */
function createRegistryPlanningRecord(LegacyImportBatch $batch, string $datasetKey, string $entityType, string $legacyId, array $payload): LegacyRecord
{
    $payload = ['_id' => $legacyId, ...$payload];
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    return LegacyRecord::query()->create([
        'legacy_import_batch_id' => $batch->id,
        'legacy_source_id' => $batch->legacy_source_id,
        'dataset_key' => $datasetKey,
        'entity_type' => $entityType,
        'legacy_id' => $legacyId,
        'payload' => $payload,
        'payload_hash' => hash('sha256', $encoded),
        'status' => 'staged',
        'line_number' => $batch->records()->where('dataset_key', $datasetKey)->count() + 1,
    ]);
}

/**
 * @return array{batch: LegacyImportBatch, initial_owner_count: int, initial_business_count: int}
 */
function createRegistryCollisionFixture(): array
{
    ['batch' => $batch] = createRegistryPlanningBatch();
    $exactOwner = BusinessOwner::factory()->create([
        'name' => 'Exact Existing Owner',
        'email' => 'exact.owner@example.test',
        'legacy_source_id' => 'owner-exact',
    ]);
    Business::factory()->for($exactOwner, 'owner')->create([
        'name' => 'Exact Existing Business',
        'registration_number' => 'REG-EXACT-001',
        'legacy_source_id' => 'business-exact',
    ]);
    Business::factory()->for($exactOwner, 'owner')->create([
        'name' => 'Existing Registration Collision',
        'registration_number' => 'REG-COLLISION-001',
    ]);
    $owners = [
        ['owner-ready', ['firstName' => 'Ready', 'lastName' => 'Owner', 'email' => 'ready.owner@example.test', 'mobile' => '09170000001']],
        ['owner-collision-a', ['firstName' => 'Collision', 'lastName' => 'One', 'email' => 'shared.owner@example.test', 'mobile' => '09170000002']],
        ['owner-collision-b', ['firstName' => 'Collision', 'lastName' => 'Two', 'email' => 'shared.owner@example.test', 'mobile' => '09170000003']],
        ['owner-group', ['firstName' => 'Group', 'lastName' => 'Representative', 'ownerType' => 'Group', 'groupName' => 'Group Legal Entity', 'email' => 'group@example.test']],
        ['owner-missing-name', ['email' => 'missing.name@example.test']],
        ['owner-exact', ['firstName' => 'Exact', 'lastName' => 'Existing Owner', 'email' => 'exact.owner@example.test']],
    ];

    foreach ($owners as [$legacyId, $payload]) {
        createRegistryPlanningRecord($batch, 'business_owners', 'business_owner', $legacyId, $payload);
    }

    $businesses = [
        ['business-ready', ['ownerId' => 'owner-ready', 'name' => 'Ready Business', 'registrationNumber' => 'REG-READY-001']],
        ['business-blocked-owner', ['ownerId' => 'owner-collision-a', 'name' => 'Blocked By Owner Business']],
        ['business-missing-owner', ['ownerId' => 'owner-not-staged', 'name' => 'Missing Owner Business']],
        ['business-exact', ['ownerId' => 'owner-exact', 'name' => 'Exact Existing Business', 'registrationNumber' => 'REG-EXACT-001']],
        ['business-registration-collision', ['ownerId' => 'owner-ready', 'name' => 'Registration Collision Business', 'registrationNumber' => 'REG-COLLISION-001']],
    ];

    foreach ($businesses as [$legacyId, $payload]) {
        createRegistryPlanningRecord($batch, 'businesses', 'business', $legacyId, $payload);
    }

    $batch->update(['source_record_count' => 11, 'staged_record_count' => 11]);

    return [
        'batch' => $batch->fresh(),
        'initial_owner_count' => BusinessOwner::query()->count(),
        'initial_business_count' => Business::query()->count(),
    ];
}

test('a clean staged registry produces deterministic create proposals without domain writes', function () {
    ['batch' => $batch] = createRegistryPlanningBatch('registry-clean-staging-001');
    $ownerRecord = createRegistryPlanningRecord($batch, 'business_owners', 'business_owner', 'owner-clean', [
        'firstName' => 'Clean',
        'middleName' => 'Migration',
        'lastName' => 'Owner',
        'email' => 'clean.owner@example.test',
        'mobile' => '09170000010',
        'birthDate' => '1980-01-02',
    ]);
    $businessRecord = createRegistryPlanningRecord($batch, 'businesses', 'business', 'business-clean', [
        'ownerId' => 'owner-clean',
        'name' => 'Clean Migration Business',
        'registrationNumber' => 'REG-CLEAN-001',
    ]);
    $batch->update(['source_record_count' => 2, 'staged_record_count' => 2]);

    $plan = app(PlanLegacyRegistryMigration::class)->handle($batch->fresh(), 'registry-clean-plan-001');
    $ownerProposal = $plan->proposals->firstWhere('legacy_record_id', $ownerRecord->id);
    $businessProposal = $plan->proposals->firstWhere('legacy_record_id', $businessRecord->id);

    expect($plan)
        ->status->toBe(LegacyMappingPlanStatus::Planned)
        ->owner_proposal_count->toBe(1)
        ->business_proposal_count->toBe(1)
        ->ready_count->toBe(2)
        ->review_count->toBe(0)
        ->blocked_count->toBe(0)
        ->exact_link_count->toBe(0)
        ->and($ownerProposal?->status)->toBe(LegacyMappingProposalStatus::Ready)
        ->and($ownerProposal?->proposed_action)->toBe(LegacyMappingProposalAction::Create)
        ->and($businessProposal?->status)->toBe(LegacyMappingProposalStatus::Ready)
        ->and($businessProposal?->proposed_action)->toBe(LegacyMappingProposalAction::Create)
        ->and($businessProposal?->parent_legacy_record_id)->toBe($ownerRecord->id)
        ->and(BusinessOwner::query()->count())->toBe(0)
        ->and(Business::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0);
});

test('collisions policy gaps and ownership dependencies remain reviewable without guessed mappings', function () {
    $fixture = createRegistryCollisionFixture();
    $batch = $fixture['batch'];

    $plan = app(PlanLegacyRegistryMigration::class)->handle($batch, 'registry-collision-plan-001');
    $proposals = $plan->proposals->keyBy(fn (LegacyMappingProposal $proposal): string => $proposal->metadata['legacy_id_sha256']);
    $proposalFor = fn (string $legacyId): ?LegacyMappingProposal => $proposals->get(hash('sha256', $legacyId));

    expect($plan)
        ->status->toBe(LegacyMappingPlanStatus::PlannedWithExceptions)
        ->owner_proposal_count->toBe(6)
        ->business_proposal_count->toBe(5)
        ->ready_count->toBe(4)
        ->review_count->toBe(4)
        ->blocked_count->toBe(3)
        ->exact_link_count->toBe(2)
        ->and($proposalFor('owner-ready')?->proposed_action)->toBe(LegacyMappingProposalAction::Create)
        ->and($proposalFor('owner-collision-a')?->reasons)->toContain('potential_source_owner_collision')
        ->and($proposalFor('owner-group')?->reasons)->toContain('group_owner_semantics_require_reconciliation')
        ->and($proposalFor('owner-missing-name')?->status)->toBe(LegacyMappingProposalStatus::Blocked)
        ->and($proposalFor('owner-exact')?->proposed_action)->toBe(LegacyMappingProposalAction::LinkExactLegacyId)
        ->and($proposalFor('business-blocked-owner')?->reasons)->toContain('owner_mapping_proposal_not_ready')
        ->and($proposalFor('business-missing-owner')?->reasons)->toContain('owner_mapping_proposal_missing')
        ->and($proposalFor('business-exact')?->proposed_action)->toBe(LegacyMappingProposalAction::LinkExactLegacyId)
        ->and($proposalFor('business-registration-collision')?->reasons)->toContain('potential_existing_business_collision')
        ->and(BusinessOwner::query()->count())->toBe($fixture['initial_owner_count'])
        ->and(Business::query()->count())->toBe($fixture['initial_business_count'])
        ->and(LegacyIdMapping::query()->count())->toBe(0);
});

test('a stable mapping plan retry is idempotent and refuses a changed registry snapshot', function () {
    ['batch' => $batch] = createRegistryPlanningBatch('registry-idempotency-staging-001');
    createRegistryPlanningRecord($batch, 'business_owners', 'business_owner', 'owner-stable', [
        'firstName' => 'Stable',
        'lastName' => 'Owner',
    ]);
    createRegistryPlanningRecord($batch, 'businesses', 'business', 'business-stable', [
        'ownerId' => 'owner-stable',
        'name' => 'Stable Business',
    ]);
    $batch->update(['source_record_count' => 2, 'staged_record_count' => 2]);
    $action = app(PlanLegacyRegistryMigration::class);

    $first = $action->handle($batch->fresh(), 'registry-stable-plan-001');
    $second = $action->handle($batch->fresh(), 'registry-stable-plan-001');

    expect($second->id)->toBe($first->id)
        ->and(LegacyMappingPlan::query()->count())->toBe(1)
        ->and(LegacyMappingProposal::query()->count())->toBe(2);

    BusinessOwner::factory()->create();

    expect(fn () => $action->handle($batch->fresh(), 'registry-stable-plan-001'))
        ->toThrow(RuntimeException::class, 'different registry snapshot');
});

test('registry planning requires both canonical registry datasets', function () {
    ['batch' => $batch] = createRegistryPlanningBatch('registry-incomplete-staging-001');
    createRegistryPlanningRecord($batch, 'business_owners', 'business_owner', 'owner-only', [
        'firstName' => 'Owner',
        'lastName' => 'Only',
    ]);

    expect(fn () => app(PlanLegacyRegistryMigration::class)->handle($batch->fresh(), 'registry-incomplete-plan-001'))
        ->toThrow(RuntimeException::class, 'has no staged [businesses] dataset');
});

test('the command emits a payload free mapping plan and requires exact stable identifiers', function () {
    Storage::fake('local');
    ['batch' => $batch] = createRegistryPlanningBatch('registry-command-staging-001');
    createRegistryPlanningRecord($batch, 'business_owners', 'business_owner', 'owner-command-secret', [
        'firstName' => 'Sensitive',
        'lastName' => 'Command Owner',
        'email' => 'sensitive.command@example.test',
    ]);
    createRegistryPlanningRecord($batch, 'businesses', 'business', 'business-command-secret', [
        'ownerId' => 'owner-command-secret',
        'name' => 'Sensitive Command Business',
    ]);
    $batch->update(['source_record_count' => 2, 'staged_record_count' => 2]);

    $this->artisan('legacy:plan-registry', ['batch' => $batch->id])
        ->expectsOutput('A stable --run-id is required.')
        ->assertFailed();

    $this->artisan('legacy:plan-registry', [
        'batch' => $batch->id,
        '--run-id' => 'registry-command-plan-001',
        '--json' => true,
    ])->assertSuccessful();

    $root = "legacy-migrations/LEGACY-REGISTRY-PLAN-TEST/{$batch->run_reference}/mapping-plans/registry-command-plan-001";
    Storage::disk('local')->assertExists($root.'/registry-plan.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $report = Storage::disk('local')->get($root.'/registry-plan.json');
    $decoded = json_decode($report, true, 512, JSON_THROW_ON_ERROR);

    expect($report)
        ->not->toContain('Sensitive')
        ->not->toContain('sensitive.command@example.test')
        ->not->toContain('owner-command-secret')
        ->not->toContain('business-command-secret')
        ->and($decoded['result'])->toMatchArray([
            'owner_proposal_count' => 1,
            'business_proposal_count' => 1,
            'accepted_id_mappings' => false,
            'domain_writes' => false,
        ])
        ->and($decoded['safety']['identity_similarity_is_authority'])->toBeFalse()
        ->and(LegacyIdMapping::query()->count())->toBe(0);
});
