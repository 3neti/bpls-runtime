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

    public function handle(LegacyImportBatch $batch, string $runReference): LegacyMappingPlan
    {
        $this->assertRunReference($runReference);
        $this->assertBatchCanBePlanned($batch);
        $registrySnapshotHash = $this->registrySnapshotHash();
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
            $projection = $this->ownerProjection($record);
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
                'identity_fingerprint' => $this->hashCanonical($projection['identity']),
                'projection_hash' => $this->hashCanonical($projection['attributes']),
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
            $projection = $this->businessProjection($record);
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
                'identity_fingerprint' => $this->hashCanonical($projection['identity']),
                'projection_hash' => $this->hashCanonical($projection['attributes']),
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
     * @return array{attributes: array<string, mixed>, identity: array<string, mixed>, reasons: list<string>, blocked: bool}
     */
    private function ownerProjection(LegacyRecord $record): array
    {
        $payload = $record->payload;
        $firstName = $this->string($payload, 'firstName');
        $middleName = $this->string($payload, 'middleName');
        $lastName = $this->string($payload, 'lastName');
        $ownerType = $this->string($payload, 'ownerType');
        $groupName = $this->string($payload, 'groupName');
        $individualName = Str::of(implode(' ', array_filter([$firstName, $middleName, $lastName])))->squish()->toString();
        $name = $ownerType === 'Group' && $groupName !== '' ? $groupName : $individualName;
        $reasons = [];
        $blocked = false;

        if ($name === '') {
            $reasons[] = 'required_owner_name_missing';
            $blocked = true;
        }

        if ($ownerType === 'Group') {
            $reasons[] = 'group_owner_semantics_require_reconciliation';
        }

        if (($payload['isDeleted'] ?? false) === true) {
            $reasons[] = 'soft_deleted_record_policy_unresolved';
        }

        if (($payload['isBlacklisted'] ?? false) === true || $this->string($payload, 'blacklistReason') !== '') {
            $reasons[] = 'blacklist_state_requires_registry_policy';
        }

        $attributes = [
            'name' => $name,
            'email' => $this->stringOrNull($payload, 'email'),
            'phone' => $this->stringOrNull($payload, 'mobile'),
            'address' => $this->stringOrNull($payload, 'address'),
            'legacy_source_id' => $record->legacy_id,
            'metadata' => [
                'legacy_owner_type' => $ownerType !== '' ? $ownerType : null,
                'legacy_group_name' => $groupName !== '' ? $groupName : null,
                'legacy_birth_date' => $this->stringOrNull($payload, 'birthDate'),
                'legacy_civil_status' => $this->stringOrNull($payload, 'civilStatus'),
                'legacy_gender' => $this->stringOrNull($payload, 'gender'),
                'legacy_citizenship' => $this->stringOrNull($payload, 'citizenship'),
                'legacy_tin' => $this->stringOrNull($payload, 'tin'),
                'legacy_location_ids_preserved' => array_filter([
                    'provinceId' => $this->stringOrNull($payload, 'provinceId'),
                    'cityId' => $this->stringOrNull($payload, 'cityId'),
                    'barangayId' => $this->stringOrNull($payload, 'barangayId'),
                ]),
            ],
        ];

        return [
            'attributes' => $attributes,
            'identity' => [
                'name' => $this->normalize($name),
                'birth_date' => $this->normalize($this->string($payload, 'birthDate')),
                'tin' => $this->normalize($this->string($payload, 'tin')),
                'email' => $this->normalize($this->string($payload, 'email')),
                'phone' => $this->normalizePhone($this->string($payload, 'mobile')),
            ],
            'reasons' => $reasons,
            'blocked' => $blocked,
        ];
    }

    /**
     * @return array{attributes: array<string, mixed>, identity: array<string, mixed>, reasons: list<string>, blocked: bool, owner_legacy_id: string|null}
     */
    private function businessProjection(LegacyRecord $record): array
    {
        $payload = $record->payload;
        $name = $this->string($payload, 'name');
        $ownerLegacyId = $this->stringOrNull($payload, 'ownerId');
        $reasons = [];
        $blocked = false;

        if ($name === '') {
            $reasons[] = 'required_business_name_missing';
            $blocked = true;
        }

        if ($ownerLegacyId === null) {
            $reasons[] = 'required_business_owner_reference_missing';
            $blocked = true;
        }

        foreach (['provinceId', 'cityId', 'barangayId', 'categoryId', 'subCategoryId'] as $referenceField) {
            if ($this->string($payload, $referenceField) !== '') {
                $reasons[] = 'reference_data_mapping_required';
                break;
            }
        }

        if (($payload['isDeleted'] ?? false) === true) {
            $reasons[] = 'soft_deleted_record_policy_unresolved';
        }

        if (($payload['isBlacklisted'] ?? false) === true || $this->string($payload, 'blacklistReason') !== '') {
            $reasons[] = 'blacklist_state_requires_registry_policy';
        }

        $attributes = [
            'name' => $name,
            'registration_number' => $this->stringOrNull($payload, 'registrationNumber'),
            'address' => $this->stringOrNull($payload, 'address'),
            'barangay' => $this->stringOrNull($payload, 'barangay'),
            'ownership_type' => $this->stringOrNull($payload, 'ownershipType'),
            'organization_name' => $this->stringOrNull($payload, 'groupName'),
            'occupancy' => $this->stringOrNull($payload, 'occupancy'),
            'building_name' => $this->stringOrNull($payload, 'buildingName'),
            'property_index_number' => $this->stringOrNull($payload, 'propertyIndexNumber'),
            'business_area_square_meters' => $payload['businessArea'] ?? null,
            'male_employee_count' => $payload['maleEmployeeCount'] ?? null,
            'female_employee_count' => $payload['femaleEmployeeCount'] ?? null,
            'contact_number' => $this->stringOrNull($payload, 'contactNumber'),
            'email' => $this->stringOrNull($payload, 'email'),
            'established_on' => $this->stringOrNull($payload, 'establishedDate'),
            'started_on' => $this->stringOrNull($payload, 'dateStarted'),
            'registered_on' => $this->stringOrNull($payload, 'registrationDate'),
            'legacy_source_id' => $record->legacy_id,
            'metadata' => [
                'legacy_business_scale' => $this->stringOrNull($payload, 'businessScale'),
                'legacy_annual_revenue' => $this->stringOrNull($payload, 'annualRevenue'),
                'legacy_permit_payment_cadence' => $this->stringOrNull($payload, 'permitPaymentCadence'),
                'legacy_location_ids_preserved' => array_filter([
                    'provinceId' => $this->stringOrNull($payload, 'provinceId'),
                    'cityId' => $this->stringOrNull($payload, 'cityId'),
                    'barangayId' => $this->stringOrNull($payload, 'barangayId'),
                ]),
                'legacy_classification_ids_preserved' => array_filter([
                    'categoryId' => $this->stringOrNull($payload, 'categoryId'),
                    'subCategoryId' => $this->stringOrNull($payload, 'subCategoryId'),
                ]),
            ],
        ];

        return [
            'attributes' => $attributes,
            'identity' => [
                'owner_legacy_id' => $ownerLegacyId,
                'name' => $this->normalize($name),
                'registration_number' => $this->normalize($this->string($payload, 'registrationNumber')),
            ],
            'reasons' => $reasons,
            'blocked' => $blocked,
            'owner_legacy_id' => $ownerLegacyId,
        ];
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

    private function registrySnapshotHash(): string
    {
        $context = hash_init('sha256');

        foreach (BusinessOwner::query()->select(['id', 'name', 'email', 'phone', 'address', 'legacy_source_id', 'updated_at'])->orderBy('id')->cursor() as $owner) {
            hash_update($context, $this->canonicalJson(['owner', ...$owner->getAttributes()]));
        }

        foreach (Business::query()->select(['id', 'business_owner_id', 'name', 'registration_number', 'legacy_source_id', 'updated_at'])->orderBy('id')->cursor() as $business) {
            hash_update($context, $this->canonicalJson(['business', ...$business->getAttributes()]));
        }

        return hash_final($context);
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

    /** @param array<string, mixed> $payload */
    private function stringOrNull(array $payload, string $key): ?string
    {
        $value = $this->string($payload, $key);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $value */
    private function hashCanonical(array $value): string
    {
        return hash('sha256', $this->canonicalJson($value));
    }

    /** @param array<array-key, mixed> $value */
    private function canonicalJson(array $value): string
    {
        $normalized = $this->sortRecursively($value);

        return json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function sortRecursively(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursively($item);
            }
        }

        return $value;
    }

    private function withEvidence(LegacyMappingPlan $plan): LegacyMappingPlan
    {
        return $plan->fresh(['importBatch.source', 'proposals']) ?? $plan;
    }
}
