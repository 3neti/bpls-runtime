<?php

namespace App\Actions;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\LegacyDeclarationLineMapping;
use App\Models\LegacyDeclarationMappingExecution;
use App\Models\PermitApplicationLine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RollbackLegacyApplicationDeclarations
{
    public function __construct(private LegacyApplicationDeclarationProjector $projector) {}

    public function handle(LegacyDeclarationMappingExecution $execution): LegacyDeclarationMappingExecution
    {
        $this->assertEnvironment();

        if ($execution->status === LegacyMappingExecutionStatus::RolledBack) {
            return $execution->load(['mappingPlan.importBatch.source', 'mappings']);
        }

        if ($execution->status !== LegacyMappingExecutionStatus::Completed) {
            throw new RuntimeException("Declaration execution [{$execution->run_reference}] is not completed and cannot be rolled back.");
        }

        return DB::transaction(function () use ($execution): LegacyDeclarationMappingExecution {
            $lockedExecution = LegacyDeclarationMappingExecution::query()->lockForUpdate()->findOrFail($execution->id);
            $mappings = $lockedExecution->mappings()->with('permitApplicationLine')->orderByDesc('id')->get();

            foreach ($mappings as $mapping) {
                $this->assertRollbackSafe($mapping);
            }

            foreach ($mappings as $mapping) {
                $target = $mapping->permitApplicationLine;
                $created = ($mapping->metadata['created_by_execution'] ?? false) === true;
                $mapping->delete();

                if ($created && $target instanceof PermitApplicationLine) {
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
                        ->filter(fn (LegacyDeclarationLineMapping $mapping): bool => ($mapping->metadata['created_by_execution'] ?? false) === true)
                        ->count(),
                    'pre_existing_targets_deleted' => false,
                ],
            ]);

            return $lockedExecution->fresh(['mappingPlan.importBatch.source', 'mappings']) ?? $lockedExecution;
        }, 3);
    }

    private function assertRollbackSafe(LegacyDeclarationLineMapping $mapping): void
    {
        $target = $mapping->permitApplicationLine;

        if (! $target instanceof PermitApplicationLine) {
            throw new RuntimeException("Mapped permit application line [{$mapping->permit_application_line_id}] no longer exists; rollback refused.");
        }

        if (($mapping->metadata['created_by_execution'] ?? false) !== true) {
            return;
        }

        $expectedHash = $mapping->metadata['target_snapshot_hash'] ?? null;

        if (! is_string($expectedHash) || ! hash_equals($expectedHash, $this->projector->targetSnapshotHash($target))) {
            throw new RuntimeException("Created permit application line [{$target->id}] changed after migration; rollback refused.");
        }

        if ($target->assessmentLines()->exists()) {
            throw new RuntimeException("Created permit application line [{$target->id}] has assessment dependencies; rollback refused.");
        }
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy application declaration rollback is restricted to local and testing environments.');
        }
    }
}
