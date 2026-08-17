<?php

namespace App\Actions;

use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMappingProposal;
use Illuminate\Support\Collection;

class CharacterizeLegacyHistoricalFinancialHumanIdentityFrontier
{
    public const SchemaVersion = 'bpls.historical-financial-human-identity-frontier.v1';

    private const CollisionReasons = [
        'potential_existing_business_collision',
        'potential_existing_owner_collision',
        'potential_source_business_collision',
        'potential_source_owner_collision',
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
        $mixedSemanticCandidates = $candidateEvidence
            ->where('shape.semantic_scope', 'identity_collision_with_additional_semantic_reconciliation')
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
                    'business_or_application_mapping_candidate_count' => 0,
                    'class_count' => $classes->count(),
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
                ],
                'fingerprints' => [
                    'human_identity_frontier_sha256' => $frontierSha256,
                ],
                'state_model' => [
                    'observed' => 'Exact source records, ownership edges, collision signals, and frozen proposal reasons.',
                    'inferred' => 'Repeated collision shapes define bounded review classes; they do not establish shared legal identity.',
                    'proposed' => 'Only collision-free owner proposals are candidates for an independent future mapping decision.',
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
            'owner_mapping_acceptance_status' => 'not_accepted',
            'business_mapping_acceptance_status' => 'not_accepted',
            'application_mapping_acceptance_status' => 'not_accepted',
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @param  Collection<int, LegacyMappingProposal>  $proposals
     * @return array<string, mixed>
     */
    private function collisionClusterSummary(Collection $candidates, Collection $proposals, string $entity): array
    {
        $candidateProposalIds = $candidates->pluck($entity.'_proposal_id')->unique();
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
