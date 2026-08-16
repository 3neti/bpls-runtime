<?php

namespace App\Actions;

use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalAction;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PlanLegacyRegistryMigration
{
    public const PlannerVersion = 'bpls.registry-mapping-plan.v1';

    public function __construct(private LegacyRegistryMappingProjector $projector) {}

    public function handle(LegacyImportBatch $batch, string $runReference): LegacyMappingPlan
    {
        $this->assertRunReference($runReference);
        $this->assertBatchCanBePlanned($batch);
        $registrySnapshotHash = $this->projector->registrySnapshotHash();
        $plan = $this->resolvePlan($batch, $runReference, $registrySnapshotHash);

        if (in_array($plan->status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
            return $this->withEvidence($plan);
        }

        $this->resetPlan($plan);

        try {
            $ownerAnalysis = $this->ownerAnalysis($batch);
            $ownerProposals = $this->planOwners($plan, $batch, $ownerAnalysis);
            $businessAnalysis = $this->businessAnalysis($batch);
            $this->planBusinesses($plan, $batch, $ownerProposals, $businessAnalysis);
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

    private function assertBatchCanBePlanned(LegacyImportBatch $batch): void
    {
        if (! in_array($batch->status, [LegacyImportBatchStatus::Staged, LegacyImportBatchStatus::StagedWithExceptions], true)) {
            throw new RuntimeException("Legacy import batch [{$batch->id}] must finish staging before registry planning.");
        }

        foreach (['business_owners', 'businesses'] as $datasetKey) {
            if (! $batch->records()->where('dataset_key', $datasetKey)->exists()) {
                throw new RuntimeException("Legacy import batch [{$batch->id}] has no staged [{$datasetKey}] dataset.");
            }
        }
    }

    private function assertRunReference(string $runReference): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Mapping plan run reference must be 3-100 characters and contain only letters, numbers, dots, underscores, or hyphens.');
        }
    }

    private function resolvePlan(LegacyImportBatch $batch, string $runReference, string $registrySnapshotHash): LegacyMappingPlan
    {
        return DB::transaction(function () use ($batch, $runReference, $registrySnapshotHash): LegacyMappingPlan {
            $plan = LegacyMappingPlan::query()
                ->whereBelongsTo($batch, 'importBatch')
                ->where('run_reference', $runReference)
                ->lockForUpdate()
                ->first();

            if ($plan instanceof LegacyMappingPlan) {
                if (! hash_equals($plan->registry_snapshot_hash, $registrySnapshotHash)) {
                    throw new RuntimeException("Mapping plan run reference [{$runReference}] is already bound to a different registry snapshot.");
                }

                if ($plan->planner_version !== self::PlannerVersion) {
                    throw new RuntimeException("Mapping plan run reference [{$runReference}] was created by a different planner version.");
                }

                return $plan;
            }

            return $batch->mappingPlans()->create([
                'run_reference' => $runReference,
                'planner_version' => self::PlannerVersion,
                'registry_snapshot_hash' => $registrySnapshotHash,
                'status' => LegacyMappingPlanStatus::Planning,
                'started_at' => now(),
                'metadata' => [
                    'domain_writes' => false,
                    'accepted_id_mappings' => false,
                    'identity_similarity_is_authority' => false,
                ],
            ]);
        });
    }

    private function resetPlan(LegacyMappingPlan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $plan->proposals()->delete();
            $plan->update([
                'status' => LegacyMappingPlanStatus::Planning,
                'owner_proposal_count' => 0,
                'business_proposal_count' => 0,
                'ready_count' => 0,
                'review_count' => 0,
                'blocked_count' => 0,
                'exact_link_count' => 0,
                'started_at' => now(),
                'completed_at' => null,
            ]);
        });
    }

    /**
     * @return array{
     *   source_signal_counts: array<string, int>,
     *   existing_signal_targets: array<string, list<int>>,
     *   exact_legacy_targets: array<string, list<int>>
     * }
     */
    private function ownerAnalysis(LegacyImportBatch $batch): array
    {
        $sourceSignalCounts = [];

        foreach ($this->records($batch, 'business_owners') as $record) {
            foreach ($this->ownerSignals($record->payload) as $signal) {
                $sourceSignalCounts[$signal] = ($sourceSignalCounts[$signal] ?? 0) + 1;
            }
        }

        $existingSignalTargets = [];
        $exactLegacyTargets = [];

        foreach (BusinessOwner::query()->select(['id', 'name', 'email', 'phone', 'legacy_source_id'])->orderBy('id')->cursor() as $owner) {
            if (is_string($owner->legacy_source_id) && $owner->legacy_source_id !== '') {
                $exactLegacyTargets[$owner->legacy_source_id][] = $owner->id;
            }

            foreach ($this->runtimeOwnerSignals($owner) as $signal) {
                $existingSignalTargets[$signal][] = $owner->id;
            }
        }

        return [
            'source_signal_counts' => $sourceSignalCounts,
            'existing_signal_targets' => $existingSignalTargets,
            'exact_legacy_targets' => $exactLegacyTargets,
        ];
    }

    /**
     * @param  array{
     *   source_signal_counts: array<string, int>,
     *   existing_signal_targets: array<string, list<int>>,
     *   exact_legacy_targets: array<string, list<int>>
     * }  $analysis
     * @return array<string, array{proposal_id: int, record_id: int, status: LegacyMappingProposalStatus, target_id: int|null}>
     */
    private function planOwners(LegacyMappingPlan $plan, LegacyImportBatch $batch, array $analysis): array
    {
        $proposalsByLegacyId = [];

        foreach ($this->records($batch, 'business_owners') as $record) {
            $projection = $this->projector->owner($record);
            $signals = $this->ownerSignals($record->payload);
            $reasons = $projection['reasons'];
            $collisionFingerprints = [];
            $exactTargets = $analysis['exact_legacy_targets'][$record->legacy_id] ?? [];

            if (count($exactTargets) > 1) {
                $reasons[] = 'duplicate_exact_legacy_id_target';
            }

            foreach ($signals as $signalName => $fingerprint) {
                if (($analysis['source_signal_counts'][$fingerprint] ?? 0) > 1) {
                    $reasons[] = 'potential_source_owner_collision';
                    $collisionFingerprints[$signalName] = $fingerprint;
                }

                $otherExistingTargets = array_diff(
                    $analysis['existing_signal_targets'][$fingerprint] ?? [],
                    count($exactTargets) === 1 ? $exactTargets : [],
                );

                if ($otherExistingTargets !== []) {
                    $reasons[] = 'potential_existing_owner_collision';
                    $collisionFingerprints[$signalName] = $fingerprint;
                }
            }

            $targetId = count($exactTargets) === 1 ? $exactTargets[0] : null;
            [$status, $action] = $this->proposalState($projection['blocked'], $reasons, $targetId !== null);
            $proposal = $plan->proposals()->create([
                'legacy_record_id' => $record->id,
                'parent_legacy_record_id' => null,
                'dataset_key' => $record->dataset_key,
                'entity_type' => $record->entity_type,
                'target_type' => 'business_owner',
                'target_id' => $targetId,
                'proposed_action' => $action,
                'status' => $status,
                'identity_fingerprint' => $this->projector->hashCanonical($projection['identity']),
                'projection_hash' => $this->projector->hashCanonical($projection['attributes']),
                'collision_fingerprints' => $collisionFingerprints,
                'reasons' => array_values(array_unique($reasons)),
                'metadata' => [
                    'projected_fields' => array_keys($projection['attributes']),
                    'exact_legacy_id_match' => $targetId !== null,
                    'legacy_id_sha256' => hash('sha256', $record->legacy_id),
                ],
            ]);

            $proposalsByLegacyId[$record->legacy_id] = [
                'proposal_id' => $proposal->id,
                'record_id' => $record->id,
                'status' => $status,
                'target_id' => $targetId,
            ];
        }

        return $proposalsByLegacyId;
    }

    /**
     * @return array{
     *   source_signal_counts: array<string, int>,
     *   existing_registration_targets: array<string, list<int>>,
     *   existing_owner_name_targets: array<string, list<int>>,
     *   exact_legacy_targets: array<string, list<array{id: int, business_owner_id: int}>>
     * }
     */
    private function businessAnalysis(LegacyImportBatch $batch): array
    {
        $sourceSignalCounts = [];

        foreach ($this->records($batch, 'businesses') as $record) {
            foreach ($this->businessSignals($record->payload) as $signal) {
                $sourceSignalCounts[$signal] = ($sourceSignalCounts[$signal] ?? 0) + 1;
            }
        }

        $existingRegistrationTargets = [];
        $existingOwnerNameTargets = [];
        $exactLegacyTargets = [];

        foreach (Business::query()->select(['id', 'business_owner_id', 'name', 'registration_number', 'legacy_source_id'])->orderBy('id')->cursor() as $business) {
            if (is_string($business->legacy_source_id) && $business->legacy_source_id !== '') {
                $exactLegacyTargets[$business->legacy_source_id][] = [
                    'id' => $business->id,
                    'business_owner_id' => $business->business_owner_id,
                ];
            }

            if (is_string($business->registration_number) && $this->normalize($business->registration_number) !== '') {
                $fingerprint = $this->signal('registration', $business->registration_number);
                $existingRegistrationTargets[$fingerprint][] = $business->id;
            }

            $ownerNameFingerprint = $this->signal('owner_name', $business->business_owner_id.'|'.$business->name);
            $existingOwnerNameTargets[$ownerNameFingerprint][] = $business->id;
        }

        return [
            'source_signal_counts' => $sourceSignalCounts,
            'existing_registration_targets' => $existingRegistrationTargets,
            'existing_owner_name_targets' => $existingOwnerNameTargets,
            'exact_legacy_targets' => $exactLegacyTargets,
        ];
    }

    /**
     * @param  array<string, array{proposal_id: int, record_id: int, status: LegacyMappingProposalStatus, target_id: int|null}>  $ownerProposals
     * @param  array{
     *   source_signal_counts: array<string, int>,
     *   existing_registration_targets: array<string, list<int>>,
     *   existing_owner_name_targets: array<string, list<int>>,
     *   exact_legacy_targets: array<string, list<array{id: int, business_owner_id: int}>>
     * }  $analysis
     */
    private function planBusinesses(LegacyMappingPlan $plan, LegacyImportBatch $batch, array $ownerProposals, array $analysis): void
    {
        foreach ($this->records($batch, 'businesses') as $record) {
            $projection = $this->projector->business($record);
            $ownerLegacyId = $projection['owner_legacy_id'];
            $ownerProposal = $ownerLegacyId === null ? null : ($ownerProposals[$ownerLegacyId] ?? null);
            $reasons = $projection['reasons'];
            $blocked = $projection['blocked'];
            $collisionFingerprints = [];
            $exactTargets = $analysis['exact_legacy_targets'][$record->legacy_id] ?? [];
            $exactTargetIds = array_column($exactTargets, 'id');

            if ($ownerProposal === null) {
                $blocked = true;
                $reasons[] = 'owner_mapping_proposal_missing';
            } elseif ($ownerProposal['status'] !== LegacyMappingProposalStatus::Ready) {
                $blocked = true;
                $reasons[] = 'owner_mapping_proposal_not_ready';
            }

            foreach ($this->businessSignals($record->payload) as $signalName => $fingerprint) {
                if (($analysis['source_signal_counts'][$fingerprint] ?? 0) > 1) {
                    $reasons[] = 'potential_source_business_collision';
                    $collisionFingerprints[$signalName] = $fingerprint;
                }

                $otherExistingTargets = array_diff($analysis['existing_registration_targets'][$fingerprint] ?? [], $exactTargetIds);

                if ($signalName === 'registration' && $otherExistingTargets !== []) {
                    $reasons[] = 'potential_existing_business_collision';
                    $collisionFingerprints[$signalName] = $fingerprint;
                }
            }

            if ($ownerProposal !== null && $ownerProposal['target_id'] !== null) {
                $ownerNameFingerprint = $this->signal('owner_name', $ownerProposal['target_id'].'|'.($record->payload['name'] ?? ''));

                $otherOwnerNameTargets = array_diff($analysis['existing_owner_name_targets'][$ownerNameFingerprint] ?? [], $exactTargetIds);

                if ($otherOwnerNameTargets !== []) {
                    $reasons[] = 'potential_existing_business_collision';
                    $collisionFingerprints['owner_name'] = $ownerNameFingerprint;
                }
            }

            $targetId = null;

            if (count($exactTargets) > 1) {
                $reasons[] = 'duplicate_exact_legacy_id_target';
            } elseif (count($exactTargets) === 1) {
                $targetId = $exactTargets[0]['id'];

                if ($ownerProposal === null || $ownerProposal['target_id'] === null) {
                    $reasons[] = 'exact_business_owner_mapping_not_established';
                } elseif ($exactTargets[0]['business_owner_id'] !== $ownerProposal['target_id']) {
                    $reasons[] = 'exact_business_owner_mismatch';
                }
            }

            [$status, $action] = $this->proposalState($blocked, $reasons, $targetId !== null);
            $plan->proposals()->create([
                'legacy_record_id' => $record->id,
                'parent_legacy_record_id' => $ownerProposal['record_id'] ?? null,
                'dataset_key' => $record->dataset_key,
                'entity_type' => $record->entity_type,
                'target_type' => 'business',
                'target_id' => $targetId,
                'proposed_action' => $action,
                'status' => $status,
                'identity_fingerprint' => $this->projector->hashCanonical($projection['identity']),
                'projection_hash' => $this->projector->hashCanonical($projection['attributes']),
                'collision_fingerprints' => $collisionFingerprints,
                'reasons' => array_values(array_unique($reasons)),
                'metadata' => [
                    'projected_fields' => array_keys($projection['attributes']),
                    'exact_legacy_id_match' => $targetId !== null,
                    'legacy_id_sha256' => hash('sha256', $record->legacy_id),
                    'owner_legacy_id_sha256' => $ownerLegacyId === null ? null : hash('sha256', $ownerLegacyId),
                    'owner_proposal_id' => $ownerProposal['proposal_id'] ?? null,
                ],
            ]);
        }
    }

    private function completePlan(LegacyMappingPlan $plan): void
    {
        $ownerProposalCount = $plan->proposals()->where('target_type', 'business_owner')->count();
        $businessProposalCount = $plan->proposals()->where('target_type', 'business')->count();
        $readyCount = $plan->proposals()->where('status', LegacyMappingProposalStatus::Ready)->count();
        $reviewCount = $plan->proposals()->where('status', LegacyMappingProposalStatus::ReviewRequired)->count();
        $blockedCount = $plan->proposals()->where('status', LegacyMappingProposalStatus::Blocked)->count();
        $exactLinkCount = $plan->proposals()->where('proposed_action', LegacyMappingProposalAction::LinkExactLegacyId)->count();

        $plan->update([
            'status' => $reviewCount > 0 || $blockedCount > 0
                ? LegacyMappingPlanStatus::PlannedWithExceptions
                : LegacyMappingPlanStatus::Planned,
            'owner_proposal_count' => $ownerProposalCount,
            'business_proposal_count' => $businessProposalCount,
            'ready_count' => $readyCount,
            'review_count' => $reviewCount,
            'blocked_count' => $blockedCount,
            'exact_link_count' => $exactLinkCount,
            'completed_at' => now(),
            'metadata' => [
                ...($plan->metadata ?? []),
                'proposal_count' => $ownerProposalCount + $businessProposalCount,
                'domain_writes' => false,
                'accepted_id_mappings' => false,
                'payloads_in_report' => false,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function ownerSignals(array $payload): array
    {
        $name = Str::of(implode(' ', array_filter([
            $this->string($payload, 'firstName'),
            $this->string($payload, 'middleName'),
            $this->string($payload, 'lastName'),
        ])))->squish()->toString();
        $signals = [];

        foreach ([
            'tin' => $this->string($payload, 'tin'),
            'email' => $this->string($payload, 'email'),
            'phone' => $this->normalizePhone($this->string($payload, 'mobile')),
            'name_birth' => $name.'|'.$this->string($payload, 'birthDate'),
        ] as $type => $value) {
            if ($this->normalize($value) !== '' && $value !== '|') {
                $signals[$type] = $this->signal($type, $value);
            }
        }

        return $signals;
    }

    /** @return array<string, string> */
    private function runtimeOwnerSignals(BusinessOwner $owner): array
    {
        $signals = [];

        foreach (['email' => $owner->email, 'phone' => $this->normalizePhone($owner->phone ?? '')] as $type => $value) {
            if (is_string($value) && $this->normalize($value) !== '') {
                $signals[$type] = $this->signal($type, $value);
            }
        }

        return $signals;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function businessSignals(array $payload): array
    {
        $signals = [];
        $registrationNumber = $this->string($payload, 'registrationNumber');
        $ownerLegacyId = $this->string($payload, 'ownerId');
        $name = $this->string($payload, 'name');

        if ($registrationNumber !== '') {
            $signals['registration'] = $this->signal('registration', $registrationNumber);
        }

        if ($ownerLegacyId !== '' && $name !== '') {
            $signals['owner_name'] = $this->signal('owner_name', $ownerLegacyId.'|'.$name);
        }

        return $signals;
    }

    /** @return iterable<int, LegacyRecord> */
    private function records(LegacyImportBatch $batch, string $datasetKey): iterable
    {
        return LegacyRecord::query()
            ->whereBelongsTo($batch, 'importBatch')
            ->where('dataset_key', $datasetKey)
            ->select(['id', 'dataset_key', 'entity_type', 'legacy_id', 'payload'])
            ->orderBy('id')
            ->cursor();
    }

    /**
     * @param  list<string>  $reasons
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

    private function signal(string $type, string $value): string
    {
        return hash('sha256', $type.'|'.$this->normalize($value));
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->squish()->lower()->toString();
    }

    private function normalizePhone(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    /** @param array<string, mixed> $payload */
    private function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) || is_int($value) ? Str::of((string) $value)->trim()->toString() : '';
    }

    private function withEvidence(LegacyMappingPlan $plan): LegacyMappingPlan
    {
        return $plan->fresh(['importBatch.source', 'proposals']) ?? $plan;
    }
}
