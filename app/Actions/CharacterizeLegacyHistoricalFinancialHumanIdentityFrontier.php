<?php

namespace App\Actions;

use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMappingProposal;
use Illuminate\Support\Collection;

class CharacterizeLegacyHistoricalFinancialHumanIdentityFrontier
{
    public const SchemaVersion = 'bpls.historical-financial-human-identity-frontier.v6';

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
        $priorityReviewClasses = collect($this->priorityReviewClasses($candidateEvidence, $proposals));
        $softDeletedDecisionRoutes = collect($this->softDeletedDecisionRoutes(
            $candidateEvidence->where('decision_cohort_key', 'soft_deleted_registry_policy')->values(),
        ));
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
        $businessSourceEvidenceSubclassSha256 = $this->hash([
            $evidenceBinding,
            ...$exactBusinessEvidenceCandidates->map(fn (array $candidate): array => [
                $candidate['candidate_fingerprint'],
                $candidate['class_sha256'],
            ])->all(),
        ]);
        $decisionCohortSetSha256 = $this->hash([
            $evidenceBinding,
            ...$decisionCohorts->map(fn (array $cohort): array => [
                $cohort['key'],
                $cohort['cohort_sha256'],
                $cohort['application_count'],
                $cohort['blocker_categories'],
            ])->all(),
        ]);
        $municipalIdentityEvidenceClassSetSha256 = $this->hash([
            $evidenceBinding,
            ...$municipalIdentityEvidenceClasses->map(fn (array $class): array => [
                $class['key'],
                $class['class_sha256'],
                $class['application_count'],
                $class['collision_review_unit_count'],
                $class['observed_collision_signal_names'],
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
                    'priority_review_class_count' => $priorityReviewClasses->count(),
                    'soft_deleted_decision_route_count' => $softDeletedDecisionRoutes->count(),
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
                'priority_review_classes' => array_values($priorityReviewClasses->all()),
                'soft_deleted_decision_routes' => array_values($softDeletedDecisionRoutes->all()),
                'fingerprints' => [
                    'human_identity_frontier_sha256' => $frontierSha256,
                    'business_source_evidence_subclass_sha256' => $businessSourceEvidenceSubclassSha256,
                    'decision_cohort_set_sha256' => $decisionCohortSetSha256,
                    'municipal_identity_evidence_class_set_sha256' => $municipalIdentityEvidenceClassSetSha256,
                    'priority_review_class_set_sha256' => $this->hash([
                        $evidenceBinding,
                        ...$priorityReviewClasses->map(fn (array $class): array => [
                            $class['key'],
                            $class['class_sha256'],
                            $class['application_count'],
                            $this->v5ReviewUnits($class['review_units']),
                            $class['blocker_categories'],
                        ])->all(),
                    ]),
                    'priority_decision_unlock_set_sha256' => $this->hash([
                        $evidenceBinding,
                        ...$priorityReviewClasses->map(fn (array $class): array => [
                            $class['key'],
                            $this->v6ReviewUnitClosureFingerprintRows($class['review_units']),
                        ])->all(),
                        ...$softDeletedDecisionRoutes->map(fn (array $route): array => [
                            $route['key'],
                            $route['route_sha256'],
                            $route['application_count'],
                            $route['blocker_categories'],
                        ])->all(),
                    ]),
                ],
                'preserved_v5_outputs' => [
                    'schema_version' => 'bpls.historical-financial-human-identity-frontier.v5',
                    'human_identity_frontier_sha256' => $frontierSha256,
                    'business_source_evidence_subclass_sha256' => $businessSourceEvidenceSubclassSha256,
                    'decision_cohort_set_sha256' => $decisionCohortSetSha256,
                    'municipal_identity_evidence_class_set_sha256' => $municipalIdentityEvidenceClassSetSha256,
                    'priority_review_class_set_sha256' => $this->hash([
                        $evidenceBinding,
                        ...$priorityReviewClasses->map(fn (array $class): array => [
                            $class['key'],
                            $class['class_sha256'],
                            $class['application_count'],
                            $this->v5ReviewUnits($class['review_units']),
                            $class['blocker_categories'],
                        ])->all(),
                    ]),
                ],
                'preserved_v4_outputs' => [
                    'schema_version' => 'bpls.historical-financial-human-identity-frontier.v4',
                    'human_identity_application_count' => $humanCandidates->count(),
                    'decision_cohort_count' => $decisionCohorts->count(),
                    'contact_signals_only_application_count' => $municipalIdentityEvidenceClasses
                        ->firstWhere('key', 'contact_signals_only')['application_count'] ?? 0,
                    'non_contact_identity_signal_application_count' => $municipalIdentityEvidenceClasses
                        ->firstWhere('key', 'non_contact_identity_signal_present')['application_count'] ?? 0,
                    'human_identity_frontier_sha256' => $frontierSha256,
                    'business_source_evidence_subclass_sha256' => $businessSourceEvidenceSubclassSha256,
                    'decision_cohort_set_sha256' => $decisionCohortSetSha256,
                    'municipal_identity_evidence_class_set_sha256' => $municipalIdentityEvidenceClassSetSha256,
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
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @param  Collection<int, LegacyMappingProposal>  $proposals
     * @return list<array<string, mixed>>
     */
    private function priorityReviewClasses(Collection $candidates, Collection $proposals): array
    {
        $nonContactCollisionFreeBusiness = $candidates
            ->where('business_source_evidence_disposition', 'business_source_evidence_may_be_prepared_independently')
            ->filter(fn (array $candidate): bool => array_diff(
                $candidate['owner_collision_signal_names'],
                ['email', 'phone'],
            ) !== [])
            ->values();
        $compound = $candidates
            ->where('decision_cohort_key', 'compound_owner_business_identity_collision')
            ->values();
        $compoundRegistration = $compound
            ->where('business_collision_signal_names', ['registration'])
            ->values();
        $compoundContactRegistration = $compound
            ->filter(fn (array $candidate): bool => $candidate['owner_collision_signal_names'] !== []
                && array_diff($candidate['owner_collision_signal_names'], ['email', 'phone']) === []
                && $candidate['business_collision_signal_names'] === ['registration'])
            ->values();
        $compoundNonContactRegistration = $compound
            ->filter(fn (array $candidate): bool => array_diff(
                $candidate['owner_collision_signal_names'],
                ['email', 'phone'],
            ) !== [] && $candidate['business_collision_signal_names'] === ['registration'])
            ->values();
        $compoundOther = $compound
            ->reject(fn (array $candidate): bool => $compoundContactRegistration
                ->contains('candidate_fingerprint', $candidate['candidate_fingerprint'])
                || $compoundNonContactRegistration
                    ->contains('candidate_fingerprint', $candidate['candidate_fingerprint']))
            ->values();
        $softDeleted = $candidates
            ->where('decision_cohort_key', 'soft_deleted_registry_policy')
            ->values();
        $identityFinancial = $candidates
            ->where('decision_cohort_key', 'identity_collision_with_semantic_exception')
            ->values();

        return array_values(collect([
            $this->priorityReviewClass(
                key: 'non_contact_identity_collision_free_business',
                members: $nonContactCollisionFreeBusiness,
                reviewUnits: [
                    'owner_non_contact_collision_groups' => $this->collisionClusterSummary(
                        $nonContactCollisionFreeBusiness,
                        $proposals,
                        'owner',
                        ['name_birth'],
                        proposalPreparationAfterDisposition: true,
                    ),
                ],
                whatIsProven: 'The collision-free-business non-contact class is reducible to exact hashed name-and-birth collision groups. Each group is a bounded review unit; the signal does not establish legal identity.',
                exactBlocker: 'An authorized reviewer must reconcile every name-and-birth collision group against authoritative legal-owner records, followed by reference-data and exact mapping acceptance.',
                acceptanceWouldMean: 'Every reviewed member has an evidence-backed legal-owner disposition and accepted exact owner, business, application, and reference mappings.',
                acceptanceWouldNotMean: 'It would not make name or birth date identity authority, activate Released, accept fee identity, authorize rehearsal, or authorize production migration.',
                oneDecisionCouldMakeRehearsalReady: false,
                whyNotOneDecision: 'The class requires group-level legal-identity reconciliation, reference-data reconciliation, exact mapping acceptance, cohort freeze, and separate rehearsal authorization.',
            ),
            $this->priorityReviewClass(
                key: 'compound_registration_business_collision',
                members: $compoundRegistration,
                reviewUnits: [
                    'business_registration_collision_groups' => $this->collisionClusterSummary(
                        $compoundRegistration,
                        $proposals,
                        'business',
                        ['registration'],
                        proposalPreparationAfterDisposition: true,
                    ),
                ],
                whatIsProven: 'The dominant compound class shares one independently reproducible business-registration collision dimension, regardless of whether its owner evidence is contact-only or non-contact.',
                exactBlocker: 'Authorized reviewers must reconcile each hashed registration-number collision group as business identity evidence; owner identity, reference-data, and exact mapping decisions remain independent.',
                acceptanceWouldMean: 'Authorized reviewers have resolved every business-registration review unit and the separately routed owner dependencies, then accepted every required exact mapping and reference crosswalk.',
                acceptanceWouldNotMean: 'It would not make registration number, contact points, name, birth date, or similarity identity authority; activate Released; authorize rehearsal; or authorize production migration.',
                oneDecisionCouldMakeRehearsalReady: false,
                whyNotOneDecision: 'Registration reconciliation leaves owner identity, reference data, exact mapping acceptance, cohort freeze, and separate rehearsal authorization unresolved.',
            ),
            $this->priorityReviewClass(
                key: 'compound_contact_owner_registration_business_collision',
                members: $compoundContactRegistration,
                reviewUnits: [
                    'owner_contact_collision_groups' => $this->collisionClusterSummary(
                        $compoundContactRegistration,
                        $proposals,
                        'owner',
                        ['email', 'phone'],
                    ),
                    'business_registration_collision_groups' => $this->collisionClusterSummary(
                        $compoundContactRegistration,
                        $proposals,
                        'business',
                        ['registration'],
                    ),
                ],
                whatIsProven: 'Owner collision evidence is limited to shared contact points, while a separate registration-number collision independently blocks each business identity.',
                exactBlocker: 'The bounded municipal shared-contact decision can address only the owner-evidence dependency; authorized business-registration reconciliation and exact reference/mapping acceptance remain separate.',
                acceptanceWouldMean: 'Authorized reviewers have resolved both legal-owner and business-registration review units and accepted every required exact mapping and reference crosswalk.',
                acceptanceWouldNotMean: 'It would not infer identity from contact or registration signals, activate Released, authorize rehearsal, or authorize production migration.',
                oneDecisionCouldMakeRehearsalReady: false,
                whyNotOneDecision: 'Even a favorable shared-contact rule leaves business-registration collisions, reference data, exact business/application acceptance, cohort freeze, and rehearsal authorization unresolved.',
            ),
            $this->priorityReviewClass(
                key: 'compound_non_contact_owner_registration_business_collision',
                members: $compoundNonContactRegistration,
                reviewUnits: [
                    'owner_non_contact_collision_groups' => $this->collisionClusterSummary(
                        $compoundNonContactRegistration,
                        $proposals,
                        'owner',
                        ['name_birth'],
                    ),
                    'business_registration_collision_groups' => $this->collisionClusterSummary(
                        $compoundNonContactRegistration,
                        $proposals,
                        'business',
                        ['registration'],
                    ),
                ],
                whatIsProven: 'The owner and business dependencies are independently reproducible as hashed name-and-birth and registration-number collision review groups.',
                exactBlocker: 'Authorized legal-owner reconciliation and business-registration reconciliation are both required, followed by reference-data and exact mapping acceptance.',
                acceptanceWouldMean: 'Authorized reviewers have resolved both collision-group sets and accepted every required exact mapping and reference crosswalk.',
                acceptanceWouldNotMean: 'It would not infer identity from name, birth date, registration number, or similarity; activate Released; authorize rehearsal; or authorize production migration.',
                oneDecisionCouldMakeRehearsalReady: false,
                whyNotOneDecision: 'Two independent identity dimensions, reference data, exact mapping acceptance, cohort freeze, and separate rehearsal authorization remain.',
            ),
            $this->priorityReviewClass(
                key: 'compound_other_collision_topology',
                members: $compoundOther,
                reviewUnits: [
                    'owner_collision_groups' => $this->collisionClusterSummary($compoundOther, $proposals, 'owner'),
                    'business_collision_groups' => $this->collisionClusterSummary($compoundOther, $proposals, 'business'),
                ],
                whatIsProven: 'The residual compound topology is isolated from the registration-number subclasses without changing the frozen compound cohort.',
                exactBlocker: 'Its owner and business collision evidence requires record-level authorized reconciliation and exact mapping acceptance.',
                acceptanceWouldMean: 'Authorized reviewers have resolved the exact owner and business identity evidence and accepted the required mappings and reference crosswalks.',
                acceptanceWouldNotMean: 'It would not establish identity from the observed signals or authorize rehearsal or production migration.',
                oneDecisionCouldMakeRehearsalReady: false,
                whyNotOneDecision: 'Owner identity, business identity, reference data, exact mappings, cohort freeze, and separate rehearsal authorization remain.',
            ),
            $this->priorityReviewClass(
                key: 'soft_deleted_exception_matrix',
                members: $softDeleted,
                reviewUnits: [
                    'owner_collision_groups' => $this->collisionClusterSummary($softDeleted, $proposals, 'owner'),
                    'business_collision_groups' => $this->collisionClusterSummary($softDeleted, $proposals, 'business'),
                ],
                whatIsProven: 'All soft-deleted histories remain quarantined together, while their independently observed Treasury, financial-authority, permit-authority, and source-contradiction overlays remain explicit.',
                exactBlocker: 'A municipal deletion/retention disposition is required for all members; the independently present identity, reference-data, Treasury, financial, permit, or contradiction blockers must also be resolved by their proper authorities.',
                acceptanceWouldMean: 'Authorized reviewers have recorded the deletion disposition and every applicable independent exception disposition, then accepted the exact mappings and reference crosswalks.',
                acceptanceWouldNotMean: 'It would not restore deleted records, discard contradictory evidence, infer identity or liability, activate Released, authorize rehearsal, or authorize production migration.',
                oneDecisionCouldMakeRehearsalReady: false,
                whyNotOneDecision: 'Deletion policy cannot resolve the independently present identity, reference-data, Treasury, financial, permit-authority, source-contradiction, mapping, and rehearsal gates.',
                additional: [
                    'contact_signal_only_application_count' => $softDeleted
                        ->filter(fn (array $candidate): bool => $candidate['owner_collision_signal_names'] !== []
                            && array_diff($candidate['owner_collision_signal_names'], ['email', 'phone']) === [])
                        ->count(),
                    'non_contact_identity_signal_application_count' => $softDeleted
                        ->filter(fn (array $candidate): bool => array_diff(
                            $candidate['owner_collision_signal_names'],
                            ['email', 'phone'],
                        ) !== [])
                        ->count(),
                    'treasury_interpretation_application_count' => $softDeleted
                        ->filter(fn (array $candidate): bool => in_array('treasury_interpretation', $candidate['blocker_categories'], true))
                        ->count(),
                    'financial_policy_authority_application_count' => $softDeleted
                        ->filter(fn (array $candidate): bool => in_array('financial_policy_authority', $candidate['blocker_categories'], true))
                        ->count(),
                    'permit_authority_semantics_application_count' => $softDeleted
                        ->filter(fn (array $candidate): bool => in_array('permit_authority_semantics', $candidate['blocker_categories'], true))
                        ->count(),
                    'genuine_source_data_contradiction_application_count' => $softDeleted
                        ->filter(fn (array $candidate): bool => in_array('genuine_source_data_contradiction', $candidate['blocker_categories'], true))
                        ->count(),
                ],
            ),
            $this->priorityReviewClass(
                key: 'identity_plus_financial_exception',
                members: $identityFinancial,
                reviewUnits: [
                    'owner_collision_groups' => $this->collisionClusterSummary($identityFinancial, $proposals, 'owner'),
                ],
                whatIsProven: 'The identity collision and financial-override exception are independent blockers on one preserved history.',
                exactBlocker: 'The legal-owner disposition and authorized financial-override disposition must both be recorded, followed by reference-data and exact mapping acceptance.',
                acceptanceWouldMean: 'Authorized reviewers have resolved the exact identity and financial exception without recalculating history, then accepted the required mappings and reference crosswalks.',
                acceptanceWouldNotMean: 'It would not infer identity or fee policy, alter taxpayer liability, activate Released, authorize rehearsal, or authorize production migration.',
                oneDecisionCouldMakeRehearsalReady: false,
                whyNotOneDecision: 'Identity authority, financial authority, reference data, exact mappings, cohort freeze, and separate rehearsal authorization remain independent gates.',
                additional: [
                    'identity_decision_required' => true,
                    'financial_authority_decision_required' => true,
                    'decisions_are_independent' => true,
                    'identity_disposition_alone_could_unlock_exact_proposal_preparation' => false,
                    'financial_disposition_alone_could_unlock_exact_proposal_preparation' => false,
                    'full_global_owner_collision_group_review_required' => true,
                ],
            ),
        ])->filter(fn (array $class): bool => $class['application_count'] > 0)->all());
    }

    /**
     * @param  iterable<array-key, array<string, mixed>>  $members
     * @param  array<string, array<string, mixed>>  $reviewUnits
     * @param  array<string, mixed>  $additional
     * @return array<string, mixed>
     */
    private function priorityReviewClass(
        string $key,
        iterable $members,
        array $reviewUnits,
        string $whatIsProven,
        string $exactBlocker,
        string $acceptanceWouldMean,
        string $acceptanceWouldNotMean,
        bool $oneDecisionCouldMakeRehearsalReady,
        string $whyNotOneDecision,
        array $additional = [],
    ): array {
        $members = collect($members);

        return [
            'key' => $key,
            'class_sha256' => $this->hash($members
                ->map(fn (array $candidate): array => [
                    $candidate['candidate_fingerprint'],
                    $candidate['class_sha256'],
                    $candidate['blocker_categories'],
                ])->all()),
            'application_count' => $members->count(),
            'unique_owner_proposal_count' => $members->pluck('owner_proposal_id')->unique()->count(),
            'unique_business_proposal_count' => $members->pluck('business_proposal_id')->unique()->count(),
            'review_units' => $reviewUnits,
            'what_is_proven' => $whatIsProven,
            'exact_blocker' => $exactBlocker,
            'acceptance_would_mean' => $acceptanceWouldMean,
            'acceptance_would_not_mean' => $acceptanceWouldNotMean,
            'records_that_would_advance' => $members->count(),
            'one_bounded_decision_could_make_rehearsal_ready' => $oneDecisionCouldMakeRehearsalReady,
            'why_not_one_bounded_decision' => $whyNotOneDecision,
            'what_remains_quarantined' => 'Every member remains unaccepted, unrehearsed, and production-unapplied until all listed blockers and separate authorization gates are resolved.',
            'blocker_categories' => $this->classBlockerCategories($members),
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
            ...$additional,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $members
     * @return list<array<string, mixed>>
     */
    private function softDeletedDecisionRoutes(Collection $members): array
    {
        $authorityCategories = [
            'treasury_interpretation',
            'financial_policy_authority',
            'permit_authority_semantics',
            'genuine_source_data_contradiction',
        ];
        $routes = $members
            ->groupBy(function (array $candidate) use ($authorityCategories): string {
                $signature = array_values(array_intersect($authorityCategories, $candidate['blocker_categories']));

                return $signature === []
                    ? 'deletion_identity_reference_only'
                    : implode('__', $signature);
            })
            ->map(function (Collection $routeMembers, string $key): array {
                $profile = $this->softDeletedDecisionRouteProfile($key);

                return [
                    'key' => $key,
                    'route_sha256' => $this->hash($routeMembers
                        ->map(fn (array $candidate): array => [
                            $candidate['candidate_fingerprint'],
                            $candidate['class_sha256'],
                            $candidate['blocker_categories'],
                        ])
                        ->all()),
                    'application_count' => $routeMembers->count(),
                    'unique_owner_proposal_count' => $routeMembers->pluck('owner_proposal_id')->unique()->count(),
                    'unique_business_proposal_count' => $routeMembers->pluck('business_proposal_id')->unique()->count(),
                    'deterministic_fact' => $profile['deterministic_fact'],
                    'blocker_categories' => $this->classBlockerCategories($routeMembers),
                    'one_bounded_decision_would_unlock_exact_proposal_preparation' => false,
                    'why_not_one_bounded_decision' => $profile['why_not_one_bounded_decision'],
                    'acceptance_would_mean' => $profile['acceptance_would_mean'],
                    'acceptance_would_not_mean' => 'It would not restore deleted records, infer legal identity or liability, discard contradictory evidence, accept mappings, activate historical status, authorize rehearsal, or authorize production migration.',
                    'reversible_rehearsal_after_route_acceptance' => 'None. Identity, reference-data, exact-mapping, cohort-freeze, and separate rehearsal-authorization gates remain.',
                    'what_remains_quarantined' => 'Every member remains unaccepted, unrehearsed, and production-unapplied until the deletion disposition and every listed independent blocker are resolved.',
                    'accepted_mapping_count' => 0,
                    'rehearsed_mapping_count' => 0,
                    'production_applied_count' => 0,
                ];
            });
        $routeOrder = array_flip([
            'deletion_identity_reference_only',
            'treasury_interpretation',
            'financial_policy_authority',
            'permit_authority_semantics',
            'genuine_source_data_contradiction',
        ]);

        return array_values($routes
            ->sortBy(fn (array $route): int => $routeOrder[$route['key']] ?? count($routeOrder))
            ->values()
            ->all());
    }

    /** @return array{deterministic_fact: string, why_not_one_bounded_decision: string, acceptance_would_mean: string} */
    private function softDeletedDecisionRouteProfile(string $key): array
    {
        return match ($key) {
            'deletion_identity_reference_only' => [
                'deterministic_fact' => 'No Treasury, fiscal, permit-authority, or source-contradiction overlay is observed; deletion policy, legal-owner identity, reference data, and exact mapping remain independent blockers.',
                'why_not_one_bounded_decision' => 'A deletion/retention decision would remove only the registry-policy blocker; identity, reference data, exact mappings, cohort freeze, and rehearsal authorization would remain.',
                'acceptance_would_mean' => 'The authorized registry reviewer has accepted only the deletion/retention disposition for these source histories.',
            ],
            'treasury_interpretation' => [
                'deterministic_fact' => 'The Treasury interpretation overlay is present without a fiscal, permit-authority, or source-contradiction overlay.',
                'why_not_one_bounded_decision' => 'A bounded Treasury decision would resolve only the schedule/payment interpretation; deletion, identity, reference data, exact mappings, cohort freeze, and rehearsal authorization would remain.',
                'acceptance_would_mean' => 'Treasury has accepted only the preserved schedule/payment disposition for these source histories.',
            ],
            'financial_policy_authority' => [
                'deterministic_fact' => 'The fiscal-authority overlay is present without a Treasury, permit-authority, or source-contradiction overlay.',
                'why_not_one_bounded_decision' => 'A bounded fiscal decision would resolve only the financial override; deletion, identity, business-registration, reference data, exact mappings, cohort freeze, and rehearsal authorization would remain.',
                'acceptance_would_mean' => 'The authorized fiscal reviewer has accepted only the preserved financial-override disposition without recalculating liability.',
            ],
            'permit_authority_semantics' => [
                'deterministic_fact' => 'The historical Released evidence overlay is present without a Treasury, fiscal, or source-contradiction overlay.',
                'why_not_one_bounded_decision' => 'A bounded permit-authority decision would resolve only treatment of the historical assertion; deletion, identity, reference data, exact mappings, cohort freeze, and rehearsal authorization would remain.',
                'acceptance_would_mean' => 'The authorized permit reviewer has accepted only the non-operational treatment of the preserved historical lifecycle assertion.',
            ],
            'genuine_source_data_contradiction' => [
                'deterministic_fact' => 'A source lifecycle contradiction is present without a Treasury, fiscal, or permit-authority overlay.',
                'why_not_one_bounded_decision' => 'Resolving the source contradiction would remove only that exception; deletion, identity, reference data, exact mappings, cohort freeze, and rehearsal authorization would remain.',
                'acceptance_would_mean' => 'The authorized records reviewer has accepted only an evidence-preserving disposition for the contradictory source facts.',
            ],
            default => [
                'deterministic_fact' => 'Multiple authority or contradiction overlays coexist and remain fail-closed as one exact signature.',
                'why_not_one_bounded_decision' => 'Every authority represented by the signature must act independently before the remaining deletion, identity, reference-data, mapping, freeze, and rehearsal gates can advance.',
                'acceptance_would_mean' => 'Each proper authority has accepted only its own exact exception disposition.',
            ],
        };
    }

    /**
     * @param  array<string, array<string, mixed>>  $reviewUnits
     * @return array<string, array<string, mixed>>
     */
    private function v5ReviewUnits(array $reviewUnits): array
    {
        return collect($reviewUnits)
            ->map(fn (array $reviewUnit): array => array_intersect_key($reviewUnit, array_flip([
                'unique_collision_group_count',
                'collision_group_size_distribution',
                'collision_group_count_by_signal',
                'raw_collision_fingerprints_exposed',
            ])))
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $reviewUnits
     * @return array<string, array{string, int, int, int}>
     */
    private function v6ReviewUnitClosureFingerprintRows(array $reviewUnits): array
    {
        return collect($reviewUnits)
            ->map(fn (array $reviewUnit): array => [
                $reviewUnit['collision_group_closure_sha256'],
                $reviewUnit['closed_collision_group_count'],
                $reviewUnit['externally_coupled_collision_group_count'],
                $reviewUnit['external_proposal_membership_count'],
            ])
            ->all();
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
     * @param  list<string>|null  $includedSignals
     * @return array<string, mixed>
     */
    private function collisionClusterSummary(
        iterable $candidates,
        Collection $proposals,
        string $entity,
        ?array $includedSignals = null,
        bool $proposalPreparationAfterDisposition = false,
    ): array {
        $candidates = collect($candidates);
        $candidateProposalIds = $candidates->pluck($entity.'_proposal_id')->map(fn (mixed $id): int => (int) $id)->unique();
        $collisionFingerprints = $candidateProposalIds
            ->flatMap(function (mixed $proposalId) use ($includedSignals, $proposals): array {
                $fingerprints = $proposals->get((int) $proposalId)->collision_fingerprints ?? [];

                return array_values($includedSignals === null
                    ? $fingerprints
                    : array_intersect_key($fingerprints, array_flip($includedSignals)));
            })
            ->unique();
        $clusters = [];
        foreach ($proposals as $proposalId => $proposal) {
            foreach ($proposal->collision_fingerprints ?? [] as $signal => $fingerprint) {
                if ($includedSignals !== null && ! in_array($signal, $includedSignals, true)) {
                    continue;
                }
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
        $closedCandidateProposalIds = [];
        $externallyCoupledCandidateProposalIds = [];
        $externalProposalIds = [];
        $closedCollisionGroupCount = 0;
        $externallyCoupledCollisionGroupCount = 0;
        $closureFingerprintRows = [];
        ksort($clusters);
        foreach ($clusters as $clusterKey => $cluster) {
            $size = count($cluster['proposal_ids']);
            $sizeDistribution[(string) $size] = ($sizeDistribution[(string) $size] ?? 0) + 1;
            $signalCounts[$cluster['signal']] = ($signalCounts[$cluster['signal']] ?? 0) + 1;
            $proposalIds = array_map('intval', array_keys($cluster['proposal_ids']));
            $candidateIds = array_values(array_intersect($proposalIds, $candidateProposalIds->all()));
            $outsideIds = array_values(array_diff($proposalIds, $candidateProposalIds->all()));
            if ($outsideIds === []) {
                $closedCollisionGroupCount++;
                $closedCandidateProposalIds = [...$closedCandidateProposalIds, ...$candidateIds];
            } else {
                $externallyCoupledCollisionGroupCount++;
                $externallyCoupledCandidateProposalIds = [...$externallyCoupledCandidateProposalIds, ...$candidateIds];
                $externalProposalIds = [...$externalProposalIds, ...$outsideIds];
            }
            $closureFingerprintRows[] = [
                $clusterKey,
                ...collect($proposalIds)
                    ->map(fn (int $proposalId): string => $proposals->get($proposalId)->identity_fingerprint)
                    ->sort()
                    ->values()
                    ->all(),
            ];
        }
        $externallyCoupledCandidateProposalIds = array_values(array_unique($externallyCoupledCandidateProposalIds));
        $candidateProposalClosureOverlapCount = count(array_intersect(
            array_unique($closedCandidateProposalIds),
            $externallyCoupledCandidateProposalIds,
        ));
        $closedCandidateProposalIds = array_values(array_diff(
            array_unique($closedCandidateProposalIds),
            $externallyCoupledCandidateProposalIds,
        ));
        $externalProposalIds = array_values(array_unique($externalProposalIds));
        $closedCandidates = $candidates->whereIn($entity.'_proposal_id', $closedCandidateProposalIds);
        $externallyCoupledCandidates = $candidates->whereIn($entity.'_proposal_id', $externallyCoupledCandidateProposalIds);
        ksort($sizeDistribution);
        ksort($signalCounts);

        return [
            'unique_collision_group_count' => count($clusters),
            'collision_group_size_distribution' => $sizeDistribution,
            'collision_group_count_by_signal' => $signalCounts,
            'raw_collision_fingerprints_exposed' => false,
            'closed_collision_group_count' => $closedCollisionGroupCount,
            'externally_coupled_collision_group_count' => $externallyCoupledCollisionGroupCount,
            'closed_candidate_proposal_membership_count' => count($closedCandidateProposalIds),
            'closed_candidate_application_membership_count' => $closedCandidates->count(),
            'externally_coupled_candidate_proposal_membership_count' => count($externallyCoupledCandidateProposalIds),
            'externally_coupled_candidate_application_membership_count' => $externallyCoupledCandidates->count(),
            'externally_coupled_total_proposal_membership_count' => count($externallyCoupledCandidateProposalIds) + count($externalProposalIds),
            'external_proposal_membership_count' => count($externalProposalIds),
            'candidate_proposal_closure_overlap_count' => $candidateProposalClosureOverlapCount,
            'closed_historical_released_application_count' => $closedCandidates->where('shape.source_lifecycle_assertion', 'Released')->count(),
            'closed_non_released_application_count' => $closedCandidates->where('shape.source_lifecycle_assertion', 'non_Released')->count(),
            'externally_coupled_historical_released_application_count' => $externallyCoupledCandidates->where('shape.source_lifecycle_assertion', 'Released')->count(),
            'externally_coupled_non_released_application_count' => $externallyCoupledCandidates->where('shape.source_lifecycle_assertion', 'non_Released')->count(),
            'closed_contact_owner_application_count' => $closedCandidates->filter(fn (array $candidate): bool => $this->hasOnlyContactOwnerSignals($candidate))->count(),
            'closed_non_contact_owner_application_count' => $closedCandidates->reject(fn (array $candidate): bool => $this->hasOnlyContactOwnerSignals($candidate))->count(),
            'externally_coupled_contact_owner_application_count' => $externallyCoupledCandidates->filter(fn (array $candidate): bool => $this->hasOnlyContactOwnerSignals($candidate))->count(),
            'externally_coupled_non_contact_owner_application_count' => $externallyCoupledCandidates->reject(fn (array $candidate): bool => $this->hasOnlyContactOwnerSignals($candidate))->count(),
            'collision_group_closure_sha256' => $this->hash($closureFingerprintRows),
            'closed_group_authoritative_disposition_could_unlock_exact_proposal_preparation' => $proposalPreparationAfterDisposition && $closedCollisionGroupCount > 0,
            'externally_coupled_group_requires_full_global_group_review' => $externallyCoupledCollisionGroupCount > 0,
            'decision_unlock_scope' => $proposalPreparationAfterDisposition
                ? 'An authoritative disposition covering every member of a complete closed group can unlock exact proposal preparation for that identity dimension and its cohort members. An externally coupled group must be reviewed across its full global membership.'
                : 'Collision topology is evidence only; this exception class retains independent blockers before exact proposal preparation can advance.',
            'decision_would_not_mean' => 'A group disposition would not establish identity from the collision signal, accept a mapping, resolve other blocker dimensions, authorize rehearsal, or authorize production migration.',
            'one_group_disposition_could_make_rehearsal_ready' => false,
        ];
    }

    /** @param array<string, mixed> $candidate */
    private function hasOnlyContactOwnerSignals(array $candidate): bool
    {
        return $candidate['owner_collision_signal_names'] !== []
            && array_diff($candidate['owner_collision_signal_names'], ['email', 'phone']) === [];
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
