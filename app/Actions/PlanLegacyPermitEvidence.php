<?php

namespace App\Actions;

use App\Enums\LegacyClearanceTypeReconciliationStatus;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyClearanceTypeReconciliation;
use App\Models\LegacyImportBatch;
use App\Models\LegacyPermitEvidencePlan;
use App\Models\LegacyRecord;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PlanLegacyPermitEvidence
{
    public const PlannerVersion = 'bpls.permit-evidence-plan.v1';

    public function handle(LegacyImportBatch $batch, string $runReference): LegacyPermitEvidencePlan
    {
        $this->assertReady($batch, $runReference);
        $datasets = $this->datasetKeys($batch);
        $snapshot = $this->snapshotHash($batch);
        $plan = $this->resolvePlan($batch, $runReference, $snapshot, $datasets);

        if (in_array($plan->status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
            return $plan->refresh();
        }

        foreach ($batch->records()->where('dataset_key', $datasets['applications'])->orderBy('id')->cursor() as $application) {
            $this->planApplicationAuthorityClaim($plan, $batch, $application);
        }

        if ($datasets['clearances'] !== null) {
            foreach ($batch->records()->where('dataset_key', $datasets['clearances'])->orderBy('id')->cursor() as $clearance) {
                $this->planClearance($plan, $batch, $clearance, $datasets['applications']);
            }
        }

        if ($datasets['businesses'] !== null) {
            foreach ($batch->records()->where('dataset_key', $datasets['businesses'])->orderBy('id')->cursor() as $business) {
                $this->planBusinessDocuments($plan, $business);
            }
        }

        if ($datasets['permits'] !== null) {
            foreach ($batch->records()->where('dataset_key', $datasets['permits'])->orderBy('id')->cursor() as $permit) {
                $this->planPermitClaim($plan, $batch, $permit, $datasets['applications']);
            }
        }

        return $this->complete($plan);
    }

    private function planApplicationAuthorityClaim(LegacyPermitEvidencePlan $plan, LegacyImportBatch $batch, LegacyRecord $record): void
    {
        if ($this->string($record->payload['status'] ?? null) !== 'Released') {
            return;
        }

        $reasons = [
            'legacy_released_status_semantics_require_municipal_acceptance',
            'permit_issuance_authority_unresolved',
            'permit_release_authority_unresolved',
            'permit_legal_effect_not_asserted',
        ];
        $blocked = ! $this->applicationMappingReady($batch, $record);
        if ($blocked) {
            $reasons[] = 'application_mapping_not_ready';
        }

        $releasedAt = $this->date($record->payload['releasedAt'] ?? null);
        if (array_key_exists('releasedAt', $record->payload) && $releasedAt === null) {
            $blocked = true;
            $reasons[] = 'legacy_release_timestamp_invalid';
        }

        $this->proposal($plan, $record, 'legacy_released_status_claim', 'record', null, $blocked, $reasons, [
            'legacy_status' => 'Released',
            'released_at' => $releasedAt,
        ], [
            'legacy_status' => 'Released',
            'released_at' => $releasedAt,
            'issuance_authorized' => false,
            'release_authorized' => false,
            'legal_effect_asserted' => false,
        ]);
    }

    private function planClearance(LegacyPermitEvidencePlan $plan, LegacyImportBatch $batch, LegacyRecord $record, string $applicationDataset): void
    {
        $applicationId = $this->string($record->payload['applicationId'] ?? null);
        $clearanceTypeId = $this->string($record->payload['clearanceTypeId'] ?? null);
        $application = $this->sourceRecord($batch, $applicationDataset, $applicationId);
        $reconciliation = $this->clearanceReconciliation($batch, $clearanceTypeId);
        $reasons = [];
        $blocked = false;

        if (! $application instanceof LegacyRecord) {
            $blocked = true;
            $reasons[] = 'clearance_application_reference_unresolved';
        } elseif (! $this->applicationMappingReady($batch, $application)) {
            $blocked = true;
            $reasons[] = 'application_mapping_not_ready';
        }

        if (! $reconciliation instanceof LegacyClearanceTypeReconciliation) {
            $blocked = true;
            $reasons[] = 'accepted_clearance_type_reconciliation_missing';
        } elseif ($reconciliation->target_code === null || $reconciliation->target_label === null) {
            $blocked = true;
            $reasons[] = 'accepted_clearance_type_target_incomplete';
        }

        $completed = $record->payload['isCompleted'] ?? null;
        if (! is_bool($completed)) {
            $blocked = true;
            $reasons[] = 'clearance_completion_state_invalid';
        }

        $assignedAt = $this->date($record->payload['assignedAt'] ?? null);
        if ($assignedAt === null) {
            $blocked = true;
            $reasons[] = 'clearance_assignment_timestamp_invalid';
        }

        $completedAt = $this->date($record->payload['completedAt'] ?? null);
        $completedBy = $this->string($record->payload['completedBy'] ?? null);
        if ($completed === true) {
            if ($completedAt === null) {
                $blocked = true;
                $reasons[] = 'completed_clearance_timestamp_missing_or_invalid';
            }
            if ($completedBy === '') {
                $blocked = true;
                $reasons[] = 'completed_clearance_actor_missing';
            } else {
                $reasons[] = 'completed_clearance_actor_requires_reconciliation';
            }
        } elseif ($completedAt !== null || $completedBy !== '') {
            $blocked = true;
            $reasons[] = 'pending_clearance_contains_completion_evidence';
        }

        $projection = [
            'application_legacy_id' => $applicationId,
            'clearance_code' => $reconciliation?->target_code,
            'clearance_label' => $reconciliation?->target_label,
            'completed' => is_bool($completed) ? $completed : null,
            'assigned_at' => $assignedAt,
            'completed_at' => $completedAt,
        ];
        $this->proposal($plan, $record, 'clearance', 'record', $reconciliation, $blocked, $reasons, $projection, [
            'application_legacy_id_sha256' => $applicationId === '' ? null : hash('sha256', $applicationId),
            'clearance_type_legacy_id_sha256' => $clearanceTypeId === '' ? null : hash('sha256', $clearanceTypeId),
            'clearance_code' => $reconciliation?->target_code,
            'completed' => is_bool($completed) ? $completed : null,
            'assigned_at' => $assignedAt,
            'completed_at' => $completedAt,
            'completed_by_sha256' => $completedBy === '' ? null : hash('sha256', $completedBy),
            'domain_writes' => false,
        ]);
    }

    private function planBusinessDocuments(LegacyPermitEvidencePlan $plan, LegacyRecord $record): void
    {
        $documents = $record->payload['documents'] ?? null;
        if (! is_array($documents)) {
            return;
        }

        foreach (array_values($documents) as $index => $value) {
            $document = is_array($value) ? $value : [];
            $storageId = $this->string($document['storageId'] ?? null);
            $documentType = $this->string($document['documentType'] ?? null);
            $fileName = $this->string($document['fileName'] ?? null);
            $uploadedAt = $this->date($document['uploadedAt'] ?? null);
            $reasons = [
                'legacy_business_document_application_scope_unresolved',
                'document_object_checksum_and_content_inventory_required',
            ];
            $blocked = true;

            if ($storageId === '') {
                $reasons[] = 'document_storage_reference_missing';
            }
            if ($documentType === '') {
                $reasons[] = 'document_type_missing';
            }
            if ($fileName === '') {
                $reasons[] = 'document_filename_missing';
            }
            if ($uploadedAt === null) {
                $reasons[] = 'document_upload_timestamp_invalid';
            }

            $projection = [
                'storage_reference' => $storageId,
                'document_type' => $documentType,
                'file_name' => $fileName,
                'uploaded_at' => $uploadedAt,
            ];
            $this->proposal($plan, $record, 'business_supporting_document', "document:{$index}", null, $blocked, $reasons, $projection, [
                'storage_reference_sha256' => $storageId === '' ? null : hash('sha256', $storageId),
                'document_type_sha256' => $documentType === '' ? null : hash('sha256', $documentType),
                'file_name_sha256' => $fileName === '' ? null : hash('sha256', $fileName),
                'uploaded_at' => $uploadedAt,
                'object_copied' => false,
                'domain_writes' => false,
            ]);
        }
    }

    private function planPermitClaim(LegacyPermitEvidencePlan $plan, LegacyImportBatch $batch, LegacyRecord $record, string $applicationDataset): void
    {
        $applicationId = $this->string($record->payload['applicationId'] ?? null);
        $application = $this->sourceRecord($batch, $applicationDataset, $applicationId);
        $permitNumber = $this->string($record->payload['permitNumber'] ?? null);
        $issuedBy = $this->string($record->payload['issuedBy'] ?? null);
        $issuedAt = $this->date($record->payload['issuedAt'] ?? null);
        $releasedAt = $this->date($record->payload['dateReleased'] ?? null);
        $expiryDate = $this->date($record->payload['expiryDate'] ?? null);
        $reasons = [
            'legacy_permit_number_authority_unresolved',
            'permit_issuance_authority_unresolved',
            'permit_release_authority_unresolved',
            'permit_expiry_and_legal_effect_policy_unresolved',
            'official_signatory_authority_unresolved',
            'qr_verification_semantics_unresolved',
        ];
        $blocked = false;

        if (! $application instanceof LegacyRecord) {
            $blocked = true;
            $reasons[] = 'permit_application_reference_unresolved';
        } elseif (! $this->applicationMappingReady($batch, $application)) {
            $blocked = true;
            $reasons[] = 'application_mapping_not_ready';
        }
        if ($permitNumber === '') {
            $blocked = true;
            $reasons[] = 'legacy_permit_number_missing';
        }
        if ($issuedBy === '') {
            $blocked = true;
            $reasons[] = 'legacy_permit_issuer_missing';
        }
        foreach (['issued_at' => $issuedAt, 'released_at' => $releasedAt, 'expiry_date' => $expiryDate] as $field => $date) {
            if ($date === null) {
                $blocked = true;
                $reasons[] = "legacy_permit_{$field}_invalid";
            }
        }

        $projection = [
            'application_legacy_id' => $applicationId,
            'permit_number' => $permitNumber,
            'issued_by' => $issuedBy,
            'issued_at' => $issuedAt,
            'released_at' => $releasedAt,
            'expiry_date' => $expiryDate,
            'status' => $this->string($record->payload['status'] ?? null),
        ];
        $this->proposal($plan, $record, 'permit_authority_claim', 'record', null, $blocked, $reasons, $projection, [
            'application_legacy_id_sha256' => $applicationId === '' ? null : hash('sha256', $applicationId),
            'permit_number_sha256' => $permitNumber === '' ? null : hash('sha256', $permitNumber),
            'issued_by_sha256' => $issuedBy === '' ? null : hash('sha256', $issuedBy),
            'issued_at' => $issuedAt,
            'released_at' => $releasedAt,
            'expiry_date' => $expiryDate,
            'legacy_status' => $projection['status'],
            'artifact_migration_authorized' => false,
            'issuance_authorized' => false,
            'release_authorized' => false,
            'legal_effect_asserted' => false,
            'domain_writes' => false,
        ]);
    }

    /** @param list<string> $reasons
     * @param  array<string, mixed>  $projection
     * @param  array<string, mixed>  $metadata
     */
    private function proposal(LegacyPermitEvidencePlan $plan, LegacyRecord $record, string $kind, string $itemKey, ?LegacyClearanceTypeReconciliation $reconciliation, bool $blocked, array $reasons, array $projection, array $metadata): void
    {
        $status = $blocked
            ? LegacyMappingProposalStatus::Blocked
            : ($reasons === [] ? LegacyMappingProposalStatus::Ready : LegacyMappingProposalStatus::ReviewRequired);

        $plan->proposals()->updateOrCreate(
            ['legacy_record_id' => $record->id, 'kind' => $kind, 'item_key' => $itemKey],
            [
                'legacy_clearance_type_reconciliation_id' => $reconciliation?->id,
                'source_dataset' => $record->dataset_key,
                'status' => $status,
                'projection_hash' => $this->hash($projection),
                'reasons' => array_values(array_unique($reasons)),
                'metadata' => $metadata,
            ],
        );
    }

    /** @param array<string, string|null> $datasets */
    private function resolvePlan(LegacyImportBatch $batch, string $runReference, string $snapshot, array $datasets): LegacyPermitEvidencePlan
    {
        return DB::transaction(function () use ($batch, $runReference, $snapshot, $datasets): LegacyPermitEvidencePlan {
            $plan = $batch->permitEvidencePlans()->where('run_reference', $runReference)->lockForUpdate()->first();
            if ($plan instanceof LegacyPermitEvidencePlan) {
                if (! hash_equals($plan->dependency_snapshot_hash, $snapshot) || $plan->planner_version !== self::PlannerVersion) {
                    throw new RuntimeException("Permit evidence plan run reference [{$runReference}] is bound to different source, reconciliation, or planner evidence.");
                }

                return $plan;
            }

            return $batch->permitEvidencePlans()->create([
                'run_reference' => $runReference,
                'planner_version' => self::PlannerVersion,
                'dependency_snapshot_hash' => $snapshot,
                'status' => LegacyMappingPlanStatus::Planning,
                'started_at' => now(),
                'metadata' => [
                    'datasets' => $datasets,
                    'authority_claims_are_evidence_only' => true,
                    'document_objects_copied' => false,
                    'execution_authorized' => false,
                    'domain_writes' => false,
                ],
            ]);
        });
    }

    private function complete(LegacyPermitEvidencePlan $plan): LegacyPermitEvidencePlan
    {
        $counts = $plan->proposals()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $blocked = (int) ($counts[LegacyMappingProposalStatus::Blocked->value] ?? 0);
        $review = (int) ($counts[LegacyMappingProposalStatus::ReviewRequired->value] ?? 0);

        $plan->update([
            'status' => $blocked > 0 ? LegacyMappingPlanStatus::PlannedWithExceptions : LegacyMappingPlanStatus::Planned,
            'proposal_count' => (int) $counts->sum(),
            'ready_count' => (int) ($counts[LegacyMappingProposalStatus::Ready->value] ?? 0),
            'review_count' => $review,
            'blocked_count' => $blocked,
            'completed_at' => now(),
        ]);

        return $plan->refresh();
    }

    private function applicationMappingReady(LegacyImportBatch $batch, LegacyRecord $application): bool
    {
        $plan = LegacyApplicationMappingPlan::query()
            ->whereBelongsTo($batch, 'importBatch')
            ->whereIn('status', [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions])
            ->latest('id')
            ->first();

        return $plan instanceof LegacyApplicationMappingPlan
            && $plan->proposals()->where('legacy_record_id', $application->id)->where('status', LegacyMappingProposalStatus::Ready)->exists();
    }

    private function clearanceReconciliation(LegacyImportBatch $batch, string $legacyId): ?LegacyClearanceTypeReconciliation
    {
        if ($legacyId === '') {
            return null;
        }

        return LegacyClearanceTypeReconciliation::query()
            ->where('legacy_source_id', $batch->legacy_source_id)
            ->where('source_dataset', 'clearance_types')
            ->where('source_legacy_id', $legacyId)
            ->where('status', LegacyClearanceTypeReconciliationStatus::Accepted)
            ->whereNotNull('decision_authority')
            ->whereNotNull('evidence_reference')
            ->first();
    }

    private function sourceRecord(LegacyImportBatch $batch, string $dataset, string $legacyId): ?LegacyRecord
    {
        if ($legacyId === '') {
            return null;
        }

        return $batch->records()->where('dataset_key', $dataset)->where('legacy_id', $legacyId)->first();
    }

    /** @return array{applications: string, clearances: string|null, businesses: string|null, permits: string|null} */
    private function datasetKeys(LegacyImportBatch $batch): array
    {
        return [
            'applications' => $this->datasetKey($batch, ['business_permit_applications', 'applications'], true),
            'clearances' => $this->datasetKey($batch, ['permit_clearances'], false),
            'businesses' => $this->datasetKey($batch, ['businesses'], false),
            'permits' => $this->datasetKey($batch, ['permits'], false),
        ];
    }

    /** @param list<string> $candidates */
    private function datasetKey(LegacyImportBatch $batch, array $candidates, bool $required): ?string
    {
        $available = collect($candidates)->filter(fn (string $key): bool => $batch->records()->where('dataset_key', $key)->exists())->values();
        if ($available->count() > 1) {
            throw new RuntimeException('Multiple aliases for one permit-evidence dataset are staged.');
        }
        if ($required && $available->isEmpty()) {
            throw new RuntimeException('A staged permit application dataset is required.');
        }

        return $available->first();
    }

    private function snapshotHash(LegacyImportBatch $batch): string
    {
        $parts = [['batch', $batch->id, $batch->manifest_checksum]];
        foreach ($batch->records()->select(['id', 'dataset_key', 'payload_hash'])->orderBy('id')->cursor() as $record) {
            $parts[] = ['record', $record->id, $record->dataset_key, $record->payload_hash];
        }
        foreach (LegacyClearanceTypeReconciliation::query()->where('legacy_source_id', $batch->legacy_source_id)->orderBy('id')->get() as $reconciliation) {
            $parts[] = ['clearance', $reconciliation->id, $reconciliation->updated_at?->toIso8601String(), $reconciliation->status->value, $reconciliation->target_code];
        }
        foreach ($batch->applicationMappingPlans()->with('proposals:id,legacy_application_mapping_plan_id,legacy_record_id,status,projection_hash')->orderBy('id')->get() as $plan) {
            $parts[] = ['application_plan', $plan->id, $plan->status->value, $plan->dependency_snapshot_hash, $plan->proposals->toArray()];
        }

        return $this->hash($parts);
    }

    private function assertReady(LegacyImportBatch $batch, string $runReference): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy permit evidence planning is restricted to local and testing environments.');
        }
        if (! in_array($batch->status, [LegacyImportBatchStatus::Staged, LegacyImportBatchStatus::StagedWithExceptions], true)) {
            throw new RuntimeException('Legacy import batch must finish staging before permit evidence planning.');
        }
        if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{2,99}\z/', $runReference) !== 1) {
            throw new RuntimeException('Run reference must contain 3-100 safe characters.');
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
        } catch (\Throwable) {
            return null;
        }
    }

    private function hash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
