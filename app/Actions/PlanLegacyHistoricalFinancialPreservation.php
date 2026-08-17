<?php

namespace App\Actions;

use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyHistoricalFinancialPreservationPlan;
use App\Models\LegacyRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlanLegacyHistoricalFinancialPreservation
{
    public const PlannerVersion = 'bpls.historical-financial-preservation-plan.v1';

    public function __construct(
        private LegacyHistoricalFinancialPreservationProjector $projector,
        private PlanLegacyFinancialDependencies $financialPlanner,
        private BuildLegacyHistoricalFinancialProposalIndex $buildProposalIndex,
    ) {}

    public function handle(LegacyFinancialMappingPlan $financialPlan, string $runReference): LegacyHistoricalFinancialPreservationPlan
    {
        $this->assertReady($financialPlan, $runReference);
        $dependencyHash = $this->snapshotHash($financialPlan);
        $plan = DB::transaction(function () use ($financialPlan, $runReference, $dependencyHash): LegacyHistoricalFinancialPreservationPlan {
            $existing = LegacyHistoricalFinancialPreservationPlan::query()
                ->where('legacy_import_batch_id', $financialPlan->legacy_import_batch_id)
                ->where('run_reference', $runReference)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof LegacyHistoricalFinancialPreservationPlan) {
                if ($existing->legacy_financial_mapping_plan_id !== $financialPlan->id
                    || ! hash_equals($existing->dependency_snapshot_hash, $dependencyHash)) {
                    throw new RuntimeException("Historical preservation plan run reference [{$runReference}] is bound to different evidence.");
                }

                return $existing;
            }

            return LegacyHistoricalFinancialPreservationPlan::query()->create([
                'legacy_import_batch_id' => $financialPlan->legacy_import_batch_id,
                'legacy_financial_mapping_plan_id' => $financialPlan->id,
                'run_reference' => $runReference,
                'planner_version' => self::PlannerVersion,
                'dependency_snapshot_hash' => $dependencyHash,
                'status' => LegacyMappingPlanStatus::Planning,
                'started_at' => now(),
                'metadata' => $this->safetyMetadata(),
            ]);
        });

        if (in_array($plan->status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
            return $this->evidence($plan);
        }

        $financialProposals = $financialPlan->proposals()
            ->with('legacyRecord')
            ->whereIn('kind', ['payment_schedule', 'payment_schedule_fee', 'payment', 'receipt_claim'])
            ->orderBy('id')
            ->get();
        $proposalsByApplication = $this->buildProposalIndex->handle($financialProposals);
        $applicationIds = collect(array_keys($proposalsByApplication));

        LegacyRecord::query()->whereIn('id', $applicationIds)->orderBy('id')->chunkById(100, function (Collection $applications) use ($plan, $financialPlan, $proposalsByApplication): void {
            foreach ($applications as $application) {
                $result = $this->projector->project($financialPlan, $application, $proposalsByApplication[$application->id] ?? collect());
                $projection = $result['projection'];
                $reasons = $result['reasons'];
                $plan->proposals()->updateOrCreate(
                    ['legacy_record_id' => $application->id],
                    [
                        'legacy_application_id_mapping_id' => $result['application_mapping']?->id,
                        'status' => $reasons === [] ? LegacyMappingProposalStatus::Ready : LegacyMappingProposalStatus::Blocked,
                        'projection_hash' => $this->projector->hash($projection),
                        'reasons' => $reasons,
                        'metadata' => [
                            'projection' => $projection,
                            ...$this->safetyMetadata(),
                        ],
                    ],
                );
            }
        });

        $ready = $plan->proposals()->where('status', LegacyMappingProposalStatus::Ready)->count();
        $blocked = $plan->proposals()->where('status', LegacyMappingProposalStatus::Blocked)->count();
        $plan->update([
            'status' => $blocked > 0 ? LegacyMappingPlanStatus::PlannedWithExceptions : LegacyMappingPlanStatus::Planned,
            'proposal_count' => $ready + $blocked,
            'ready_count' => $ready,
            'blocked_count' => $blocked,
            'completed_at' => now(),
        ]);

        return $this->evidence($plan);
    }

    /** @param list<int> $applicationRecordIds */
    public function handleSelection(
        LegacyFinancialMappingPlan $financialPlan,
        string $runReference,
        array $applicationRecordIds,
    ): LegacyHistoricalFinancialPreservationPlan {
        $this->assertReady($financialPlan, $runReference);
        $applicationRecordIds = array_values(array_unique($applicationRecordIds));
        sort($applicationRecordIds);
        $selectionCount = count($applicationRecordIds);
        if ($selectionCount < 1 || $selectionCount > 500 || collect($applicationRecordIds)->contains(fn (int $id): bool => $id < 1)) {
            throw new RuntimeException('Selected historical preservation planning requires 1-500 positive application record IDs.');
        }

        $dependencyHash = $this->snapshotHash($financialPlan);
        $selectionHash = hash('sha256', json_encode($applicationRecordIds, JSON_THROW_ON_ERROR));
        $plan = DB::transaction(function () use ($financialPlan, $runReference, $dependencyHash, $selectionHash, $selectionCount): LegacyHistoricalFinancialPreservationPlan {
            $existing = LegacyHistoricalFinancialPreservationPlan::query()
                ->where('legacy_import_batch_id', $financialPlan->legacy_import_batch_id)
                ->where('run_reference', $runReference)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof LegacyHistoricalFinancialPreservationPlan) {
                if ($existing->legacy_financial_mapping_plan_id !== $financialPlan->id
                    || ! hash_equals($existing->dependency_snapshot_hash, $dependencyHash)
                    || ! hash_equals((string) data_get($existing->metadata, 'selection_sha256'), $selectionHash)) {
                    throw new RuntimeException("Selected historical preservation plan [{$runReference}] is bound to different evidence.");
                }

                return $existing;
            }

            return LegacyHistoricalFinancialPreservationPlan::query()->create([
                'legacy_import_batch_id' => $financialPlan->legacy_import_batch_id,
                'legacy_financial_mapping_plan_id' => $financialPlan->id,
                'run_reference' => $runReference,
                'planner_version' => self::PlannerVersion.'.selected-bounded-v2',
                'dependency_snapshot_hash' => $dependencyHash,
                'status' => LegacyMappingPlanStatus::Planning,
                'started_at' => now(),
                'metadata' => [
                    ...$this->safetyMetadata(),
                    'selection_count' => $selectionCount,
                    'selection_sha256' => $selectionHash,
                    'selection_expansion_allowed' => false,
                ],
            ]);
        });

        if (in_array($plan->status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
            return $this->evidence($plan);
        }

        $financialProposals = $financialPlan->proposals()
            ->with('legacyRecord')
            ->whereIn('kind', ['payment_schedule', 'payment_schedule_fee', 'payment', 'receipt_claim'])
            ->orderBy('id')
            ->get();
        $proposalsByApplication = $this->buildProposalIndex->handle($financialProposals);
        $applications = LegacyRecord::query()->whereIn('id', $applicationRecordIds)->orderBy('id')->get();
        if ($applications->count() !== $selectionCount) {
            throw new RuntimeException('One or more selected historical application records are unavailable.');
        }

        foreach ($applications as $application) {
            $result = $this->projector->project($financialPlan, $application, $proposalsByApplication[$application->id] ?? collect());
            $projection = $result['projection'];
            $reasons = $result['reasons'];
            $plan->proposals()->updateOrCreate(
                ['legacy_record_id' => $application->id],
                [
                    'legacy_application_id_mapping_id' => $result['application_mapping']?->id,
                    'status' => $reasons === [] ? LegacyMappingProposalStatus::Ready : LegacyMappingProposalStatus::Blocked,
                    'projection_hash' => $this->projector->hash($projection),
                    'reasons' => $reasons,
                    'metadata' => [
                        'projection' => $projection,
                        ...$this->safetyMetadata(),
                        'selected_bounded_plan' => true,
                        'selection_count' => $selectionCount,
                    ],
                ],
            );
        }

        $ready = $plan->proposals()->where('status', LegacyMappingProposalStatus::Ready)->count();
        $blocked = $plan->proposals()->where('status', LegacyMappingProposalStatus::Blocked)->count();
        $plan->update([
            'status' => $blocked > 0 ? LegacyMappingPlanStatus::PlannedWithExceptions : LegacyMappingPlanStatus::Planned,
            'proposal_count' => $ready + $blocked,
            'ready_count' => $ready,
            'blocked_count' => $blocked,
            'completed_at' => now(),
        ]);

        return $this->evidence($plan);
    }

    public function snapshotHash(LegacyFinancialMappingPlan $financialPlan): string
    {
        $context = hash_init('sha256');
        hash_update($context, json_encode([
            $financialPlan->id,
            $financialPlan->legacy_import_batch_id,
            $financialPlan->dependency_snapshot_hash,
            $this->financialPlanner->snapshotHash($financialPlan->importBatch),
            $financialPlan->status->value,
        ], JSON_THROW_ON_ERROR));

        foreach ($financialPlan->proposals()->select(['id', 'legacy_record_id', 'kind', 'item_key', 'status', 'projection_hash', 'reasons', 'metadata'])->orderBy('id')->cursor() as $proposal) {
            hash_update($context, json_encode([
                $proposal->id,
                $proposal->legacy_record_id,
                $proposal->kind,
                $proposal->item_key,
                $proposal->status->value,
                $proposal->projection_hash,
                $proposal->reasons,
                $proposal->metadata,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }
        foreach (LegacyApplicationIdMapping::query()->where('legacy_import_batch_id', $financialPlan->legacy_import_batch_id)->orderBy('id')->cursor() as $mapping) {
            hash_update($context, json_encode([
                $mapping->id,
                $mapping->legacy_source_id,
                $mapping->permit_application_id,
                $mapping->dataset_key,
                hash('sha256', $mapping->legacy_id),
                $mapping->status,
                $mapping->mapping_basis,
                $mapping->updated_at?->toJSON(),
            ], JSON_THROW_ON_ERROR));
        }

        return hash_final($context);
    }

    private function assertReady(LegacyFinancialMappingPlan $financialPlan, string $runReference): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Historical financial preservation planning is restricted to local and testing environments.');
        }
        if (! in_array($financialPlan->status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
            throw new RuntimeException('Financial mapping plan must be complete before preservation planning.');
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Historical preservation plan run reference must be 3-100 safe characters.');
        }
    }

    /** @return array<string, bool|string> */
    private function safetyMetadata(): array
    {
        return [
            'preservation_class' => 'complete_application_financial_history_v1',
            'historical_financial_fact' => true,
            'fee_policy_provenance' => 'incomplete',
            'future_policy_executable' => false,
            'operational_financial_writes' => false,
            'liability_calculations' => false,
            'fee_identity_inference' => false,
            'production_execution_authorized' => false,
        ];
    }

    private function evidence(LegacyHistoricalFinancialPreservationPlan $plan): LegacyHistoricalFinancialPreservationPlan
    {
        return $plan->fresh(['importBatch.source', 'financialMappingPlan']) ?? $plan;
    }
}
