<?php

namespace App\Actions;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\LegacyPermitClearanceMapping;
use App\Models\LegacyPermitDocumentMapping;
use App\Models\LegacyPermitEvidenceExecution;
use App\Models\PermitApplicationDocument;
use App\Models\PermitClearance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RollbackLegacyPermitEvidence
{
    public function __construct(
        private ExecuteLegacyPermitEvidence $executor,
        private LegacyDocumentObjectIntegrity $documentIntegrity,
    ) {}

    public function handle(LegacyPermitEvidenceExecution $execution): LegacyPermitEvidenceExecution
    {
        $this->assertEnvironment();

        if ($execution->status === LegacyMappingExecutionStatus::RolledBack) {
            $this->cleanDocumentObjects($execution);

            return $execution->refresh()->load(['mappingPlan.importBatch.source', 'mappings', 'documentMappings']);
        }
        if ($execution->status !== LegacyMappingExecutionStatus::Completed) {
            throw new RuntimeException("Permit-evidence execution [{$execution->run_reference}] is not completed and cannot be rolled back.");
        }

        $rolledBack = DB::transaction(function () use ($execution): LegacyPermitEvidenceExecution {
            $lockedExecution = LegacyPermitEvidenceExecution::query()->lockForUpdate()->findOrFail($execution->id);
            $clearanceMappings = $lockedExecution->mappings()->with('permitClearance')->orderByDesc('id')->get();
            $documentMappings = $lockedExecution->documentMappings()->with('permitApplicationDocument')->orderByDesc('id')->get();

            foreach ($clearanceMappings as $mapping) {
                $this->assertClearanceRollbackSafe($mapping);
            }
            foreach ($documentMappings as $mapping) {
                $this->assertDocumentRollbackSafe($mapping);
            }

            $documentPaths = [];
            foreach ($documentMappings as $mapping) {
                $target = $mapping->permitApplicationDocument;
                if ($target instanceof PermitApplicationDocument) {
                    $documentPaths[] = ['disk' => $target->storage_disk, 'path' => $target->path];
                }
                $mapping->delete();
                $target?->delete();
            }

            foreach ($clearanceMappings as $mapping) {
                $target = $mapping->permitClearance;
                $created = ($mapping->metadata['created_by_execution'] ?? false) === true;
                $mapping->delete();
                if ($created && $target instanceof PermitClearance) {
                    $target->delete();
                }
            }

            $lockedExecution->update([
                'status' => LegacyMappingExecutionStatus::RolledBack,
                'rolled_back_at' => now(),
                'metadata' => [
                    ...($lockedExecution->metadata ?? []),
                    'rollback_mapping_count' => $clearanceMappings->count() + $documentMappings->count(),
                    'rollback_deleted_created_clearances' => $clearanceMappings
                        ->filter(fn (LegacyPermitClearanceMapping $mapping): bool => ($mapping->metadata['created_by_execution'] ?? false) === true)
                        ->count(),
                    'rollback_deleted_created_documents' => $documentMappings->count(),
                    'rollback_deleted_created_targets' => $clearanceMappings
                        ->filter(fn (LegacyPermitClearanceMapping $mapping): bool => ($mapping->metadata['created_by_execution'] ?? false) === true)
                        ->count() + $documentMappings->count(),
                    'rollback_document_objects' => $documentPaths,
                    'rollback_document_object_cleanup_complete' => $documentPaths === [],
                    'pre_existing_targets_deleted' => false,
                ],
            ]);

            return $lockedExecution->fresh(['mappingPlan.importBatch.source', 'mappings', 'documentMappings']) ?? $lockedExecution;
        }, 3);

        $this->cleanDocumentObjects($rolledBack);

        return $rolledBack->refresh()->load(['mappingPlan.importBatch.source', 'mappings', 'documentMappings']);
    }

    private function assertClearanceRollbackSafe(LegacyPermitClearanceMapping $mapping): void
    {
        $target = $mapping->permitClearance;
        if (! $target instanceof PermitClearance) {
            throw new RuntimeException("Mapped permit clearance [{$mapping->permit_clearance_id}] no longer exists; rollback refused.");
        }
        if (($mapping->metadata['created_by_execution'] ?? false) !== true) {
            return;
        }

        $expectedHash = $mapping->metadata['target_snapshot_hash'] ?? null;
        if (! is_string($expectedHash) || ! hash_equals($expectedHash, $this->executor->clearanceSnapshotHash($target))) {
            throw new RuntimeException("Created permit clearance [{$target->id}] changed after migration; rollback refused.");
        }
    }

    private function assertDocumentRollbackSafe(LegacyPermitDocumentMapping $mapping): void
    {
        $target = $mapping->permitApplicationDocument;
        if (! $target instanceof PermitApplicationDocument) {
            throw new RuntimeException("Mapped permit document [{$mapping->permit_application_document_id}] no longer exists; rollback refused.");
        }

        $expectedHash = $mapping->metadata['target_snapshot_hash'] ?? null;
        $expectedChecksum = $mapping->metadata['object_checksum'] ?? null;
        if (! is_string($expectedHash)
            || ! is_string($expectedChecksum)
            || ! hash_equals($expectedHash, $this->executor->documentSnapshotHash($target))) {
            throw new RuntimeException("Created permit document [{$target->id}] changed after migration; rollback refused.");
        }

        $this->documentIntegrity->assertDocumentObject($target, $expectedChecksum);
    }

    private function cleanDocumentObjects(LegacyPermitEvidenceExecution $execution): void
    {
        $objects = $execution->metadata['rollback_document_objects'] ?? [];
        if (! is_array($objects) || $objects === []) {
            return;
        }

        foreach ($objects as $object) {
            if (! is_array($object) || ! is_string($object['disk'] ?? null) || ! is_string($object['path'] ?? null)) {
                throw new RuntimeException('Permit-evidence rollback contains invalid document object cleanup metadata.');
            }
            if (! Storage::disk($object['disk'])->delete($object['path'])) {
                throw new RuntimeException('A migrated permit document object could not be removed; retry the same rollback.');
            }
        }

        $execution->update([
            'metadata' => [
                ...($execution->metadata ?? []),
                'rollback_document_object_cleanup_complete' => true,
            ],
        ]);
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy permit-evidence rollback is currently restricted to local and testing environments.');
        }
    }
}
