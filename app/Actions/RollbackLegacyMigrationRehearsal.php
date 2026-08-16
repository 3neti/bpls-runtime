<?php

namespace App\Actions;

use App\Enums\LegacyMigrationRehearsalStatus;
use App\Models\LegacyMigrationRehearsal;
use RuntimeException;
use Throwable;

class RollbackLegacyMigrationRehearsal
{
    public function __construct(
        private RollbackLegacyPermitEvidence $rollbackPermitEvidence,
        private RollbackLegacyFinancialSnapshots $rollbackFinancialSnapshots,
        private RollbackLegacyApplicationDeclarations $rollbackApplicationDeclarations,
        private RollbackLegacyPermitApplications $rollbackPermitApplications,
        private RollbackLegacyRegistryMigration $rollbackRegistryMigration,
    ) {}

    public function handle(LegacyMigrationRehearsal $rehearsal): LegacyMigrationRehearsal
    {
        $this->assertEnvironment();

        if ($rehearsal->status === LegacyMigrationRehearsalStatus::RolledBack) {
            return $rehearsal->load($this->relations());
        }
        if (! in_array($rehearsal->status, [LegacyMigrationRehearsalStatus::Verified, LegacyMigrationRehearsalStatus::RollbackFailed], true)) {
            throw new RuntimeException("Migration rehearsal [{$rehearsal->run_reference}] is not verified and cannot be rolled back.");
        }

        $rehearsal->load($this->relations());
        $rehearsal->update([
            'status' => LegacyMigrationRehearsalStatus::RollingBack,
            'metadata' => [
                ...($rehearsal->metadata ?? []),
                'rollback_order' => ['permit_evidence', 'financial', 'declarations', 'applications', 'registry'],
                'rollback_completed_phases' => $rehearsal->metadata['rollback_completed_phases'] ?? [],
            ],
        ]);
        $rawCompleted = $rehearsal->metadata['rollback_completed_phases'] ?? [];
        $completed = is_array($rawCompleted)
            ? array_values(array_filter($rawCompleted, fn (mixed $phase): bool => is_string($phase)))
            : [];

        try {
            $this->phase($rehearsal, $completed, 'permit_evidence', function () use ($rehearsal): void {
                if ($rehearsal->permitEvidenceExecution !== null) {
                    $this->rollbackPermitEvidence->handle($rehearsal->permitEvidenceExecution);
                }
            });
            $this->phase($rehearsal, $completed, 'financial', function () use ($rehearsal): void {
                if ($rehearsal->financialExecution !== null) {
                    $this->rollbackFinancialSnapshots->handle($rehearsal->financialExecution);
                }
            });
            $this->phase($rehearsal, $completed, 'declarations', function () use ($rehearsal): void {
                if ($rehearsal->declarationExecution !== null) {
                    $this->rollbackApplicationDeclarations->handle($rehearsal->declarationExecution);
                }
            });
            $this->phase($rehearsal, $completed, 'applications', function () use ($rehearsal): void {
                $this->rollbackPermitApplications->handle($rehearsal->applicationExecution);
            });
            $this->phase($rehearsal, $completed, 'registry', function () use ($rehearsal): void {
                $this->rollbackRegistryMigration->handle($rehearsal->registryExecution);
            });
        } catch (Throwable $exception) {
            $rehearsal->update([
                'status' => LegacyMigrationRehearsalStatus::RollbackFailed,
                'metadata' => [
                    ...($rehearsal->metadata ?? []),
                    'rollback_completed_phases' => $completed,
                    'rollback_failure_class' => class_basename($exception),
                ],
            ]);

            throw $exception;
        }

        $rehearsal->update([
            'status' => LegacyMigrationRehearsalStatus::RolledBack,
            'rolled_back_at' => now(),
            'metadata' => [
                ...($rehearsal->metadata ?? []),
                'rollback_completed_phases' => $completed,
                'rollback_complete' => true,
                'pre_existing_targets_deleted' => false,
            ],
        ]);

        return $rehearsal->refresh()->load($this->relations());
    }

    /** @param list<string> $completed */
    private function phase(LegacyMigrationRehearsal $rehearsal, array &$completed, string $key, callable $callback): void
    {
        if (in_array($key, $completed, true)) {
            return;
        }

        $callback();
        $completed[] = $key;
        $rehearsal->update([
            'metadata' => [
                ...($rehearsal->metadata ?? []),
                'rollback_completed_phases' => $completed,
            ],
        ]);
        $rehearsal->refresh();
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'importBatch.source',
            'registryExecution.mappingPlan',
            'applicationExecution.mappingPlan',
            'declarationExecution.mappingPlan',
            'financialExecution.mappingPlan',
            'permitEvidenceExecution.mappingPlan',
        ];
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy migration rehearsal rollback is restricted to local and testing environments.');
        }
    }
}
