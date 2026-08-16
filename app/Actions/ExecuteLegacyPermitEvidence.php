<?php

namespace App\Actions;

use App\Enums\LegacyClearanceTypeReconciliationStatus;
use App\Enums\LegacyDocumentObjectReconciliationStatus;
use App\Enums\LegacyMappingExecutionStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Enums\PermitClearanceStatus;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyPermitClearanceMapping;
use App\Models\LegacyPermitDocumentMapping;
use App\Models\LegacyPermitEvidenceExecution;
use App\Models\LegacyPermitEvidencePlan;
use App\Models\LegacyPermitEvidenceProposal;
use App\Models\PermitApplicationDocument;
use App\Models\PermitClearance;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ExecuteLegacyPermitEvidence
{
    public function __construct(
        private PlanLegacyPermitEvidence $planner,
        private LegacyDocumentObjectIntegrity $documentIntegrity,
    ) {}

    /** @param list<int> $proposalIds */
    public function handle(LegacyPermitEvidencePlan $plan, array $proposalIds, string $runReference): LegacyPermitEvidenceExecution
    {
        $this->assertEnvironment();
        $this->assertRunReference($runReference);
        $proposalIds = array_values(array_unique($proposalIds));
        sort($proposalIds);

        if ($proposalIds === []) {
            throw new RuntimeException('At least one exact permit-evidence proposal ID is required.');
        }

        $selectionHash = hash('sha256', json_encode($proposalIds, JSON_THROW_ON_ERROR));
        $existing = $plan->executions()->where('run_reference', $runReference)->first();

        if ($existing instanceof LegacyPermitEvidenceExecution) {
            if (! hash_equals($existing->selection_hash, $selectionHash)) {
                throw new RuntimeException("Permit-evidence execution run reference [{$runReference}] is already bound to a different proposal selection.");
            }
            if ($existing->status === LegacyMappingExecutionStatus::Completed) {
                return $existing->load(['mappingPlan.importBatch.source', 'mappings', 'documentMappings']);
            }
            if ($existing->status === LegacyMappingExecutionStatus::RolledBack) {
                throw new RuntimeException("Permit-evidence execution [{$runReference}] has already been rolled back and cannot execute again.");
            }

            throw new RuntimeException("Permit-evidence execution [{$runReference}] is not in a resumable state.");
        }

        $createdPaths = [];

        try {
            return DB::transaction(function () use ($plan, $proposalIds, $runReference, $selectionHash, &$createdPaths): LegacyPermitEvidenceExecution {
                $lockedPlan = LegacyPermitEvidencePlan::query()->lockForUpdate()->findOrFail($plan->id);

                if (! in_array($lockedPlan->status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
                    throw new RuntimeException("Permit-evidence plan [{$lockedPlan->id}] is not complete.");
                }

                $proposals = $lockedPlan->proposals()
                    ->with(['legacyRecord', 'clearanceReconciliation', 'documentReconciliation.applicationMapping'])
                    ->whereIn('id', $proposalIds)
                    ->get();

                if ($proposals->count() !== count($proposalIds)) {
                    throw new RuntimeException('Every selected proposal ID must belong to the exact permit-evidence plan.');
                }

                $this->assertExecutableSelection($proposals);

                if (! hash_equals($lockedPlan->dependency_snapshot_hash, $this->planner->dependencySnapshotHash($lockedPlan->importBatch))) {
                    throw new RuntimeException("Permit-evidence plan [{$lockedPlan->id}] no longer matches its dependency snapshot.");
                }

                $execution = $lockedPlan->executions()->create([
                    'run_reference' => $runReference,
                    'selection_hash' => $selectionHash,
                    'status' => LegacyMappingExecutionStatus::Executing,
                    'selected_count' => $proposals->count(),
                    'started_at' => now(),
                    'metadata' => [
                        'proposal_ids' => $proposalIds,
                        'supported_kinds' => ['clearance', 'business_supporting_document'],
                        'completed_clearances_created' => false,
                        'document_objects_copied' => $proposals->contains('kind', 'business_supporting_document'),
                        'legacy_document_status_authority_migrated' => false,
                        'documentary_sufficiency_asserted' => false,
                        'permit_artifacts_created' => false,
                        'issuance_release_or_legal_effect_asserted' => false,
                        'external_integrations' => false,
                        'notifications' => false,
                        'irreversible_actions' => false,
                    ],
                ]);
                $counts = ['created' => 0, 'linked' => 0, 'reused' => 0, 'mappings' => 0];

                foreach ($proposals->sortBy('id') as $proposal) {
                    $result = $proposal->kind === 'clearance'
                        ? $this->executeClearance($execution, $proposal)
                        : $this->executeDocument($execution, $proposal, $createdPaths);
                    $counts[$result]++;
                    if ($result !== 'reused') {
                        $counts['mappings']++;
                    }
                }

                $execution->update([
                    'status' => LegacyMappingExecutionStatus::Completed,
                    'created_count' => $counts['created'],
                    'linked_count' => $counts['linked'],
                    'reused_count' => $counts['reused'],
                    'mapping_count' => $counts['mappings'],
                    'completed_at' => now(),
                ]);

                return $execution->fresh(['mappingPlan.importBatch.source', 'mappings', 'documentMappings']) ?? $execution;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($createdPaths);

            throw $exception;
        }
    }

    /** @param Collection<int, LegacyPermitEvidenceProposal> $proposals */
    private function assertExecutableSelection(Collection $proposals): void
    {
        foreach ($proposals as $proposal) {
            if ($proposal->status !== LegacyMappingProposalStatus::Ready) {
                throw new RuntimeException("Permit-evidence proposal [{$proposal->id}] is not ready and cannot execute.");
            }
            if (! in_array($proposal->kind, ['clearance', 'business_supporting_document'], true)) {
                throw new RuntimeException("Permit-evidence proposal [{$proposal->id}] is authority-bearing or unsupported and cannot execute.");
            }
            if ($proposal->kind === 'clearance') {
                if (($proposal->metadata['completed'] ?? null) !== false) {
                    throw new RuntimeException("Permit-evidence proposal [{$proposal->id}] asserts completion authority and cannot execute.");
                }
                $this->validatedClearanceProjection($proposal);
                $this->clearanceApplicationMapping($proposal);
            } else {
                $this->validatedDocumentProjection($proposal);
            }
        }
    }

    private function executeClearance(LegacyPermitEvidenceExecution $execution, LegacyPermitEvidenceProposal $proposal): string
    {
        $projection = $this->validatedClearanceProjection($proposal);
        $applicationMapping = $this->clearanceApplicationMapping($proposal);
        $application = $applicationMapping->permitApplication()->firstOrFail();
        $record = $proposal->legacyRecord;
        $existingMapping = LegacyPermitClearanceMapping::query()
            ->where('legacy_source_id', $record->legacy_source_id)
            ->where('dataset_key', $record->dataset_key)
            ->where('legacy_id', $record->legacy_id)
            ->first();

        if ($existingMapping instanceof LegacyPermitClearanceMapping) {
            $target = $existingMapping->permitClearance()->first();
            if (! $target instanceof PermitClearance
                || $existingMapping->legacy_application_id_mapping_id !== $applicationMapping->id
                || $existingMapping->legacy_clearance_type_reconciliation_id !== $proposal->legacy_clearance_type_reconciliation_id
                || $existingMapping->legacy_record_id !== $record->id
                || $target->permit_application_id !== $application->id
                || $target->code !== $projection['clearance_code']) {
                throw new RuntimeException("Existing clearance mapping for proposal [{$proposal->id}] no longer matches its application and reconciled identity.");
            }

            return 'reused';
        }

        $target = $application->clearances()->where('code', $projection['clearance_code'])->first();
        $created = ! $target instanceof PermitClearance;

        if ($created) {
            $target = $application->clearances()->create([
                'code' => $projection['clearance_code'],
                'label' => $projection['clearance_label'],
                'status' => PermitClearanceStatus::Pending,
                'completed_by_id' => null,
                'completed_at' => null,
                'remarks' => null,
                'legacy_source_id' => $record->legacy_id,
                'source_snapshot' => [
                    'classification' => 'legacy_pending_clearance_assignment',
                    'assigned_at' => $projection['assigned_at'],
                    'completion_authority_asserted' => false,
                    'migration' => [
                        'schema_version' => 'bpls.legacy-permit-clearance-migration.v1',
                        'execution_id' => $execution->id,
                        'proposal_id' => $proposal->id,
                        'projection_hash' => $proposal->projection_hash,
                        'reconciliation_id' => $proposal->legacy_clearance_type_reconciliation_id,
                    ],
                ],
            ]);
        } elseif ($target->legacy_source_id !== $record->legacy_id
            || $target->status !== PermitClearanceStatus::Pending
            || $target->completed_by_id !== null
            || $target->completed_at !== null
            || $target->label !== $projection['clearance_label']) {
            throw new RuntimeException("Existing clearance identity [{$projection['clearance_code']}] conflicts with proposal [{$proposal->id}].");
        }

        LegacyPermitClearanceMapping::query()->create([
            'legacy_permit_evidence_execution_id' => $execution->id,
            'legacy_application_id_mapping_id' => $applicationMapping->id,
            'legacy_clearance_type_reconciliation_id' => $proposal->legacy_clearance_type_reconciliation_id,
            'legacy_source_id' => $record->legacy_source_id,
            'legacy_import_batch_id' => $record->legacy_import_batch_id,
            'legacy_record_id' => $record->id,
            'permit_clearance_id' => $target->id,
            'dataset_key' => $record->dataset_key,
            'legacy_id' => $record->legacy_id,
            'status' => 'mapped',
            'mapping_basis' => $created ? 'approved_pending_clearance_create' : 'exact_legacy_source_id',
            'metadata' => [
                'proposal_id' => $proposal->id,
                'created_by_execution' => $created,
                'projection_hash' => $proposal->projection_hash,
                'target_snapshot_hash' => $this->clearanceSnapshotHash($target),
                'completion_authority_asserted' => false,
            ],
        ]);

        return $created ? 'created' : 'linked';
    }

    /** @param list<string> $createdPaths */
    private function executeDocument(LegacyPermitEvidenceExecution $execution, LegacyPermitEvidenceProposal $proposal, array &$createdPaths): string
    {
        $projection = $this->validatedDocumentProjection($proposal);
        $reconciliation = $proposal->documentReconciliation;
        $applicationMapping = $reconciliation->applicationMapping;
        $application = $applicationMapping->permitApplication()->firstOrFail();
        $record = $proposal->legacyRecord;
        $existingMapping = LegacyPermitDocumentMapping::query()
            ->where('legacy_record_id', $record->id)
            ->where('item_key', $proposal->item_key)
            ->first();

        if ($existingMapping instanceof LegacyPermitDocumentMapping) {
            $target = $existingMapping->permitApplicationDocument()->first();
            if (! $target instanceof PermitApplicationDocument
                || $existingMapping->legacy_application_id_mapping_id !== $applicationMapping->id
                || $existingMapping->legacy_document_object_reconciliation_id !== $reconciliation->id
                || $target->permit_application_id !== $application->id) {
                throw new RuntimeException("Existing document mapping for proposal [{$proposal->id}] no longer matches its application and reconciled object.");
            }
            $this->documentIntegrity->assertDocumentObject($target, $reconciliation->object_checksum);

            return 'reused';
        }

        $inspection = $this->documentIntegrity->assertReconciledObject($reconciliation);
        $path = 'permit-applications/'.$application->id.'/documents/legacy-'.$record->legacy_source_id.'-'.$record->id.'-'
            .substr(hash('sha256', $proposal->item_key), 0, 24).'.'.$inspection['extension'];
        $disk = Storage::disk('local');
        $existed = $disk->exists($path);

        if (! $existed && ! $this->documentIntegrity->copyStoredObject($reconciliation->staged_disk, $reconciliation->staged_path, 'local', $path)) {
            throw new RuntimeException('Reconciled document object could not be copied to application storage.');
        }
        if (! $existed) {
            $createdPaths[] = $path;
        }

        $target = $application->documents()->create([
            'uploaded_by_id' => null,
            'label' => mb_substr($projection['document_type'], 0, 120),
            'original_name' => $projection['file_name'],
            'storage_disk' => 'local',
            'path' => $path,
            'mime_type' => $reconciliation->mime_type,
            'size_bytes' => $reconciliation->size_bytes,
            'remarks' => null,
            'source_snapshot' => [
                'classification' => 'legacy_business_supporting_evidence',
                'legacy_document_status_observed' => $proposal->metadata['legacy_document_status_observed'] ?? null,
                'legacy_document_status_authority_migrated' => false,
                'documentary_requirement_asserted' => false,
                'documentary_sufficiency_asserted' => false,
                'migration' => [
                    'schema_version' => 'bpls.legacy-permit-document-migration.v1',
                    'execution_id' => $execution->id,
                    'proposal_id' => $proposal->id,
                    'projection_hash' => $proposal->projection_hash,
                    'reconciliation_id' => $reconciliation->id,
                    'object_checksum' => $reconciliation->object_checksum,
                ],
            ],
            'uploaded_at' => $projection['uploaded_at'],
        ]);
        $this->documentIntegrity->assertDocumentObject($target, $reconciliation->object_checksum);

        LegacyPermitDocumentMapping::query()->create([
            'legacy_permit_evidence_execution_id' => $execution->id,
            'legacy_application_id_mapping_id' => $applicationMapping->id,
            'legacy_document_object_reconciliation_id' => $reconciliation->id,
            'legacy_source_id' => $record->legacy_source_id,
            'legacy_import_batch_id' => $record->legacy_import_batch_id,
            'legacy_record_id' => $record->id,
            'permit_application_document_id' => $target->id,
            'item_key' => $proposal->item_key,
            'mapping_basis' => 'accepted_application_scope_and_checksum',
            'metadata' => [
                'proposal_id' => $proposal->id,
                'created_by_execution' => true,
                'projection_hash' => $proposal->projection_hash,
                'target_snapshot_hash' => $this->documentSnapshotHash($target),
                'object_checksum' => $reconciliation->object_checksum,
                'legacy_document_status_authority_migrated' => false,
                'documentary_sufficiency_asserted' => false,
            ],
        ]);

        return 'created';
    }

    /** @return array{application_legacy_id: string, clearance_code: string, clearance_label: string, completed: false, assigned_at: string, completed_at: null} */
    private function validatedClearanceProjection(LegacyPermitEvidenceProposal $proposal): array
    {
        $record = $proposal->legacyRecord;
        $reconciliation = $proposal->clearanceReconciliation;

        if ($reconciliation === null
            || $reconciliation->status !== LegacyClearanceTypeReconciliationStatus::Accepted
            || $reconciliation->decision_authority === null
            || $reconciliation->evidence_reference === null
            || $reconciliation->target_code === null
            || $reconciliation->target_label === null) {
            throw new RuntimeException("Permit-evidence proposal [{$proposal->id}] no longer has an accepted clearance reconciliation.");
        }

        $applicationLegacyId = $this->string($record->payload['applicationId'] ?? null);
        $assignedAt = $this->date($record->payload['assignedAt'] ?? null);
        $projection = [
            'application_legacy_id' => $applicationLegacyId,
            'clearance_code' => $reconciliation->target_code,
            'clearance_label' => $reconciliation->target_label,
            'completed' => false,
            'assigned_at' => $assignedAt,
            'completed_at' => null,
        ];

        if (($record->payload['isCompleted'] ?? null) !== false
            || $applicationLegacyId === ''
            || $assignedAt === null
            || ! hash_equals($proposal->projection_hash, $this->hash($projection))) {
            throw new RuntimeException("Permit-evidence proposal [{$proposal->id}] no longer matches its staged pending-clearance projection.");
        }

        return $projection;
    }

    /** @return array{application_legacy_id: string, storage_reference_sha256: string, document_type: string, file_name: string, uploaded_at: string, object_checksum: string, size_bytes: int, mime_type: string} */
    private function validatedDocumentProjection(LegacyPermitEvidenceProposal $proposal): array
    {
        $record = $proposal->legacyRecord;
        $reconciliation = $proposal->documentReconciliation;
        $documents = $record->payload['documents'] ?? null;
        $index = filter_var(str_replace('document:', '', $proposal->item_key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $document = is_array($documents) && is_int($index) ? ($documents[$index] ?? null) : null;

        if (! is_array($document)
            || $reconciliation === null
            || $reconciliation->status !== LegacyDocumentObjectReconciliationStatus::Accepted
            || $reconciliation->decision_authority === ''
            || $reconciliation->evidence_reference === '') {
            throw new RuntimeException("Permit-evidence proposal [{$proposal->id}] no longer has an accepted document reconciliation.");
        }

        $storageReference = $this->string($document['storageId'] ?? null);
        $documentType = $this->string($document['documentType'] ?? null);
        $fileName = $this->string($document['fileName'] ?? null);
        $uploadedAt = $this->date($document['uploadedAt'] ?? null);
        $projection = [
            'application_legacy_id' => $reconciliation->applicationMapping->legacy_id,
            'storage_reference_sha256' => hash('sha256', $storageReference),
            'document_type' => $documentType,
            'file_name' => $fileName,
            'uploaded_at' => $uploadedAt,
            'object_checksum' => $reconciliation->object_checksum,
            'size_bytes' => $reconciliation->size_bytes,
            'mime_type' => $reconciliation->mime_type,
        ];

        if ($storageReference === '' || $documentType === '' || $fileName === '' || $uploadedAt === null
            || ! hash_equals($reconciliation->storage_reference_hash, hash('sha256', $storageReference))
            || ! hash_equals($reconciliation->document_type_hash, hash('sha256', $documentType))
            || ! hash_equals($reconciliation->original_name_hash, hash('sha256', $fileName))
            || ! hash_equals($proposal->projection_hash, $this->hash($projection))) {
            throw new RuntimeException("Permit-evidence proposal [{$proposal->id}] no longer matches its reconciled document projection.");
        }

        $this->documentIntegrity->assertReconciledObject($reconciliation);

        return $projection;
    }

    private function clearanceApplicationMapping(LegacyPermitEvidenceProposal $proposal): LegacyApplicationIdMapping
    {
        $legacyApplicationId = $this->string($proposal->legacyRecord->payload['applicationId'] ?? null);
        $mappings = LegacyApplicationIdMapping::query()
            ->where('legacy_source_id', $proposal->legacyRecord->legacy_source_id)
            ->where('legacy_import_batch_id', $proposal->legacyRecord->legacy_import_batch_id)
            ->whereIn('dataset_key', ['business_permit_applications', 'applications'])
            ->where('legacy_id', $legacyApplicationId)
            ->where('status', 'mapped')
            ->get();

        if ($mappings->count() !== 1 || ! $mappings->sole()->permitApplication()->exists()) {
            throw new RuntimeException("Permit-evidence proposal [{$proposal->id}] requires one exact accepted application mapping.");
        }

        return $mappings->sole();
    }

    public function clearanceSnapshotHash(PermitClearance $clearance): string
    {
        return $this->hash([
            'permit_application_id' => $clearance->permit_application_id,
            'completed_by_id' => $clearance->completed_by_id,
            'code' => $clearance->code,
            'label' => $clearance->label,
            'status' => $clearance->status->value,
            'completed_at' => $clearance->completed_at?->toIso8601String(),
            'remarks' => $clearance->remarks,
            'source_snapshot' => $clearance->source_snapshot,
            'legacy_source_id' => $clearance->legacy_source_id,
        ]);
    }

    public function targetSnapshotHash(PermitClearance $clearance): string
    {
        return $this->clearanceSnapshotHash($clearance);
    }

    public function documentSnapshotHash(PermitApplicationDocument $document): string
    {
        return $this->hash([
            'permit_application_id' => $document->permit_application_id,
            'uploaded_by_id' => $document->uploaded_by_id,
            'label' => $document->label,
            'original_name' => $document->original_name,
            'storage_disk' => $document->storage_disk,
            'path' => $document->path,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'remarks' => $document->remarks,
            'source_snapshot' => $document->source_snapshot,
            'uploaded_at' => $document->uploaded_at->toIso8601String(),
        ]);
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy permit-evidence execution is currently restricted to local and testing environments.');
        }
    }

    private function assertRunReference(string $runReference): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Permit-evidence execution run reference must be 3-100 safe characters.');
        }
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (Throwable) {
            return null;
        }
    }

    private function hash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
