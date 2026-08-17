<?php

namespace App\Actions;

use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyHistoricalFinancialMappingSet;
use App\Models\LegacyHistoricalFinancialPreservationExecution;
use App\Models\LegacyMappingPlan;
use Illuminate\Support\Collection;
use RuntimeException;

class CharacterizeLegacyHistoricalFinancialNextScaleReadiness
{
    public const SchemaVersion = 'bpls.historical-financial-next-scale-readiness.v1';

    public function __construct(
        private CharacterizeLegacyHistoricalFinancialApplicationMappings $characterizeMappings,
        private AcceptLegacyHistoricalFinancialCohortMappings $auditAcceptedMappings,
    ) {}

    /** @return array{report: array<string, mixed>, same_semantic_candidates: list<array<string, mixed>>, expansion_candidates: list<array<string, mixed>>, historical_release_candidates: list<array<string, mixed>>} */
    public function handle(
        LegacyFinancialMappingPlan $financialPlan,
        LegacyMappingPlan $registryPlan,
        LegacyHistoricalFinancialMappingSet $baselineMappingSet,
        LegacyHistoricalFinancialPreservationExecution $baselineExecution,
        string $expectedSourceSha256,
        string $expectedBaselineCohortSha256,
        string $expectedBaselineMappingSetSha256,
        string $expectedBaselineDependencySha256,
    ): array {
        $this->assertFingerprint($expectedSourceSha256, 'source snapshot');
        $this->assertFingerprint($expectedBaselineCohortSha256, 'baseline cohort');
        $this->assertFingerprint($expectedBaselineMappingSetSha256, 'baseline mapping set');
        $this->assertFingerprint($expectedBaselineDependencySha256, 'baseline preservation dependency');
        $this->assertBaseline(
            $financialPlan,
            $registryPlan,
            $baselineMappingSet,
            $baselineExecution,
            $expectedSourceSha256,
            $expectedBaselineCohortSha256,
            $expectedBaselineMappingSetSha256,
            $expectedBaselineDependencySha256,
        );

        $readiness = $this->characterizeMappings->handle($financialPlan, $registryPlan);
        $candidates = collect($readiness['candidates']);
        $sameSemanticCandidates = $candidates
            ->filter(fn (array $candidate): bool => $this->hasProvenSemantics($candidate))
            ->sortBy('candidate_fingerprint')
            ->values();
        $baselineSourceRecordIds = collect($this->applicationMappings($baselineMappingSet))
            ->pluck('source_record_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values();
        $baselineCandidates = $sameSemanticCandidates
            ->whereIn('application.source_record_id', $baselineSourceRecordIds)
            ->values();
        $expansionCandidates = $sameSemanticCandidates
            ->whereNotIn('application.source_record_id', $baselineSourceRecordIds)
            ->values();
        $historicalReleaseCandidates = $candidates
            ->filter(fn (array $candidate): bool => $this->isHistoricalReleaseEvidenceCandidate($candidate))
            ->sortBy('candidate_fingerprint')
            ->values();

        if ($baselineCandidates->count() !== $baselineMappingSet->cohort_size) {
            throw new RuntimeException('The proven baseline is no longer an exact subset of the same-semantic production candidates.');
        }

        $evidence = [
            $expectedSourceSha256,
            data_get($readiness, 'report.fingerprints.candidate_set_sha256'),
            $expectedBaselineCohortSha256,
            $expectedBaselineMappingSetSha256,
            $expectedBaselineDependencySha256,
        ];
        $sameSemanticFingerprint = $this->hash([
            ...$evidence,
            ...$sameSemanticCandidates->map(fn (array $candidate): array => $this->candidateIdentity($candidate))->all(),
        ]);
        $expansionFingerprint = $this->hash([
            ...$evidence,
            ...$expansionCandidates->map(fn (array $candidate): array => $this->candidateIdentity($candidate))->all(),
        ]);
        $historicalReleaseFingerprint = $this->hash([
            ...$evidence,
            'projection_mode' => 'historical_evidence',
            ...$historicalReleaseCandidates->map(fn (array $candidate): array => $this->candidateIdentity($candidate))->all(),
        ]);
        $materiallyLarger = $expansionCandidates->count() >= $baselineMappingSet->cohort_size;

        return [
            'report' => [
                'schema_version' => self::SchemaVersion,
                'evidence' => [
                    'source_snapshot_sha256' => $expectedSourceSha256,
                    'candidate_set_sha256' => data_get($readiness, 'report.fingerprints.candidate_set_sha256'),
                    'baseline_cohort_sha256' => $expectedBaselineCohortSha256,
                    'baseline_accepted_mapping_set_sha256' => $expectedBaselineMappingSetSha256,
                    'baseline_preservation_dependency_sha256' => $expectedBaselineDependencySha256,
                    'financial_plan_id' => $financialPlan->id,
                    'registry_plan_id' => $registryPlan->id,
                    'baseline_mapping_set_id' => $baselineMappingSet->id,
                    'baseline_execution_id' => $baselineExecution->id,
                ],
                'summary' => [
                    'strict_v1_candidate_count' => $candidates->count(),
                    'deterministic_identity_chain_count' => $candidates->where('flags.deterministic_identity_chain', true)->count(),
                    'baseline_cohort_size' => $baselineMappingSet->cohort_size,
                    'same_semantic_candidate_count' => $sameSemanticCandidates->count(),
                    'same_semantic_baseline_count' => $baselineCandidates->count(),
                    'same_semantic_expansion_count' => $expansionCandidates->count(),
                    'maximum_same_semantic_cohort_size' => $sameSemanticCandidates->count(),
                    'historical_release_evidence_candidate_count' => $historicalReleaseCandidates->count(),
                    'historical_release_current_authority_count' => 0,
                    'materially_larger_cohort_available' => $materiallyLarger,
                    'semantic_class_counts' => $this->semanticClassCounts($candidates),
                    'accepted_mappings_created' => 0,
                    'preservation_plans_created' => 0,
                    'preservation_executions_created' => 0,
                    'production_rehearsal_executed' => false,
                ],
                'fingerprints' => [
                    'maximum_same_semantic_cohort_sha256' => $sameSemanticFingerprint,
                    'same_semantic_expansion_sha256' => $expansionFingerprint,
                    'historical_release_evidence_candidates_sha256' => $historicalReleaseFingerprint,
                ],
                'authorization_gates' => [
                    'no_new_policy_assumption' => true,
                    'every_selected_application_has_accepted_exact_mapping' => false,
                    'every_v1_eligibility_check_passes' => true,
                    'every_source_and_dependency_fingerprint_matches' => true,
                    'expected_counts_and_centavos_known' => false,
                    'operational_baseline_recorded' => false,
                    'same_v1_executor' => true,
                    'operational_executor_unchanged' => true,
                    'operational_executor_not_used' => true,
                    'no_unresolved_board_trigger' => false,
                ],
                'decision' => [
                    'status' => $historicalReleaseCandidates->isNotEmpty()
                        ? 'historical_release_evidence_prerequisites_required'
                        : ($materiallyLarger ? 'candidate_prerequisites_required' : 'not_ready_for_next_scale_authorization'),
                    'reason' => $historicalReleaseCandidates->isNotEmpty()
                        ? 'Exact legacy Released assertions form a deterministic historical-evidence class. Identity and source facts may be migrated after exact prerequisites are accepted, while current release authority remains false.'
                        : ($materiallyLarger
                        ? 'A materially larger same-semantic class exists, but its exact prerequisites and mappings remain unaccepted.'
                        : 'Only one unused candidate shares the proven five-record semantics and no accelerated historical-release class is available.'),
                    'recommendation' => $historicalReleaseCandidates->isNotEmpty()
                        ? 'PREPARE EVIDENCE-PRESERVING HISTORICAL RELEASE COHORT'
                        : 'RECONCILIATION REQUIRED BEFORE FURTHER SCALE',
                ],
                'safety' => [
                    'read_only' => true,
                    'source_payloads_exposed' => false,
                    'legacy_identifiers_exposed_in_report' => false,
                    'accepted_mappings_created' => false,
                    'production_mutation' => false,
                    'historical_preservation_executed' => false,
                    'operational_finance_mutated' => false,
                ],
            ],
            'same_semantic_candidates' => array_values($sameSemanticCandidates
                ->map(fn (array $candidate): array => $this->candidateIdentity($candidate))
                ->all()),
            'expansion_candidates' => array_values($expansionCandidates
                ->map(fn (array $candidate): array => $this->candidateIdentity($candidate))
                ->all()),
            'historical_release_candidates' => array_values($historicalReleaseCandidates
                ->map(fn (array $candidate): array => [
                    ...$this->candidateIdentity($candidate),
                    'projection_mode' => 'historical_evidence',
                    'source_status_confidence' => 'exact',
                    'current_release_authorized' => false,
                    'current_legal_effect_verified' => false,
                    'operationally_eligible' => false,
                ])
                ->all()),
        ];
    }

    /** @param array<string, mixed> $candidate */
    private function isHistoricalReleaseEvidenceCandidate(array $candidate): bool
    {
        $applicationReasons = $this->applicationReasons($candidate);
        sort($applicationReasons);

        return ($candidate['classification'] ?? null) === 'application_reconciliation_required'
            && data_get($candidate, 'flags.deterministic_identity_chain') === true
            && data_get($candidate, 'flags.preservation_executor_compatible') === true
            && ($candidate['structural_reasons'] ?? null) === []
            && data_get($candidate, 'owner.reasons', []) === []
            && data_get($candidate, 'business.reasons', []) === ['reference_data_mapping_required']
            && $applicationReasons === ['legacy_release_authority_unresolved', 'line_of_business_mapping_required'];
    }

    /** @param array<string, mixed> $candidate */
    private function hasProvenSemantics(array $candidate): bool
    {
        return ($candidate['classification'] ?? null) === 'reference_data_crosswalk_only'
            && data_get($candidate, 'flags.deterministic_identity_chain') === true
            && data_get($candidate, 'flags.preservation_executor_compatible') === true
            && ($candidate['structural_reasons'] ?? null) === []
            && data_get($candidate, 'owner.reasons', []) === []
            && data_get($candidate, 'business.reasons', []) === ['reference_data_mapping_required']
            && data_get($candidate, 'application.reasons', []) === ['line_of_business_mapping_required'];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @return list<array{application_reasons: list<string>, count: int, same_as_proven_baseline: bool}>
     */
    private function semanticClassCounts(Collection $candidates): array
    {
        $classes = $candidates
            ->where('flags.deterministic_identity_chain', true)
            ->groupBy(function (array $candidate): string {
                $reasons = $this->applicationReasons($candidate);
                sort($reasons);

                return json_encode($reasons, JSON_THROW_ON_ERROR);
            })
            ->map(function (Collection $group): array {
                $first = $group->first();
                $reasons = is_array($first) ? $this->applicationReasons($first) : [];
                sort($reasons);

                return [
                    'application_reasons' => $reasons,
                    'count' => $group->count(),
                    'same_as_proven_baseline' => $reasons === ['line_of_business_mapping_required'],
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();

        return array_values($classes);
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function candidateIdentity(array $candidate): array
    {
        return [
            'candidate_fingerprint' => $candidate['candidate_fingerprint'],
            'application_source_record_id' => data_get($candidate, 'application.source_record_id'),
            'application_legacy_id_sha256' => data_get($candidate, 'application.legacy_id_sha256'),
            'application_payload_hash' => data_get($candidate, 'application.payload_hash'),
            'classification' => $candidate['classification'],
        ];
    }

    private function assertBaseline(
        LegacyFinancialMappingPlan $financialPlan,
        LegacyMappingPlan $registryPlan,
        LegacyHistoricalFinancialMappingSet $mappingSet,
        LegacyHistoricalFinancialPreservationExecution $execution,
        string $sourceSha256,
        string $cohortSha256,
        string $mappingSetSha256,
        string $dependencySha256,
    ): void {
        $financialPlan->loadMissing('importBatch.source');
        $acceptedApplicationMappingIds = collect($this->applicationMappings($mappingSet))
            ->pluck('mapping_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
        $executedApplicationMappingIds = $execution->preservationPlan->proposals()
            ->pluck('legacy_application_id_mapping_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
        if ($mappingSet->legacy_financial_mapping_plan_id !== $financialPlan->id
            || $mappingSet->legacy_mapping_plan_id !== $registryPlan->id
            || $mappingSet->legacy_source_id !== $financialPlan->importBatch->legacy_source_id
            || ! hash_equals($sourceSha256, (string) $financialPlan->importBatch->source->archive_checksum)
            || ! hash_equals($cohortSha256, $mappingSet->cohort_sha256)
            || ! hash_equals($mappingSetSha256, (string) $mappingSet->accepted_mapping_set_sha256)
            || $execution->status->value !== 'rolled_back'
            || ! hash_equals($execution->preservationPlan->dependency_snapshot_hash, $dependencySha256)
            || $execution->preservationPlan->legacy_financial_mapping_plan_id !== $financialPlan->id
            || $acceptedApplicationMappingIds !== $executedApplicationMappingIds) {
            throw new RuntimeException('The next-scale characterization is not bound to the proven five-record baseline.');
        }

        $this->auditAcceptedMappings->audit($mappingSet);
    }

    private function assertFingerprint(string $fingerprint, string $label): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $fingerprint) !== 1) {
            throw new RuntimeException("The expected {$label} fingerprint must be a lowercase SHA-256 value.");
        }
    }

    /** @return list<array<string, mixed>> */
    private function applicationMappings(LegacyHistoricalFinancialMappingSet $mappingSet): array
    {
        $mappings = data_get($mappingSet->manifest, 'application_mappings', []);

        return is_array($mappings)
            ? array_values(array_filter($mappings, fn (mixed $mapping): bool => is_array($mapping)))
            : [];
    }

    /** @param array<string, mixed> $candidate
     * @return list<string>
     */
    private function applicationReasons(array $candidate): array
    {
        $reasons = data_get($candidate, 'application.reasons', []);

        return is_array($reasons)
            ? array_values(array_filter($reasons, fn (mixed $reason): bool => is_string($reason)))
            : [];
    }

    /** @param array<array-key, mixed> $value */
    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
