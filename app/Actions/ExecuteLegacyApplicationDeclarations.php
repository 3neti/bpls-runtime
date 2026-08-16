<?php

namespace App\Actions;

use App\Enums\LegacyLineOfBusinessReconciliationStatus;
use App\Enums\LegacyMappingExecutionStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyDeclarationLineMapping;
use App\Models\LegacyDeclarationMappingExecution;
use App\Models\LegacyDeclarationMappingPlan;
use App\Models\LegacyDeclarationMappingProposal;
use App\Models\LegacyLineOfBusinessReconciliation;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExecuteLegacyApplicationDeclarations
{
    public function __construct(
        private LegacyApplicationDeclarationProjector $projector,
        private PlanLegacyApplicationDeclarations $planner,
    ) {}

    /** @param list<int> $proposalIds */
    public function handle(LegacyDeclarationMappingPlan $plan, array $proposalIds, string $runReference): LegacyDeclarationMappingExecution
    {
        $this->assertEnvironment();
        $this->assertRunReference($runReference);
        $proposalIds = array_values(array_unique($proposalIds));
        sort($proposalIds);

        if ($proposalIds === []) {
            throw new RuntimeException('At least one exact declaration mapping proposal ID is required.');
        }

        $selectionHash = hash('sha256', json_encode($proposalIds, JSON_THROW_ON_ERROR));
        $existing = $plan->executions()->where('run_reference', $runReference)->first();

        if ($existing instanceof LegacyDeclarationMappingExecution) {
            if (! hash_equals($existing->selection_hash, $selectionHash)) {
                throw new RuntimeException("Declaration execution run reference [{$runReference}] is already bound to a different proposal selection.");
            }

            if ($existing->status === LegacyMappingExecutionStatus::Completed) {
                return $existing->load(['mappingPlan.importBatch.source', 'mappings']);
            }

            if ($existing->status === LegacyMappingExecutionStatus::RolledBack) {
                throw new RuntimeException("Declaration execution [{$runReference}] has already been rolled back and cannot execute again.");
            }

            throw new RuntimeException("Declaration execution [{$runReference}] is not in a resumable state.");
        }

        return DB::transaction(function () use ($plan, $proposalIds, $runReference, $selectionHash): LegacyDeclarationMappingExecution {
            $lockedPlan = LegacyDeclarationMappingPlan::query()->lockForUpdate()->findOrFail($plan->id);

            if (! in_array($lockedPlan->status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
                throw new RuntimeException("Declaration mapping plan [{$lockedPlan->id}] is not complete.");
            }

            $proposals = $lockedPlan->proposals()
                ->with(['legacyRecord', 'reconciliation', 'lineOfBusiness'])
                ->whereIn('id', $proposalIds)
                ->get();

            if ($proposals->count() !== count($proposalIds)) {
                throw new RuntimeException('Every selected proposal ID must belong to the exact declaration mapping plan.');
            }

            $applicationMappings = $this->assertExecutableSelection($lockedPlan, $proposals);
            $dataset = $lockedPlan->metadata['application_dataset_key'] ?? null;

            if (! is_string($dataset)
                || ! hash_equals($lockedPlan->dependency_snapshot_hash, $this->planner->snapshotHash($lockedPlan->importBatch, $dataset))) {
                throw new RuntimeException("Declaration mapping plan [{$lockedPlan->id}] no longer matches its dependency snapshot.");
            }

            $execution = $lockedPlan->executions()->create([
                'run_reference' => $runReference,
                'selection_hash' => $selectionHash,
                'status' => LegacyMappingExecutionStatus::Executing,
                'selected_count' => $proposals->count(),
                'started_at' => now(),
                'metadata' => [
                    'proposal_ids' => $proposalIds,
                    'complete_application_sets_required' => true,
                    'financial_calculations' => false,
                    'assessment_records_created' => false,
                    'external_integrations' => false,
                    'notifications' => false,
                    'irreversible_actions' => false,
                ],
            ]);
            $counts = ['created' => 0, 'reused' => 0, 'mappings' => 0];

            foreach ($proposals->sortBy(['legacy_record_id', 'line_index']) as $proposal) {
                $applicationMapping = $applicationMappings->get($proposal->legacy_record_id);

                if (! $applicationMapping instanceof LegacyApplicationIdMapping) {
                    throw new RuntimeException("Declaration proposal [{$proposal->id}] has no executable application mapping.");
                }

                $result = $this->executeProposal($execution, $proposal, $applicationMapping);
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
     * @param  Collection<int, LegacyDeclarationMappingProposal>  $proposals
     * @return Collection<int, LegacyApplicationIdMapping>
     */
    private function assertExecutableSelection(LegacyDeclarationMappingPlan $plan, Collection $proposals): Collection
    {
        $applicationMappings = collect();

        foreach ($proposals->groupBy('legacy_record_id') as $recordId => $applicationProposals) {
            $allProposals = $plan->proposals()->where('legacy_record_id', $recordId)->get();

            if ($allProposals->count() !== $applicationProposals->count()) {
                throw new RuntimeException("Legacy application record [{$recordId}] must execute its complete declaration proposal set atomically.");
            }

            foreach ($applicationProposals as $proposal) {
                if ($proposal->status !== LegacyMappingProposalStatus::Ready) {
                    throw new RuntimeException("Declaration mapping proposal [{$proposal->id}] is not ready and cannot execute.");
                }
            }

            $record = $applicationProposals->firstOrFail()->legacyRecord;
            $applicationMapping = LegacyApplicationIdMapping::query()
                ->whereBelongsTo($plan->importBatch, 'importBatch')
                ->where('legacy_source_id', $record->legacy_source_id)
                ->where('dataset_key', $record->dataset_key)
                ->where('legacy_id', $record->legacy_id)
                ->where('status', 'mapped')
                ->first();

            if (! $applicationMapping instanceof LegacyApplicationIdMapping) {
                throw new RuntimeException("Legacy application record [{$recordId}] has no accepted permit application mapping.");
            }

            $application = $applicationMapping->permitApplication()->first();

            if (! $application instanceof PermitApplication) {
                throw new RuntimeException("Legacy application record [{$recordId}] mapped permit application no longer exists.");
            }

            $mappedLineIds = LegacyDeclarationLineMapping::query()
                ->whereBelongsTo($applicationMapping, 'applicationMapping')
                ->where('status', 'mapped')
                ->pluck('permit_application_line_id');

            if ($application->lines()->whereNotIn('id', $mappedLineIds)->exists()) {
                throw new RuntimeException("Mapped permit application [{$application->id}] has unmanaged existing declarations; execution refused.");
            }

            $applicationMappings->put((int) $recordId, $applicationMapping);
        }

        return $applicationMappings;
    }

    private function executeProposal(LegacyDeclarationMappingExecution $execution, LegacyDeclarationMappingProposal $proposal, LegacyApplicationIdMapping $applicationMapping): string
    {
        $projection = $this->projector->project($proposal->legacyRecord, $proposal->line_index);

        if ($projection['status'] !== LegacyMappingProposalStatus::Ready
            || ! hash_equals($proposal->projection_hash, $this->projector->hashCanonical($projection['attributes']))
            || $proposal->legacy_line_of_business_reconciliation_id !== $projection['reconciliation']?->id
            || $proposal->line_of_business_id !== $projection['line_of_business']?->id) {
            throw new RuntimeException("Declaration mapping proposal [{$proposal->id}] no longer matches its staged projection or reconciliation.");
        }

        $reconciliation = $proposal->reconciliation;

        if (! $reconciliation instanceof LegacyLineOfBusinessReconciliation
            || $reconciliation->status !== LegacyLineOfBusinessReconciliationStatus::Accepted
            || $reconciliation->decision_authority === null
            || $reconciliation->evidence_reference === null) {
            throw new RuntimeException("Declaration mapping proposal [{$proposal->id}] no longer has accepted reconciliation authority.");
        }

        $existingMapping = LegacyDeclarationLineMapping::query()
            ->where('legacy_source_id', $proposal->legacyRecord->legacy_source_id)
            ->where('dataset_key', $proposal->legacyRecord->dataset_key)
            ->where('legacy_id', $proposal->legacyRecord->legacy_id)
            ->where('line_index', $proposal->line_index)
            ->first();

        if ($existingMapping instanceof LegacyDeclarationLineMapping) {
            $target = $existingMapping->permitApplicationLine()->first();

            if (! $target instanceof PermitApplicationLine
                || $existingMapping->legacy_application_id_mapping_id !== $applicationMapping->id
                || $target->permit_application_id !== $applicationMapping->permit_application_id
                || ! $this->targetMatchesProjection($target, $projection['attributes'])) {
                throw new RuntimeException("Existing declaration mapping for proposal [{$proposal->id}] no longer matches its application and line identity.");
            }

            return 'reused';
        }

        $metadata = is_array($projection['attributes']['metadata'] ?? null) ? $projection['attributes']['metadata'] : [];
        $target = PermitApplicationLine::query()->create([
            ...$projection['attributes'],
            'permit_application_id' => $applicationMapping->permit_application_id,
            'metadata' => [
                ...$metadata,
                'migration' => [
                    'schema_version' => 'bpls.legacy-declaration-migration.v1',
                    'execution_id' => $execution->id,
                    'proposal_id' => $proposal->id,
                    'projection_hash' => $proposal->projection_hash,
                    'reconciliation_id' => $reconciliation->id,
                ],
            ],
        ]);

        LegacyDeclarationLineMapping::query()->create([
            'legacy_declaration_mapping_execution_id' => $execution->id,
            'legacy_application_id_mapping_id' => $applicationMapping->id,
            'legacy_line_of_business_reconciliation_id' => $reconciliation->id,
            'legacy_source_id' => $proposal->legacyRecord->legacy_source_id,
            'legacy_import_batch_id' => $proposal->legacyRecord->legacy_import_batch_id,
            'permit_application_line_id' => $target->id,
            'dataset_key' => $proposal->legacyRecord->dataset_key,
            'legacy_id' => $proposal->legacyRecord->legacy_id,
            'line_index' => $proposal->line_index,
            'status' => 'mapped',
            'mapping_basis' => 'approved_complete_declaration_set',
            'metadata' => [
                'proposal_id' => $proposal->id,
                'created_by_execution' => true,
                'projection_hash' => $proposal->projection_hash,
                'target_snapshot_hash' => $this->projector->targetSnapshotHash($target),
                'financial_calculations' => false,
                'assessment_records_created' => false,
            ],
        ]);

        return 'created';
    }

    /** @param array<string, mixed> $attributes */
    private function targetMatchesProjection(PermitApplicationLine $target, array $attributes): bool
    {
        $metadata = is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [];

        return $target->line_of_business_id === ($attributes['line_of_business_id'] ?? null)
            && $target->declared_gross_sales_cents === ($attributes['declared_gross_sales_cents'] ?? null)
            && $target->capital_investment_cents === ($attributes['capital_investment_cents'] ?? null)
            && $target->quantity === ($attributes['quantity'] ?? null)
            && $target->started_on?->toDateString() === ($attributes['started_on'] ?? null)
            && ($target->metadata['legacy_number_of_employees'] ?? null) === ($metadata['legacy_number_of_employees'] ?? null)
            && ($target->metadata['legacy_category_hash'] ?? null) === ($metadata['legacy_category_hash'] ?? null);
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy application declaration execution is restricted to local and testing environments.');
        }
    }

    private function assertRunReference(string $runReference): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Declaration execution run reference must be 3-100 safe characters.');
        }
    }
}
