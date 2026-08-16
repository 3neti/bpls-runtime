<?php

namespace App\Actions;

use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalAction;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyDeclarationMappingPlan;
use App\Models\LegacyIdMapping;
use App\Models\LegacyImportBatch;
use App\Models\LegacyRecord;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PlanLegacyPermitApplications
{
    public const PlannerVersion = 'bpls.application-mapping-plan.v1';

    public function __construct(private LegacyPermitApplicationProjector $projector) {}

    public function handle(LegacyImportBatch $batch, string $runReference): LegacyApplicationMappingPlan
    {
        $this->assertEnvironment();
        $this->assertRunReference($runReference);
        $datasetKey = $this->applicationDatasetKey($batch);
        $dependencySnapshotHash = $this->dependencySnapshotHash($batch);
        $plan = $this->resolvePlan($batch, $runReference, $dependencySnapshotHash, $datasetKey);

        if (in_array($plan->status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
            return $this->withEvidence($plan);
        }

        $this->resetPlan($plan);

        try {
            $this->planApplications($plan, $batch, $datasetKey);
            $this->completePlan($plan);
        } catch (Throwable $exception) {
            $plan->update([
                'status' => LegacyMappingPlanStatus::Failed,
                'completed_at' => now(),
                'metadata' => [
                    ...($plan->metadata ?? []),
                    'failure_type' => $exception::class,
                ],
            ]);

            throw $exception;
        }

        return $this->withEvidence($plan);
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy permit application planning is currently restricted to local and testing environments.');
        }
    }

    private function assertRunReference(string $runReference): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Application mapping plan run reference must be 3-100 characters and contain only letters, numbers, dots, underscores, or hyphens.');
        }
    }

    private function applicationDatasetKey(LegacyImportBatch $batch): string
    {
        if (! in_array($batch->status, [LegacyImportBatchStatus::Staged, LegacyImportBatchStatus::StagedWithExceptions], true)) {
            throw new RuntimeException("Legacy import batch [{$batch->id}] must finish staging before application planning.");
        }

        $available = collect(['applications', 'business_permit_applications'])
            ->filter(fn (string $key): bool => $batch->records()->where('dataset_key', $key)->exists())
            ->values();

        if ($available->count() !== 1) {
            throw new RuntimeException("Legacy import batch [{$batch->id}] must contain exactly one declared application dataset.");
        }

        return $available->sole();
    }

    private function resolvePlan(LegacyImportBatch $batch, string $runReference, string $snapshotHash, string $datasetKey): LegacyApplicationMappingPlan
    {
        return DB::transaction(function () use ($batch, $runReference, $snapshotHash, $datasetKey): LegacyApplicationMappingPlan {
            $plan = $batch->applicationMappingPlans()
                ->where('run_reference', $runReference)
                ->lockForUpdate()
                ->first();

            if ($plan instanceof LegacyApplicationMappingPlan) {
                if (! hash_equals($plan->dependency_snapshot_hash, $snapshotHash)) {
                    throw new RuntimeException("Application mapping plan run reference [{$runReference}] is already bound to different registry mappings or application records.");
                }

                if ($plan->planner_version !== self::PlannerVersion) {
                    throw new RuntimeException("Application mapping plan run reference [{$runReference}] was created by a different planner version.");
                }

                return $plan;
            }

            return $batch->applicationMappingPlans()->create([
                'run_reference' => $runReference,
                'planner_version' => self::PlannerVersion,
                'dependency_snapshot_hash' => $snapshotHash,
                'status' => LegacyMappingPlanStatus::Planning,
                'started_at' => now(),
                'metadata' => [
                    'application_dataset_key' => $datasetKey,
                    'domain_writes' => false,
                    'accepted_id_mappings' => false,
                    'official_application_number_authority' => 'unresolved',
                ],
            ]);
        });
    }

    private function resetPlan(LegacyApplicationMappingPlan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $plan->proposals()->delete();
            $plan->update([
                'status' => LegacyMappingPlanStatus::Planning,
                'proposal_count' => 0,
                'ready_count' => 0,
                'review_count' => 0,
                'blocked_count' => 0,
                'exact_link_count' => 0,
                'started_at' => now(),
                'completed_at' => null,
            ]);
        });
    }

    private function planApplications(LegacyApplicationMappingPlan $plan, LegacyImportBatch $batch, string $datasetKey): void
    {
        foreach ($batch->records()->where('dataset_key', $datasetKey)->orderBy('id')->cursor() as $record) {
            $projection = $this->projector->project($record);
            $reasons = $projection['reasons'];

            if (in_array('line_of_business_mapping_required', $reasons, true) && $this->declarationsAreReady($batch, $record)) {
                $reasons = array_values(array_diff($reasons, ['line_of_business_mapping_required']));
            }

            $blocked = $projection['blocked'];
            $ownerMapping = $this->mapping($batch, 'business_owners', $projection['owner_legacy_id'], 'business_owner');
            $businessMapping = $this->mapping($batch, 'businesses', $projection['business_legacy_id'], 'business');

            if (! $ownerMapping instanceof LegacyIdMapping) {
                $reasons[] = 'accepted_owner_mapping_missing';
                $blocked = true;
            }

            if (! $businessMapping instanceof LegacyIdMapping) {
                $reasons[] = 'accepted_business_mapping_missing';
                $blocked = true;
            }

            $owner = $ownerMapping instanceof LegacyIdMapping ? BusinessOwner::query()->find($ownerMapping->target_id) : null;
            $business = $businessMapping instanceof LegacyIdMapping ? Business::query()->find($businessMapping->target_id) : null;

            if ($ownerMapping instanceof LegacyIdMapping && ! $owner instanceof BusinessOwner) {
                $reasons[] = 'mapped_owner_target_missing';
                $blocked = true;
            }

            if ($businessMapping instanceof LegacyIdMapping && ! $business instanceof Business) {
                $reasons[] = 'mapped_business_target_missing';
                $blocked = true;
            }

            if ($owner instanceof BusinessOwner && $business instanceof Business && $business->business_owner_id !== $owner->id) {
                $reasons[] = 'mapped_business_owner_mismatch';
                $blocked = true;
            }

            $exactTargets = PermitApplication::query()->where('legacy_source_id', $record->legacy_id)->pluck('id')->all();
            $targetId = count($exactTargets) === 1 ? $exactTargets[0] : null;

            if (count($exactTargets) > 1) {
                $reasons[] = 'duplicate_exact_application_legacy_id_target';
            }

            if ($targetId !== null && $business instanceof Business) {
                $target = PermitApplication::query()->find($targetId);

                if (! $target instanceof PermitApplication || $target->business_id !== $business->id) {
                    $reasons[] = 'exact_application_business_mismatch';
                }
            }

            $collisionFingerprints = [];

            if ($projection['source_application_number'] !== null) {
                $numberFingerprint = hash('sha256', 'application_number|'.mb_strtolower($projection['source_application_number']));
                $otherTargets = PermitApplication::query()
                    ->where('application_number', $projection['source_application_number'])
                    ->when($targetId !== null, fn ($query) => $query->whereKeyNot($targetId))
                    ->exists();

                if ($otherTargets) {
                    $reasons[] = 'potential_existing_application_number_collision';
                    $collisionFingerprints['application_number'] = $numberFingerprint;
                }
            }

            [$status, $action] = $this->proposalState($blocked, $reasons, $targetId !== null);
            $plan->proposals()->create([
                'legacy_record_id' => $record->id,
                'owner_mapping_id' => $ownerMapping?->id,
                'business_mapping_id' => $businessMapping?->id,
                'target_id' => $targetId,
                'proposed_action' => $action,
                'status' => $status,
                'identity_fingerprint' => $this->projector->hashCanonical($projection['identity']),
                'projection_hash' => $this->projector->hashCanonical($projection['attributes']),
                'collision_fingerprints' => $collisionFingerprints,
                'reasons' => array_values(array_unique($reasons)),
                'metadata' => [
                    'legacy_id_sha256' => hash('sha256', $record->legacy_id),
                    'owner_legacy_id_sha256' => $projection['owner_legacy_id'] === null ? null : hash('sha256', $projection['owner_legacy_id']),
                    'business_legacy_id_sha256' => $projection['business_legacy_id'] === null ? null : hash('sha256', $projection['business_legacy_id']),
                    'source_application_number_sha256' => $projection['source_application_number'] === null ? null : hash('sha256', $projection['source_application_number']),
                    'projected_fields' => array_keys($projection['attributes']),
                    'official_application_number_projected' => false,
                    'domain_writes' => false,
                ],
            ]);
        }
    }

    private function mapping(LegacyImportBatch $batch, string $datasetKey, ?string $legacyId, string $targetType): ?LegacyIdMapping
    {
        if ($legacyId === null) {
            return null;
        }

        return LegacyIdMapping::query()
            ->where('legacy_source_id', $batch->legacy_source_id)
            ->where('dataset_key', $datasetKey)
            ->where('legacy_id', $legacyId)
            ->where('target_type', $targetType)
            ->where('status', 'mapped')
            ->first();
    }

    private function declarationsAreReady(LegacyImportBatch $batch, LegacyRecord $record): bool
    {
        $lines = $record->payload['linesOfBusiness'] ?? null;

        if (! is_array($lines) || $lines === []) {
            return false;
        }

        $plan = LegacyDeclarationMappingPlan::query()
            ->whereBelongsTo($batch, 'importBatch')
            ->whereIn('status', [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions])
            ->latest('id')
            ->first();

        if (! $plan instanceof LegacyDeclarationMappingPlan) {
            return false;
        }

        $proposals = $plan->proposals()->where('legacy_record_id', $record->id);

        return $proposals->count() === count($lines)
            && ! $proposals->where('status', '!=', LegacyMappingProposalStatus::Ready)->exists();
    }

    /** @param list<string> $reasons
     * @return array{LegacyMappingProposalStatus, LegacyMappingProposalAction}
     */
    private function proposalState(bool $blocked, array $reasons, bool $hasExactTarget): array
    {
        if ($blocked) {
            return [LegacyMappingProposalStatus::Blocked, LegacyMappingProposalAction::Blocked];
        }

        if ($reasons !== []) {
            return [LegacyMappingProposalStatus::ReviewRequired, LegacyMappingProposalAction::Review];
        }

        if ($hasExactTarget) {
            return [LegacyMappingProposalStatus::Ready, LegacyMappingProposalAction::LinkExactLegacyId];
        }

        return [LegacyMappingProposalStatus::Ready, LegacyMappingProposalAction::Create];
    }

    private function completePlan(LegacyApplicationMappingPlan $plan): void
    {
        $proposalCount = $plan->proposals()->count();
        $readyCount = $plan->proposals()->where('status', LegacyMappingProposalStatus::Ready)->count();
        $reviewCount = $plan->proposals()->where('status', LegacyMappingProposalStatus::ReviewRequired)->count();
        $blockedCount = $plan->proposals()->where('status', LegacyMappingProposalStatus::Blocked)->count();
        $exactLinkCount = $plan->proposals()->where('proposed_action', LegacyMappingProposalAction::LinkExactLegacyId)->count();

        $plan->update([
            'status' => $reviewCount > 0 || $blockedCount > 0
                ? LegacyMappingPlanStatus::PlannedWithExceptions
                : LegacyMappingPlanStatus::Planned,
            'proposal_count' => $proposalCount,
            'ready_count' => $readyCount,
            'review_count' => $reviewCount,
            'blocked_count' => $blockedCount,
            'exact_link_count' => $exactLinkCount,
            'completed_at' => now(),
            'metadata' => [
                ...($plan->metadata ?? []),
                'domain_writes' => false,
                'accepted_id_mappings' => false,
                'payloads_in_report' => false,
            ],
        ]);
    }

    public function dependencySnapshotHash(LegacyImportBatch $batch): string
    {
        $context = hash_init('sha256');

        foreach (LegacyIdMapping::query()->where('legacy_source_id', $batch->legacy_source_id)->whereIn('target_type', ['business_owner', 'business'])->orderBy('id')->cursor() as $mapping) {
            hash_update($context, json_encode([
                $mapping->id,
                $mapping->dataset_key,
                hash('sha256', $mapping->legacy_id),
                $mapping->target_type,
                $mapping->target_id,
                $mapping->status,
                $mapping->updated_at?->toJSON(),
            ], JSON_THROW_ON_ERROR));
        }

        foreach (LegacyApplicationIdMapping::query()->whereBelongsTo($batch, 'importBatch')->orderBy('id')->cursor() as $mapping) {
            hash_update($context, json_encode([
                'application_mapping',
                $mapping->id,
                hash('sha256', $mapping->legacy_id),
                $mapping->permit_application_id,
                $mapping->status,
                $mapping->updated_at?->toJSON(),
            ], JSON_THROW_ON_ERROR));
        }

        foreach (BusinessOwner::query()->select(['id', 'name', 'email', 'phone', 'legacy_source_id', 'updated_at'])->orderBy('id')->cursor() as $owner) {
            hash_update($context, json_encode(['owner', ...$owner->getAttributes()], JSON_THROW_ON_ERROR));
        }

        foreach (Business::query()->select(['id', 'business_owner_id', 'name', 'registration_number', 'legacy_source_id', 'updated_at'])->orderBy('id')->cursor() as $business) {
            hash_update($context, json_encode(['business', ...$business->getAttributes()], JSON_THROW_ON_ERROR));
        }

        foreach (PermitApplication::query()->select(['id', 'business_id', 'application_number', 'legacy_source_id', 'updated_at'])->orderBy('id')->cursor() as $application) {
            hash_update($context, json_encode(['application', ...$application->getAttributes()], JSON_THROW_ON_ERROR));
        }

        foreach (LegacyDeclarationMappingPlan::query()->whereBelongsTo($batch, 'importBatch')->with('proposals')->orderBy('id')->cursor() as $plan) {
            hash_update($context, json_encode([
                'declaration_plan', $plan->id, $plan->dependency_snapshot_hash, $plan->status->value,
                $plan->proposals->map(fn ($proposal): array => [$proposal->id, $proposal->legacy_record_id, $proposal->line_index, $proposal->status->value, $proposal->projection_hash])->all(),
            ], JSON_THROW_ON_ERROR));
        }

        return hash_final($context);
    }

    private function withEvidence(LegacyApplicationMappingPlan $plan): LegacyApplicationMappingPlan
    {
        return $plan->fresh(['importBatch.source', 'proposals']) ?? $plan;
    }
}
