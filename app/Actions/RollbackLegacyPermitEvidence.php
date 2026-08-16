<?php

namespace App\Actions;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\LegacyPermitClearanceMapping;
use App\Models\LegacyPermitEvidenceExecution;
use App\Models\PermitClearance;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RollbackLegacyPermitEvidence
{
    public function __construct(private ExecuteLegacyPermitEvidence $executor) {}

    public function handle(LegacyPermitEvidenceExecution $execution): LegacyPermitEvidenceExecution
    {
        $this->assertEnvironment();

        if ($execution->status === LegacyMappingExecutionStatus::RolledBack) {
            return $execution->load(['mappingPlan.importBatch.source', 'mappings']);
        }
        if ($execution->status !== LegacyMappingExecutionStatus::Completed) {
            throw new RuntimeException("Permit-evidence execution [{$execution->run_reference}] is not completed and cannot be rolled back.");
        }

        return DB::transaction(function () use ($execution): LegacyPermitEvidenceExecution {
            $lockedExecution = LegacyPermitEvidenceExecution::query()->lockForUpdate()->findOrFail($execution->id);
            $mappings = $lockedExecution->mappings()->with('permitClearance')->orderByDesc('id')->get();

            foreach ($mappings as $mapping) {
                $this->assertRollbackSafe($mapping);
            }

            foreach ($mappings as $mapping) {
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
                    'rollback_mapping_count' => $mappings->count(),
                    'rollback_deleted_created_targets' => $mappings
                        ->filter(fn (LegacyPermitClearanceMapping $mapping): bool => ($mapping->metadata['created_by_execution'] ?? false) === true)
                        ->count(),
                    'pre_existing_targets_deleted' => false,
                ],
            ]);

            return $lockedExecution->fresh(['mappingPlan.importBatch.source', 'mappings']) ?? $lockedExecution;
        }, 3);
    }

    private function assertRollbackSafe(LegacyPermitClearanceMapping $mapping): void
    {
        $target = $mapping->permitClearance;
        if (! $target instanceof PermitClearance) {
            throw new RuntimeException("Mapped permit clearance [{$mapping->permit_clearance_id}] no longer exists; rollback refused.");
        }
        if (($mapping->metadata['created_by_execution'] ?? false) !== true) {
            return;
        }

        $expectedHash = $mapping->metadata['target_snapshot_hash'] ?? null;
        if (! is_string($expectedHash) || ! hash_equals($expectedHash, $this->executor->targetSnapshotHash($target))) {
            throw new RuntimeException("Created permit clearance [{$target->id}] changed after migration; rollback refused.");
        }
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy permit-evidence rollback is currently restricted to local and testing environments.');
        }
    }
}
