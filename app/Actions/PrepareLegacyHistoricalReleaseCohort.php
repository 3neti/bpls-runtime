<?php

namespace App\Actions;

use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyFinancialMappingProposal;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyRecord;
use Illuminate\Support\Collection;
use RuntimeException;

class PrepareLegacyHistoricalReleaseCohort
{
    public const SchemaVersion = 'bpls.historical-release-evidence-cohort.v1';

    public const DefaultCohortSize = 25;

    private const LocationFields = [
        'provinceId' => 'provinces',
        'cityId' => 'cities',
        'barangayId' => 'barangays',
    ];

    public function __construct(
        private CharacterizeLegacyHistoricalFinancialApplicationMappings $mappingReadiness,
        private LegacyHistoricalFinancialPreservationProjector $preservationProjector,
        private LegacyPermitApplicationProjector $applicationProjector,
        private BuildLegacyHistoricalFinancialProposalIndex $buildProposalIndex,
    ) {}

    /**
     * @return array{
     *   report: array<string, mixed>,
     *   location_proposals: list<array<string, mixed>>,
     *   line_of_business_proposals: list<array<string, mixed>>,
     *   exact_mapping_proposals: list<array<string, mixed>>,
     *   selected_candidates: list<array<string, mixed>>
     * }
     */
    public function handle(
        LegacyFinancialMappingPlan $financialPlan,
        LegacyMappingPlan $registryPlan,
        int $cohortSize = self::DefaultCohortSize,
        bool $excludeAcceptedMappings = false,
        string $sourceStatus = 'Released',
    ): array {
        if ($cohortSize < 1 || $cohortSize > 500) {
            throw new RuntimeException('Historical release cohort size must be between 1 and 500 records.');
        }
        if (! in_array($sourceStatus, ['Assessment', 'Released'], true)) {
            throw new RuntimeException('Historical evidence cohort source status must be Assessment or Released.');
        }

        $readiness = $this->mappingReadiness->handle($financialPlan, $registryPlan);
        $candidateSourceRecordIds = collect($readiness['candidates'])
            ->pluck('application.source_record_id')
            ->filter(fn (mixed $id): bool => is_int($id))
            ->all();
        $sourceStatuses = LegacyRecord::query()
            ->whereIn('id', $candidateSourceRecordIds)
            ->get()
            ->mapWithKeys(fn (LegacyRecord $record): array => [
                $record->id => is_string($record->payload['status'] ?? null) ? $record->payload['status'] : '',
            ]);
        $historicalReleaseCandidates = collect($readiness['candidates'])
            ->filter(fn (array $candidate): bool => $this->isHistoricalEvidenceCandidate($candidate, $sourceStatus)
                && $sourceStatuses->get((int) data_get($candidate, 'application.source_record_id')) === $sourceStatus)
            ->sortBy('candidate_fingerprint')
            ->values();
        $mappedSourceRecordIds = $excludeAcceptedMappings
            ? $this->mappedApplicationSourceRecordIds($financialPlan)
            : collect();
        $financialProposals = $financialPlan->proposals()
            ->with('legacyRecord')
            ->whereIn('kind', ['payment_schedule', 'payment_schedule_fee', 'payment', 'receipt_claim'])
            ->orderBy('id')
            ->get();
        $proposalsByApplication = $this->buildProposalIndex->handle($financialProposals);
        $lookupRecords = LegacyRecord::query()
            ->where('legacy_import_batch_id', $registryPlan->legacy_import_batch_id)
            ->whereIn('dataset_key', array_values(self::LocationFields))
            ->get()
            ->keyBy(fn (LegacyRecord $record): string => $record->dataset_key.'|'.$record->legacy_id);
        $locationReady = $historicalReleaseCandidates
            ->reject(fn (array $candidate): bool => $mappedSourceRecordIds->contains((int) data_get($candidate, 'application.source_record_id')))
            ->map(fn (array $candidate): array => $this->enrichCandidate(
                $candidate,
                $financialPlan,
                $proposalsByApplication,
                $lookupRecords,
            ))
            ->filter(fn (array $candidate): bool => $candidate['location']['proposal_status'] === 'evidence_complete_acceptance_pending')
            ->values();
        $topologyCounts = $locationReady->countBy('coherent_topology')->sortDesc();
        $selectedTopology = $topologyCounts->keys()->first();
        $eligible = is_string($selectedTopology)
            ? $locationReady->where('coherent_topology', $selectedTopology)->sortBy('candidate_fingerprint')->values()
            : collect();

        if ($eligible->count() < $cohortSize) {
            throw new RuntimeException("Only {$eligible->count()} historical Released applications satisfy the largest exact financial topology; {$cohortSize} were requested.");
        }

        $selected = $eligible->take($cohortSize)->values();
        $locationProposals = $selected->pluck('location')->values()->all();
        $exactMappingProposals = $selected->map(fn (array $candidate): array => [
            'candidate_fingerprint' => $candidate['candidate_fingerprint'],
            'owner' => [
                'source_record_id' => data_get($candidate, 'owner.source_record_id'),
                'registry_proposal_id' => data_get($candidate, 'owner.proposal_id'),
            ],
            'business' => [
                'source_record_id' => data_get($candidate, 'business.source_record_id'),
                'registry_proposal_id' => data_get($candidate, 'business.proposal_id'),
            ],
            'application' => [
                'source_record_id' => data_get($candidate, 'application.source_record_id'),
                'payload_hash' => data_get($candidate, 'application.payload_hash'),
                'historical_projection_hash' => $candidate['historical_projection_hash'],
            ],
            'proposal_status' => 'evidence_complete_acceptance_pending',
            'acceptance_status' => 'proposed_not_accepted',
        ])->values()->all();
        $uniqueOwnerProposalCount = collect($exactMappingProposals)->pluck('owner.registry_proposal_id')->unique()->count();
        $uniqueBusinessProposalCount = collect($exactMappingProposals)->pluck('business.registry_proposal_id')->unique()->count();
        $classEvidenceBinding = [
            'source_archive_checksum' => $financialPlan->importBatch->source->archive_checksum,
            'financial_plan_id' => $financialPlan->id,
            'financial_dependency_snapshot_hash' => $financialPlan->dependency_snapshot_hash,
            'registry_plan_id' => $registryPlan->id,
            'registry_snapshot_hash' => $registryPlan->registry_snapshot_hash,
            'strict_candidate_set_sha256' => data_get($readiness, 'report.fingerprints.candidate_set_sha256'),
            'projection_mode' => 'historical_evidence',
            ...($sourceStatus === 'Released' ? [] : ['source_status' => $sourceStatus]),
        ];
        $evidenceBinding = [
            ...$classEvidenceBinding,
            'topology' => $selectedTopology,
            'accepted_mappings_excluded' => $excludeAcceptedMappings,
        ];
        $historicalReleaseClassSha256 = $this->hash([
            $classEvidenceBinding,
            ...$historicalReleaseCandidates->map(fn (array $candidate): array => [
                $candidate['candidate_fingerprint'],
                data_get($candidate, 'application.source_record_id'),
                data_get($candidate, 'application.payload_hash'),
            ])->all(),
        ]);
        $selectedEvidence = $selected->map(fn (array $candidate): array => [
            'candidate_fingerprint' => $candidate['candidate_fingerprint'],
            'application_source_record_id' => data_get($candidate, 'application.source_record_id'),
            'application_payload_hash' => data_get($candidate, 'application.payload_hash'),
            'historical_projection_hash' => $candidate['historical_projection_hash'],
            'financial_projection_hash' => $candidate['financial_projection_hash'],
        ])->values()->all();
        $cohortSha256 = $this->hash([$evidenceBinding, $selectedEvidence]);
        $proposalPackageSha256 = $this->hash([
            $evidenceBinding,
            $cohortSha256,
            $locationProposals,
            $exactMappingProposals,
        ]);

        return [
            'report' => [
                'schema_version' => self::SchemaVersion,
                'evidence' => $evidenceBinding,
                'summary' => [
                    'strict_v1_candidate_count' => count($readiness['candidates']),
                    'historical_release_candidate_count' => $historicalReleaseCandidates->count(),
                    'accepted_mapping_exclusion_count' => $mappedSourceRecordIds->count(),
                    'exact_location_candidate_count' => $locationReady->count(),
                    'selected_topology' => $selectedTopology,
                    'selected_topology_candidate_count' => $eligible->count(),
                    'topology_counts' => $topologyCounts->all(),
                    'cohort_size' => $selected->count(),
                    'unique_owner_identity_count' => $uniqueOwnerProposalCount,
                    'unique_business_identity_count' => $uniqueBusinessProposalCount,
                    'shared_identity_dependency_count' => ($selected->count() * 2) - $uniqueOwnerProposalCount - $uniqueBusinessProposalCount,
                    'current_release_authorized_count' => 0,
                    'current_legal_effect_verified_count' => 0,
                    'operationally_eligible_count' => 0,
                    'accepted_mapping_count' => 0,
                    'historical_preservation_executed' => false,
                ],
                'fingerprints' => [
                    'historical_release_class_sha256' => $historicalReleaseClassSha256,
                    'selected_cohort_sha256' => $cohortSha256,
                    'prerequisite_proposals_sha256' => $proposalPackageSha256,
                ],
                'semantic_boundary' => [
                    'historical_source_assertion' => 'exact',
                    'current_release_authority' => 'not_asserted',
                    'current_legal_effect' => 'not_verified',
                    'source_classifications' => 'historical_only',
                    'future_policy' => 'not_executable',
                ],
                'safety' => [
                    'read_only' => true,
                    'source_payloads_exposed' => false,
                    'legacy_identifiers_exposed' => false,
                    'identity_similarity_used' => false,
                    'historical_liability_recalculated' => false,
                    'fee_identity_inferred' => false,
                    'production_mutation' => false,
                ],
            ],
            'location_proposals' => array_values($locationProposals),
            'line_of_business_proposals' => [],
            'exact_mapping_proposals' => array_values($exactMappingProposals),
            'selected_candidates' => array_values($selectedEvidence),
        ];
    }

    /** @param array<string, mixed> $candidate */
    private function isHistoricalEvidenceCandidate(array $candidate, string $sourceStatus): bool
    {
        $applicationReasons = data_get($candidate, 'application.reasons', []);
        $applicationReasons = is_array($applicationReasons) ? array_values($applicationReasons) : [];
        sort($applicationReasons);

        $expectedApplicationReasons = $sourceStatus === 'Released'
            ? ['legacy_release_authority_unresolved', 'line_of_business_mapping_required']
            : ['line_of_business_mapping_required'];

        return data_get($candidate, 'flags.deterministic_identity_chain') === true
            && data_get($candidate, 'flags.preservation_executor_compatible') === true
            && ($candidate['structural_reasons'] ?? null) === []
            && data_get($candidate, 'owner.reasons', []) === []
            && data_get($candidate, 'business.reasons', []) === ['reference_data_mapping_required']
            && $applicationReasons === $expectedApplicationReasons;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<int, Collection<int, LegacyFinancialMappingProposal>>  $proposalsByApplication
     * @param  Collection<string, LegacyRecord>  $lookupRecords
     * @return array<string, mixed>
     */
    private function enrichCandidate(
        array $candidate,
        LegacyFinancialMappingPlan $financialPlan,
        array $proposalsByApplication,
        Collection $lookupRecords,
    ): array {
        $application = LegacyRecord::query()->find((int) data_get($candidate, 'application.source_record_id'));
        $business = LegacyRecord::query()->find((int) data_get($candidate, 'business.source_record_id'));
        if (! $application instanceof LegacyRecord || ! $business instanceof LegacyRecord) {
            throw new RuntimeException('A deterministic historical release candidate lost an exact source dependency.');
        }

        $applicationProposals = $proposalsByApplication[$application->id] ?? collect();
        $financialProjection = $this->preservationProjector->project($financialPlan, $application, $applicationProposals);
        $schedules = data_get($financialProjection, 'projection.financial_history.schedules', []);
        $totals = data_get($financialProjection, 'projection.financial_history.totals', []);
        $statuses = is_array($schedules)
            ? collect($schedules)->pluck('status')->map(fn (mixed $status): string => (string) $status)->sort()->values()->implode(',')
            : 'invalid';
        $coherentTopology = implode('|', [
            'schedules:'.(is_array($schedules) ? count($schedules) : -1),
            'payments:'.(is_int($totals['payment_count'] ?? null) ? $totals['payment_count'] : -1),
            'statuses:'.$statuses,
        ]);
        $historicalProjection = $this->applicationProjector->projectHistoricalEvidence($application);

        return [
            ...$candidate,
            'coherent_topology' => $coherentTopology,
            'historical_projection_hash' => $this->applicationProjector->hashCanonical($historicalProjection['attributes']),
            'financial_projection_hash' => $this->preservationProjector->hash($financialProjection['projection']),
            'location' => $this->locationProposal($candidate, $business, $lookupRecords),
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  Collection<string, LegacyRecord>  $lookupRecords
     * @return array<string, mixed>
     */
    private function locationProposal(array $candidate, LegacyRecord $business, Collection $lookupRecords): array
    {
        $references = [];
        $resolved = [];
        foreach (self::LocationFields as $field => $dataset) {
            $value = $business->payload[$field] ?? null;
            $value = is_string($value) ? trim($value) : '';
            $record = $value === '' ? null : $lookupRecords->get($dataset.'|'.$value);
            $resolved[$field] = $record;
            $references[] = [
                'field' => $field,
                'source_dataset' => $dataset,
                'source_value_sha256' => $value === '' ? null : hash('sha256', $value),
                'source_lookup_record_id' => $record?->id,
                'source_lookup_payload_hash' => $record?->payload_hash,
                'resolution_status' => $record instanceof LegacyRecord ? 'exact_source_id_resolved' : 'unresolved',
            ];
        }

        $provinceId = is_string($business->payload['provinceId'] ?? null) ? trim($business->payload['provinceId']) : '';
        $cityId = is_string($business->payload['cityId'] ?? null) ? trim($business->payload['cityId']) : '';
        $city = $resolved['cityId'];
        $barangay = $resolved['barangayId'];
        $exact = collect($resolved)->every(fn (?LegacyRecord $record): bool => $record instanceof LegacyRecord)
            && data_get($city?->payload, 'provinceId') === $provinceId
            && data_get($barangay?->payload, 'cityId') === $cityId;

        return [
            'candidate_fingerprint' => $candidate['candidate_fingerprint'],
            'business_source_record_id' => $business->id,
            'references' => $references,
            'source_chain_status' => $exact ? 'exact_hierarchy_resolved' : 'unresolved_source_hierarchy',
            'proposed_disposition' => $exact
                ? 'preserve_exact_source_lookup_chain_as_registry_provenance'
                : 'remain_blocked_pending_source_reconciliation',
            'proposal_status' => $exact ? 'evidence_complete_acceptance_pending' : 'blocked',
            'acceptance_status' => 'proposed_not_accepted',
        ];
    }

    /** @return Collection<int, int> */
    private function mappedApplicationSourceRecordIds(LegacyFinancialMappingPlan $financialPlan): Collection
    {
        $legacyIds = LegacyApplicationIdMapping::query()
            ->where('legacy_source_id', $financialPlan->importBatch->legacy_source_id)
            ->where('dataset_key', 'business_permit_applications')
            ->where('status', 'mapped')
            ->pluck('legacy_id');

        return LegacyRecord::query()
            ->where('legacy_import_batch_id', $financialPlan->legacy_import_batch_id)
            ->where('dataset_key', 'business_permit_applications')
            ->whereIn('legacy_id', $legacyIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();
    }

    /** @param array<array-key, mixed> $value */
    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
