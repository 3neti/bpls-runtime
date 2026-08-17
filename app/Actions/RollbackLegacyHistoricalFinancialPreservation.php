<?php

namespace App\Actions;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\LegacyHistoricalFinancialPreservationExecution;
use App\Models\LegacyHistoricalFinancialPreservedBundle;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RollbackLegacyHistoricalFinancialPreservation
{
    public function __construct(private LegacyHistoricalFinancialPreservationProjector $projector) {}

    public function handle(LegacyHistoricalFinancialPreservationExecution $execution): LegacyHistoricalFinancialPreservationExecution
    {
        $this->assertEnvironment();
        if ($execution->status === LegacyMappingExecutionStatus::RolledBack) {
            return $execution->load('bundles');
        }
        if ($execution->status !== LegacyMappingExecutionStatus::Completed) {
            throw new RuntimeException("Historical preservation execution [{$execution->run_reference}] is not completed.");
        }

        return DB::transaction(function () use ($execution): LegacyHistoricalFinancialPreservationExecution {
            $locked = LegacyHistoricalFinancialPreservationExecution::query()->lockForUpdate()->findOrFail($execution->id);
            $bundles = $locked->bundles()->with('applicationMapping')->orderByDesc('id')->get();
            foreach ($bundles as $bundle) {
                $this->assertSafe($bundle);
            }
            foreach ($bundles as $bundle) {
                $bundle->delete();
            }

            $locked->update([
                'status' => LegacyMappingExecutionStatus::RolledBack,
                'rolled_back_at' => now(),
                'metadata' => [
                    ...($locked->metadata ?? []),
                    'rollback_bundle_count' => $bundles->count(),
                    'source_records_deleted' => false,
                    'application_mappings_deleted' => false,
                    'operational_financial_records_deleted' => false,
                ],
            ]);

            return $locked->fresh(['preservationPlan.importBatch.source', 'bundles']) ?? $locked;
        }, 3);
    }

    private function assertSafe(LegacyHistoricalFinancialPreservedBundle $bundle): void
    {
        if (! hash_equals($bundle->bundle_snapshot_hash, $this->projector->hash($bundle->snapshot))) {
            throw new RuntimeException("Historical preservation bundle [{$bundle->id}] changed after execution; rollback refused.");
        }
        if (($bundle->metadata['reviewer_disposition'] ?? null) !== null || (int) ($bundle->metadata['downstream_reference_count'] ?? 0) !== 0) {
            throw new RuntimeException("Historical preservation bundle [{$bundle->id}] has review or downstream evidence; rollback refused.");
        }
        if ($bundle->applicationMapping === null
            || $bundle->applicationMapping->permit_application_id !== $bundle->permit_application_id
            || $bundle->applicationMapping->status !== 'mapped') {
            throw new RuntimeException("Historical preservation bundle [{$bundle->id}] no longer matches its application mapping; rollback refused.");
        }
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Historical financial preservation rollback is restricted to local and testing environments.');
        }
    }
}
