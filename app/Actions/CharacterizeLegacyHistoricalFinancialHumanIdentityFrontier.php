<?php

namespace App\Actions;

use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMappingProposal;
use Illuminate\Support\Collection;

class CharacterizeLegacyHistoricalFinancialHumanIdentityFrontier
{
    public const SchemaVersion = 'bpls.historical-financial-human-identity-frontier.v4';

    private const BlockerCategories = [
        'exact_mapping_acceptance',
        'municipal_identity_decision',
        'registry_policy_decision',
        'reference_data_reconciliation',
        'treasury_interpretation',
        'financial_policy_authority',
        'permit_authority_semantics',
        'genuine_source_data_contradiction',
    ];

    private const BusinessEvidenceReasons = [
        'owner_mapping_proposal_not_ready',
        'reference_data_mapping_required',
    ];

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

    public function __construct(
        private CharacterizeLegacyHistoricalFinancialApplicationMappings $mappingReadiness,
    ) {}

    /** @return array{report: array<string, mixed>, classes: list<array<string, mixed>>, candidates: list<array<string, mixed>>} */
    public function handle(LegacyFinancialMappingPlan $financialPlan, LegacyMappingPlan $registryPlan): array
    {
        $readiness = $this->mappingReadiness->handle($financialPlan, $registryPlan);
        $humanCandidates = collect($readiness['candidates'])
            ->where('classification', 'human_identity_reconciliation')
            ->sortBy('candidate_fingerprint')
            ->values();
        $proposals = LegacyMappingProposal::query()
            ->where('legacy_mapping_plan_id', $registryPlan->id)
            ->get()
            ->keyBy('id');
        $candidateEvidence = $humanCandidates
            ->map(fn (array $candidate): array => $this->candidateEvidence($candidate, $proposals))
            ->values();
        $classes = $candidateEvidence
            ->groupBy('class_sha256')
            ->map(function (Collection $members): array {
                $first = $members->first();

                return [
                    'class_sha256' => $first['class_sha256'],
                    'shape' => $first['shape'],
                    'application_count' => $members->count(),
                    'unique_owner_proposal_count' => $members->pluck('owner_proposal_id')->unique()->count(),
                    'unique_business_proposal_count' => $members->pluck('business_proposal_id')->unique()->count(),
                    'proposed_disposition' => $first['proposed_disposition'],
                    'mapping_acceptance_status' => 'not_accepted',
                ];
            })
            ->sortByDesc('application_count')
            ->values();
        $exactOwnerOnlyCandidates = $candidateEvidence
            ->where('proposed_disposition', 'owner_identity_may_be_proposed_independently')
            ->values();
        $exactBusinessEvidenceCandidates = $candidateEvidence
            ->where('business_source_evidence_disposition', 'business_source_evidence_may_be_prepared_independently')
            ->values();
        $municipalIdentityEvidenceClasses = collect($this->municipalIdentityEvidenceClasses(
            $exactBusinessEvidenceCandidates,
            $proposals,
        ));
        $mixedSemanticCandidates = $candidateEvidence
            ->where('shape.semantic_scope', 'identity_collision_with_additional_semantic_reconciliation')
            ->values();
        $decisionCohorts = $candidateEvidence
            ->groupBy('decision_cohort_key')
            ->map(fn (Collection $members, string $key): array => $this->decisionCohort($key, $members))
            ->sortByDesc('application_count')
            ->values();
        $evidenceBinding = [
            'source_archive_checksum' => $financialPlan->importBatch->source->archive_checksum,
            'financial_plan_id' => $financialPlan->id,
            'financial_dependency_snapshot_hash' => $financialPlan->dependency_snapshot_hash,
            'registry_plan_id' => $registryPlan->id,
            'registry_snapshot_hash' => $registryPlan->registry_snapshot_hash,
            'strict_candidate_set_sha256' => data_get($readiness, 'report.fingerprints.candidate_set_sha256'),
        ];
        $frontierSha256 = $this->hash([
            $evidenceBinding,
            ...$candidateEvidence->map(fn (array $candidate): array => [
                $candidate['candidate_fingerprint'],
                $candidate['class_sha256'],
                $candidate['owner_collision_signal_names'],
                $candidate['business_collision_signal_names'],
            ])->all(),
        ]);

        return [
            'report' => [
                'schema_version' => self::SchemaVersion,
                'evidence' => $evidenceBinding,
                'summary' => [
                    'strict_v1_candidate_count' => count($readiness['candidates']),
                    'human_identity_application_count' => $humanCandidates->count(),
                    'unique_owner_proposal_count' => $candidateEvidence->pluck('owner_proposal_id')->unique()->count(),
                    'unique_business_proposal_count' => $candidateEvidence->pluck('business_proposal_id')->unique()->count(),
                    'identity_collision_only_count' => $humanCandidates->count() - $mixedSemanticCandidates->count(),
                    'additional_semantic_reconciliation_count' => $mixedSemanticCandidates->count(),
                    'group_owner_overlay_count' => $candidateEvidence->where('shape.group_owner_policy_overlay', true)->count(),
                    'exact_owner_mapping_candidate_count' => $exactOwnerOnlyCandidates->count(),
                    'exact_owner_mapping_unique_proposal_count' => $exactOwnerOnlyCandidates->pluck('owner_proposal_id')->unique()->count(),
                    'exact_business_source_evidence_candidate_count' => $exactBusinessEvidenceCandidates->count(),
                    'exact_business_source_evidence_unique_proposal_count' => $exactBusinessEvidenceCandidates->pluck('business_proposal_id')->unique()->count(),
                    'business_or_application_mapping_candidate_count' => 0,
                    'class_count' => $classes->count(),
                    'decision_cohort_count' => $decisionCohorts->count(),
                    'municipal_identity_evidence_class_count' => $municipalIdentityEvidenceClasses->count(),
                    'contact_signals_only_application_count' => $municipalIdentityEvidenceClasses
                        ->firstWhere('key', 'contact_signals_only')['application_count'] ?? 0,
                    'non_contact_identity_signal_application_count' => $municipalIdentityEvidenceClasses
                        ->firstWhere('key', 'non_contact_identity_signal_present')['application_count'] ?? 0,
                    'accepted_mapping_count' => 0,
                    'historical_preservation_executed' => false,
                ],
                'collision_clusters' => [
                    'owner' => $this->collisionClusterSummary($candidateEvidence, $proposals, 'owner'),
                    'business' => $this->collisionClusterSummary($candidateEvidence, $proposals, 'business'),
                ],
                'bounded_deterministic_subclasses' => [
                    [
                        'key' => 'collision_free_owner_business_collision_pending',
                        'application_count' => $exactOwnerOnlyCandidates->count(),
                        'unique_owner_proposal_count' => $exactOwnerOnlyCandidates->pluck('owner_proposal_id')->unique()->count(),
                        'deterministic_fact' => 'The exact source owner proposal is collision-free and may be reviewed independently.',
                        'still_unresolved' => 'Business identity collision and application mapping remain human-review boundaries.',
                        'acceptance_status' => 'proposed_not_accepted',
                    ],
                    [
                        'key' => 'collision_free_business_source_owner_collision_pending',
                        'application_count' => $exactBusinessEvidenceCandidates->count(),
                        'unique_business_proposal_count' => $exactBusinessEvidenceCandidates->pluck('business_proposal_id')->unique()->count(),
                        'deterministic_fact' => 'The exact source business record, source owner edge, and business projection are collision-free and may be reviewed independently.',
                        'still_unresolved' => 'Legal owner identity, reference-data reconciliation, business mapping acceptance, and application mapping remain blocked.',
                        'acceptance_status' => 'evidence_only_not_accepted',
                    ],
                ],
                'municipal_identity_evidence_classes' => array_values($municipalIdentityEvidenceClasses->all()),
                'decision_ready_cohorts' => array_values($decisionCohorts->all()),
                'fingerprints' => [
                    'human_identity_frontier_sha256' => $frontierSha256,
                    'business_source_evidence_subclass_sha256' => $this->hash([
                        $evidenceBinding,
                        ...$exactBusinessEvidenceCandidates->map(fn (array $candidate): array => [
                            $candidate['candidate_fingerprint'],
                            $candidate['class_sha256'],
                        ])->all(),
                    ]),
                    'decision_cohort_set_sha256' => $this->hash([
                        $evidenceBinding,
                        ...$decisionCohorts->map(fn (array $cohort): array => [
                            $cohort['key'],
                            $cohort['cohort_sha256'],
                            $cohort['application_count'],
                            $cohort['blocker_categories'],
                        ])->all(),
                    ]),
                    'municipal_identity_evidence_class_set_sha256' => $this->hash([
                        $evidenceBinding,
                        ...$municipalIdentityEvidenceClasses->map(fn (array $class): array => [
                            $class['key'],
                            $class['class_sha256'],
                            $class['application_count'],
                            $class['collision_review_unit_count'],
                            $class['observed_collision_signal_names'],
                        ])->all(),
                    ]),
                ],
                'state_model' => [
                    'observed' => 'Exact source records, ownership edges, collision signals, and frozen proposal reasons.',
                    'inferred' => 'Repeated collision shapes define bounded review classes; they do not establish shared legal identity.',
                    'proposed' => 'Collision-free owner proposals may be reviewed for a future mapping decision. Collision-free business source evidence may be prepared independently, but remains ineligible for mapping acceptance while legal owner identity is unresolved.',
                    'accepted' => 'No identity, registry, business, or application mapping was accepted.',
                    'rehearsed' => 'No human-identity candidate was rehearsed.',
                    'production_applied' => 'No source or target production mutation occurred.',
                ],
                'safety' => [
                    'read_only' => true,
                    'source_payloads_exposed' => false,
                    'legacy_identifiers_exposed' => false,
                    'similarity_based_mapping' => false,
                    'identity_merge_performed' => false,
                    'accepted_mappings_created' => false,
                    'production_mutation' => false,
                ],
            ],
            'classes' => array_values($classes->all()),
            'candidates' => array_values($candidateEvidence->all()),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @param  Collection<int, LegacyMappingProposal>  $proposals
     * @return list<array<string, mixed>>
     */
    private function municipalIdentityEvidenceClasses(Collection $candidates, Collection $proposals): array
    {
        return array_values(collect([
            'contact_signals_only' => $candidates
                ->filter(fn (array $candidate): bool => $candidate['owner_collision_signal_names'] !== []
                    && array_diff($candidate['owner_collision_signal_names'], ['email', 'phone']) === [])
                ->values(),
            'non_contact_identity_signal_present' => $candidates
                ->filter(fn (array $candidate): bool => array_diff(
                    $candidate['owner_collision_signal_names'],
                    ['email', 'phone'],
                ) !== [])
                ->values(),
        ])->filter(fn (Collection $members): bool => $members->isNotEmpty())
            ->map(function (Collection $members, string $key) use ($proposals): array {
                $profile = $this->municipalIdentityEvidenceProfile($key);
                $observedCollisionSignalNames = $members
                    ->pluck('owner_collision_signal_names')
                    ->flatten()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
                $collisionClusters = $this->collisionClusterSummary($members, $proposals, 'owner');

                return [
                    'key' => $key,
                    'class_sha256' => $this->hash($members
                        ->map(fn (array $candidate): array => [
                            $candidate['candidate_fingerprint'],
                            $candidate['class_sha256'],
                            $candidate['owner_collision_signal_names'],
                        ])
                        ->all()),
                    'application_count' => $members->count(),
                    'unique_owner_proposal_count' => $members->pluck('owner_proposal_id')->unique()->count(),
                    'unique_business_proposal_count' => $members->pluck('business_proposal_id')->unique()->count(),
                    'historical_released_application_count' => $members->where('shape.source_lifecycle_assertion', 'Released')->count(),
                    'non_released_application_count' => $members->where('shape.source_lifecycle_assertion', 'non_Released')->count(),
                    'collision_review_unit_count' => $collisionClusters['unique_collision_group_count'],
                    'observed_collision_signal_names' => $observedCollisionSignalNames,
                    'collision_clusters' => $collisionClusters,
                    'collision_group_counts_may_overlap_evidence_classes' => true,
                    'what_is_proven' => $profile['what_is_proven'],
                    'municipal_decision_required' => $profile['municipal_decision_required'],
                    'decision_would_advance_to' => $profile['decision_would_advance_to'],
                    'decision_would_not_mean' => $profile['decision_would_not_mean'],
                    'remaining_blocker_categories' => $this->classBlockerCategories($members),
                    'one_decision_from_rehearsal' => false,
                    'why_not_one_decision_from_rehearsal' => 'Reference-data reconciliation, exact owner/business/application mapping acceptance, cohort freeze, and separate rehearsal authorization would still remain.',
                    'decision_status' => $profile['decision_status'],
                    'accepted_mapping_count' => 0,
                    'rehearsed_mapping_count' => 0,
                    'production_applied_count' => 0,
                ];
            })->values()->all());
    }

    /** @return array{what_is_proven: string, municipal_decision_required: string, decision_would_advance_to: string, decision_would_not_mean: string, decision_status: string} */
    private function municipalIdentityEvidenceProfile(string $key): array
    {
        if ($key === 'contact_signals_only') {
            return [
                'what_is_proven' => 'Every owner collision in this class is evidenced only by shared normalized email and/or phone signals; no name_birth collision signal is present. Exact source owner records, source-owner edges, and collision-free business projections remain distinct and preserved.',
                'municipal_decision_required' => 'Decide whether shared contact points alone are legal-owner identity conflicts in Municipality of Ipil registry practice, and identify the authoritative evidence required to validate distinct legal owners when those contact points are reused.',
                'decision_would_advance_to' => 'If the Municipality adopts a deterministic source-backed disposition, this class could advance to bounded reference-data and exact owner/business/application mapping review.',
                'decision_would_not_mean' => 'It would not make email or phone an identity authority, merge or split owners automatically, accept any mapping, activate historical Released, authorize rehearsal, or authorize production migration.',
                'decision_status' => 'bounded_municipal_identity_evidence_decision_required',
            ];
        }

        return [
            'what_is_proven' => 'Every owner collision in this class includes at least one non-contact identity signal; the current production class observes the planner name_birth signal, with shared email or phone signals preserved separately. Every signal remains collision evidence and not legal identity authority.',
            'municipal_decision_required' => 'Reconcile the person-oriented or other non-contact collision evidence against authoritative municipal legal-owner records before any exact owner disposition is proposed.',
            'decision_would_advance_to' => 'Only evidence-backed owner dispositions could advance individual members to reference-data and exact mapping review.',
            'decision_would_not_mean' => 'It would not treat normalized name, birth date, email, phone, or similarity as identity authority; accept any mapping; activate historical Released; authorize rehearsal; or authorize production migration.',
            'decision_status' => 'human_legal_identity_reconciliation_required',
        ];
    }

    /**
     * @param  iterable<array-key, array<string, mixed>>  $members
     * @return list<string>
     */
    private function classBlockerCategories(iterable $members): array
    {
        $blockerOrder = array_flip(self::BlockerCategories);

        return array_values(collect($members)
            ->pluck('blocker_categories')
            ->flatten()
            ->unique()
            ->sortBy(fn (string $category): int => $blockerOrder[$category])
            ->values()
            ->all());
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  Collection<int, LegacyMappingProposal>  $proposals
     * @return array<string, mixed>
     */
    private function candidateEvidence(array $candidate, Collection $proposals): array
    {
        $ownerProposalId = (int) data_get($candidate, 'owner.proposal_id');
        $businessProposalId = (int) data_get($candidate, 'business.proposal_id');
        $ownerProposal = $proposals->get($ownerProposalId);
        $businessProposal = $proposals->get($businessProposalId);
        $ownerReasons = $this->strings(data_get($candidate, 'owner.reasons', []));
        $businessReasons = $this->strings(data_get($candidate, 'business.reasons', []));
        $applicationReasons = $this->strings(data_get($candidate, 'application.reasons', []));
        $ownerCollisionReasons = array_values(array_intersect($ownerReasons, self::CollisionReasons));
        $businessCollisionReasons = array_values(array_intersect($businessReasons, self::CollisionReasons));
        $expectedApplicationReasonSets = [
            [],
            ['legacy_release_authority_unresolved', 'line_of_business_mapping_required'],
            ['line_of_business_mapping_required'],
        ];
        sort($applicationReasons);
        $semanticScope = in_array($applicationReasons, $expectedApplicationReasonSets, true)
            && data_get($candidate, 'flags.soft_deleted') !== true
            && data_get($candidate, 'flags.blacklisted') !== true
                ? 'identity_collision_only'
                : 'identity_collision_with_additional_semantic_reconciliation';
        $ownerCollisionSignalNames = $this->collisionSignalNames($ownerProposal);
        $businessCollisionSignalNames = $this->collisionSignalNames($businessProposal);
        $registryPolicyReasons = array_values(array_intersect(
            [...$ownerReasons, ...$businessReasons],
            self::RegistryPolicyReasons,
        ));
        $shape = [
            'semantic_scope' => $semanticScope,
            'source_lifecycle_assertion' => in_array('legacy_release_authority_unresolved', $applicationReasons, true) ? 'Released' : 'non_Released',
            'owner_collision_reasons' => $ownerCollisionReasons,
            'business_collision_reasons' => $businessCollisionReasons,
            'owner_collision_signal_names' => $ownerCollisionSignalNames,
            'business_collision_signal_names' => $businessCollisionSignalNames,
            'group_owner_policy_overlay' => data_get($candidate, 'flags.group_owner') === true,
            'soft_deleted_overlay' => data_get($candidate, 'flags.soft_deleted') === true,
            'application_reasons' => $applicationReasons,
        ];
        $ownerMayProceed = $ownerReasons === []
            && $ownerProposal?->status->value === 'ready'
            && $businessCollisionReasons !== [];
        $businessEvidenceMayProceed = $ownerCollisionReasons !== []
            && $businessCollisionReasons === []
            && $semanticScope === 'identity_collision_only'
            && $registryPolicyReasons === []
            && $businessProposal?->status->value === 'blocked'
            && in_array('owner_mapping_proposal_not_ready', $businessReasons, true)
            && array_diff($businessReasons, self::BusinessEvidenceReasons) === [];
        $decisionCohortKey = $this->decisionCohortKey(
            businessEvidenceMayProceed: $businessEvidenceMayProceed,
            ownerMayProceed: $ownerMayProceed,
            shape: $shape,
        );
        $blockerCategories = $this->blockerCategories(
            ownerReasons: $ownerReasons,
            businessReasons: $businessReasons,
            applicationReasons: $applicationReasons,
        );

        return [
            'candidate_fingerprint' => $candidate['candidate_fingerprint'],
            'application_legacy_id_sha256' => data_get($candidate, 'application.legacy_id_sha256'),
            'owner_proposal_id' => $ownerProposalId,
            'business_proposal_id' => $businessProposalId,
            'class_sha256' => $this->hash($shape),
            'shape' => $shape,
            'owner_collision_signal_names' => $ownerCollisionSignalNames,
            'business_collision_signal_names' => $businessCollisionSignalNames,
            'proposed_disposition' => $ownerMayProceed
                ? 'owner_identity_may_be_proposed_independently'
                : 'human_identity_reconciliation_required',
            'business_source_evidence_disposition' => $businessEvidenceMayProceed
                ? 'business_source_evidence_may_be_prepared_independently'
                : 'business_source_evidence_quarantined',
            'owner_mapping_acceptance_status' => 'not_accepted',
            'business_mapping_acceptance_status' => 'not_accepted',
            'application_mapping_acceptance_status' => 'not_accepted',
            'decision_cohort_key' => $decisionCohortKey,
            'blocker_categories' => $blockerCategories,
        ];
    }

    /** @param array<string, mixed> $shape */
    private function decisionCohortKey(bool $businessEvidenceMayProceed, bool $ownerMayProceed, array $shape): string
    {
        if ($businessEvidenceMayProceed) {
            return $shape['source_lifecycle_assertion'] === 'Released'
                ? 'collision_free_business_source_owner_decision_released'
                : 'collision_free_business_source_owner_decision_non_released';
        }

        if ($ownerMayProceed) {
            return 'collision_free_owner_business_decision';
        }

        if ($shape['group_owner_policy_overlay'] === true) {
            return 'group_owner_registry_policy';
        }

        if ($shape['soft_deleted_overlay'] === true) {
            return 'soft_deleted_registry_policy';
        }

        if ($shape['semantic_scope'] === 'identity_collision_with_additional_semantic_reconciliation') {
            return 'identity_collision_with_semantic_exception';
        }

        return 'compound_owner_business_identity_collision';
    }

    /**
     * @param  list<string>  $ownerReasons
     * @param  list<string>  $businessReasons
     * @param  list<string>  $applicationReasons
     * @return list<string>
     */
    private function blockerCategories(array $ownerReasons, array $businessReasons, array $applicationReasons): array
    {
        $allReasons = [...$ownerReasons, ...$businessReasons, ...$applicationReasons];
        $categories = ['exact_mapping_acceptance'];

        if (array_intersect($allReasons, self::CollisionReasons) !== []) {
            $categories[] = 'municipal_identity_decision';
        }

        if (array_intersect($allReasons, self::RegistryPolicyReasons) !== []
            || in_array('soft_deleted_application_policy_unresolved', $applicationReasons, true)
            || in_array('legacy_rejection_state_not_represented', $applicationReasons, true)) {
            $categories[] = 'registry_policy_decision';
        }

        if (in_array('reference_data_mapping_required', $businessReasons, true)
            || in_array('line_of_business_mapping_required', $applicationReasons, true)) {
            $categories[] = 'reference_data_reconciliation';
        }

        if (in_array('assessment_and_payment_schedule_migration_required', $applicationReasons, true)) {
            $categories[] = 'treasury_interpretation';
        }

        if (in_array('financial_override_reconciliation_required', $applicationReasons, true)) {
            $categories[] = 'financial_policy_authority';
        }

        if (in_array('legacy_release_authority_unresolved', $applicationReasons, true)) {
            $categories[] = 'permit_authority_semantics';
        }

        if (in_array('legacy_draft_submission_timestamp_conflict', $applicationReasons, true)) {
            $categories[] = 'genuine_source_data_contradiction';
        }

        return array_values(array_intersect(self::BlockerCategories, array_unique($categories)));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $members
     * @return array<string, mixed>
     */
    private function decisionCohort(string $key, Collection $members): array
    {
        $blockerOrder = array_flip(self::BlockerCategories);
        $blockerCategories = $members
            ->pluck('blocker_categories')
            ->flatten()
            ->unique()
            ->sortBy(fn (string $category): int => $blockerOrder[$category])
            ->values()
            ->all();
        $profile = $this->decisionCohortProfile($key);

        return [
            'key' => $key,
            'cohort_sha256' => $this->hash($members
                ->map(fn (array $candidate): array => [
                    $candidate['candidate_fingerprint'],
                    $candidate['class_sha256'],
                    $candidate['blocker_categories'],
                ])
                ->all()),
            'application_count' => $members->count(),
            'unique_owner_proposal_count' => $members->pluck('owner_proposal_id')->unique()->count(),
            'unique_business_proposal_count' => $members->pluck('business_proposal_id')->unique()->count(),
            'what_is_proven' => $profile['what_is_proven'],
            'blocker_categories' => $blockerCategories,
            'accepting_the_cohort_would_mean' => $profile['accepting_the_cohort_would_mean'],
            'accepting_the_cohort_would_not_mean' => $profile['accepting_the_cohort_would_not_mean'],
            'records_that_would_advance' => $members->count(),
            'reversible_rehearsal_that_would_become_possible' => $profile['reversible_rehearsal_that_would_become_possible'],
            'decision_status' => $profile['decision_status'],
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
        ];
    }

    /** @return array{what_is_proven: string, accepting_the_cohort_would_mean: string, accepting_the_cohort_would_not_mean: string, reversible_rehearsal_that_would_become_possible: string, decision_status: string} */
    private function decisionCohortProfile(string $key): array
    {
        return match ($key) {
            'collision_free_business_source_owner_decision_released' => [
                'what_is_proven' => 'Each source business record, its exact source-owner edge, and its business projection are collision-free; Released remains historical evidence only.',
                'accepting_the_cohort_would_mean' => 'Authorized reviewers have resolved every legal-owner identity, reference-data crosswalk, and exact owner, business, and application mapping for this frozen cohort.',
                'accepting_the_cohort_would_not_mean' => 'It would not infer legal identity, activate Released, authorize permit issuance or release, accept fee identity, recalculate history, authorize rehearsal, or authorize production migration.',
                'reversible_rehearsal_that_would_become_possible' => 'A separately authorized historical-preservation rehearsal could bind the accepted application mappings while retaining Released as non-operational historical evidence.',
                'decision_status' => 'characterized_not_ready_for_acceptance',
            ],
            'collision_free_business_source_owner_decision_non_released' => [
                'what_is_proven' => 'Each source business record, its exact source-owner edge, and its business projection are collision-free without a Released-authority overlay.',
                'accepting_the_cohort_would_mean' => 'Authorized reviewers have resolved every legal-owner identity, reference-data crosswalk, and exact owner, business, and application mapping for this frozen cohort.',
                'accepting_the_cohort_would_not_mean' => 'It would not infer legal identity, accept fee identity, recalculate history, authorize rehearsal, or authorize production migration.',
                'reversible_rehearsal_that_would_become_possible' => 'A separately authorized historical-preservation rehearsal could bind the accepted application mappings for this non-Released cohort.',
                'decision_status' => 'characterized_not_ready_for_acceptance',
            ],
            'collision_free_owner_business_decision' => [
                'what_is_proven' => 'The exact source owner proposals are collision-free and can be decided independently from the unresolved business collisions.',
                'accepting_the_cohort_would_mean' => 'Authorized reviewers accept only the exact owner proposals represented by this cohort.',
                'accepting_the_cohort_would_not_mean' => 'It would not accept business or application identity, resolve reference data, activate Released, authorize historical-preservation rehearsal, or authorize production migration.',
                'reversible_rehearsal_that_would_become_possible' => 'A separately authorized registry rehearsal could exercise only the accepted owner proposals; historical-preservation rehearsal would remain blocked by business and application mappings.',
                'decision_status' => 'ready_for_owner_acceptance_review',
            ],
            'group_owner_registry_policy' => [
                'what_is_proven' => 'Exact source records and ownership edges are preserved, while Group-owner semantics remain isolated from all other frontier records.',
                'accepting_the_cohort_would_mean' => 'The Municipality has adopted a Group-owner registry disposition and reviewers have accepted the resulting exact identity and reference mappings.',
                'accepting_the_cohort_would_not_mean' => 'It would not convert a Group label into inferred legal identity, activate Released, authorize rehearsal, or authorize production migration.',
                'reversible_rehearsal_that_would_become_possible' => 'Only after policy disposition and exact mapping acceptance could a separately authorized reversible registry and historical-preservation rehearsal be prepared.',
                'decision_status' => 'blocked_by_registry_policy',
            ],
            'soft_deleted_registry_policy' => [
                'what_is_proven' => 'The soft-deleted source histories and their additional lifecycle or financial exception signals are isolated as a distinct cohort.',
                'accepting_the_cohort_would_mean' => 'Authorized reviewers have recorded the deletion, identity, reference-data, and any financial or lifecycle dispositions for every member.',
                'accepting_the_cohort_would_not_mean' => 'It would not restore deleted records, discard contradictory evidence, recalculate history, authorize rehearsal, or authorize production migration.',
                'reversible_rehearsal_that_would_become_possible' => 'Only a separately authorized rehearsal matching the accepted deletion and exception dispositions could proceed.',
                'decision_status' => 'blocked_by_registry_policy_and_exceptions',
            ],
            'identity_collision_with_semantic_exception' => [
                'what_is_proven' => 'The identity collision is preserved together with an independently visible non-identity semantic exception.',
                'accepting_the_cohort_would_mean' => 'Authorized reviewers have resolved both exact identity mappings and the separately classified semantic exception.',
                'accepting_the_cohort_would_not_mean' => 'It would not infer identity, infer financial authority, activate Released, authorize rehearsal, or authorize production migration.',
                'reversible_rehearsal_that_would_become_possible' => 'Only after both decisions could a separately authorized exception-aware rehearsal be prepared.',
                'decision_status' => 'blocked_by_identity_and_semantic_exception',
            ],
            default => [
                'what_is_proven' => 'Exact source records, source-owner edges, and collision evidence are preserved without choosing a legal identity.',
                'accepting_the_cohort_would_mean' => 'Authorized reviewers have resolved the owner and business identity collisions and accepted every required exact mapping and reference crosswalk.',
                'accepting_the_cohort_would_not_mean' => 'It would not use similarity, activate Released, authorize rehearsal, or authorize production migration.',
                'reversible_rehearsal_that_would_become_possible' => 'Only after exact decisions and separate authorization could a reversible registry and historical-preservation rehearsal be prepared.',
                'decision_status' => 'blocked_by_municipal_identity_decision',
            ],
        };
    }

    /**
     * @param  iterable<array-key, array<string, mixed>>  $candidates
     * @param  Collection<int, LegacyMappingProposal>  $proposals
     * @return array<string, mixed>
     */
    private function collisionClusterSummary(iterable $candidates, Collection $proposals, string $entity): array
    {
        $candidateProposalIds = collect($candidates)->pluck($entity.'_proposal_id')->unique();
        $collisionFingerprints = $candidateProposalIds
            ->flatMap(fn (mixed $proposalId): array => array_values($proposals->get((int) $proposalId)->collision_fingerprints ?? []))
            ->unique();
        $clusters = [];
        foreach ($proposals as $proposalId => $proposal) {
            foreach ($proposal->collision_fingerprints ?? [] as $signal => $fingerprint) {
                if (! $collisionFingerprints->contains($fingerprint)) {
                    continue;
                }
                $key = $signal.'|'.$fingerprint;
                $clusters[$key]['signal'] = $signal;
                $clusters[$key]['proposal_ids'][(int) $proposalId] = true;
            }
        }
        $sizeDistribution = [];
        $signalCounts = [];
        foreach ($clusters as $cluster) {
            $size = count($cluster['proposal_ids']);
            $sizeDistribution[(string) $size] = ($sizeDistribution[(string) $size] ?? 0) + 1;
            $signalCounts[$cluster['signal']] = ($signalCounts[$cluster['signal']] ?? 0) + 1;
        }
        ksort($sizeDistribution);
        ksort($signalCounts);

        return [
            'unique_collision_group_count' => count($clusters),
            'collision_group_size_distribution' => $sizeDistribution,
            'collision_group_count_by_signal' => $signalCounts,
            'raw_collision_fingerprints_exposed' => false,
        ];
    }

    /** @return list<string> */
    private function collisionSignalNames(LegacyMappingProposal $proposal): array
    {
        $names = array_keys($proposal->collision_fingerprints ?? []);
        sort($names);

        return $names;
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        $values = is_array($value)
            ? array_values(array_filter($value, fn (mixed $item): bool => is_string($item)))
            : [];
        sort($values);

        return $values;
    }

    /** @param array<array-key, mixed> $value */
    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
