<?php

use App\Actions\PlanLegacyPermitEvidence;
use App\Enums\LegacyClearanceTypeReconciliationStatus;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyApplicationMappingProposal;
use App\Models\LegacyClearanceTypeReconciliation;
use App\Models\LegacyImportBatch;
use App\Models\LegacyPermitEvidencePlan;
use App\Models\LegacyPermitEvidenceProposal;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\PermitApplicationDocument;
use App\Models\PermitClearance;
use Illuminate\Support\Facades\Storage;

/** @return array{source: LegacySource, batch: LegacyImportBatch} */
function permitEvidenceBatch(string $suffix): array
{
    $source = LegacySource::factory()->create(['key' => 'LEGACY-PERMIT-EVIDENCE-'.$suffix]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'permit-evidence-staging-'.$suffix,
        'status' => LegacyImportBatchStatus::Staged,
    ]);

    return compact('source', 'batch');
}

/** @param array<string, mixed> $payload */
function permitEvidenceRecord(LegacyImportBatch $batch, string $dataset, string $legacyId, array $payload): LegacyRecord
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

function readyPermitEvidenceApplication(LegacyImportBatch $batch, LegacyRecord $application): void
{
    $plan = LegacyApplicationMappingPlan::factory()->for($batch, 'importBatch')->create([
        'run_reference' => 'application-plan-'.$application->legacy_id,
        'status' => LegacyMappingPlanStatus::Planned,
        'proposal_count' => 1,
        'ready_count' => 1,
    ]);
    LegacyApplicationMappingProposal::factory()->for($plan, 'mappingPlan')->for($application, 'legacyRecord')->create([
        'status' => LegacyMappingProposalStatus::Ready,
    ]);
}

function acceptedClearanceIdentity(LegacySource $source, string $legacyId): LegacyClearanceTypeReconciliation
{
    return LegacyClearanceTypeReconciliation::query()->create([
        'legacy_source_id' => $source->id,
        'source_dataset' => 'clearance_types',
        'source_legacy_id' => $legacyId,
        'target_code' => 'sanitary',
        'target_label' => 'Sanitary Clearance',
        'status' => LegacyClearanceTypeReconciliationStatus::Accepted,
        'decision_authority' => 'Municipal reconciliation fixture',
        'evidence_reference' => 'TEST-CLEARANCE-RECONCILIATION',
        'decided_at' => now(),
    ]);
}

test('accepted clearance identity produces structurally ready pending clearance evidence without domain writes', function () {
    $fixture = permitEvidenceBatch('ready');
    $application = permitEvidenceRecord($fixture['batch'], 'business_permit_applications', 'application-ready', ['status' => 'Pending Payment']);
    readyPermitEvidenceApplication($fixture['batch'], $application);
    $reconciliation = acceptedClearanceIdentity($fixture['source'], 'clearance-type-sanitary');
    permitEvidenceRecord($fixture['batch'], 'permit_clearances', 'clearance-ready', [
        'applicationId' => $application->legacy_id,
        'clearanceTypeId' => 'clearance-type-sanitary',
        'clearanceName' => 'Name must not establish identity',
        'clearanceShortName' => 'Sanitary',
        'certificateName' => 'Sanitary Permit',
        'isCompleted' => false,
        'assignedAt' => '2026-08-16T09:00:00+08:00',
    ]);

    $plan = app(PlanLegacyPermitEvidence::class)->handle($fixture['batch'], 'permit-evidence-ready-001');
    $proposal = $plan->proposals->sole();

    expect($plan->status)->toBe(LegacyMappingPlanStatus::Planned)
        ->and($proposal->status)->toBe(LegacyMappingProposalStatus::Ready)
        ->and($proposal->legacy_clearance_type_reconciliation_id)->toBe($reconciliation->id)
        ->and($proposal->metadata['clearance_code'])->toBe('sanitary')
        ->and($proposal->metadata['domain_writes'])->toBeFalse()
        ->and(PermitClearance::query()->count())->toBe(0)
        ->and(PermitApplicationDocument::query()->count())->toBe(0);
});

test('clearance labels never establish identity and completed actor evidence requires reconciliation', function () {
    $fixture = permitEvidenceBatch('identity');
    $application = permitEvidenceRecord($fixture['batch'], 'business_permit_applications', 'application-identity', ['status' => 'Released']);
    readyPermitEvidenceApplication($fixture['batch'], $application);
    permitEvidenceRecord($fixture['batch'], 'permit_clearances', 'clearance-identity', [
        'applicationId' => $application->legacy_id,
        'clearanceTypeId' => 'unknown-clearance-type',
        'clearanceName' => 'Sanitary Clearance',
        'clearanceShortName' => 'Sanitary',
        'certificateName' => 'Sanitary Permit',
        'isCompleted' => true,
        'completedAt' => '2026-08-16T10:00:00+08:00',
        'completedBy' => 'legacy-user-sensitive',
        'assignedAt' => '2026-08-16T09:00:00+08:00',
    ]);

    $plan = app(PlanLegacyPermitEvidence::class)->handle($fixture['batch'], 'permit-evidence-identity');
    $clearance = $plan->proposals->firstWhere('kind', 'clearance');
    $released = $plan->proposals->firstWhere('kind', 'legacy_released_status_claim');

    expect($clearance?->status)->toBe(LegacyMappingProposalStatus::Blocked)
        ->and($clearance?->legacy_clearance_type_reconciliation_id)->toBeNull()
        ->and($clearance?->reasons)->toContain('accepted_clearance_type_reconciliation_missing', 'completed_clearance_actor_requires_reconciliation')
        ->and($clearance?->metadata['completed_by_sha256'])->toBe(hash('sha256', 'legacy-user-sensitive'))
        ->and($released?->status)->toBe(LegacyMappingProposalStatus::ReviewRequired)
        ->and($released?->metadata['release_authorized'])->toBeFalse();
});

test('legacy business documents are inventory evidence and never copied or scoped to an application by inference', function () {
    $fixture = permitEvidenceBatch('documents');
    permitEvidenceRecord($fixture['batch'], 'business_permit_applications', 'application-documents', ['status' => 'Draft']);
    permitEvidenceRecord($fixture['batch'], 'businesses', 'business-documents', [
        'documents' => [[
            'storageId' => 'convex-storage-sensitive-id',
            'documentType' => 'DTI Certificate',
            'fileName' => 'sensitive-owner-file.pdf',
            'uploadedAt' => '2026-08-16T08:00:00+08:00',
        ]],
    ]);

    $proposal = app(PlanLegacyPermitEvidence::class)
        ->handle($fixture['batch'], 'permit-evidence-documents')
        ->proposals
        ->sole();

    expect($proposal->kind)->toBe('business_supporting_document')
        ->and($proposal->status)->toBe(LegacyMappingProposalStatus::Blocked)
        ->and($proposal->reasons)->toContain('legacy_business_document_application_scope_unresolved', 'document_object_checksum_and_content_inventory_required')
        ->and($proposal->metadata['storage_reference_sha256'])->toBe(hash('sha256', 'convex-storage-sensitive-id'))
        ->and($proposal->metadata['object_copied'])->toBeFalse()
        ->and($proposal->metadata)->not->toContain('sensitive-owner-file.pdf')
        ->and(PermitApplicationDocument::query()->count())->toBe(0);
});

test('legacy permit records remain authority claims even when structurally complete', function () {
    $fixture = permitEvidenceBatch('permit');
    $application = permitEvidenceRecord($fixture['batch'], 'business_permit_applications', 'application-permit', ['status' => 'Released']);
    readyPermitEvidenceApplication($fixture['batch'], $application);
    permitEvidenceRecord($fixture['batch'], 'permits', 'permit-sensitive', [
        'applicationId' => $application->legacy_id,
        'permitNumber' => 'MP-SECRET-0001',
        'issuedBy' => 'legacy-issuer-sensitive',
        'issuedAt' => '2026-08-16T11:00:00+08:00',
        'dateReleased' => '2026-08-16T11:00:00+08:00',
        'expiryDate' => '2026-12-31T23:59:59+08:00',
        'status' => 'Active',
    ]);

    $permit = app(PlanLegacyPermitEvidence::class)
        ->handle($fixture['batch'], 'permit-evidence-permit')
        ->proposals
        ->firstWhere('kind', 'permit_authority_claim');

    expect($permit?->status)->toBe(LegacyMappingProposalStatus::ReviewRequired)
        ->and($permit?->reasons)->toContain(
            'legacy_permit_number_authority_unresolved',
            'permit_issuance_authority_unresolved',
            'permit_release_authority_unresolved',
            'official_signatory_authority_unresolved',
            'qr_verification_semantics_unresolved',
        )
        ->and($permit?->metadata['permit_number_sha256'])->toBe(hash('sha256', 'MP-SECRET-0001'))
        ->and($permit?->metadata['artifact_migration_authorized'])->toBeFalse()
        ->and($permit?->metadata['issuance_authorized'])->toBeFalse()
        ->and($permit?->metadata['legal_effect_asserted'])->toBeFalse();
});

test('permit evidence planning is idempotent and rejects dependency drift for a stable run reference', function () {
    $fixture = permitEvidenceBatch('stable');
    $application = permitEvidenceRecord($fixture['batch'], 'business_permit_applications', 'application-stable', ['status' => 'Released']);
    readyPermitEvidenceApplication($fixture['batch'], $application);
    $action = app(PlanLegacyPermitEvidence::class);

    $first = $action->handle($fixture['batch'], 'permit-evidence-stable');
    $second = $action->handle($fixture['batch'], 'permit-evidence-stable');

    expect($second->id)->toBe($first->id)
        ->and(LegacyPermitEvidencePlan::query()->count())->toBe(1)
        ->and(LegacyPermitEvidenceProposal::query()->count())->toBe(1);

    $application->update(['payload_hash' => hash('sha256', 'changed-evidence')]);
    expect(fn () => $action->handle($fixture['batch'], 'permit-evidence-stable'))
        ->toThrow(RuntimeException::class, 'different source, reconciliation, or planner evidence');
});

test('permit evidence command writes redacted portable review artifacts', function () {
    Storage::fake('local');
    $fixture = permitEvidenceBatch('command');
    $fixture['batch']->update(['status' => LegacyImportBatchStatus::StagedWithExceptions]);
    permitEvidenceRecord($fixture['batch'], 'business_permit_applications', 'application-command', ['status' => 'Draft']);
    permitEvidenceRecord($fixture['batch'], 'businesses', 'business-command', [
        'documents' => [[
            'storageId' => 'secret-storage-reference',
            'documentType' => 'SEC Certificate',
            'fileName' => 'secret-company-file.pdf',
            'uploadedAt' => '2026-08-16T08:00:00+08:00',
        ]],
    ]);

    $this->artisan('legacy:plan-permit-evidence', [
        'batch' => $fixture['batch']->id,
        '--run-id' => 'permit-evidence-command',
        '--json' => true,
    ])->assertSuccessful();

    $root = "legacy-migrations/{$fixture['source']->key}/{$fixture['batch']->run_reference}/permit-evidence-plans/permit-evidence-command";
    Storage::disk('local')->assertExists($root.'/permit-evidence-plan.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $report = Storage::disk('local')->get($root.'/permit-evidence-plan.json');

    expect($report)->not->toContain('secret-storage-reference', 'secret-company-file.pdf')
        ->and(json_decode($report, true, flags: JSON_THROW_ON_ERROR)['safety'])->toMatchArray([
            'remote_object_access' => false,
            'permit_artifact_generation' => false,
            'issuance_authorized' => false,
            'release_authorized' => false,
            'legal_effect_asserted' => false,
            'domain_writes' => false,
        ]);
});
