<?php

namespace App\Actions;

use App\Enums\LegacyLineOfBusinessReconciliationStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalAction;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyApplicationMappingProposal;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyHistoricalFinancialMappingSet;
use App\Models\LegacyIdMapping;
use App\Models\LegacyLineOfBusinessReconciliation;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMappingProposal;
use App\Models\LegacyRecord;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLine;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AcceptLegacyHistoricalFinancialCohortMappings
{
    public const SchemaVersion = 'bpls.historical-financial-mapping-set.v1';

    public function __construct(
        private CharacterizeLegacyHistoricalFinancialCohortPrerequisites $characterize,
        private LegacyRegistryMappingProjector $registryProjector,
        private ExecuteLegacyRegistryMigration $executeRegistry,
        private PlanLegacyApplicationDeclarations $planDeclarations,
        private PlanLegacyPermitApplications $planApplications,
        private ExecuteLegacyPermitApplications $executeApplications,
        private LegacyApplicationDeclarationProjector $declarationProjector,
        private LegacyPermitApplicationProjector $applicationProjector,
    ) {}

    public function handle(
        LegacyFinancialMappingPlan $financialPlan,
        LegacyMappingPlan $registryPlan,
        string $expectedCohortSha256,
        string $expectedProposalPackageSha256,
        string $runReference,
        string $decisionAuthority,
        string $evidenceReference,
    ): LegacyHistoricalFinancialMappingSet {
        $this->assertEnvironment();
        $this->assertInputs($expectedCohortSha256, $expectedProposalPackageSha256, $runReference, $decisionAuthority, $evidenceReference);
        $this->assertPlanRelationship($financialPlan, $registryPlan);

        $existing = LegacyHistoricalFinancialMappingSet::query()
            ->where('legacy_source_id', $financialPlan->importBatch->legacy_source_id)
            ->where('cohort_sha256', $expectedCohortSha256)
            ->first();
        if ($existing instanceof LegacyHistoricalFinancialMappingSet) {
            $this->assertIdempotentRetry($existing, $runReference, $expectedProposalPackageSha256, $decisionAuthority, $evidenceReference);
            $this->audit($existing);

            return $existing;
        }

        $evidence = $this->characterize->handle($financialPlan, $registryPlan, $expectedCohortSha256);
        $actualPackage = (string) data_get($evidence, 'report.fingerprints.prerequisite_proposals_sha256');
        if (! hash_equals($expectedProposalPackageSha256, $actualPackage)) {
            throw new RuntimeException('The recomputed prerequisite proposal package does not match the Board-accepted fingerprint.');
        }
        $this->assertAcceptedEvidenceShape($evidence);

        return DB::transaction(function () use (
            $financialPlan,
            $registryPlan,
            $expectedCohortSha256,
            $expectedProposalPackageSha256,
            $runReference,
            $decisionAuthority,
            $evidenceReference,
            $evidence,
        ): LegacyHistoricalFinancialMappingSet {
            $operationalBefore = $this->operationalCounts();
            $mappingSet = LegacyHistoricalFinancialMappingSet::query()->create([
                'legacy_source_id' => $financialPlan->importBatch->legacy_source_id,
                'financial_import_batch_id' => $financialPlan->legacy_import_batch_id,
                'registry_import_batch_id' => $registryPlan->legacy_import_batch_id,
                'legacy_financial_mapping_plan_id' => $financialPlan->id,
                'legacy_mapping_plan_id' => $registryPlan->id,
                'run_reference' => $runReference,
                'cohort_sha256' => $expectedCohortSha256,
                'proposal_package_sha256' => $expectedProposalPackageSha256,
                'status' => 'accepting',
                'cohort_size' => 5,
                'decision_authority' => $decisionAuthority,
                'evidence_reference' => $evidenceReference,
                'metadata' => $this->safetyMetadata(),
            ]);

            $lineOfBusinessEvidence = $this->acceptLineOfBusinesses($mappingSet, $evidence['line_of_business_proposals']);
            $acceptedRegistryPlan = $this->acceptedRegistryPlan($mappingSet, $registryPlan, $evidence['exact_mapping_proposals'], $evidence['location_proposals']);
            $registryExecution = $this->executeRegistry->handle(
                $acceptedRegistryPlan,
                array_values($acceptedRegistryPlan->proposals()->orderBy('id')->get()->map(fn (LegacyMappingProposal $proposal): int => $proposal->id)->all()),
                $runReference.'-registry',
            );
            $declarationPlan = $this->planDeclarations->handle($financialPlan->importBatch, $runReference.'-declarations');
            $characterizationApplicationPlan = $this->planApplications->handle($financialPlan->importBatch, $runReference.'-application-characterization');
            $applicationRecordIds = collect($evidence['exact_mapping_proposals'])->pluck('application.source_record_id')->map(fn (mixed $id): int => (int) $id)->all();
            $applicationPlan = $this->acceptedApplicationPlan($mappingSet, $characterizationApplicationPlan, array_values($applicationRecordIds));
            $applicationProposals = $applicationPlan->proposals()->orderBy('id')->get();
            $applicationExecution = $this->executeApplications->handle(
                $applicationPlan,
                array_values($applicationProposals->map(fn (LegacyApplicationMappingProposal $proposal): int => $proposal->id)->all()),
                $runReference.'-applications',
            );

            $operationalAfter = $this->operationalCounts();
            if ($operationalBefore !== $operationalAfter) {
                throw new RuntimeException('Operational financial records changed while accepting historical mapping prerequisites.');
            }

            $manifest = $this->manifest(
                $mappingSet,
                $evidence,
                $lineOfBusinessEvidence,
                $acceptedRegistryPlan,
                $registryExecution->id,
                $declarationPlan->id,
                $applicationPlan->id,
                $applicationExecution->id,
                $operationalBefore,
            );
            $fingerprint = $this->hash($manifest);
            $mappingSet->update([
                'accepted_registry_plan_id' => $acceptedRegistryPlan->id,
                'registry_execution_id' => $registryExecution->id,
                'declaration_plan_id' => $declarationPlan->id,
                'application_plan_id' => $applicationPlan->id,
                'application_execution_id' => $applicationExecution->id,
                'accepted_mapping_set_sha256' => $fingerprint,
                'status' => 'frozen',
                'accepted_at' => now(),
                'manifest' => $manifest,
                'metadata' => [
                    ...$this->safetyMetadata(),
                    'operational_counts_before' => $operationalBefore,
                    'operational_counts_after' => $operationalAfter,
                ],
            ]);

            $frozen = $mappingSet->fresh() ?? $mappingSet;
            $this->audit($frozen);

            return $frozen;
        }, 3);
    }

    public function audit(LegacyHistoricalFinancialMappingSet $mappingSet): void
    {
        if ($mappingSet->status !== 'frozen' || ! is_array($mappingSet->manifest) || $mappingSet->accepted_mapping_set_sha256 === null) {
            throw new RuntimeException('Historical financial mapping set is not frozen.');
        }
        if (! hash_equals($mappingSet->accepted_mapping_set_sha256, $this->hash($mappingSet->manifest))) {
            throw new RuntimeException('Historical financial mapping-set manifest fingerprint no longer matches.');
        }

        foreach ((array) data_get($mappingSet->manifest, 'line_of_business_targets', []) as $item) {
            $target = LineOfBusiness::query()->find((int) ($item['target_id'] ?? 0));
            $reconciliation = LegacyLineOfBusinessReconciliation::query()->find((int) ($item['reconciliation_id'] ?? 0));
            $source = LegacyRecord::query()->find((int) ($item['source_group_record_id'] ?? 0));
            if (! $target instanceof LineOfBusiness
                || ! $reconciliation instanceof LegacyLineOfBusinessReconciliation
                || ! $source instanceof LegacyRecord
                || ! hash_equals((string) $item['source_group_payload_hash'], $source->payload_hash)
                || $reconciliation->status !== LegacyLineOfBusinessReconciliationStatus::Accepted
                || $reconciliation->line_of_business_id !== $target->id
                || ! hash_equals((string) $item['source_value_hash'], $reconciliation->source_value_hash)
                || ! hash_equals((string) $item['target_snapshot_sha256'], $this->lineOfBusinessSnapshotHash($target))) {
                throw new RuntimeException('An accepted line-of-business target or reconciliation has changed.');
            }
        }

        foreach ((array) data_get($mappingSet->manifest, 'location_provenance', []) as $location) {
            foreach ((array) ($location['references'] ?? []) as $reference) {
                $source = LegacyRecord::query()->find((int) ($reference['source_lookup_record_id'] ?? 0));
                if (! $source instanceof LegacyRecord
                    || ! hash_equals((string) $reference['source_lookup_payload_hash'], $source->payload_hash)) {
                    throw new RuntimeException('An accepted historical location-provenance record has changed.');
                }
            }
        }

        foreach ((array) data_get($mappingSet->manifest, 'registry_mappings', []) as $item) {
            $mapping = LegacyIdMapping::query()->find((int) ($item['mapping_id'] ?? 0));
            $source = LegacyRecord::query()->find((int) ($item['source_record_id'] ?? 0));
            $target = match ($mapping?->target_type) {
                'business_owner' => BusinessOwner::query()->find($mapping->target_id),
                'business' => Business::query()->find($mapping->target_id),
                default => null,
            };
            if (! $mapping instanceof LegacyIdMapping
                || ! $source instanceof LegacyRecord
                || (! $target instanceof BusinessOwner && ! $target instanceof Business)
                || $mapping->target_id !== (int) $item['target_id']
                || $mapping->status !== 'mapped'
                || ! hash_equals((string) $item['source_payload_hash'], $source->payload_hash)
                || ! hash_equals((string) $item['target_snapshot_sha256'], $this->registryProjector->targetSnapshotHash($target))) {
                throw new RuntimeException('An accepted registry mapping has changed.');
            }
        }
        foreach ((array) data_get($mappingSet->manifest, 'application_mappings', []) as $item) {
            $mapping = LegacyApplicationIdMapping::query()->find((int) ($item['mapping_id'] ?? 0));
            $source = LegacyRecord::query()->find((int) ($item['source_record_id'] ?? 0));
            $target = PermitApplication::query()->find($mapping?->permit_application_id);
            if (! $mapping instanceof LegacyApplicationIdMapping
                || ! $source instanceof LegacyRecord
                || ! $target instanceof PermitApplication
                || $mapping->permit_application_id !== (int) $item['target_id']
                || $mapping->status !== 'mapped'
                || ! hash_equals((string) $item['source_payload_hash'], $source->payload_hash)
                || ! hash_equals((string) $item['target_snapshot_sha256'], $this->applicationProjector->targetSnapshotHash($target))) {
                throw new RuntimeException('An accepted application mapping has changed.');
            }
        }

        $operationalBefore = (array) data_get($mappingSet->metadata, 'operational_counts_before', []);
        $operationalAfter = (array) data_get($mappingSet->metadata, 'operational_counts_after', []);
        if ($operationalBefore === [] || $operationalBefore !== $operationalAfter) {
            throw new RuntimeException('The mapping acceptance no longer proves operational financial isolation.');
        }
    }

    /** @param list<array<string, mixed>> $proposals
     * @return list<array<string, mixed>>
     */
    private function acceptLineOfBusinesses(LegacyHistoricalFinancialMappingSet $mappingSet, array $proposals): array
    {
        $accepted = [];
        foreach ($proposals as $proposal) {
            $group = LegacyRecord::query()->findOrFail((int) $proposal['source_group_record_id']);
            if ($group->dataset_key !== 'groups' || ! hash_equals($group->payload_hash, (string) $proposal['source_group_payload_hash'])) {
                throw new RuntimeException('A proposed line-of-business source group no longer matches its staged evidence.');
            }
            if (LineOfBusiness::query()->where('legacy_source_id', $group->legacy_id)->exists()) {
                throw new RuntimeException('An exact line-of-business target already exists outside this acceptance run.');
            }
            $hierarchy = (array) $proposal['hierarchy'];
            if (count($hierarchy) !== 1) {
                throw new RuntimeException('The five-record acceptance requires one exact division hierarchy per source group.');
            }
            $major = LegacyRecord::query()->findOrFail((int) $hierarchy[0]['major_source_record_id']);
            $target = LineOfBusiness::query()->create([
                'name' => $this->requiredString($group->payload['name'] ?? null, 'source group name'),
                'major_category' => $this->requiredString($major->payload['name'] ?? null, 'source major name'),
                'is_active' => true,
                'legacy_source_id' => $group->legacy_id,
                'metadata' => [
                    'historical_mapping_set_id' => $mappingSet->id,
                    'source_group_record_id' => $group->id,
                    'source_group_payload_hash' => $group->payload_hash,
                    'source_hierarchy' => $hierarchy,
                    'cohort_sha256' => $mappingSet->cohort_sha256,
                    'proposal_package_sha256' => $mappingSet->proposal_package_sha256,
                    'decision_authority' => $mappingSet->decision_authority,
                    'evidence_reference' => $mappingSet->evidence_reference,
                    'future_catalog_authorized' => false,
                    'fee_policy_authorized' => false,
                ],
            ]);
            $reconciliation = LegacyLineOfBusinessReconciliation::query()->create([
                'legacy_source_id' => $mappingSet->legacy_source_id,
                'source_dataset' => 'groups',
                'source_value_hash' => $proposal['normalized_source_value_sha256'],
                'line_of_business_id' => $target->id,
                'status' => LegacyLineOfBusinessReconciliationStatus::Accepted,
                'decision_authority' => $mappingSet->decision_authority,
                'evidence_reference' => $mappingSet->evidence_reference,
                'decided_at' => now(),
                'metadata' => [
                    'historical_mapping_set_id' => $mappingSet->id,
                    'source_group_record_id' => $group->id,
                    'source_group_payload_hash' => $group->payload_hash,
                    'proposed_target_definition_sha256' => $proposal['proposed_target_definition_sha256'],
                    'production_rehearsal_authorized' => false,
                    'future_policy_authorized' => false,
                ],
            ]);
            $accepted[] = [
                'source_group_record_id' => $group->id,
                'source_group_payload_hash' => $group->payload_hash,
                'source_value_hash' => $proposal['normalized_source_value_sha256'],
                'target_id' => $target->id,
                'target_snapshot_sha256' => $this->lineOfBusinessSnapshotHash($target),
                'reconciliation_id' => $reconciliation->id,
            ];
        }

        return $accepted;
    }

    /** @param list<array<string, mixed>> $mappingProposals
     * @param  list<array<string, mixed>>  $locationProposals
     */
    private function acceptedRegistryPlan(
        LegacyHistoricalFinancialMappingSet $mappingSet,
        LegacyMappingPlan $sourcePlan,
        array $mappingProposals,
        array $locationProposals,
    ): LegacyMappingPlan {
        $sourceProposalIds = collect($mappingProposals)->flatMap(fn (array $proposal): array => [
            (int) data_get($proposal, 'owner.registry_proposal_id'),
            (int) data_get($proposal, 'business.registry_proposal_id'),
        ])->unique()->sort()->values();
        $sourceProposals = $sourcePlan->proposals()->whereIn('id', $sourceProposalIds)->get()->keyBy('id');
        if ($sourceProposals->count() !== 10) {
            throw new RuntimeException('The Board-accepted cohort no longer resolves to exactly ten registry proposals.');
        }

        $plan = LegacyMappingPlan::query()->create([
            'legacy_import_batch_id' => $sourcePlan->legacy_import_batch_id,
            'run_reference' => $mappingSet->run_reference.'-registry-acceptance',
            'planner_version' => 'bpls.historical-financial-cohort-registry-acceptance.v1',
            'registry_snapshot_hash' => $this->registryProjector->registrySnapshotHash(),
            'status' => LegacyMappingPlanStatus::Planned,
            'owner_proposal_count' => 5,
            'business_proposal_count' => 5,
            'ready_count' => 10,
            'review_count' => 0,
            'blocked_count' => 0,
            'exact_link_count' => 0,
            'started_at' => now(),
            'completed_at' => now(),
            'metadata' => [
                'historical_mapping_set_id' => $mappingSet->id,
                'source_registry_plan_id' => $sourcePlan->id,
                'decision_authority' => $mappingSet->decision_authority,
                'evidence_reference' => $mappingSet->evidence_reference,
                'accepted_location_disposition' => 'preserve_exact_source_lookup_chain_as_registry_provenance',
                'cohort_sha256' => $mappingSet->cohort_sha256,
                'production_rehearsal_authorized' => false,
            ],
        ]);

        $locations = collect($locationProposals)->keyBy('business_source_record_id');
        foreach ($sourceProposalIds as $sourceProposalId) {
            $source = $sourceProposals->get($sourceProposalId);
            if (! $source instanceof LegacyMappingProposal) {
                throw new RuntimeException('A Board-accepted source registry proposal is missing.');
            }
            $reasons = $source->reasons ?? [];
            if ($source->target_type === 'business') {
                $location = $locations->get($source->legacy_record_id);
                if (! is_array($location) || $location['proposal_status'] !== 'evidence_complete_acceptance_pending') {
                    throw new RuntimeException('A business proposal lacks its accepted exact location provenance.');
                }
                $reasons = array_values(array_diff($reasons, ['reference_data_mapping_required']));
            }
            if ($reasons !== [] || ! in_array($source->proposed_action, [LegacyMappingProposalAction::Create, LegacyMappingProposalAction::Review], true)) {
                throw new RuntimeException('A registry proposal has unresolved semantics beyond the accepted location disposition: '.json_encode([
                    'target_type' => $source->target_type,
                    'action' => $source->proposed_action->value,
                    'reasons' => $reasons,
                ], JSON_THROW_ON_ERROR));
            }
            $plan->proposals()->create([
                'legacy_record_id' => $source->legacy_record_id,
                'parent_legacy_record_id' => $source->parent_legacy_record_id,
                'dataset_key' => $source->dataset_key,
                'entity_type' => $source->entity_type,
                'target_type' => $source->target_type,
                'target_id' => null,
                'proposed_action' => LegacyMappingProposalAction::Create,
                'status' => LegacyMappingProposalStatus::Ready,
                'identity_fingerprint' => $source->identity_fingerprint,
                'projection_hash' => $source->projection_hash,
                'collision_fingerprints' => $source->collision_fingerprints,
                'reasons' => [],
                'metadata' => [
                    ...($source->metadata ?? []),
                    'source_registry_proposal_id' => $source->id,
                    'historical_mapping_set_id' => $mappingSet->id,
                    'location_provenance_accepted' => $source->target_type === 'business',
                ],
            ]);
        }

        return $plan->fresh(['proposals']) ?? $plan;
    }

    /** @param list<int> $applicationRecordIds */
    private function acceptedApplicationPlan(
        LegacyHistoricalFinancialMappingSet $mappingSet,
        LegacyApplicationMappingPlan $sourcePlan,
        array $applicationRecordIds,
    ): LegacyApplicationMappingPlan {
        $sourceProposals = $sourcePlan->proposals()->whereIn('legacy_record_id', $applicationRecordIds)->orderBy('legacy_record_id')->get();
        if ($sourceProposals->count() !== 5) {
            throw new RuntimeException('The exact five application proposals are absent from the characterization plan.');
        }

        $plan = LegacyApplicationMappingPlan::query()->create([
            'legacy_import_batch_id' => $sourcePlan->legacy_import_batch_id,
            'run_reference' => $mappingSet->run_reference.'-application-acceptance',
            'planner_version' => 'bpls.historical-financial-cohort-application-acceptance.v1',
            'dependency_snapshot_hash' => $sourcePlan->dependency_snapshot_hash,
            'status' => LegacyMappingPlanStatus::Planned,
            'proposal_count' => 5,
            'ready_count' => 5,
            'review_count' => 0,
            'blocked_count' => 0,
            'exact_link_count' => 0,
            'started_at' => now(),
            'completed_at' => now(),
            'metadata' => [
                'historical_mapping_set_id' => $mappingSet->id,
                'source_application_plan_id' => $sourcePlan->id,
                'decision_authority' => $mappingSet->decision_authority,
                'evidence_reference' => $mappingSet->evidence_reference,
                'accepted_line_of_business_identity_only' => true,
                'declaration_migration_executed' => false,
                'declaration_financial_policy_authorized' => false,
                'official_application_number_authorized' => false,
                'production_rehearsal_authorized' => false,
            ],
        ]);

        foreach ($sourceProposals as $source) {
            $reasons = $source->reasons ?? [];
            if (! in_array($reasons, [[], ['line_of_business_mapping_required']], true)) {
                throw new RuntimeException('An application proposal has unresolved semantics beyond the accepted line-of-business identity boundary: '.json_encode([
                    'source_record_id' => $source->legacy_record_id,
                    'reasons' => $reasons,
                ], JSON_THROW_ON_ERROR));
            }
            if ($source->target_id !== null
                || ! in_array($source->proposed_action, [LegacyMappingProposalAction::Create, LegacyMappingProposalAction::Review], true)) {
                throw new RuntimeException('An accepted application mapping unexpectedly resolves to an existing target or a different action.');
            }
            $record = $source->legacyRecord;
            $lines = $record->payload['linesOfBusiness'] ?? null;
            if (! is_array($lines) || $lines === []) {
                throw new RuntimeException('An accepted application mapping has no source line-of-business declaration.');
            }
            foreach (array_keys(array_values($lines)) as $index) {
                $projection = $this->declarationProjector->project($record, $index);
                if ($projection['reconciliation'] === null
                    || $projection['line_of_business'] === null
                    || $projection['reconciliation']->status !== LegacyLineOfBusinessReconciliationStatus::Accepted) {
                    throw new RuntimeException('An accepted application mapping lacks an exact accepted line-of-business identity.');
                }
            }

            $plan->proposals()->create([
                'legacy_record_id' => $source->legacy_record_id,
                'owner_mapping_id' => $source->owner_mapping_id,
                'business_mapping_id' => $source->business_mapping_id,
                'target_id' => $source->target_id,
                'proposed_action' => LegacyMappingProposalAction::Create,
                'status' => LegacyMappingProposalStatus::Ready,
                'identity_fingerprint' => $source->identity_fingerprint,
                'projection_hash' => $source->projection_hash,
                'collision_fingerprints' => $source->collision_fingerprints,
                'reasons' => [],
                'metadata' => [
                    ...($source->metadata ?? []),
                    'source_application_proposal_id' => $source->id,
                    'historical_mapping_set_id' => $mappingSet->id,
                    'line_of_business_identity_accepted' => true,
                    'declaration_migration_executed' => false,
                ],
            ]);
        }

        return $plan->fresh(['proposals']) ?? $plan;
    }

    /** @param array<string, mixed> $evidence
     * @param  list<array<string, mixed>>  $lineOfBusinesses
     * @param  array<string, int>  $operationalCounts
     * @return array<string, mixed>
     */
    private function manifest(
        LegacyHistoricalFinancialMappingSet $mappingSet,
        array $evidence,
        array $lineOfBusinesses,
        LegacyMappingPlan $acceptedRegistryPlan,
        int $registryExecutionId,
        int $declarationPlanId,
        int $applicationPlanId,
        int $applicationExecutionId,
        array $operationalCounts,
    ): array {
        $registryRecordIds = [];
        $applicationRecordIds = [];
        foreach ($evidence['exact_mapping_proposals'] as $proposal) {
            $registryRecordIds[] = (int) data_get($proposal, 'owner.source_record_id');
            $registryRecordIds[] = (int) data_get($proposal, 'business.source_record_id');
            $applicationRecordIds[] = (int) data_get($proposal, 'application.source_record_id');
        }
        $registryRecords = LegacyRecord::query()->whereIn('id', $registryRecordIds)->get()->keyBy(fn (LegacyRecord $record): string => $record->dataset_key.'|'.$record->legacy_id);
        $registryMappings = LegacyIdMapping::query()->where('legacy_mapping_execution_id', $registryExecutionId)->orderBy('id')->get()->map(function (LegacyIdMapping $mapping) use ($registryRecords): array {
            $record = $registryRecords->get($mapping->dataset_key.'|'.$mapping->legacy_id);

            return [
                'mapping_id' => $mapping->id,
                'source_record_id' => $record?->id,
                'source_payload_hash' => $record?->payload_hash,
                'target_type' => $mapping->target_type,
                'target_id' => $mapping->target_id,
                'target_snapshot_sha256' => data_get($mapping->metadata, 'target_snapshot_hash'),
            ];
        })->all();
        $applicationRecords = LegacyRecord::query()->whereIn('id', $applicationRecordIds)->get()->keyBy('legacy_id');
        $applicationMappings = LegacyApplicationIdMapping::query()->where('legacy_application_mapping_execution_id', $applicationExecutionId)->orderBy('id')->get()->map(function (LegacyApplicationIdMapping $mapping) use ($applicationRecords): array {
            $record = $applicationRecords->get($mapping->legacy_id);

            return [
                'mapping_id' => $mapping->id,
                'source_record_id' => $record?->id,
                'source_payload_hash' => $record?->payload_hash,
                'target_id' => $mapping->permit_application_id,
                'target_snapshot_sha256' => data_get($mapping->metadata, 'target_snapshot_hash'),
            ];
        })->all();

        return [
            'schema_version' => self::SchemaVersion,
            'mapping_set_id' => $mappingSet->id,
            'source' => [
                'legacy_source_id' => $mappingSet->legacy_source_id,
                'archive_checksum' => $mappingSet->source()->value('archive_checksum'),
                'financial_plan_id' => $mappingSet->legacy_financial_mapping_plan_id,
                'registry_plan_id' => $mappingSet->legacy_mapping_plan_id,
                'cohort_sha256' => $mappingSet->cohort_sha256,
                'proposal_package_sha256' => $mappingSet->proposal_package_sha256,
            ],
            'decision' => [
                'authority' => $mappingSet->decision_authority,
                'evidence_reference' => $mappingSet->evidence_reference,
            ],
            'location_provenance' => $evidence['location_proposals'],
            'line_of_business_targets' => $lineOfBusinesses,
            'registry_plan_id' => $acceptedRegistryPlan->id,
            'registry_execution_id' => $registryExecutionId,
            'registry_mappings' => $registryMappings,
            'declaration_plan_id' => $declarationPlanId,
            'application_plan_id' => $applicationPlanId,
            'application_execution_id' => $applicationExecutionId,
            'application_mappings' => $applicationMappings,
            'operational_financial_counts' => $operationalCounts,
            'safety' => $this->safetyMetadata(),
        ];
    }

    /** @param array<string, mixed> $evidence */
    private function assertAcceptedEvidenceShape(array $evidence): void
    {
        $summary = (array) data_get($evidence, 'report.summary', []);
        $expected = [
            'cohort_size' => 5,
            'exact_location_hierarchy_count' => 5,
            'line_of_business_declaration_count' => 5,
            'exact_legacy_group_hierarchy_count' => 5,
            'existing_exact_target_count' => 0,
            'source_backed_target_creation_proposal_count' => 5,
            'evidence_complete_acceptance_pending_count' => 5,
            'accepted_reconciliation_count' => 0,
            'accepted_application_mapping_count' => 0,
            'accepted_registry_mapping_count' => 0,
        ];
        foreach ($expected as $key => $value) {
            if (($summary[$key] ?? null) !== $value) {
                throw new RuntimeException("The prerequisite evidence no longer has the accepted [{$key}] value.");
            }
        }
    }

    private function assertPlanRelationship(LegacyFinancialMappingPlan $financialPlan, LegacyMappingPlan $registryPlan): void
    {
        $financialPlan->loadMissing('importBatch.source');
        $registryPlan->loadMissing('importBatch.source');
        if ($financialPlan->importBatch->legacy_source_id !== $registryPlan->importBatch->legacy_source_id) {
            throw new RuntimeException('Financial and registry plans must belong to the same immutable legacy source.');
        }
    }

    private function assertIdempotentRetry(
        LegacyHistoricalFinancialMappingSet $mappingSet,
        string $runReference,
        string $proposalPackageSha256,
        string $decisionAuthority,
        string $evidenceReference,
    ): void {
        if ($mappingSet->run_reference !== $runReference
            || ! hash_equals($mappingSet->proposal_package_sha256, $proposalPackageSha256)
            || $mappingSet->decision_authority !== $decisionAuthority
            || $mappingSet->evidence_reference !== $evidenceReference) {
            throw new RuntimeException('The frozen cohort is already bound to a different acceptance decision.');
        }
    }

    private function assertInputs(string $cohort, string $package, string $runReference, string $authority, string $evidence): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $cohort) !== 1 || preg_match('/^[a-f0-9]{64}$/', $package) !== 1) {
            throw new RuntimeException('Cohort and proposal-package fingerprints must be lowercase SHA-256 values.');
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,79}$/', $runReference) !== 1) {
            throw new RuntimeException('Acceptance run reference must be 3-80 safe characters.');
        }
        if (trim($authority) === '' || trim($evidence) === '') {
            throw new RuntimeException('Decision authority and evidence reference are required.');
        }
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Historical cohort mapping acceptance is restricted to local and testing environments.');
        }
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
            'mapping_scope' => 'frozen_five_record_historical_financial_cohort',
            'location_policy_created' => false,
            'future_classification_catalog_authorized' => false,
            'fee_policy_authorized' => false,
            'production_rehearsal_authorized' => false,
            'historical_financial_preservation_executed' => false,
            'operational_financial_writes' => false,
            'production_mutation' => false,
            'formula_execution' => false,
            'fee_identity_inference' => false,
        ];
    }

    private function lineOfBusinessSnapshotHash(LineOfBusiness $lineOfBusiness): string
    {
        return $this->hash($lineOfBusiness->only(['code', 'name', 'major_category', 'is_active', 'legacy_source_id', 'metadata']));
    }

    private function requiredString(mixed $value, string $label): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("The {$label} is missing.");
        }

        return trim($value);
    }

    /** @param array<array-key, mixed> $value */
    private function hash(array $value): string
    {
        return hash('sha256', json_encode($this->sortRecursively($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @param array<array-key, mixed> $value
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
}
