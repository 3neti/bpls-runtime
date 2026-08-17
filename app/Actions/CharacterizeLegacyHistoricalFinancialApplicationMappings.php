<?php

namespace App\Actions;

use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyFinancialMappingProposal;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMappingProposal;
use App\Models\LegacyRecord;
use Illuminate\Support\Collection;
use RuntimeException;

class CharacterizeLegacyHistoricalFinancialApplicationMappings
{
    public const SchemaVersion = 'bpls.historical-financial-application-mapping-readiness.v2';

    public const CohortSize = 5;

    private const CollisionReasons = [
        'potential_existing_business_collision',
        'potential_existing_owner_collision',
        'potential_source_business_collision',
        'potential_source_owner_collision',
    ];

    private const RegistryPolicyReasons = [
        'blacklist_state_requires_registry_policy',
        'group_owner_semantics_require_reconciliation',
        'soft_deleted_record_policy_unresolved',
    ];

    private const ReferenceDataReasons = [
        'line_of_business_mapping_required',
        'reference_data_mapping_required',
    ];

    private const MappingPrerequisiteReasons = [
        'accepted_application_mapping_required',
        'application_mapping_ambiguous',
    ];

    private const FrozenCensusExceptionReasons = [
        'application_has_unassigned_payment_events',
    ];

    public function __construct(
        private LegacyHistoricalFinancialPreservationProjector $preservationProjector,
        private LegacyPermitApplicationProjector $applicationProjector,
    ) {}

    /** @return array{report: array<string, mixed>, candidates: list<array<string, mixed>>, exceptions: list<array<string, mixed>>, cohort: list<array<string, mixed>>, cohort_prerequisites: list<array<string, mixed>>} */
    public function handle(LegacyFinancialMappingPlan $financialPlan, LegacyMappingPlan $registryPlan): array
    {
        $this->assertEvidence($financialPlan, $registryPlan);

        $financialBatch = $financialPlan->importBatch;
        $registryBatch = $registryPlan->importBatch;
        $financialProposals = $financialPlan->proposals()
            ->with('legacyRecord')
            ->whereIn('kind', ['payment_schedule', 'payment_schedule_fee', 'payment', 'receipt_claim'])
            ->orderBy('id')
            ->get();
        $proposalsByApplication = $this->financialProposalsByApplication($financialProposals);
        $applicationIds = collect(array_keys($proposalsByApplication))->sort()->values();

        $applications = LegacyRecord::query()
            ->where('legacy_import_batch_id', $financialBatch->id)
            ->where('dataset_key', 'business_permit_applications')
            ->whereIn('id', $applicationIds)
            ->orderBy('id')
            ->get();
        $excludedReasonCounts = [];
        $frozenCensusBundleTotals = $strictBundleTotals = [
            'schedule_count' => 0,
            'fee_line_count' => 0,
            'payment_count' => 0,
            'unpaid_schedule_count' => 0,
        ];
        $frozenCensus = $applications
            ->map(function (LegacyRecord $application) use ($financialPlan, $proposalsByApplication, &$excludedReasonCounts, &$frozenCensusBundleTotals, &$strictBundleTotals): ?array {
                $applicationProposals = $proposalsByApplication[$application->id] ?? collect();
                $projection = $this->preservationProjector->project(
                    $financialPlan,
                    $application,
                    $applicationProposals,
                );
                $structuralReasons = array_values(array_diff(
                    $projection['reasons'],
                    [...self::MappingPrerequisiteReasons, ...self::FrozenCensusExceptionReasons],
                ));
                $frozenCensusExceptionReasons = array_values(array_intersect(
                    $projection['reasons'],
                    self::FrozenCensusExceptionReasons,
                ));
                foreach ($structuralReasons as $reason) {
                    $excludedReasonCounts[$reason] = ($excludedReasonCounts[$reason] ?? 0) + 1;
                }
                if ($structuralReasons === []) {
                    $totals = $projection['projection']['financial_history']['totals'];
                    $unpaidScheduleCount = count(array_filter(
                        $projection['projection']['financial_history']['schedules'],
                        fn (array $schedule): bool => $schedule['status'] === 'pending' && $schedule['payments'] === [],
                    ));
                    $this->addBundleTotals($frozenCensusBundleTotals, $totals, $unpaidScheduleCount);
                    if ($frozenCensusExceptionReasons === []) {
                        $this->addBundleTotals($strictBundleTotals, $totals, $unpaidScheduleCount);
                    }
                }

                return $structuralReasons === [] ? [
                    'application' => $application,
                    'preservation_executor_compatibility_reasons' => $frozenCensusExceptionReasons,
                    'compatibility_evidence' => $this->compatibilityEvidence($application, $applicationProposals, $projection['projection']),
                ] : null;
            })
            ->filter()
            ->values();
        arsort($excludedReasonCounts);
        unset($applications, $financialProposals, $proposalsByApplication);
        gc_collect_cycles();

        $registryRecords = $this->registryRecords($registryBatch->id, $frozenCensus);
        $financialRegistryRecords = $this->registryRecords($financialBatch->id, $frozenCensus);
        $registryProposals = $registryPlan->proposals()
            ->whereIn('legacy_record_id', $registryRecords->pluck('id'))
            ->get()
            ->keyBy('legacy_record_id');

        $characterizedCensus = $frozenCensus
            ->map(fn (array $candidate): array => $this->characterize(
                $candidate['application'],
                $financialPlan->dependency_snapshot_hash,
                $candidate['preservation_executor_compatibility_reasons'],
                $candidate['compatibility_evidence'],
                $registryRecords,
                $financialRegistryRecords,
                $registryProposals,
            ))
            ->sortBy('candidate_fingerprint')
            ->values();
        $candidates = $characterizedCensus
            ->where('flags.preservation_executor_compatible', true)
            ->values();
        $exceptions = $characterizedCensus
            ->where('flags.preservation_executor_compatible', false)
            ->values();
        unset($frozenCensus, $characterizedCensus);
        gc_collect_cycles();

        $cohortCandidates = $candidates
            ->filter(fn (array $candidate): bool => in_array($candidate['classification'], [
                'deterministic_exact_mapping_candidate',
                'reference_data_crosswalk_only',
            ], true))
            ->take(self::CohortSize);
        $cohort = $cohortCandidates
            ->map(fn (array $candidate): array => [
                'candidate_fingerprint' => $candidate['candidate_fingerprint'],
                'application_source_record_id' => $candidate['application']['source_record_id'],
                'application_legacy_id_sha256' => $candidate['application']['legacy_id_sha256'],
                'proposed_mapping_status' => $candidate['classification'] === 'reference_data_crosswalk_only'
                    ? 'pending_reference_data_and_mapping_acceptance'
                    : 'pending_mapping_acceptance',
            ])
            ->values();
        $cohortPrerequisites = $cohortCandidates
            ->map(fn (array $candidate): array => $this->cohortPrerequisiteProposal($candidate, $registryRecords))
            ->values();

        $classificationCounts = $candidates->countBy('classification')->sortKeys()->all();
        $flagCounts = array_fill_keys([
            'deterministic_identity_chain',
            'preservation_executor_compatible',
            'reference_data_crosswalk_only',
            'human_identity_reconciliation',
            'group_owner',
            'soft_deleted',
            'blacklisted',
            'collision',
        ], 0);
        $reasonCounts = ['application' => [], 'owner' => [], 'business' => []];
        foreach ($candidates as $candidate) {
            foreach ($candidate['flags'] as $flag => $enabled) {
                if ($enabled) {
                    $flagCounts[$flag]++;
                }
            }
            foreach (['application', 'owner', 'business'] as $area) {
                foreach ($candidate[$area]['reasons'] ?? [] as $reason) {
                    $reasonCounts[$area][$reason] = ($reasonCounts[$area][$reason] ?? 0) + 1;
                }
            }
        }
        ksort($flagCounts);
        foreach ($reasonCounts as &$counts) {
            arsort($counts);
        }
        unset($counts);

        $evidenceFingerprint = [
            $financialBatch->source->archive_checksum,
            $financialBatch->manifest_checksum,
            $financialPlan->dependency_snapshot_hash,
            $registryBatch->manifest_checksum,
            $registryPlan->registry_snapshot_hash,
        ];
        $candidateFingerprint = $this->hash([
            ...$evidenceFingerprint,
            ...$candidates->map(fn (array $candidate): array => [
                $candidate['candidate_fingerprint'],
                $candidate['classification'],
            ])->all(),
        ]);
        $cohortFingerprint = $this->hash([...$evidenceFingerprint, ...$cohort->all()]);
        $exceptionFingerprint = $this->hash([
            ...$evidenceFingerprint,
            ...$exceptions->map(fn (array $exception): array => [
                $exception['candidate_fingerprint'],
                $exception['preservation_executor_compatibility_reasons'],
                $exception['compatibility_evidence'],
            ])->all(),
        ]);
        $cohortPrerequisiteFingerprint = $this->hash([...$evidenceFingerprint, ...$cohortPrerequisites->all()]);
        $exceptionPaymentCounts = $exceptions
            ->countBy(fn (array $exception): int => (int) ($exception['compatibility_evidence']['unassigned_payment_event_count'] ?? 0))
            ->sortKeys()
            ->mapWithKeys(fn (int $count, int $paymentCount): array => [(string) $paymentCount => $count])
            ->all();
        $exceptionEdgeClassCounts = $this->sumNestedCounts($exceptions, 'edge_class_counts');
        $exceptionStatusCounts = $this->sumNestedCounts($exceptions, 'status_counts');

        return [
            'report' => [
                'schema_version' => self::SchemaVersion,
                'evidence' => [
                    'legacy_source_id' => $financialBatch->legacy_source_id,
                    'source_archive_checksum' => $financialBatch->source->archive_checksum,
                    'financial_import_batch_id' => $financialBatch->id,
                    'financial_manifest_checksum' => $financialBatch->manifest_checksum,
                    'financial_mapping_plan_id' => $financialPlan->id,
                    'financial_dependency_snapshot_hash' => $financialPlan->dependency_snapshot_hash,
                    'registry_import_batch_id' => $registryBatch->id,
                    'registry_manifest_checksum' => $registryBatch->manifest_checksum,
                    'registry_mapping_plan_id' => $registryPlan->id,
                    'registry_snapshot_hash' => $registryPlan->registry_snapshot_hash,
                    'preservation_bundle_schema' => 'bpls.historical-financial-preservation-bundle.v1',
                ],
                'summary' => [
                    'original_frozen_candidate_census_count' => $candidates->count() + $exceptions->count(),
                    'original_frozen_bundle_totals' => $frozenCensusBundleTotals,
                    'strict_preservation_candidate_count' => $candidates->count(),
                    'strict_bundle_totals' => $strictBundleTotals,
                    'deterministic_identity_chain_count' => $candidates->where('flags.deterministic_identity_chain', true)->count(),
                    'preservation_executor_compatible_count' => $candidates->count(),
                    'preservation_executor_incompatible_count' => $exceptions->count(),
                    'incompatible_deterministic_identity_chain_count' => $exceptions->where('flags.deterministic_identity_chain', true)->count(),
                    'frozen_census_exception_class_counts' => $exceptions->flatMap(fn (array $exception): array => $exception['preservation_executor_compatibility_reasons'])->countBy()->sortKeys()->all(),
                    'unassigned_payment_events_by_application' => $exceptionPaymentCounts,
                    'unassigned_payment_event_count' => $exceptions->sum(fn (array $exception): int => (int) ($exception['compatibility_evidence']['unassigned_payment_event_count'] ?? 0)),
                    'unassigned_payment_edge_class_counts' => $exceptionEdgeClassCounts,
                    'unassigned_payment_status_counts' => $exceptionStatusCounts,
                    'classification_counts' => $classificationCounts,
                    'flag_counts' => $flagCounts,
                    'reason_counts' => $reasonCounts,
                    'strict_candidate_exclusion_reason_counts' => $excludedReasonCounts,
                    'accepted_mapping_count' => 0,
                    'recommended_first_rehearsal_cohort_size' => $cohort->count(),
                    'production_rehearsal_authorized' => false,
                    'production_migration_executed' => false,
                    'cutover_authorized' => false,
                ],
                'fingerprints' => [
                    'candidate_set_sha256' => $candidateFingerprint,
                    'frozen_census_exceptions_sha256' => $exceptionFingerprint,
                    'recommended_cohort_sha256' => $cohortFingerprint,
                    'cohort_prerequisites_sha256' => $cohortPrerequisiteFingerprint,
                ],
                'classification_semantics' => [
                    'deterministic_exact_mapping_candidate' => 'The exact owner, business, and application chain is structurally deterministic under the existing plans. This is a proposed mapping candidate only and requires acceptance before any mapping is created.',
                    'reference_data_crosswalk_only' => 'Identity is deterministic, but the existing plans require only explicit reference-data reconciliation before an exact application mapping can become Ready.',
                    'human_identity_reconciliation' => 'One or more existing collision signals require human identity review. Similarity is not treated as legal identity.',
                    'registry_policy_reconciliation' => 'Deleted, blacklisted, Group-owner, or related registry policy prevents deterministic operational mapping.',
                    'application_reconciliation_required' => 'The identity chain is structurally present, but application lifecycle or non-reference policy evidence remains unresolved.',
                    'structural_reference_break' => 'An exact source record, payload match, ownership edge, or mapping proposal is absent or contradictory.',
                ],
                'safety' => [
                    'read_only_characterization' => true,
                    'accepted_mappings_created' => false,
                    'legacy_ids_in_report' => false,
                    'source_payloads_in_report' => false,
                    'identity_similarity_is_authority' => false,
                    'production_execution_authorized' => false,
                    'production_mutation' => false,
                    'migration_executed' => false,
                ],
            ],
            'candidates' => array_values($candidates->all()),
            'exceptions' => array_values($exceptions->all()),
            'cohort' => array_values($cohort->all()),
            'cohort_prerequisites' => array_values($cohortPrerequisites->all()),
        ];
    }

    /**
     * @param  Collection<int, array{application: LegacyRecord, preservation_executor_compatibility_reasons: list<string>, compatibility_evidence: array<string, mixed>}>  $strictCandidates
     * @return Collection<string, LegacyRecord>
     */
    private function registryRecords(int $batchId, Collection $strictCandidates): Collection
    {
        $ids = $strictCandidates->flatMap(function (array $candidate): array {
            $payload = $candidate['application']->payload;

            return array_values(array_filter([
                is_string($payload['businessOwnerId'] ?? null) ? $payload['businessOwnerId'] : null,
                is_string($payload['businessId'] ?? null) ? $payload['businessId'] : null,
            ]));
        })->unique()->values();

        return LegacyRecord::query()
            ->where('legacy_import_batch_id', $batchId)
            ->whereIn('dataset_key', ['business_owners', 'businesses'])
            ->whereIn('legacy_id', $ids)
            ->get()
            ->keyBy(fn (LegacyRecord $record): string => $record->dataset_key.'|'.$record->legacy_id);
    }

    /**
     * @param  Collection<int, LegacyFinancialMappingProposal>  $proposals
     * @return array<int, Collection<int, LegacyFinancialMappingProposal>>
     */
    private function financialProposalsByApplication(Collection $proposals): array
    {
        $bySourceRecord = $proposals->groupBy('legacy_record_id');
        $schedulesByApplication = $proposals
            ->where('kind', 'payment_schedule')
            ->groupBy(fn (LegacyFinancialMappingProposal $proposal): int => (int) ($proposal->metadata['application_source_record_id'] ?? 0));
        $paymentsByApplication = $proposals
            ->where('kind', 'payment')
            ->groupBy(fn (LegacyFinancialMappingProposal $proposal): int => (int) ($proposal->metadata['application_source_record_id'] ?? 0));
        $result = [];

        foreach ($schedulesByApplication as $applicationId => $schedules) {
            if ((int) $applicationId < 1) {
                continue;
            }
            $applicationProposals = collect();
            foreach ($schedules as $schedule) {
                $applicationProposals->push(...($bySourceRecord->get($schedule->legacy_record_id) ?? collect()));
            }
            foreach ($paymentsByApplication->get($applicationId, collect()) as $payment) {
                $applicationProposals->push(...($bySourceRecord->get($payment->legacy_record_id) ?? collect()));
            }
            $result[(int) $applicationId] = $applicationProposals->unique('id')->sortBy('id')->values();
        }

        return $result;
    }

    /**
     * @param  array<string, int>  $bundleTotals
     * @param  array<string, int>  $projectionTotals
     */
    private function addBundleTotals(array &$bundleTotals, array $projectionTotals, int $unpaidScheduleCount): void
    {
        $bundleTotals['schedule_count'] += $projectionTotals['schedule_count'];
        $bundleTotals['fee_line_count'] += $projectionTotals['fee_line_count'];
        $bundleTotals['payment_count'] += $projectionTotals['payment_count'];
        $bundleTotals['unpaid_schedule_count'] += $unpaidScheduleCount;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $exceptions
     * @return array<string, int>
     */
    private function sumNestedCounts(Collection $exceptions, string $key): array
    {
        $counts = [];
        foreach ($exceptions as $exception) {
            foreach ($exception['compatibility_evidence'][$key] ?? [] as $value => $count) {
                $counts[(string) $value] = ($counts[(string) $value] ?? 0) + (int) $count;
            }
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @param  Collection<int, LegacyFinancialMappingProposal>  $financialProposals
     * @param  array<string, mixed>  $projection
     * @return array<string, mixed>
     */
    private function compatibilityEvidence(LegacyRecord $application, Collection $financialProposals, array $projection): array
    {
        $schedules = data_get($projection, 'financial_history.schedules', []);
        $schedules = is_array($schedules) ? $schedules : [];
        $assignedPaymentIds = collect($schedules)
            ->flatMap(fn (array $schedule): array => array_column($schedule['payments'] ?? [], 'source_record_id'))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $unassignedPayments = $financialProposals
            ->where('kind', 'payment')
            ->filter(fn (LegacyFinancialMappingProposal $proposal): bool => (int) ($proposal->metadata['application_source_record_id'] ?? 0) === $application->id)
            ->reject(fn (LegacyFinancialMappingProposal $proposal): bool => $assignedPaymentIds->contains($proposal->legacy_record_id))
            ->values();

        $edgeClassCounts = $unassignedPayments
            ->countBy(function (LegacyFinancialMappingProposal $proposal): string {
                $metadata = $proposal->metadata ?? [];
                if (($metadata['schedule_source_record_id'] ?? null) !== null) {
                    return 'referenced_schedule_outside_application_bundle';
                }

                return is_string($metadata['legacy_schedule_id_sha256'] ?? null)
                    ? 'referenced_schedule_absent_from_snapshot'
                    : 'source_schedule_identifier_missing';
            })
            ->sortKeys()
            ->all();
        $statusCounts = $unassignedPayments
            ->countBy(fn (LegacyFinancialMappingProposal $proposal): string => (string) ($proposal->metadata['status'] ?? 'unknown'))
            ->sortKeys()
            ->all();

        return [
            'unassigned_payment_event_count' => $unassignedPayments->count(),
            'edge_class_counts' => $edgeClassCounts,
            'status_counts' => $statusCounts,
            'all_source_schedule_identifiers_present' => $unassignedPayments->every(
                fn (LegacyFinancialMappingProposal $proposal): bool => is_string($proposal->metadata['legacy_schedule_id_sha256'] ?? null),
            ),
            'source_identifiers_exposed' => false,
            'source_payloads_exposed' => false,
        ];
    }

    /**
     * @param  list<string>  $preservationExecutorCompatibilityReasons
     * @param  array<string, mixed>  $compatibilityEvidence
     * @param  Collection<string, LegacyRecord>  $registryRecords
     * @param  Collection<string, LegacyRecord>  $financialRegistryRecords
     * @param  Collection<int, LegacyMappingProposal>  $registryProposals
     * @return array<string, mixed>
     */
    private function characterize(
        LegacyRecord $application,
        string $financialDependencySnapshotHash,
        array $preservationExecutorCompatibilityReasons,
        array $compatibilityEvidence,
        Collection $registryRecords,
        Collection $financialRegistryRecords,
        Collection $registryProposals,
    ): array {
        $applicationProjection = $this->applicationProjector->project($application);
        $ownerLegacyId = $applicationProjection['owner_legacy_id'];
        $businessLegacyId = $applicationProjection['business_legacy_id'];
        $owner = $ownerLegacyId === null ? null : $registryRecords->get('business_owners|'.$ownerLegacyId);
        $business = $businessLegacyId === null ? null : $registryRecords->get('businesses|'.$businessLegacyId);
        $financialOwner = $ownerLegacyId === null ? null : $financialRegistryRecords->get('business_owners|'.$ownerLegacyId);
        $financialBusiness = $businessLegacyId === null ? null : $financialRegistryRecords->get('businesses|'.$businessLegacyId);
        $ownerProposal = $owner instanceof LegacyRecord ? $registryProposals->get($owner->id) : null;
        $businessProposal = $business instanceof LegacyRecord ? $registryProposals->get($business->id) : null;

        $structuralReasons = [];
        foreach ([
            'exact_owner_record_missing' => $owner,
            'exact_business_record_missing' => $business,
            'financial_batch_owner_record_missing' => $financialOwner,
            'financial_batch_business_record_missing' => $financialBusiness,
            'owner_mapping_proposal_missing' => $ownerProposal,
            'business_mapping_proposal_missing' => $businessProposal,
        ] as $reason => $value) {
            if ($value === null) {
                $structuralReasons[] = $reason;
            }
        }
        if ($owner instanceof LegacyRecord && $financialOwner instanceof LegacyRecord && ! hash_equals($owner->payload_hash, $financialOwner->payload_hash)) {
            $structuralReasons[] = 'owner_payload_differs_between_bound_batches';
        }
        if ($business instanceof LegacyRecord && $financialBusiness instanceof LegacyRecord && ! hash_equals($business->payload_hash, $financialBusiness->payload_hash)) {
            $structuralReasons[] = 'business_payload_differs_between_bound_batches';
        }
        if ($business instanceof LegacyRecord && ($business->payload['ownerId'] ?? null) !== $ownerLegacyId) {
            $structuralReasons[] = 'business_owner_edge_conflicts_with_application';
        }
        if ($businessProposal instanceof LegacyMappingProposal && $owner instanceof LegacyRecord && $businessProposal->parent_legacy_record_id !== $owner->id) {
            $structuralReasons[] = 'business_proposal_parent_conflicts_with_owner';
        }

        $ownerReasons = $ownerProposal instanceof LegacyMappingProposal ? ($ownerProposal->reasons ?? []) : [];
        $businessReasons = $businessProposal instanceof LegacyMappingProposal ? ($businessProposal->reasons ?? []) : [];
        $applicationReasons = $applicationProjection['reasons'];
        $allReasons = array_values(array_unique([...$ownerReasons, ...$businessReasons, ...$applicationReasons]));
        $collisionReasons = array_values(array_intersect($allReasons, self::CollisionReasons));
        $registryPolicyReasons = array_values(array_intersect($allReasons, self::RegistryPolicyReasons));
        $relevantReasons = array_values(array_diff($allReasons, ['owner_mapping_proposal_not_ready']));
        $referenceOnly = $structuralReasons === []
            && $relevantReasons !== []
            && array_diff($relevantReasons, self::ReferenceDataReasons) === [];
        $deterministicIdentity = $structuralReasons === [] && $collisionReasons === [] && $registryPolicyReasons === [];

        $classification = match (true) {
            $structuralReasons !== [] || $applicationProjection['blocked'] => 'structural_reference_break',
            $collisionReasons !== [] => 'human_identity_reconciliation',
            $registryPolicyReasons !== [] => 'registry_policy_reconciliation',
            $referenceOnly => 'reference_data_crosswalk_only',
            $allReasons !== [] => 'application_reconciliation_required',
            $ownerProposal?->status === LegacyMappingProposalStatus::Ready
                && $businessProposal?->status === LegacyMappingProposalStatus::Ready => 'deterministic_exact_mapping_candidate',
            default => 'application_reconciliation_required',
        };

        $candidateFingerprint = $this->hash([
            hash('sha256', $application->legacy_id),
            $application->payload_hash,
            $owner instanceof LegacyRecord ? $owner->payload_hash : null,
            $business instanceof LegacyRecord ? $business->payload_hash : null,
            $financialDependencySnapshotHash,
        ]);

        return [
            'candidate_fingerprint' => $candidateFingerprint,
            'classification' => $classification,
            'proposed_mapping_status' => 'pending_acceptance',
            'application' => [
                'source_record_id' => $application->id,
                'legacy_id_sha256' => hash('sha256', $application->legacy_id),
                'payload_hash' => $application->payload_hash,
                'projection_hash' => $this->applicationProjector->hashCanonical($applicationProjection['attributes']),
                'reasons' => $applicationReasons,
            ],
            'owner' => $this->recordEvidence($owner, $ownerProposal),
            'business' => $this->recordEvidence($business, $businessProposal),
            'structural_reasons' => $structuralReasons,
            'preservation_executor_compatibility_reasons' => $preservationExecutorCompatibilityReasons,
            'compatibility_evidence' => $compatibilityEvidence,
            'flags' => [
                'deterministic_identity_chain' => $deterministicIdentity,
                'preservation_executor_compatible' => $preservationExecutorCompatibilityReasons === [],
                'reference_data_crosswalk_only' => $referenceOnly,
                'human_identity_reconciliation' => $collisionReasons !== [],
                'group_owner' => in_array('group_owner_semantics_require_reconciliation', $allReasons, true),
                'soft_deleted' => in_array('soft_deleted_record_policy_unresolved', $allReasons, true)
                    || in_array('soft_deleted_application_policy_unresolved', $allReasons, true),
                'blacklisted' => in_array('blacklist_state_requires_registry_policy', $allReasons, true),
                'collision' => $collisionReasons !== [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  Collection<string, LegacyRecord>  $registryRecords
     * @return array<string, mixed>
     */
    private function cohortPrerequisiteProposal(array $candidate, Collection $registryRecords): array
    {
        $businessRecord = $registryRecords->first(fn (LegacyRecord $record): bool => $record->dataset_key === 'businesses'
            && hash_equals(hash('sha256', $record->legacy_id), (string) $candidate['business']['legacy_id_sha256']));
        $applicationRecord = LegacyRecord::query()->find((int) $candidate['application']['source_record_id']);
        $referenceFields = [];
        if ($businessRecord instanceof LegacyRecord) {
            foreach (['provinceId', 'cityId', 'barangayId', 'categoryId', 'subCategoryId'] as $field) {
                $value = $businessRecord->payload[$field] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $referenceFields[] = [
                        'field' => $field,
                        'source_value_sha256' => hash('sha256', trim($value)),
                        'proposed_disposition' => 'pending_source_backed_crosswalk',
                        'acceptance_status' => 'pending',
                    ];
                }
            }
        }
        $linesOfBusiness = $applicationRecord?->payload['linesOfBusiness'] ?? [];
        $linesOfBusiness = is_array($linesOfBusiness) ? $linesOfBusiness : [];
        $declarationValues = collect($linesOfBusiness)
            ->map(fn (mixed $line): ?string => is_array($line) && is_string($line['businessCategory'] ?? null)
                ? trim($line['businessCategory'])
                : null)
            ->filter(fn (?string $value): bool => $value !== null && $value !== '')
            ->values()
            ->map(fn (string $value): array => [
                'source_value_sha256' => hash('sha256', $value),
                'proposed_target_line_of_business_id' => null,
                'proposal_status' => 'target_evidence_required',
                'acceptance_status' => 'pending',
            ])
            ->all();

        return [
            'candidate_fingerprint' => $candidate['candidate_fingerprint'],
            'cohort_acceptance_status' => 'pending',
            'reference_data_crosswalk_proposals' => $referenceFields,
            'line_of_business_crosswalk_proposals' => $declarationValues,
            'exact_mapping_proposals' => [
                'owner' => [
                    'source_record_id' => $candidate['owner']['source_record_id'] ?? null,
                    'registry_proposal_id' => $candidate['owner']['proposal_id'] ?? null,
                    'acceptance_status' => 'pending',
                ],
                'business' => [
                    'source_record_id' => $candidate['business']['source_record_id'] ?? null,
                    'registry_proposal_id' => $candidate['business']['proposal_id'] ?? null,
                    'acceptance_status' => 'blocked_by_reference_data_crosswalk',
                ],
                'application' => [
                    'source_record_id' => $candidate['application']['source_record_id'],
                    'acceptance_status' => 'blocked_by_registry_and_declaration_acceptance',
                ],
            ],
            'accepted_reconciliations_created' => false,
            'accepted_mappings_created' => false,
            'production_rehearsal_authorized' => false,
        ];
    }

    /** @return array<string, mixed>|null */
    private function recordEvidence(?LegacyRecord $record, ?LegacyMappingProposal $proposal): ?array
    {
        if (! $record instanceof LegacyRecord) {
            return null;
        }

        return [
            'source_record_id' => $record->id,
            'legacy_id_sha256' => hash('sha256', $record->legacy_id),
            'payload_hash' => $record->payload_hash,
            'proposal_id' => $proposal?->id,
            'proposal_status' => $proposal?->status->value,
            'proposed_action' => $proposal?->proposed_action->value,
            'reasons' => $proposal instanceof LegacyMappingProposal ? ($proposal->reasons ?? []) : [],
        ];
    }

    private function assertEvidence(LegacyFinancialMappingPlan $financialPlan, LegacyMappingPlan $registryPlan): void
    {
        foreach ([$financialPlan->status, $registryPlan->status] as $status) {
            if (! in_array($status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
                throw new RuntimeException('Financial and registry plans must both be complete before mapping-readiness characterization.');
            }
        }
        if ($financialPlan->legacy_import_batch_id === $registryPlan->legacy_import_batch_id) {
            return;
        }
        $financialSource = $financialPlan->importBatch->source;
        $registrySource = $registryPlan->importBatch->source;
        if ($financialSource->id !== $registrySource->id
            || ! is_string($financialSource->archive_checksum)
            || ! is_string($registrySource->archive_checksum)
            || ! hash_equals($financialSource->archive_checksum, $registrySource->archive_checksum)) {
            throw new RuntimeException('Financial and registry plans must be bound to the same checksum-verified legacy source.');
        }
    }

    /** @param array<array-key, mixed> $value */
    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
