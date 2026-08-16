<?php

use App\Actions\PlanLegacyPermitApplications;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalAction;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyApplicationMappingProposal;
use App\Models\LegacyIdMapping;
use App\Models\LegacyImportBatch;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** @return array{source: LegacySource, batch: LegacyImportBatch, owner: BusinessOwner, business: Business} */
function createApplicationPlanningBatch(string $suffix = '001'): array
{
    $source = LegacySource::factory()->create([
        'key' => 'LEGACY-APPLICATION-PLAN-'.$suffix,
        'baseline' => 'application-plan-baseline-'.$suffix,
        'archive_checksum' => hash('sha256', 'application-plan-archive-'.$suffix),
    ]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'application-plan-staging-'.$suffix,
        'status' => LegacyImportBatchStatus::Staged,
        'source_record_count' => 0,
        'staged_record_count' => 0,
        'exception_count' => 0,
        'mapping_count' => 2,
    ]);
    $owner = BusinessOwner::factory()->create(['legacy_source_id' => 'owner-'.$suffix]);
    $business = Business::factory()->for($owner, 'owner')->create(['legacy_source_id' => 'business-'.$suffix]);
    createAcceptedApplicationDependency($batch, 'business_owners', 'business_owner', 'owner-'.$suffix, $owner->id);
    createAcceptedApplicationDependency($batch, 'businesses', 'business', 'business-'.$suffix, $business->id);

    return compact('source', 'batch', 'owner', 'business');
}

function createAcceptedApplicationDependency(LegacyImportBatch $batch, string $dataset, string $entity, string $legacyId, int $targetId): LegacyIdMapping
{
    return LegacyIdMapping::query()->create([
        'legacy_source_id' => $batch->legacy_source_id,
        'legacy_import_batch_id' => $batch->id,
        'dataset_key' => $dataset,
        'entity_type' => $entity,
        'legacy_id' => $legacyId,
        'target_type' => $entity,
        'target_id' => $targetId,
        'status' => 'mapped',
        'mapping_basis' => 'test_accepted_mapping',
        'metadata' => ['fixture' => true],
    ]);
}

/** @param array<string, mixed> $overrides */
function createApplicationPlanningRecord(LegacyImportBatch $batch, string $legacyId, array $overrides = []): LegacyRecord
{
    $suffix = Str::after($batch->run_reference, 'application-plan-staging-');
    $payload = [
        '_id' => $legacyId,
        'businessOwnerId' => 'owner-'.$suffix,
        'businessId' => 'business-'.$suffix,
        'status' => 'Assessment',
        'permitApplicationType' => 'New',
        'applicationNumber' => 'BPA-2026-'.mb_strtoupper($legacyId),
        'submittedAt' => '2026-08-01T09:00:00+08:00',
        'linesOfBusiness' => [],
        ...$overrides,
    ];
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $record = LegacyRecord::query()->create([
        'legacy_import_batch_id' => $batch->id,
        'legacy_source_id' => $batch->legacy_source_id,
        'dataset_key' => 'applications',
        'entity_type' => 'application',
        'legacy_id' => $legacyId,
        'payload' => $payload,
        'payload_hash' => hash('sha256', $encoded),
        'status' => 'staged',
        'line_number' => $batch->records()->where('dataset_key', 'applications')->count() + 1,
    ]);
    $batch->increment('source_record_count');
    $batch->increment('staged_record_count');

    return $record;
}

test('a deterministic assessment application is ready without assigning official numbering or writing domain records', function () {
    $fixture = createApplicationPlanningBatch('ready');
    createApplicationPlanningRecord($fixture['batch'], 'application-ready');

    $plan = app(PlanLegacyPermitApplications::class)->handle($fixture['batch']->fresh(), 'application-plan-ready-001');
    $proposal = $plan->proposals->sole();

    expect($plan)
        ->status->toBe(LegacyMappingPlanStatus::Planned)
        ->proposal_count->toBe(1)
        ->ready_count->toBe(1)
        ->review_count->toBe(0)
        ->blocked_count->toBe(0)
        ->and($proposal->status)->toBe(LegacyMappingProposalStatus::Ready)
        ->and($proposal->proposed_action)->toBe(LegacyMappingProposalAction::Create)
        ->and($proposal->owner_mapping_id)->not->toBeNull()
        ->and($proposal->business_mapping_id)->not->toBeNull()
        ->and($proposal->metadata['official_application_number_projected'])->toBeFalse()
        ->and(PermitApplication::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(2);
});

test('draft release financial and line state remain visible for review rather than becoming executable behavior', function () {
    $fixture = createApplicationPlanningBatch('policy');
    $draft = createApplicationPlanningRecord($fixture['batch'], 'application-policy-draft', [
        'status' => 'Draft',
        'linesOfBusiness' => [['businessCategory' => 'Retail', 'capitalInvestment' => '100000']],
        'feeOverrides' => [['feeId' => 'fee-secret', 'overriddenAmount' => 1]],
    ]);
    $released = createApplicationPlanningRecord($fixture['batch'], 'application-policy-released', [
        'status' => 'Released',
        'releasedAt' => '2026-08-02T10:00:00+08:00',
    ]);

    $proposals = app(PlanLegacyPermitApplications::class)
        ->handle($fixture['batch']->fresh(), 'application-plan-policy-001')
        ->proposals
        ->keyBy('legacy_record_id');

    expect($proposals[$draft->id])
        ->status->toBe(LegacyMappingProposalStatus::ReviewRequired)
        ->proposed_action->toBe(LegacyMappingProposalAction::Review)
        ->and($proposals[$draft->id]->reasons)->toContain(
            'legacy_draft_submission_timestamp_conflict',
            'line_of_business_mapping_required',
            'financial_override_reconciliation_required',
        )
        ->and($proposals[$released->id]->status)->toBe(LegacyMappingProposalStatus::ReviewRequired)
        ->and($proposals[$released->id]->reasons)->toContain('legacy_release_authority_unresolved')
        ->and(PermitApplication::query()->count())->toBe(0);
});

test('missing mappings and mapped ownership disagreement block application proposals', function () {
    $fixture = createApplicationPlanningBatch('ownership');
    $otherOwner = BusinessOwner::factory()->create();
    $fixture['business']->update(['business_owner_id' => $otherOwner->id]);
    createApplicationPlanningRecord($fixture['batch'], 'application-ownership');
    createApplicationPlanningRecord($fixture['batch'], 'application-missing', [
        'businessOwnerId' => 'owner-ownership',
        'businessId' => 'business-not-mapped',
    ]);

    $proposals = app(PlanLegacyPermitApplications::class)
        ->handle($fixture['batch']->fresh(), 'application-plan-ownership-001')
        ->proposals
        ->keyBy(fn (LegacyApplicationMappingProposal $proposal): string => $proposal->metadata['legacy_id_sha256']);

    expect($proposals[hash('sha256', 'application-ownership')])
        ->status->toBe(LegacyMappingProposalStatus::Blocked)
        ->and($proposals[hash('sha256', 'application-ownership')]->reasons)->toContain('mapped_business_owner_mismatch')
        ->and($proposals[hash('sha256', 'application-missing')])
        ->status->toBe(LegacyMappingProposalStatus::Blocked)
        ->and($proposals[hash('sha256', 'application-missing')]->reasons)->toContain('accepted_business_mapping_missing');
});

test('an exact legacy application target is linkable only when its mapped business agrees', function () {
    $fixture = createApplicationPlanningBatch('exact');
    $record = createApplicationPlanningRecord($fixture['batch'], 'application-exact');
    $existing = PermitApplication::factory()->for($fixture['business'])->create([
        'legacy_source_id' => $record->legacy_id,
        'application_number' => null,
    ]);

    $proposal = app(PlanLegacyPermitApplications::class)
        ->handle($fixture['batch']->fresh(), 'application-plan-exact-001')
        ->proposals
        ->sole();

    expect($proposal)
        ->status->toBe(LegacyMappingProposalStatus::Ready)
        ->proposed_action->toBe(LegacyMappingProposalAction::LinkExactLegacyId)
        ->target_id->toBe($existing->id);
});

test('stable retries are idempotent and dependency snapshot drift is refused', function () {
    $fixture = createApplicationPlanningBatch('stable');
    createApplicationPlanningRecord($fixture['batch'], 'application-stable');
    $action = app(PlanLegacyPermitApplications::class);

    $first = $action->handle($fixture['batch']->fresh(), 'application-plan-stable-001');
    $second = $action->handle($fixture['batch']->fresh(), 'application-plan-stable-001');

    expect($second->id)->toBe($first->id)
        ->and(LegacyApplicationMappingPlan::query()->count())->toBe(1)
        ->and(LegacyApplicationMappingProposal::query()->count())->toBe(1);

    $fixture['business']->update(['name' => 'Changed After Planning']);

    expect(fn () => $action->handle($fixture['batch']->fresh(), 'application-plan-stable-001'))
        ->toThrow(RuntimeException::class, 'different registry mappings or application records');
});

test('the command requires exact identifiers and writes payload free evidence', function () {
    Storage::fake('local');
    $fixture = createApplicationPlanningBatch('command');
    createApplicationPlanningRecord($fixture['batch'], 'application-command-secret', [
        'applicationNumber' => 'SECRET-BPA-REFERENCE',
        'assessmentRemarks' => 'Sensitive assessment note',
    ]);

    $this->artisan('legacy:plan-applications', ['batch' => $fixture['batch']->id])
        ->expectsOutput('A stable --run-id is required.')
        ->assertFailed();

    $this->artisan('legacy:plan-applications', [
        'batch' => $fixture['batch']->id,
        '--run-id' => 'application-plan-command-001',
        '--json' => true,
    ])->assertSuccessful();

    $root = 'legacy-migrations/LEGACY-APPLICATION-PLAN-command/application-plan-staging-command/application-mapping-plans/application-plan-command-001';
    Storage::disk('local')->assertExists($root.'/application-plan.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $report = Storage::disk('local')->get($root.'/application-plan.json');
    $decoded = json_decode($report, true, 512, JSON_THROW_ON_ERROR);

    expect($report)
        ->not->toContain('application-command-secret')
        ->not->toContain('SECRET-BPA-REFERENCE')
        ->not->toContain('Sensitive assessment note')
        ->and($decoded['result'])->toMatchArray([
            'proposal_count' => 1,
            'ready_count' => 1,
            'accepted_id_mappings' => false,
            'permit_application_writes' => false,
        ])
        ->and($decoded['policy_boundaries']['official_application_number_authority'])->toBe('unresolved');
});
