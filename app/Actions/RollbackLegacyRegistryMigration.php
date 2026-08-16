<?php

namespace App\Actions;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyIdMapping;
use App\Models\LegacyMappingExecution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RollbackLegacyRegistryMigration
{
    public function __construct(private LegacyRegistryMappingProjector $projector) {}

    public function handle(LegacyMappingExecution $execution): LegacyMappingExecution
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy registry rollback is currently restricted to local and testing environments.');
        }

        if ($execution->status === LegacyMappingExecutionStatus::RolledBack) {
            return $execution->load(['mappingPlan.importBatch.source', 'mappings']);
        }

        if ($execution->status !== LegacyMappingExecutionStatus::Completed) {
            throw new RuntimeException("Registry execution [{$execution->run_reference}] is not completed and cannot be rolled back.");
        }

        return DB::transaction(function () use ($execution): LegacyMappingExecution {
            $lockedExecution = LegacyMappingExecution::query()->lockForUpdate()->findOrFail($execution->id);
            $mappings = $lockedExecution->mappings()->orderByDesc('id')->get();

            foreach ($mappings as $mapping) {
                $this->assertRollbackSafe($mapping);
            }

            foreach ($mappings->sortByDesc(fn (LegacyIdMapping $mapping): int => $mapping->target_type === 'business' ? 1 : 0) as $mapping) {
                if (($mapping->metadata['created_by_execution'] ?? false) === true) {
                    $this->resolveTarget($mapping)->delete();
                }

                $mapping->delete();
            }

            $lockedExecution->update([
                'status' => LegacyMappingExecutionStatus::RolledBack,
                'rolled_back_at' => now(),
                'metadata' => [
                    ...($lockedExecution->metadata ?? []),
                    'rollback_mapping_count' => $mappings->count(),
                    'rollback_deleted_created_targets' => $mappings
                        ->filter(fn (LegacyIdMapping $mapping): bool => ($mapping->metadata['created_by_execution'] ?? false) === true)
                        ->count(),
                    'pre_existing_targets_deleted' => false,
                ],
            ]);

            return $lockedExecution->fresh(['mappingPlan.importBatch.source', 'mappings']) ?? $lockedExecution;
        }, 3);
    }

    private function assertRollbackSafe(LegacyIdMapping $mapping): void
    {
        if (($mapping->metadata['created_by_execution'] ?? false) !== true) {
            return;
        }

        $target = $this->resolveTarget($mapping);
        $expectedHash = $mapping->metadata['target_snapshot_hash'] ?? null;

        if (! is_string($expectedHash) || ! hash_equals($expectedHash, $this->projector->targetSnapshotHash($target))) {
            throw new RuntimeException("Created target [{$mapping->target_type}:{$mapping->target_id}] changed after migration; rollback refused.");
        }

        if ($target instanceof Business && $target->permitApplications()->exists()) {
            throw new RuntimeException("Created business [{$target->id}] has permit applications; rollback refused.");
        }

        if ($target instanceof BusinessOwner) {
            $executionBusinessIds = $mapping->execution->mappings()
                ->where('target_type', 'business')
                ->whereJsonContains('metadata->created_by_execution', true)
                ->pluck('target_id');

            if ($target->businesses()->whereNotIn('id', $executionBusinessIds)->exists() || $target->users()->exists()) {
                throw new RuntimeException("Created business owner [{$target->id}] has non-migration dependencies; rollback refused.");
            }
        }
    }

    private function resolveTarget(LegacyIdMapping $mapping): BusinessOwner|Business
    {
        $target = match ($mapping->target_type) {
            'business_owner' => BusinessOwner::query()->find($mapping->target_id),
            'business' => Business::query()->find($mapping->target_id),
            default => null,
        };

        if (! $target instanceof Model) {
            throw new RuntimeException("Mapped target [{$mapping->target_type}:{$mapping->target_id}] no longer exists; rollback refused.");
        }

        return $target;
    }
}
