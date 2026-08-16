<?php

namespace App\Actions;

use App\Enums\LegacyMappingExecutionStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\Assessment;
use App\Models\LegacyFinancialMappingExecution;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyFinancialMappingProposal;
use App\Models\LegacyFinancialSnapshotMapping;
use App\Models\PaymentSchedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExecuteLegacyFinancialSnapshots
{
    public function __construct(
        private LegacyFinancialSnapshotProjector $projector,
        private PlanLegacyFinancialDependencies $planner,
    ) {}

    /** @param list<int> $proposalIds */
    public function handle(LegacyFinancialMappingPlan $plan, array $proposalIds, string $runReference): LegacyFinancialMappingExecution
    {
        $this->assertEnvironment();
        $this->assertRunReference($runReference);
        $proposalIds = array_values(array_unique($proposalIds));
        sort($proposalIds);

        if ($proposalIds === []) {
            throw new RuntimeException('At least one exact financial mapping proposal ID is required.');
        }

        $selectionHash = hash('sha256', json_encode($proposalIds, JSON_THROW_ON_ERROR));
        $existing = $plan->executions()->where('run_reference', $runReference)->first();

        if ($existing instanceof LegacyFinancialMappingExecution) {
            if (! hash_equals($existing->selection_hash, $selectionHash)) {
                throw new RuntimeException("Financial execution run reference [{$runReference}] is already bound to a different proposal selection.");
            }
            if ($existing->status === LegacyMappingExecutionStatus::Completed) {
                return $existing->load(['mappingPlan.importBatch.source', 'mappings']);
            }
            if ($existing->status === LegacyMappingExecutionStatus::RolledBack) {
                throw new RuntimeException("Financial execution [{$runReference}] has already been rolled back and cannot execute again.");
            }

            throw new RuntimeException("Financial execution [{$runReference}] is not in a resumable state.");
        }

        return DB::transaction(function () use ($plan, $proposalIds, $runReference, $selectionHash): LegacyFinancialMappingExecution {
            $lockedPlan = LegacyFinancialMappingPlan::query()->lockForUpdate()->findOrFail($plan->id);

            if (! in_array($lockedPlan->status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
                throw new RuntimeException("Financial mapping plan [{$lockedPlan->id}] is not complete.");
            }

            $proposals = $lockedPlan->proposals()
                ->with(['legacyRecord', 'feeReconciliation', 'feeRule'])
                ->whereIn('id', $proposalIds)
                ->get();

            if ($proposals->count() !== count($proposalIds)) {
                throw new RuntimeException('Every selected proposal ID must belong to the exact financial mapping plan.');
            }
            if ($proposals->contains(fn (LegacyFinancialMappingProposal $proposal): bool => ! in_array($proposal->kind, ['payment_schedule', 'payment_schedule_fee'], true))) {
                throw new RuntimeException('Financial execution accepts only annual schedule and schedule-fee proposals.');
            }
            if ($proposals->contains(fn (LegacyFinancialMappingProposal $proposal): bool => $proposal->status !== LegacyMappingProposalStatus::Ready)) {
                throw new RuntimeException('Every selected financial proposal must be ready.');
            }
            if (! hash_equals($lockedPlan->dependency_snapshot_hash, $this->planner->snapshotHash($lockedPlan->importBatch))) {
                throw new RuntimeException("Financial mapping plan [{$lockedPlan->id}] no longer matches its dependency snapshot.");
            }

            $groups = $this->assertCompleteScheduleSets($lockedPlan, $proposals);
            $execution = $lockedPlan->executions()->create([
                'run_reference' => $runReference,
                'selection_hash' => $selectionHash,
                'status' => LegacyMappingExecutionStatus::Executing,
                'selected_count' => $proposals->count(),
                'started_at' => now(),
                'metadata' => [
                    'proposal_ids' => $proposalIds,
                    'annual_single_section_only' => true,
                    'historical_amount_conversion_only' => true,
                    'liability_calculations' => false,
                    'payment_status_inference' => false,
                    'collections_created' => false,
                    'receipts_created' => false,
                    'application_lifecycle_mutated' => false,
                    'external_integrations' => false,
                    'notifications' => false,
                    'irreversible_actions' => false,
                ],
            ]);
            $counts = ['created' => 0, 'reused' => 0, 'mappings' => 0];

            foreach ($groups as $group) {
                $result = $this->executeGroup($execution, $lockedPlan, $group['schedule'], $group['fees']);
                $counts[$result]++;
                if ($result !== 'reused') {
                    $counts['mappings']++;
                }
            }

            $execution->update([
                'status' => LegacyMappingExecutionStatus::Completed,
                'created_count' => $counts['created'],
                'reused_count' => $counts['reused'],
                'mapping_count' => $counts['mappings'],
                'completed_at' => now(),
            ]);

            return $execution->fresh(['mappingPlan.importBatch.source', 'mappings']) ?? $execution;
        }, 3);
    }

    /**
     * @param  Collection<int, LegacyFinancialMappingProposal>  $proposals
     * @return Collection<int, array{schedule: LegacyFinancialMappingProposal, fees: Collection<int, LegacyFinancialMappingProposal>}>
     */
    private function assertCompleteScheduleSets(LegacyFinancialMappingPlan $plan, Collection $proposals): Collection
    {
        return $proposals->groupBy('legacy_record_id')->map(function (Collection $selected, int $recordId) use ($plan): array {
            $all = $plan->proposals()
                ->where('legacy_record_id', $recordId)
                ->whereIn('kind', ['payment_schedule', 'payment_schedule_fee'])
                ->get();
            if ($all->count() !== $selected->count() || $selected->pluck('id')->sort()->values()->all() !== $all->pluck('id')->sort()->values()->all()) {
                throw new RuntimeException("Legacy financial record [{$recordId}] must execute its complete schedule proposal set atomically.");
            }

            $schedule = $selected->where('kind', 'payment_schedule')->sole();
            $fees = $selected->where('kind', 'payment_schedule_fee')->values();

            return compact('schedule', 'fees');
        })->values();
    }

    /** @param Collection<int, LegacyFinancialMappingProposal> $feeProposals */
    private function executeGroup(
        LegacyFinancialMappingExecution $execution,
        LegacyFinancialMappingPlan $plan,
        LegacyFinancialMappingProposal $scheduleProposal,
        Collection $feeProposals,
    ): string {
        $projection = $this->projector->project($plan, $scheduleProposal, $feeProposals);
        $applicationMapping = $projection['application_mapping'];
        $permitApplication = $projection['permit_application'];
        $record = $scheduleProposal->legacyRecord;

        $existingMapping = LegacyFinancialSnapshotMapping::query()
            ->where('legacy_source_id', $record->legacy_source_id)
            ->where('dataset_key', $record->dataset_key)
            ->where('legacy_id', $record->legacy_id)
            ->first();

        if ($existingMapping instanceof LegacyFinancialSnapshotMapping) {
            $assessment = $existingMapping->assessment()->first();
            $schedule = $existingMapping->paymentSchedule()->first();
            if (! $assessment instanceof Assessment
                || ! $schedule instanceof PaymentSchedule
                || $existingMapping->legacy_application_id_mapping_id !== $applicationMapping->id
                || $assessment->permit_application_id !== $permitApplication->id
                || $schedule->permit_application_id !== $permitApplication->id
                || $schedule->assessment_id !== $assessment->id
                || ! hash_equals($this->projector->projectionSnapshotHash($projection), $this->projector->targetProjectionHash($assessment, $schedule))
                || ! hash_equals((string) ($existingMapping->metadata['target_snapshot_hash'] ?? ''), $this->projector->targetSnapshotHash($assessment, $schedule))) {
                throw new RuntimeException("Existing financial mapping for proposal [{$scheduleProposal->id}] no longer matches its authoritative targets.");
            }

            return 'reused';
        }

        if ($permitApplication->assessments()->exists() || $permitApplication->paymentSchedules()->exists()) {
            throw new RuntimeException("Mapped permit application [{$permitApplication->id}] has unmanaged existing financial records; execution refused.");
        }

        $assessment = Assessment::query()->create($projection['assessment']);
        $assessmentLines = collect($projection['assessment_lines'])->map(fn (array $line) => $assessment->lines()->create($line))->values();
        $schedule = PaymentSchedule::query()->create([
            ...$projection['payment_schedule'],
            'assessment_id' => $assessment->id,
        ]);
        foreach ($projection['payment_schedule_lines'] as $line) {
            $assessmentLine = $assessmentLines->get($line['assessment_line_index']);
            unset($line['assessment_line_index']);
            $schedule->lines()->create([
                ...$line,
                'assessment_line_id' => $assessmentLine?->id,
            ]);
        }

        LegacyFinancialSnapshotMapping::query()->create([
            'legacy_financial_mapping_execution_id' => $execution->id,
            'legacy_application_id_mapping_id' => $applicationMapping->id,
            'legacy_source_id' => $record->legacy_source_id,
            'legacy_import_batch_id' => $record->legacy_import_batch_id,
            'legacy_record_id' => $record->id,
            'assessment_id' => $assessment->id,
            'payment_schedule_id' => $schedule->id,
            'dataset_key' => $record->dataset_key,
            'legacy_id' => $record->legacy_id,
            'status' => 'mapped',
            'mapping_basis' => 'approved_annual_single_section_snapshot',
            'metadata' => [
                'schedule_proposal_id' => $scheduleProposal->id,
                'fee_proposal_ids' => $feeProposals->pluck('id')->sort()->values()->all(),
                'created_by_execution' => true,
                'projection_snapshot_hash' => $this->projector->projectionSnapshotHash($projection),
                'target_snapshot_hash' => $this->projector->targetSnapshotHash($assessment, $schedule),
                'liability_calculations' => false,
                'payment_status_inference' => false,
                'collections_created' => false,
                'receipts_created' => false,
            ],
        ]);

        return 'created';
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy financial snapshot execution is restricted to local and testing environments.');
        }
    }

    private function assertRunReference(string $runReference): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Financial execution run reference must be 3-100 safe characters.');
        }
    }
}
