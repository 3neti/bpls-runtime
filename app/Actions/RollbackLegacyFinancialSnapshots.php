<?php

namespace App\Actions;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\Assessment;
use App\Models\LegacyFinancialMappingExecution;
use App\Models\LegacyFinancialSnapshotMapping;
use App\Models\PaymentSchedule;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RollbackLegacyFinancialSnapshots
{
    public function __construct(private LegacyFinancialSnapshotProjector $projector) {}

    public function handle(LegacyFinancialMappingExecution $execution): LegacyFinancialMappingExecution
    {
        $this->assertEnvironment();

        if ($execution->status === LegacyMappingExecutionStatus::RolledBack) {
            return $execution->load(['mappingPlan.importBatch.source', 'mappings']);
        }
        if ($execution->status !== LegacyMappingExecutionStatus::Completed) {
            throw new RuntimeException("Financial execution [{$execution->run_reference}] is not completed and cannot be rolled back.");
        }

        return DB::transaction(function () use ($execution): LegacyFinancialMappingExecution {
            $lockedExecution = LegacyFinancialMappingExecution::query()->lockForUpdate()->findOrFail($execution->id);
            $mappings = $lockedExecution->mappings()->with(['assessment.lines', 'paymentSchedule.lines'])->orderByDesc('id')->get();

            foreach ($mappings as $mapping) {
                $this->assertRollbackSafe($mapping);
            }

            foreach ($mappings as $mapping) {
                $assessment = $mapping->assessment;
                $schedule = $mapping->paymentSchedule;
                $created = ($mapping->metadata['created_by_execution'] ?? false) === true;
                $mapping->delete();

                if ($created) {
                    $schedule?->delete();
                    $assessment?->delete();
                }
            }

            $lockedExecution->update([
                'status' => LegacyMappingExecutionStatus::RolledBack,
                'rolled_back_at' => now(),
                'metadata' => [
                    ...($lockedExecution->metadata ?? []),
                    'rollback_mapping_count' => $mappings->count(),
                    'rollback_deleted_created_snapshots' => $mappings
                        ->filter(fn (LegacyFinancialSnapshotMapping $mapping): bool => ($mapping->metadata['created_by_execution'] ?? false) === true)
                        ->count(),
                    'pre_existing_targets_deleted' => false,
                ],
            ]);

            return $lockedExecution->fresh(['mappingPlan.importBatch.source', 'mappings']) ?? $lockedExecution;
        }, 3);
    }

    private function assertRollbackSafe(LegacyFinancialSnapshotMapping $mapping): void
    {
        $assessment = $mapping->assessment;
        $schedule = $mapping->paymentSchedule;

        if (! $assessment instanceof Assessment || ! $schedule instanceof PaymentSchedule) {
            throw new RuntimeException("Mapped financial snapshot [{$mapping->id}] no longer has both authoritative targets; rollback refused.");
        }
        if (($mapping->metadata['created_by_execution'] ?? false) !== true) {
            return;
        }

        $expectedHash = $mapping->metadata['target_snapshot_hash'] ?? null;
        if (! is_string($expectedHash) || ! hash_equals($expectedHash, $this->projector->targetSnapshotHash($assessment, $schedule))) {
            throw new RuntimeException("Created financial snapshot [{$mapping->id}] changed after migration; rollback refused.");
        }
        if ($schedule->treasuryCollections()->exists()) {
            throw new RuntimeException("Created payment schedule [{$schedule->id}] has collection dependencies; rollback refused.");
        }
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy financial snapshot rollback is restricted to local and testing environments.');
        }
    }
}
