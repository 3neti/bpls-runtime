<?php

namespace App\Actions;

use App\Enums\LegacyMappingExecutionStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalAction;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyApplicationMappingExecution;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyApplicationMappingProposal;
use App\Models\LegacyIdMapping;
use App\Models\PermitApplication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExecuteLegacyPermitApplications
{
    public function __construct(
        private LegacyPermitApplicationProjector $projector,
        private PlanLegacyPermitApplications $planner,
        private PermitApplicationStatusMutation $statusMutation,
    ) {}

    /** @param list<int> $proposalIds */
    public function handle(LegacyApplicationMappingPlan $plan, array $proposalIds, string $runReference): LegacyApplicationMappingExecution
    {
        $this->assertEnvironment();
        $this->assertRunReference($runReference);
        $proposalIds = array_values(array_unique($proposalIds));
        sort($proposalIds);

        if ($proposalIds === []) {
            throw new RuntimeException('At least one exact application mapping proposal ID is required.');
        }

        $selectionHash = hash('sha256', json_encode($proposalIds, JSON_THROW_ON_ERROR));
        $existing = $plan->executions()->where('run_reference', $runReference)->first();

        if ($existing instanceof LegacyApplicationMappingExecution) {
            if (! hash_equals($existing->selection_hash, $selectionHash)) {
                throw new RuntimeException("Application execution run reference [{$runReference}] is already bound to a different proposal selection.");
            }

            if ($existing->status === LegacyMappingExecutionStatus::Completed) {
                return $existing->load(['mappingPlan.importBatch.source', 'mappings']);
            }

            if ($existing->status === LegacyMappingExecutionStatus::RolledBack) {
                throw new RuntimeException("Application execution [{$runReference}] has already been rolled back and cannot execute again.");
            }

            throw new RuntimeException("Application execution [{$runReference}] is not in a resumable state.");
        }

        return DB::transaction(function () use ($plan, $proposalIds, $runReference, $selectionHash): LegacyApplicationMappingExecution {
            $lockedPlan = LegacyApplicationMappingPlan::query()->lockForUpdate()->findOrFail($plan->id);

            if (! in_array($lockedPlan->status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
                throw new RuntimeException("Application mapping plan [{$lockedPlan->id}] is not complete.");
            }

            $proposals = $lockedPlan->proposals()
                ->with(['legacyRecord', 'ownerMapping', 'businessMapping'])
                ->whereIn('id', $proposalIds)
                ->get();

            if ($proposals->count() !== count($proposalIds)) {
                throw new RuntimeException('Every selected proposal ID must belong to the exact application mapping plan.');
            }

            $this->assertExecutableSelection($proposals);

            if (! hash_equals($lockedPlan->dependency_snapshot_hash, $this->planner->dependencySnapshotHash($lockedPlan->importBatch))) {
                throw new RuntimeException("Application mapping plan [{$lockedPlan->id}] no longer matches its dependency snapshot.");
            }

            $execution = $lockedPlan->executions()->create([
                'run_reference' => $runReference,
                'selection_hash' => $selectionHash,
                'status' => LegacyMappingExecutionStatus::Executing,
                'selected_count' => $proposals->count(),
                'started_at' => now(),
                'metadata' => [
                    'proposal_ids' => $proposalIds,
                    'official_application_numbers_assigned' => false,
                    'application_actor_attribution_inferred' => false,
                    'downstream_records_created' => false,
                    'external_integrations' => false,
                    'notifications' => false,
                    'irreversible_actions' => false,
                ],
            ]);
            $counts = ['created' => 0, 'linked' => 0, 'reused' => 0, 'mappings' => 0];

            foreach ($proposals->sortBy('id') as $proposal) {
                $result = $this->executeProposal($execution, $proposal);
                $counts[$result]++;

                if ($result !== 'reused') {
                    $counts['mappings']++;
                }
            }

            $execution->update([
                'status' => LegacyMappingExecutionStatus::Completed,
                'created_count' => $counts['created'],
                'linked_count' => $counts['linked'],
                'reused_count' => $counts['reused'],
                'mapping_count' => $counts['mappings'],
                'completed_at' => now(),
            ]);

            return $execution->fresh(['mappingPlan.importBatch.source', 'mappings']) ?? $execution;
        }, 3);
    }

    /** @param Collection<int, LegacyApplicationMappingProposal> $proposals */
    private function assertExecutableSelection(Collection $proposals): void
    {
        foreach ($proposals as $proposal) {
            if ($proposal->status !== LegacyMappingProposalStatus::Ready) {
                throw new RuntimeException("Application mapping proposal [{$proposal->id}] is not ready and cannot execute.");
            }

            if (! in_array($proposal->proposed_action, [LegacyMappingProposalAction::Create, LegacyMappingProposalAction::LinkExactLegacyId], true)) {
                throw new RuntimeException("Application mapping proposal [{$proposal->id}] has no executable action.");
            }

            $this->resolveDependencies($proposal);
        }
    }

    private function executeProposal(LegacyApplicationMappingExecution $execution, LegacyApplicationMappingProposal $proposal): string
    {
        $record = $proposal->legacyRecord;
        $projection = $this->projectionFor($proposal);

        if ($projection['blocked']
            || ! hash_equals($proposal->projection_hash, $this->projector->hashCanonical($projection['attributes']))
            || ! hash_equals($proposal->identity_fingerprint, $this->projector->hashCanonical($projection['identity']))) {
            throw new RuntimeException("Application mapping proposal [{$proposal->id}] no longer matches its staged projection.");
        }

        $business = $this->resolveDependencies($proposal);
        $existingMapping = LegacyApplicationIdMapping::query()
            ->where('legacy_source_id', $record->legacy_source_id)
            ->where('dataset_key', $record->dataset_key)
            ->where('legacy_id', $record->legacy_id)
            ->first();

        if ($existingMapping instanceof LegacyApplicationIdMapping) {
            $target = $existingMapping->permitApplication()->first();

            if (! $target instanceof PermitApplication || $target->business_id !== $business->id) {
                throw new RuntimeException("Existing application mapping for proposal [{$proposal->id}] no longer resolves to the mapped business.");
            }

            return 'reused';
        }

        $created = $proposal->proposed_action === LegacyMappingProposalAction::Create;
        $target = $created
            ? $this->createTarget($business, $projection['attributes'], $execution, $proposal)
            : $this->exactTarget($proposal, $business);

        LegacyApplicationIdMapping::query()->create([
            'legacy_application_mapping_execution_id' => $execution->id,
            'legacy_source_id' => $record->legacy_source_id,
            'legacy_import_batch_id' => $record->legacy_import_batch_id,
            'permit_application_id' => $target->id,
            'dataset_key' => $record->dataset_key,
            'legacy_id' => $record->legacy_id,
            'status' => 'mapped',
            'mapping_basis' => $created ? 'approved_create_proposal' : 'exact_legacy_source_id',
            'metadata' => [
                'proposal_id' => $proposal->id,
                'created_by_execution' => $created,
                'projection_hash' => $proposal->projection_hash,
                'target_snapshot_hash' => $this->projector->targetSnapshotHash($target),
                'official_application_number_assigned' => false,
                'projection_mode' => $proposal->metadata['projection_mode'] ?? 'operational',
            ],
        ]);

        return $created ? 'created' : 'linked';
    }

    /** @return array<string, mixed> */
    private function projectionFor(LegacyApplicationMappingProposal $proposal): array
    {
        $mode = $proposal->metadata['projection_mode'] ?? 'operational';

        return match ($mode) {
            'operational' => $this->projector->project($proposal->legacyRecord),
            'historical_evidence' => $this->projector->projectHistoricalEvidence($proposal->legacyRecord),
            default => throw new RuntimeException("Application mapping proposal [{$proposal->id}] has an unsupported projection mode."),
        };
    }

    /** @param array<string, mixed> $attributes */
    private function createTarget(Business $business, array $attributes, LegacyApplicationMappingExecution $execution, LegacyApplicationMappingProposal $proposal): PermitApplication
    {
        if (($attributes['application_number'] ?? null) !== null || ($attributes['submitted_by_id'] ?? null) !== null) {
            throw new RuntimeException("Application mapping proposal [{$proposal->id}] attempts to infer numbering or actor authority.");
        }

        $metadata = is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [];

        return $this->statusMutation->createHistoricalMigrationProjection([
            ...$attributes,
            'business_id' => $business->id,
            'metadata' => [
                ...$metadata,
                'migration' => [
                    'schema_version' => 'bpls.legacy-application-migration.v1',
                    'execution_id' => $execution->id,
                    'proposal_id' => $proposal->id,
                    'projection_hash' => $proposal->projection_hash,
                ],
            ],
        ]);
    }

    private function exactTarget(LegacyApplicationMappingProposal $proposal, Business $business): PermitApplication
    {
        if ($proposal->target_id === null) {
            throw new RuntimeException("Exact-link application proposal [{$proposal->id}] has no target.");
        }

        $target = PermitApplication::query()->find($proposal->target_id);

        if (! $target instanceof PermitApplication
            || $target->legacy_source_id !== $proposal->legacyRecord->legacy_id
            || $target->business_id !== $business->id) {
            throw new RuntimeException("Exact-link application proposal [{$proposal->id}] no longer matches its target and mapped business.");
        }

        return $target;
    }

    private function resolveDependencies(LegacyApplicationMappingProposal $proposal): Business
    {
        $ownerMapping = $proposal->ownerMapping;
        $businessMapping = $proposal->businessMapping;

        if (! $ownerMapping instanceof LegacyIdMapping || ! $businessMapping instanceof LegacyIdMapping
            || $ownerMapping->status !== 'mapped' || $businessMapping->status !== 'mapped') {
            throw new RuntimeException("Application mapping proposal [{$proposal->id}] no longer has accepted registry mappings.");
        }

        $owner = BusinessOwner::query()->find($ownerMapping->target_id);
        $business = Business::query()->find($businessMapping->target_id);

        if (! $owner instanceof BusinessOwner || ! $business instanceof Business || $business->business_owner_id !== $owner->id) {
            throw new RuntimeException("Application mapping proposal [{$proposal->id}] no longer agrees with registry ownership.");
        }

        $projection = $this->projector->project($proposal->legacyRecord);

        if ($ownerMapping->legacy_source_id !== $proposal->legacyRecord->legacy_source_id
            || $ownerMapping->dataset_key !== 'business_owners'
            || $ownerMapping->legacy_id !== $projection['owner_legacy_id']
            || $businessMapping->legacy_source_id !== $proposal->legacyRecord->legacy_source_id
            || $businessMapping->dataset_key !== 'businesses'
            || $businessMapping->legacy_id !== $projection['business_legacy_id']) {
            throw new RuntimeException("Application mapping proposal [{$proposal->id}] registry mapping provenance changed after planning.");
        }

        return $business;
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy permit application execution is currently restricted to local and testing environments.');
        }
    }

    private function assertRunReference(string $runReference): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Application execution run reference must be 3-100 characters and contain only letters, numbers, dots, underscores, or hyphens.');
        }
    }
}
