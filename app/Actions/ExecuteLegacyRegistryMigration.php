<?php

namespace App\Actions;

use App\Enums\LegacyMappingExecutionStatus;
use App\Enums\LegacyMappingProposalAction;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyIdMapping;
use App\Models\LegacyMappingExecution;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMappingProposal;
use App\Models\LegacyRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExecuteLegacyRegistryMigration
{
    public function __construct(private LegacyRegistryMappingProjector $projector) {}

    /** @param list<int> $proposalIds */
    public function handle(LegacyMappingPlan $plan, array $proposalIds, string $runReference): LegacyMappingExecution
    {
        $this->assertEnvironment();
        $this->assertRunReference($runReference);
        $proposalIds = array_values(array_unique($proposalIds));
        sort($proposalIds);

        if ($proposalIds === []) {
            throw new RuntimeException('At least one exact mapping proposal ID is required.');
        }

        $selectionHash = hash('sha256', json_encode($proposalIds, JSON_THROW_ON_ERROR));
        $existing = $plan->executions()->where('run_reference', $runReference)->first();

        if ($existing instanceof LegacyMappingExecution) {
            if (! hash_equals($existing->selection_hash, $selectionHash)) {
                throw new RuntimeException("Registry execution run reference [{$runReference}] is already bound to a different proposal selection.");
            }

            if ($existing->status === LegacyMappingExecutionStatus::Completed) {
                return $existing->load(['mappingPlan.importBatch.source', 'mappings']);
            }

            if ($existing->status === LegacyMappingExecutionStatus::RolledBack) {
                throw new RuntimeException("Registry execution [{$runReference}] has already been rolled back and cannot execute again.");
            }

            throw new RuntimeException("Registry execution [{$runReference}] is not in a resumable state.");
        }

        return DB::transaction(function () use ($plan, $proposalIds, $runReference, $selectionHash): LegacyMappingExecution {
            $lockedPlan = LegacyMappingPlan::query()->lockForUpdate()->findOrFail($plan->id);

            if (! hash_equals($lockedPlan->registry_snapshot_hash, $this->projector->registrySnapshotHash())) {
                throw new RuntimeException("Registry mapping plan [{$lockedPlan->id}] no longer matches the current registry snapshot.");
            }

            $proposals = $lockedPlan->proposals()
                ->with('legacyRecord')
                ->whereIn('id', $proposalIds)
                ->get();

            if ($proposals->count() !== count($proposalIds)) {
                throw new RuntimeException('Every selected proposal ID must belong to the exact mapping plan.');
            }

            $this->assertExecutableSelection($proposals);
            $execution = $lockedPlan->executions()->create([
                'run_reference' => $runReference,
                'selection_hash' => $selectionHash,
                'status' => LegacyMappingExecutionStatus::Executing,
                'selected_count' => $proposals->count(),
                'started_at' => now(),
                'metadata' => [
                    'proposal_ids' => $proposalIds,
                    'external_integrations' => false,
                    'notifications' => false,
                    'irreversible_actions' => false,
                ],
            ]);
            $counts = ['created' => 0, 'linked' => 0, 'reused' => 0, 'mappings' => 0];

            foreach ($proposals->sortBy(fn (LegacyMappingProposal $proposal): int => $proposal->target_type === 'business_owner' ? 0 : 1) as $proposal) {
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

    /** @param Collection<int, LegacyMappingProposal> $proposals */
    private function assertExecutableSelection(Collection $proposals): void
    {
        $selectedRecordIds = $proposals->pluck('legacy_record_id')->all();

        foreach ($proposals as $proposal) {
            if ($proposal->status !== LegacyMappingProposalStatus::Ready) {
                throw new RuntimeException("Mapping proposal [{$proposal->id}] is not ready and cannot execute.");
            }

            if (! in_array($proposal->proposed_action, [LegacyMappingProposalAction::Create, LegacyMappingProposalAction::LinkExactLegacyId], true)) {
                throw new RuntimeException("Mapping proposal [{$proposal->id}] has no executable action.");
            }

            if ($proposal->target_type === 'business' && $proposal->parent_legacy_record_id !== null) {
                $parentAlreadyMapped = LegacyIdMapping::query()
                    ->where('legacy_source_id', $proposal->legacyRecord->legacy_source_id)
                    ->where('dataset_key', 'business_owners')
                    ->where('legacy_id', LegacyRecord::query()->findOrFail($proposal->parent_legacy_record_id)->legacy_id)
                    ->where('target_type', 'business_owner')
                    ->exists();

                if (! in_array($proposal->parent_legacy_record_id, $selectedRecordIds, true) && ! $parentAlreadyMapped) {
                    throw new RuntimeException("Business proposal [{$proposal->id}] requires its owner proposal in the same selection or an accepted owner mapping.");
                }
            }
        }
    }

    private function executeProposal(LegacyMappingExecution $execution, LegacyMappingProposal $proposal): string
    {
        $record = $proposal->legacyRecord;
        $projection = $proposal->target_type === 'business_owner'
            ? $this->projector->owner($record)
            : $this->projector->business($record);

        if (! hash_equals($proposal->projection_hash, $this->projector->hashCanonical($projection['attributes']))
            || ! hash_equals($proposal->identity_fingerprint, $this->projector->hashCanonical($projection['identity']))) {
            throw new RuntimeException("Mapping proposal [{$proposal->id}] no longer matches its staged projection.");
        }

        $existingMapping = LegacyIdMapping::query()
            ->where('legacy_source_id', $record->legacy_source_id)
            ->where('dataset_key', $record->dataset_key)
            ->where('legacy_id', $record->legacy_id)
            ->where('target_type', $proposal->target_type)
            ->first();

        if ($existingMapping instanceof LegacyIdMapping) {
            $this->resolveTarget($existingMapping->target_type, $existingMapping->target_id);

            return 'reused';
        }

        $created = $proposal->proposed_action === LegacyMappingProposalAction::Create;
        $target = $created
            ? $this->createTarget($proposal, $projection['attributes'])
            : $this->exactTarget($proposal, $record);

        LegacyIdMapping::query()->create([
            'legacy_mapping_execution_id' => $execution->id,
            'legacy_source_id' => $record->legacy_source_id,
            'legacy_import_batch_id' => $record->legacy_import_batch_id,
            'dataset_key' => $record->dataset_key,
            'entity_type' => $record->entity_type,
            'legacy_id' => $record->legacy_id,
            'target_type' => $proposal->target_type,
            'target_id' => $target->getKey(),
            'status' => 'mapped',
            'mapping_basis' => $created ? 'approved_create_proposal' : 'exact_legacy_source_id',
            'metadata' => [
                'proposal_id' => $proposal->id,
                'created_by_execution' => $created,
                'projection_hash' => $proposal->projection_hash,
                'target_snapshot_hash' => $this->projector->targetSnapshotHash($target),
            ],
        ]);

        return $created ? 'created' : 'linked';
    }

    /** @param array<string, mixed> $attributes */
    private function createTarget(LegacyMappingProposal $proposal, array $attributes): Model
    {
        if ($proposal->target_type === 'business_owner') {
            return BusinessOwner::query()->create($attributes);
        }

        $parentRecord = LegacyRecord::query()->findOrFail($proposal->parent_legacy_record_id);
        $ownerMapping = LegacyIdMapping::query()
            ->where('legacy_source_id', $parentRecord->legacy_source_id)
            ->where('dataset_key', $parentRecord->dataset_key)
            ->where('legacy_id', $parentRecord->legacy_id)
            ->where('target_type', 'business_owner')
            ->first();

        if (! $ownerMapping instanceof LegacyIdMapping) {
            throw new RuntimeException("Business proposal [{$proposal->id}] has no accepted owner mapping.");
        }

        return Business::query()->create(['business_owner_id' => $ownerMapping->target_id, ...$attributes]);
    }

    private function exactTarget(LegacyMappingProposal $proposal, LegacyRecord $record): Model
    {
        if ($proposal->target_id === null) {
            throw new RuntimeException("Exact-link proposal [{$proposal->id}] has no target.");
        }

        $target = $this->resolveTarget($proposal->target_type, $proposal->target_id);

        if ($target->legacy_source_id !== $record->legacy_id) {
            throw new RuntimeException("Exact-link proposal [{$proposal->id}] no longer matches the target legacy source ID.");
        }

        return $target;
    }

    private function resolveTarget(string $targetType, int $targetId): BusinessOwner|Business
    {
        $target = match ($targetType) {
            'business_owner' => BusinessOwner::query()->find($targetId),
            'business' => Business::query()->find($targetId),
            default => null,
        };

        if (! $target instanceof Model) {
            throw new RuntimeException("Mapped target [{$targetType}:{$targetId}] does not exist.");
        }

        return $target;
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy registry execution is currently restricted to local and testing environments.');
        }
    }

    private function assertRunReference(string $runReference): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Registry execution run reference must be 3-100 characters and contain only letters, numbers, dots, underscores, or hyphens.');
        }
    }
}
