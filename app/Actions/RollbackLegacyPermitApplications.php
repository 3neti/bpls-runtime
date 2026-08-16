<?php

namespace App\Actions;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyApplicationMappingExecution;
use App\Models\PermitApplication;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RollbackLegacyPermitApplications
{
    public function __construct(private LegacyPermitApplicationProjector $projector) {}

    public function handle(LegacyApplicationMappingExecution $execution): LegacyApplicationMappingExecution
    {
        $this->assertEnvironment();

        if ($execution->status === LegacyMappingExecutionStatus::RolledBack) {
            return $execution->load(['mappingPlan.importBatch.source', 'mappings']);
        }

        if ($execution->status !== LegacyMappingExecutionStatus::Completed) {
            throw new RuntimeException("Application execution [{$execution->run_reference}] is not completed and cannot be rolled back.");
        }

        return DB::transaction(function () use ($execution): LegacyApplicationMappingExecution {
            $lockedExecution = LegacyApplicationMappingExecution::query()->lockForUpdate()->findOrFail($execution->id);
            $mappings = $lockedExecution->mappings()->with('permitApplication')->orderByDesc('id')->get();

            foreach ($mappings as $mapping) {
                $this->assertRollbackSafe($mapping);
            }

            foreach ($mappings as $mapping) {
                $target = $mapping->permitApplication;
                $created = ($mapping->metadata['created_by_execution'] ?? false) === true;
                $mapping->delete();

                if ($created && $target instanceof PermitApplication) {
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
                        ->filter(fn (LegacyApplicationIdMapping $mapping): bool => ($mapping->metadata['created_by_execution'] ?? false) === true)
                        ->count(),
                    'pre_existing_targets_deleted' => false,
                ],
            ]);

            return $lockedExecution->fresh(['mappingPlan.importBatch.source', 'mappings']) ?? $lockedExecution;
        }, 3);
    }

    private function assertRollbackSafe(LegacyApplicationIdMapping $mapping): void
    {
        $target = $mapping->permitApplication;

        if (! $target instanceof PermitApplication) {
            throw new RuntimeException("Mapped permit application [{$mapping->permit_application_id}] no longer exists; rollback refused.");
        }

        if (($mapping->metadata['created_by_execution'] ?? false) !== true) {
            return;
        }

        $expectedHash = $mapping->metadata['target_snapshot_hash'] ?? null;

        if (! is_string($expectedHash) || ! hash_equals($expectedHash, $this->projector->targetSnapshotHash($target))) {
            throw new RuntimeException("Created permit application [{$target->id}] changed after migration; rollback refused.");
        }

        $hasDependencies = $target->lines()->exists()
            || $target->assessments()->exists()
            || $target->paymentSchedules()->exists()
            || $target->treasuryCollections()->exists()
            || $target->clearances()->exists()
            || $target->documents()->exists()
            || Receipt::query()->whereBelongsTo($target, 'permitApplication')->exists();

        if ($hasDependencies) {
            throw new RuntimeException("Created permit application [{$target->id}] has downstream records; rollback refused.");
        }
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy permit application rollback is currently restricted to local and testing environments.');
        }
    }
}
