<?php

namespace App\Actions;

use App\Enums\LegacyMappingExecutionStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use App\Models\LegacyFinancialMappingProposal;
use App\Models\LegacyHistoricalFinancialPreservationExecution;
use App\Models\LegacyHistoricalFinancialPreservationPlan;
use App\Models\LegacyHistoricalFinancialPreservationProposal;
use App\Models\LegacyHistoricalFinancialPreservedBundle;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLine;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExecuteLegacyHistoricalFinancialPreservation
{
    public function __construct(
        private LegacyHistoricalFinancialPreservationProjector $projector,
        private PlanLegacyHistoricalFinancialPreservation $planner,
    ) {}

    /** @param list<int> $proposalIds */
    public function handle(LegacyHistoricalFinancialPreservationPlan $plan, array $proposalIds, string $runReference): LegacyHistoricalFinancialPreservationExecution
    {
        $this->assertEnvironment();
        $this->assertRunReference($runReference);
        $proposalIds = array_values(array_unique($proposalIds));
        sort($proposalIds);
        if ($proposalIds === []) {
            throw new RuntimeException('At least one exact historical preservation proposal ID is required.');
        }

        $selectionHash = hash('sha256', json_encode($proposalIds, JSON_THROW_ON_ERROR));
        $existing = $plan->executions()->where('run_reference', $runReference)->first();
        if ($existing instanceof LegacyHistoricalFinancialPreservationExecution) {
            if (! hash_equals($existing->selection_hash, $selectionHash)) {
                throw new RuntimeException("Historical preservation execution run reference [{$runReference}] is bound to a different selection.");
            }
            if ($existing->status === LegacyMappingExecutionStatus::Completed) {
                return $existing->load(['preservationPlan.importBatch.source', 'bundles']);
            }
            if ($existing->status === LegacyMappingExecutionStatus::RolledBack) {
                throw new RuntimeException("Historical preservation execution [{$runReference}] was rolled back and cannot execute again.");
            }

            throw new RuntimeException("Historical preservation execution [{$runReference}] is not resumable.");
        }

        return DB::transaction(function () use ($plan, $proposalIds, $runReference, $selectionHash): LegacyHistoricalFinancialPreservationExecution {
            $lockedPlan = LegacyHistoricalFinancialPreservationPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if (! in_array($lockedPlan->status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
                throw new RuntimeException("Historical preservation plan [{$lockedPlan->id}] is not complete.");
            }
            if (! hash_equals($lockedPlan->dependency_snapshot_hash, $this->planner->snapshotHash($lockedPlan->financialMappingPlan))) {
                throw new RuntimeException("Historical preservation plan [{$lockedPlan->id}] no longer matches its dependency snapshot.");
            }

            $proposals = $lockedPlan->proposals()->with(['legacyRecord', 'applicationMapping'])->whereIn('id', $proposalIds)->get();
            if ($proposals->count() !== count($proposalIds)) {
                throw new RuntimeException('Every selected proposal must belong to the exact preservation plan.');
            }
            if ($proposals->contains(fn (LegacyHistoricalFinancialPreservationProposal $proposal): bool => $proposal->status !== LegacyMappingProposalStatus::Ready)) {
                throw new RuntimeException('Every selected historical preservation proposal must be ready.');
            }

            $financialProposals = $lockedPlan->financialMappingPlan->proposals()
                ->with('legacyRecord')
                ->whereIn('kind', ['payment_schedule', 'payment_schedule_fee', 'payment', 'receipt_claim'])
                ->orderBy('id')
                ->get();
            $before = $this->operationalCounts();
            $execution = $lockedPlan->executions()->create([
                'run_reference' => $runReference,
                'selection_hash' => $selectionHash,
                'status' => LegacyMappingExecutionStatus::Executing,
                'selected_count' => $proposals->count(),
                'started_at' => now(),
                'metadata' => [
                    'proposal_ids' => $proposalIds,
                    'operational_counts_before' => $before,
                    ...$this->safetyMetadata(),
                ],
            ]);

            foreach ($proposals as $proposal) {
                $this->preserve($execution, $lockedPlan, $proposal, $financialProposals);
            }

            $after = $this->operationalCounts();
            if ($before !== $after) {
                throw new RuntimeException('Operational financial records changed during historical preservation; transaction refused.');
            }

            $execution->update([
                'status' => LegacyMappingExecutionStatus::Completed,
                'created_count' => $proposals->count(),
                'reused_count' => 0,
                'completed_at' => now(),
                'metadata' => [
                    ...($execution->metadata ?? []),
                    'operational_counts_after' => $after,
                ],
            ]);

            return $execution->fresh(['preservationPlan.importBatch.source', 'bundles']) ?? $execution;
        }, 3);
    }

    /** @param Collection<int, LegacyFinancialMappingProposal> $financialProposals */
    private function preserve(LegacyHistoricalFinancialPreservationExecution $execution, LegacyHistoricalFinancialPreservationPlan $plan, LegacyHistoricalFinancialPreservationProposal $proposal, Collection $financialProposals): void
    {
        $existing = LegacyHistoricalFinancialPreservedBundle::query()
            ->where('legacy_source_id', $proposal->legacyRecord->legacy_source_id)
            ->where('legacy_record_id', $proposal->legacy_record_id)
            ->first();
        if ($existing instanceof LegacyHistoricalFinancialPreservedBundle) {
            throw new RuntimeException("Historical application record [{$proposal->legacy_record_id}] is already preserved by another execution.");
        }

        $result = $this->projector->project($plan->financialMappingPlan, $proposal->legacyRecord, $financialProposals);
        $projection = $result['projection'];
        if ($result['reasons'] !== [] || ! hash_equals($proposal->projection_hash, $this->projector->hash($projection))) {
            throw new RuntimeException("Historical preservation proposal [{$proposal->id}] no longer matches its source projection.");
        }
        $mapping = $result['application_mapping'];
        if ($mapping === null || $mapping->id !== $proposal->legacy_application_id_mapping_id) {
            throw new RuntimeException("Historical preservation proposal [{$proposal->id}] no longer has its exact application mapping.");
        }

        LegacyHistoricalFinancialPreservedBundle::query()->create([
            'legacy_historical_financial_preservation_execution_id' => $execution->id,
            'legacy_historical_financial_preservation_proposal_id' => $proposal->id,
            'legacy_application_id_mapping_id' => $mapping->id,
            'legacy_source_id' => $proposal->legacyRecord->legacy_source_id,
            'legacy_import_batch_id' => $proposal->legacyRecord->legacy_import_batch_id,
            'legacy_record_id' => $proposal->legacy_record_id,
            'permit_application_id' => $mapping->permit_application_id,
            'source_projection_hash' => $proposal->projection_hash,
            'bundle_snapshot_hash' => $this->projector->hash($projection),
            'status' => 'preserved',
            'mapping_basis' => 'accepted_exact_application_mapping',
            'snapshot' => $projection,
            'metadata' => [
                'created_by_execution' => true,
                'reviewer_disposition' => null,
                'downstream_reference_count' => 0,
                ...$this->safetyMetadata(),
            ],
        ]);
    }

    /** @return array<string, int> */
    private function operationalCounts(): array
    {
        return [
            'assessments' => Assessment::query()->count(),
            'assessment_lines' => AssessmentLine::query()->count(),
            'payment_schedules' => PaymentSchedule::query()->count(),
            'payment_schedule_lines' => PaymentScheduleLine::query()->count(),
            'treasury_collections' => TreasuryCollection::query()->count(),
            'receipts' => Receipt::query()->count(),
        ];
    }

    /** @return array<string, bool|string> */
    private function safetyMetadata(): array
    {
        return [
            'preservation_class' => 'complete_application_financial_history_v1',
            'historical_financial_fact' => true,
            'fee_policy_provenance' => 'incomplete',
            'future_policy_executable' => false,
            'operational_financial_record' => false,
            'liability_recalculated' => false,
            'fee_identity_inferred' => false,
            'collections_created' => false,
            'receipts_created' => false,
            'notifications_sent' => false,
            'external_calls' => false,
            'production_execution_authorized' => false,
        ];
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Historical financial preservation execution is restricted to local and testing environments.');
        }
    }

    private function assertRunReference(string $runReference): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Historical preservation execution run reference must be 3-100 safe characters.');
        }
    }
}
