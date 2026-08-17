<?php

namespace App\Actions;

use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyIdMapping;
use App\Models\LegacyLineOfBusinessReconciliation;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyRecord;
use App\Models\LineOfBusiness;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class CharacterizeLegacyHistoricalFinancialCohortPrerequisites
{
    public const SchemaVersion = 'bpls.historical-financial-cohort-prerequisites.v1';

    private const LocationFields = [
        'provinceId' => 'provinces',
        'cityId' => 'cities',
        'barangayId' => 'barangays',
    ];

    public function __construct(
        private CharacterizeLegacyHistoricalFinancialApplicationMappings $mappingReadiness,
        private LegacyApplicationDeclarationProjector $declarationProjector,
    ) {}

    /**
     * @return array{
     *   report: array<string, mixed>,
     *   location_proposals: list<array<string, mixed>>,
     *   line_of_business_proposals: list<array<string, mixed>>,
     *   exact_mapping_proposals: list<array<string, mixed>>
     * }
     */
    public function handle(
        LegacyFinancialMappingPlan $financialPlan,
        LegacyMappingPlan $registryPlan,
        string $expectedCohortFingerprint,
    ): array {
        $readiness = $this->mappingReadiness->handle($financialPlan, $registryPlan);
        $actualCohortFingerprint = (string) data_get($readiness, 'report.fingerprints.recommended_cohort_sha256');
        $this->assertExpectedCohort($expectedCohortFingerprint, $actualCohortFingerprint);

        $cohort = collect($readiness['cohort']);
        if ($cohort->count() !== CharacterizeLegacyHistoricalFinancialApplicationMappings::CohortSize) {
            throw new RuntimeException('The prerequisite review requires the complete frozen five-record cohort.');
        }

        $lookupRecords = LegacyRecord::query()
            ->where('legacy_import_batch_id', $registryPlan->legacy_import_batch_id)
            ->whereIn('dataset_key', [
                ...array_values(self::LocationFields),
                'groups',
                'division_groups',
                'divisions',
                'majors',
            ])
            ->orderBy('dataset_key')
            ->orderBy('id')
            ->get();
        $lookupsByIdentity = $lookupRecords->keyBy(
            fn (LegacyRecord $record): string => $record->dataset_key.'|'.$record->legacy_id,
        );
        $lineOfBusinesses = LineOfBusiness::query()
            ->orderBy('id')
            ->get(['id', 'name', 'legacy_source_id', 'is_active']);
        $locationProposals = [];
        $lineOfBusinessProposals = [];
        $exactMappingProposals = [];

        foreach ($cohort as $cohortCandidate) {
            $candidate = collect($readiness['candidates'])->firstWhere(
                'candidate_fingerprint',
                $cohortCandidate['candidate_fingerprint'],
            );
            if (! is_array($candidate)) {
                throw new RuntimeException('A frozen cohort member is absent from the current candidate set.');
            }

            $application = LegacyRecord::query()->find((int) data_get($candidate, 'application.source_record_id'));
            $business = LegacyRecord::query()->find((int) data_get($candidate, 'business.source_record_id'));
            if (! $application instanceof LegacyRecord || ! $business instanceof LegacyRecord) {
                throw new RuntimeException('A frozen cohort application or business record is unavailable.');
            }

            $location = $this->locationProposal($candidate, $business, $lookupsByIdentity);
            $declarations = $this->lineOfBusinessProposals(
                $candidate,
                $application,
                $lookupRecords,
                $lookupsByIdentity,
                $lineOfBusinesses,
            );
            $locationProposals[] = $location;
            array_push($lineOfBusinessProposals, ...$declarations);
            $exactMappingProposals[] = $this->exactMappingProposal($candidate, $location, $declarations);
        }

        $locationProposals = array_values(collect($locationProposals)->sortBy('candidate_fingerprint')->values()->all());
        $lineOfBusinessProposals = array_values(collect($lineOfBusinessProposals)
            ->sortBy(fn (array $proposal): string => $proposal['candidate_fingerprint'].'|'.str_pad((string) $proposal['declaration_index'], 6, '0', STR_PAD_LEFT))
            ->values()
            ->all());
        $exactMappingProposals = array_values(collect($exactMappingProposals)->sortBy('candidate_fingerprint')->values()->all());
        $evidenceBinding = [
            'legacy_source_id' => $financialPlan->importBatch->legacy_source_id,
            'source_archive_checksum' => $financialPlan->importBatch->source->archive_checksum,
            'financial_import_batch_id' => $financialPlan->legacy_import_batch_id,
            'financial_manifest_checksum' => $financialPlan->importBatch->manifest_checksum,
            'financial_mapping_plan_id' => $financialPlan->id,
            'financial_dependency_snapshot_hash' => $financialPlan->dependency_snapshot_hash,
            'registry_import_batch_id' => $registryPlan->legacy_import_batch_id,
            'registry_manifest_checksum' => $registryPlan->importBatch->manifest_checksum,
            'registry_mapping_plan_id' => $registryPlan->id,
            'registry_snapshot_hash' => $registryPlan->registry_snapshot_hash,
            'mapping_readiness_schema_version' => CharacterizeLegacyHistoricalFinancialApplicationMappings::SchemaVersion,
            'recommended_cohort_sha256' => $actualCohortFingerprint,
        ];
        $locationChainCount = collect($locationProposals)->where('source_chain_status', 'exact_hierarchy_resolved')->count();
        $lineTargetEvidenceCount = collect($lineOfBusinessProposals)->where('source_target_evidence_status', 'exact_group_hierarchy_resolved')->count();
        $existingTargetCount = collect($lineOfBusinessProposals)->whereNotNull('proposed_target_line_of_business_id')->count();
        $acceptanceReadyCount = collect($exactMappingProposals)->where('proposal_status', 'evidence_complete_acceptance_pending')->count();
        $applicationLegacyIds = LegacyRecord::query()
            ->whereIn('id', collect($exactMappingProposals)->pluck('application.source_record_id')->filter()->all())
            ->pluck('legacy_id');
        $registryLegacyIds = LegacyRecord::query()
            ->whereIn('id', collect($exactMappingProposals)
                ->flatMap(fn (array $proposal): array => [
                    data_get($proposal, 'owner.source_record_id'),
                    data_get($proposal, 'business.source_record_id'),
                ])
                ->filter()
                ->all())
            ->pluck('legacy_id');
        $lineOfBusinessSourceHashes = collect($lineOfBusinessProposals)
            ->pluck('normalized_source_value_sha256')
            ->filter()
            ->all();
        $proposalFingerprint = $this->hash([
            $evidenceBinding,
            $locationProposals,
            $lineOfBusinessProposals,
            $exactMappingProposals,
        ]);

        return [
            'report' => [
                'schema_version' => self::SchemaVersion,
                'evidence' => $evidenceBinding,
                'summary' => [
                    'cohort_size' => $cohort->count(),
                    'location_reference_count' => collect($locationProposals)->sum(fn (array $proposal): int => count($proposal['references'])),
                    'exact_location_hierarchy_count' => $locationChainCount,
                    'line_of_business_declaration_count' => count($lineOfBusinessProposals),
                    'exact_legacy_group_hierarchy_count' => $lineTargetEvidenceCount,
                    'existing_exact_target_count' => $existingTargetCount,
                    'source_backed_target_creation_proposal_count' => count($lineOfBusinessProposals) - $existingTargetCount,
                    'evidence_complete_acceptance_pending_count' => $acceptanceReadyCount,
                    'accepted_reconciliation_count' => LegacyLineOfBusinessReconciliation::query()
                        ->where('legacy_source_id', $financialPlan->importBatch->legacy_source_id)
                        ->where('source_dataset', 'groups')
                        ->whereIn('source_value_hash', $lineOfBusinessSourceHashes)
                        ->where('status', 'accepted')
                        ->count(),
                    'accepted_application_mapping_count' => LegacyApplicationIdMapping::query()
                        ->where('legacy_source_id', $financialPlan->importBatch->legacy_source_id)
                        ->where('legacy_import_batch_id', $financialPlan->legacy_import_batch_id)
                        ->where('dataset_key', 'business_permit_applications')
                        ->whereIn('legacy_id', $applicationLegacyIds)
                        ->count(),
                    'accepted_registry_mapping_count' => LegacyIdMapping::query()
                        ->where('legacy_source_id', $registryPlan->importBatch->legacy_source_id)
                        ->where('legacy_import_batch_id', $registryPlan->legacy_import_batch_id)
                        ->whereIn('legacy_id', $registryLegacyIds)
                        ->count(),
                    'cohort_changed' => false,
                    'production_rehearsal_authorized' => false,
                    'production_migration_executed' => false,
                ],
                'fingerprints' => [
                    'recommended_cohort_sha256' => $actualCohortFingerprint,
                    'prerequisite_proposals_sha256' => $proposalFingerprint,
                ],
                'remaining_acceptance_boundaries' => [
                    'Accept the exact source location-chain disposition for registry preservation.',
                    'Create or select explicit Laravel line-of-business targets from the exact legacy group evidence.',
                    'Accept line-of-business reconciliations with authority and evidence references.',
                    'Accept exact owner, business, and application mappings in dependency order.',
                    'Obtain separate Board authorization before any production-derived preservation rehearsal.',
                ],
                'state_model' => [
                    'observed' => 'Exact source records and hierarchy edges were read from the checksum-bound snapshot.',
                    'inferred' => 'Legacy source establishes groups as the fee-driving line-of-business dataset.',
                    'proposed' => 'Location disposition, target definitions, and exact mappings are review proposals only.',
                    'accepted' => 'No reconciliation or mapping was accepted by this characterization.',
                    'rehearsed' => 'No production-derived preservation rehearsal was executed.',
                    'production_applied' => 'No source or target production mutation occurred.',
                ],
                'legacy_implementation_evidence' => [
                    'baseline_commit' => 'b5a66a6a8b3828ebae9916f4bde1da729b1b9154',
                    'line_of_business_schema' => 'packages/backend/convex/schema.ts: groups, division_groups, divisions, majors',
                    'line_of_business_queries' => 'packages/backend/convex/groups.ts',
                    'line_of_business_contract' => 'packages/shared/src/types/line-of-business.ts',
                    'location_schema' => 'packages/backend/convex/schema.ts: provinces, cities, barangays',
                ],
                'safety' => [
                    'read_only_characterization' => true,
                    'source_payloads_in_report' => false,
                    'source_values_in_report' => false,
                    'identity_similarity_is_authority' => false,
                    'fee_names_used_as_identity' => false,
                    'accepted_reconciliations_created' => false,
                    'accepted_mappings_created' => false,
                    'production_mutation' => false,
                    'migration_executed' => false,
                ],
            ],
            'location_proposals' => $locationProposals,
            'line_of_business_proposals' => $lineOfBusinessProposals,
            'exact_mapping_proposals' => $exactMappingProposals,
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  Collection<string, LegacyRecord>  $lookupsByIdentity
     * @return array<string, mixed>
     */
    private function locationProposal(array $candidate, LegacyRecord $business, Collection $lookupsByIdentity): array
    {
        $references = [];
        $resolved = [];
        foreach (self::LocationFields as $field => $dataset) {
            $sourceValue = $this->string($business->payload[$field] ?? null);
            $record = $sourceValue === '' ? null : $lookupsByIdentity->get($dataset.'|'.$sourceValue);
            $resolved[$field] = $record;
            $references[] = [
                'field' => $field,
                'source_dataset' => $dataset,
                'source_value_sha256' => $sourceValue === '' ? null : hash('sha256', $sourceValue),
                'source_lookup_record_id' => $record?->id,
                'source_lookup_payload_hash' => $record?->payload_hash,
                'resolution_status' => $record instanceof LegacyRecord ? 'exact_source_id_resolved' : 'unresolved',
            ];
        }

        $provinceId = $this->string($business->payload['provinceId'] ?? null);
        $cityId = $this->string($business->payload['cityId'] ?? null);
        $city = $resolved['cityId'];
        $barangay = $resolved['barangayId'];
        $cityProvinceConsistent = $city instanceof LegacyRecord
            && hash_equals($provinceId, $this->string($city->payload['provinceId'] ?? null));
        $barangayCityConsistent = $barangay instanceof LegacyRecord
            && hash_equals($cityId, $this->string($barangay->payload['cityId'] ?? null));
        $exact = collect($resolved)->every(fn (?LegacyRecord $record): bool => $record instanceof LegacyRecord)
            && $cityProvinceConsistent
            && $barangayCityConsistent;

        return [
            'candidate_fingerprint' => $candidate['candidate_fingerprint'],
            'business_source_record_id' => $business->id,
            'references' => $references,
            'hierarchy' => [
                'city_belongs_to_source_province' => $cityProvinceConsistent,
                'barangay_belongs_to_source_city' => $barangayCityConsistent,
            ],
            'source_chain_status' => $exact ? 'exact_hierarchy_resolved' : 'unresolved_source_hierarchy',
            'target_catalog_status' => 'no_normalized_laravel_location_catalog',
            'proposed_disposition' => $exact
                ? 'preserve_exact_source_lookup_chain_as_registry_provenance'
                : 'remain_blocked_pending_source_reconciliation',
            'proposal_status' => $exact ? 'evidence_complete_acceptance_pending' : 'blocked',
            'acceptance_status' => 'proposed_not_accepted',
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  EloquentCollection<int, LegacyRecord>  $lookupRecords
     * @param  Collection<string, LegacyRecord>  $lookupsByIdentity
     * @param  EloquentCollection<int, LineOfBusiness>  $lineOfBusinesses
     * @return list<array<string, mixed>>
     */
    private function lineOfBusinessProposals(
        array $candidate,
        LegacyRecord $application,
        EloquentCollection $lookupRecords,
        Collection $lookupsByIdentity,
        EloquentCollection $lineOfBusinesses,
    ): array {
        $lines = $application->payload['linesOfBusiness'] ?? [];
        $lines = is_array($lines) ? array_values($lines) : [];

        return array_values(collect($lines)->map(function (mixed $line, int $index) use (
            $candidate,
            $application,
            $lookupRecords,
            $lookupsByIdentity,
            $lineOfBusinesses,
        ): array {
            $category = is_array($line) ? $this->string($line['businessCategory'] ?? null) : '';
            $matches = $category === '' ? collect() : $lookupRecords
                ->where('dataset_key', 'groups')
                ->filter(fn (LegacyRecord $record): bool => $this->string($record->payload['name'] ?? null) === $category)
                ->values();
            $group = $matches->count() === 1 ? $matches->first() : null;
            $divisionGroups = $group instanceof LegacyRecord
                ? $lookupRecords
                    ->where('dataset_key', 'division_groups')
                    ->filter(fn (LegacyRecord $record): bool => $this->string($record->payload['groupId'] ?? null) === $group->legacy_id)
                    ->values()
                : collect();
            $hierarchy = $divisionGroups->map(function (LegacyRecord $edge) use ($lookupsByIdentity): array {
                $divisionId = $this->string($edge->payload['divisionId'] ?? null);
                $division = $divisionId === '' ? null : $lookupsByIdentity->get('divisions|'.$divisionId);
                $majorId = $division instanceof LegacyRecord ? $this->string($division->payload['majorId'] ?? null) : '';
                $major = $majorId === '' ? null : $lookupsByIdentity->get('majors|'.$majorId);

                return [
                    'division_group_source_record_id' => $edge->id,
                    'division_group_payload_hash' => $edge->payload_hash,
                    'division_source_record_id' => $division?->id,
                    'division_payload_hash' => $division?->payload_hash,
                    'major_source_record_id' => $major?->id,
                    'major_payload_hash' => $major?->payload_hash,
                    'resolved' => $division instanceof LegacyRecord && $major instanceof LegacyRecord,
                ];
            })->sortBy('division_group_source_record_id')->values();
            $hierarchyResolved = $group instanceof LegacyRecord
                && $divisionGroups->isNotEmpty()
                && $hierarchy->every(fn (array $edge): bool => $edge['resolved']);
            $legacyBoundTargets = $group instanceof LegacyRecord
                ? $lineOfBusinesses->where('legacy_source_id', $group->legacy_id)->values()
                : collect();
            $normalizedCategory = Str::of($category)->squish()->lower()->toString();
            $nameCollisionCount = $category === '' ? 0 : $lineOfBusinesses
                ->filter(fn (LineOfBusiness $target): bool => Str::of($target->name)->squish()->lower()->toString() === $normalizedCategory)
                ->count();
            $target = $legacyBoundTargets->count() === 1 ? $legacyBoundTargets->first() : null;
            $declarationProjection = $this->declarationProjector->project($application, $index);
            $sourceEvidenceExact = $matches->count() === 1 && $hierarchyResolved;
            $targetDefinitionHash = $sourceEvidenceExact ? $this->hash([
                $group->payload_hash,
                $hierarchy->map(fn (array $edge): array => [
                    $edge['division_group_payload_hash'],
                    $edge['division_payload_hash'],
                    $edge['major_payload_hash'],
                ])->all(),
            ]) : null;

            return [
                'candidate_fingerprint' => $candidate['candidate_fingerprint'],
                'application_source_record_id' => $application->id,
                'declaration_index' => $index,
                'source_value_sha256' => $category === '' ? null : hash('sha256', $category),
                'normalized_source_value_sha256' => $category === '' ? null : hash('sha256', $normalizedCategory),
                'exact_group_match_count' => $matches->count(),
                'source_group_record_id' => $group?->id,
                'source_group_legacy_id_sha256' => $group instanceof LegacyRecord ? hash('sha256', $group->legacy_id) : null,
                'source_group_payload_hash' => $group?->payload_hash,
                'hierarchy' => $hierarchy->all(),
                'source_target_evidence_status' => $sourceEvidenceExact
                    ? 'exact_group_hierarchy_resolved'
                    : 'source_target_evidence_unresolved',
                'proposed_target_definition_sha256' => $targetDefinitionHash,
                'proposed_target_line_of_business_id' => $target?->id,
                'existing_legacy_bound_target_count' => $legacyBoundTargets->count(),
                'existing_normalized_name_collision_count' => $nameCollisionCount,
                'proposed_target_action' => match (true) {
                    ! $sourceEvidenceExact => 'none_source_evidence_unresolved',
                    $target instanceof LineOfBusiness => 'review_existing_legacy_bound_target',
                    default => 'create_target_from_exact_source_group_after_acceptance',
                },
                'declaration_projection_status' => $declarationProjection['status']->value,
                'declaration_projection_reasons' => $declarationProjection['reasons'],
                'proposal_status' => $sourceEvidenceExact
                    ? 'evidence_complete_acceptance_pending'
                    : 'blocked',
                'acceptance_status' => 'proposed_not_accepted',
            ];
        })->values()->all());
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $location
     * @param  list<array<string, mixed>>  $declarations
     * @return array<string, mixed>
     */
    private function exactMappingProposal(array $candidate, array $location, array $declarations): array
    {
        $prerequisitesComplete = $location['proposal_status'] === 'evidence_complete_acceptance_pending'
            && $declarations !== []
            && collect($declarations)->every(fn (array $declaration): bool => $declaration['proposal_status'] === 'evidence_complete_acceptance_pending');

        return [
            'candidate_fingerprint' => $candidate['candidate_fingerprint'],
            'owner' => [
                'source_record_id' => data_get($candidate, 'owner.source_record_id'),
                'registry_proposal_id' => data_get($candidate, 'owner.proposal_id'),
                'acceptance_status' => 'proposed_not_accepted',
            ],
            'business' => [
                'source_record_id' => data_get($candidate, 'business.source_record_id'),
                'registry_proposal_id' => data_get($candidate, 'business.proposal_id'),
                'acceptance_status' => $location['proposal_status'] === 'evidence_complete_acceptance_pending'
                    ? 'proposed_pending_location_disposition_acceptance'
                    : 'blocked_by_location_evidence',
            ],
            'application' => [
                'source_record_id' => data_get($candidate, 'application.source_record_id'),
                'acceptance_status' => $prerequisitesComplete
                    ? 'proposed_pending_registry_and_declaration_acceptance'
                    : 'blocked_by_unresolved_prerequisites',
            ],
            'proposal_status' => $prerequisitesComplete ? 'evidence_complete_acceptance_pending' : 'blocked',
            'accepted_mapping_created' => false,
            'rehearsal_authorized' => false,
        ];
    }

    private function assertExpectedCohort(string $expected, string $actual): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $expected) !== 1) {
            throw new RuntimeException('The expected cohort SHA-256 must be a lowercase hexadecimal fingerprint.');
        }
        if (! hash_equals($expected, $actual)) {
            throw new RuntimeException('The recomputed cohort does not match the expected frozen fingerprint.');
        }
    }

    private function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }

    /** @param array<array-key, mixed> $value */
    private function hash(array $value): string
    {
        return hash('sha256', json_encode($this->sortRecursively($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
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
}
