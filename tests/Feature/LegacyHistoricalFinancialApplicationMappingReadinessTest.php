<?php

use App\Actions\CharacterizeLegacyHistoricalFinancialApplicationMappings;
use App\Actions\CharacterizeLegacyHistoricalFinancialHumanIdentityFrontier;
use App\Actions\PlanLegacyFinancialDependencies;
use App\Actions\PlanLegacyRegistryMigration;
use App\Enums\LegacyImportBatchStatus;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyIdMapping;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingProposal;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use Illuminate\Support\Facades\Storage;

/** @param array<string, mixed> $payload */
function mappingReadinessRecord(LegacyImportBatch $batch, string $dataset, string $legacyId, array $payload): LegacyRecord
{
    $payload = ['_id' => $legacyId, ...$payload];

    return LegacyRecord::query()->create([
        'legacy_import_batch_id' => $batch->id,
        'legacy_source_id' => $batch->legacy_source_id,
        'dataset_key' => $dataset,
        'entity_type' => str($dataset)->singular()->toString(),
        'legacy_id' => $legacyId,
        'payload' => $payload,
        'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        'status' => 'staged',
        'line_number' => $batch->records()->count() + 1,
    ]);
}

/**
 * @param  array<string, mixed>  $owner
 * @param  array<string, mixed>  $business
 * @param  array<string, mixed>  $application
 */
function addMappingReadinessCandidate(
    LegacyImportBatch $registryBatch,
    LegacyImportBatch $financialBatch,
    string $key,
    array $owner = [],
    array $business = [],
    array $application = [],
): void {
    $ownerId = 'owner-'.$key;
    $businessId = 'business-'.$key;
    $ownerPayload = [
        'ownerType' => 'Individual',
        'firstName' => 'Owner '.$key,
        'lastName' => 'Example',
        ...$owner,
    ];
    $businessPayload = [
        'ownerId' => $ownerId,
        'name' => 'Business '.$key,
        ...$business,
    ];
    $applicationPayload = [
        'businessOwnerId' => $ownerId,
        'businessId' => $businessId,
        'applicationNumber' => 'APP-'.$key,
        'status' => 'Assessment',
        'permitApplicationType' => 'New',
        'submittedAt' => '2026-01-10T08:00:00+08:00',
        'linesOfBusiness' => [],
        ...$application,
    ];

    foreach ([$registryBatch, $financialBatch] as $batch) {
        mappingReadinessRecord($batch, 'business_owners', $ownerId, $ownerPayload);
        mappingReadinessRecord($batch, 'businesses', $businessId, $businessPayload);
    }
    mappingReadinessRecord($registryBatch, 'business_permit_applications', 'application-'.$key, $applicationPayload);
    mappingReadinessRecord($financialBatch, 'business_permit_applications', 'application-'.$key, $applicationPayload);
    mappingReadinessRecord($financialBatch, 'payment_schedules', 'schedule-'.$key, [
        'applicationId' => 'application-'.$key,
        'sectionNumber' => 1,
        'dueDate' => '2026-01-20',
        'status' => 'pending',
        'fees' => [[
            'feeName' => 'Historical fee '.$key,
            'feeCategory' => 'Fee',
            'originalAmount' => 100,
            'sectionAmount' => 100,
        ]],
        'surcharge' => 0,
        'penalty' => 0,
        'totalAmount' => 100,
        'paidAmount' => 0,
    ]);
}

/** @return array{registry: LegacyImportBatch, financial: LegacyImportBatch} */
function mappingReadinessBatches(string $suffix): array
{
    $source = LegacySource::factory()->create([
        'key' => 'MAPPING-READINESS-'.$suffix,
        'archive_checksum' => hash('sha256', 'mapping-readiness-'.$suffix),
    ]);

    return [
        'registry' => LegacyImportBatch::factory()->for($source, 'source')->create([
            'run_reference' => 'mapping-readiness-registry-'.$suffix,
            'manifest_checksum' => hash('sha256', 'registry-'.$suffix),
            'status' => LegacyImportBatchStatus::Staged,
        ]),
        'financial' => LegacyImportBatch::factory()->for($source, 'source')->create([
            'run_reference' => 'mapping-readiness-financial-'.$suffix,
            'manifest_checksum' => hash('sha256', 'financial-'.$suffix),
            'status' => LegacyImportBatchStatus::Staged,
        ]),
    ];
}

function addMappingReadinessUnassignedFailedPayment(LegacyImportBatch $financialBatch, string $key): void
{
    $schedule = $financialBatch->records()
        ->where('dataset_key', 'payment_schedules')
        ->where('legacy_id', 'schedule-'.$key)
        ->sole();
    $schedulePayload = [
        ...$schedule->payload,
        'status' => 'paid',
        'paidAmount' => 100,
    ];
    $schedule->update([
        'payload' => $schedulePayload,
        'payload_hash' => hash('sha256', json_encode($schedulePayload, JSON_THROW_ON_ERROR)),
    ]);
    mappingReadinessRecord($financialBatch, 'payments', 'payment-completed-'.$key, [
        'applicationId' => 'application-'.$key,
        'scheduleId' => 'schedule-'.$key,
        'amount' => 100,
        'paymentMethod' => 'Cash',
        'status' => 'completed',
        'paidAt' => '2026-01-20T09:00:00+08:00',
        'processedBy' => 'operator-'.$key,
    ]);
    mappingReadinessRecord($financialBatch, 'payments', 'payment-failed-'.$key, [
        'applicationId' => 'application-'.$key,
        'scheduleId' => 'missing-schedule-'.$key,
        'amount' => 100,
        'paymentMethod' => 'Cash',
        'status' => 'failed',
        'paidAt' => '2026-01-19T09:00:00+08:00',
        'processedBy' => 'operator-'.$key,
    ]);
}

test('strict preservation candidates are classified by exact identity evidence without accepting mappings', function () {
    $batches = mappingReadinessBatches('taxonomy');
    addMappingReadinessCandidate($batches['registry'], $batches['financial'], 'exact');
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'crosswalk',
        business: ['categoryId' => 'legacy-category'],
        application: ['linesOfBusiness' => [['businessCategory' => 'Legacy category']]],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'group',
        owner: ['ownerType' => 'Group', 'groupName' => 'Historical group'],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'collision',
        owner: ['email' => 'shared@example.test'],
    );
    foreach ([$batches['registry'], $batches['financial']] as $batch) {
        mappingReadinessRecord($batch, 'business_owners', 'owner-collision-decoy', [
            'ownerType' => 'Individual',
            'firstName' => 'Collision Decoy',
            'lastName' => 'Example',
            'email' => 'shared@example.test',
        ]);
    }

    $registryPlan = app(PlanLegacyRegistryMigration::class)->handle($batches['registry'], 'registry-taxonomy');
    $financialPlan = app(PlanLegacyFinancialDependencies::class)->handle($batches['financial'], 'financial-taxonomy');
    $beforeRecordCount = LegacyRecord::query()->count();
    $result = app(CharacterizeLegacyHistoricalFinancialApplicationMappings::class)->handle($financialPlan, $registryPlan);

    expect($result['report']['summary'])
        ->strict_preservation_candidate_count->toBe(4)
        ->deterministic_identity_chain_count->toBe(2)
        ->accepted_mapping_count->toBe(0)
        ->and($result['report']['summary']['classification_counts'])->toMatchArray([
            'deterministic_exact_mapping_candidate' => 1,
            'human_identity_reconciliation' => 1,
            'reference_data_crosswalk_only' => 1,
            'registry_policy_reconciliation' => 1,
        ])
        ->and($result['cohort'])->toHaveCount(2)
        ->and(collect($result['cohort'])->pluck('proposed_mapping_status')->sort()->values()->all())->toBe([
            'pending_mapping_acceptance',
            'pending_reference_data_and_mapping_acceptance',
        ])
        ->and(LegacyApplicationIdMapping::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0)
        ->and(LegacyRecord::query()->count())->toBe($beforeRecordCount)
        ->and($result['report']['safety'])->toMatchArray([
            'read_only_characterization' => true,
            'accepted_mappings_created' => false,
            'production_mutation' => false,
            'migration_executed' => false,
        ]);
});

test('human identity frontier isolates collision classes without accepting similarity based mappings', function () {
    Storage::fake('local');
    $batches = mappingReadinessBatches('human-identity-frontier');
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'owner-collision',
        owner: ['email' => 'shared-owner@example.test'],
    );
    foreach ([$batches['registry'], $batches['financial']] as $batch) {
        mappingReadinessRecord($batch, 'business_owners', 'owner-collision-decoy', [
            'ownerType' => 'Individual',
            'firstName' => 'Unrelated',
            'lastName' => 'Person',
            'email' => 'shared-owner@example.test',
        ]);
    }
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'person-oriented-owner-collision',
        owner: [
            'firstName' => 'Shared',
            'lastName' => 'Person',
            'birthDate' => '1980-01-01',
        ],
    );
    foreach ([$batches['registry'], $batches['financial']] as $batch) {
        mappingReadinessRecord($batch, 'business_owners', 'person-oriented-owner-collision-decoy', [
            'ownerType' => 'Individual',
            'firstName' => 'Shared',
            'lastName' => 'Person',
            'birthDate' => '1980-01-01',
        ]);
    }
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'business-collision-a',
        business: ['registrationNumber' => 'SHARED-REGISTRATION'],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'business-collision-b',
        business: ['registrationNumber' => 'SHARED-REGISTRATION'],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'group-owner-business-collision-a',
        owner: ['ownerType' => 'Group', 'groupName' => 'Historical group A'],
        business: ['registrationNumber' => 'GROUP-SHARED-REGISTRATION'],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'group-owner-business-collision-b',
        owner: ['ownerType' => 'Group', 'groupName' => 'Historical group B'],
        business: ['registrationNumber' => 'GROUP-SHARED-REGISTRATION'],
    );

    $registryPlan = app(PlanLegacyRegistryMigration::class)->handle($batches['registry'], 'registry-human-identity-frontier');
    $financialPlan = app(PlanLegacyFinancialDependencies::class)->handle($batches['financial'], 'financial-human-identity-frontier');
    $result = app(CharacterizeLegacyHistoricalFinancialHumanIdentityFrontier::class)->handle($financialPlan, $registryPlan);
    $groupOwnerCandidates = collect($result['candidates'])
        ->whereIn('application_legacy_id_sha256', [
            hash('sha256', 'application-group-owner-business-collision-a'),
            hash('sha256', 'application-group-owner-business-collision-b'),
        ])
        ->values();
    $ownerCollisionCandidate = collect($result['candidates'])
        ->firstWhere('application_legacy_id_sha256', hash('sha256', 'application-owner-collision'));
    $personOrientedOwnerCollisionCandidate = collect($result['candidates'])
        ->firstWhere('application_legacy_id_sha256', hash('sha256', 'application-person-oriented-owner-collision'));
    $decisionCohorts = collect($result['report']['decision_ready_cohorts'])
        ->keyBy('key');
    $municipalIdentityEvidenceClasses = collect($result['report']['municipal_identity_evidence_classes'])
        ->keyBy('key');

    expect($result['report']['summary'])
        ->human_identity_application_count->toBe(6)
        ->identity_collision_only_count->toBe(6)
        ->additional_semantic_reconciliation_count->toBe(0)
        ->exact_owner_mapping_candidate_count->toBe(2)
        ->exact_owner_mapping_unique_proposal_count->toBe(2)
        ->exact_business_source_evidence_candidate_count->toBe(2)
        ->exact_business_source_evidence_unique_proposal_count->toBe(2)
        ->business_or_application_mapping_candidate_count->toBe(0)
        ->group_owner_overlay_count->toBe(2)
        ->decision_cohort_count->toBe(3)
        ->municipal_identity_evidence_class_count->toBe(2)
        ->contact_signals_only_application_count->toBe(1)
        ->non_contact_identity_signal_application_count->toBe(1)
        ->accepted_mapping_count->toBe(0)
        ->and(data_get($result, 'report.collision_clusters.owner.unique_collision_group_count'))->toBe(2)
        ->and(data_get($result, 'report.collision_clusters.owner.collision_group_size_distribution'))->toBe(['2' => 2])
        ->and(data_get($result, 'report.collision_clusters.business.unique_collision_group_count'))->toBe(2)
        ->and(data_get($result, 'report.collision_clusters.business.collision_group_size_distribution'))->toBe(['2' => 2])
        ->and($result['classes'])->toHaveCount(4)
        // Counterexample: Group-owner semantics leave the owner collision-reason
        // subset empty, but must still block the collision-free-owner disposition.
        ->and($groupOwnerCandidates)->toHaveCount(2)
        ->and($groupOwnerCandidates->pluck('proposed_disposition')->unique()->all())->toBe(['human_identity_reconciliation_required'])
        ->and($groupOwnerCandidates->pluck('shape.owner_collision_reasons')->all())->toBe([[], []])
        ->and($groupOwnerCandidates->pluck('shape.business_collision_reasons')->all())->toBe([['potential_source_business_collision'], ['potential_source_business_collision']])
        ->and($groupOwnerCandidates->pluck('shape.group_owner_policy_overlay')->unique()->all())->toBe([true])
        ->and($groupOwnerCandidates->pluck('business_source_evidence_disposition')->unique()->all())->toBe(['business_source_evidence_quarantined'])
        ->and($ownerCollisionCandidate['business_source_evidence_disposition'])->toBe('business_source_evidence_may_be_prepared_independently')
        ->and($ownerCollisionCandidate['business_mapping_acceptance_status'])->toBe('not_accepted')
        ->and($ownerCollisionCandidate['decision_cohort_key'])->toBe('collision_free_business_source_owner_decision_non_released')
        ->and($ownerCollisionCandidate['blocker_categories'])->toContain(
            'exact_mapping_acceptance',
            'municipal_identity_decision',
        )
        ->and($personOrientedOwnerCollisionCandidate['owner_collision_signal_names'])->toBe(['name_birth'])
        ->and($personOrientedOwnerCollisionCandidate['business_source_evidence_disposition'])->toBe('business_source_evidence_may_be_prepared_independently')
        ->and($municipalIdentityEvidenceClasses->keys()->all())->toBe([
            'contact_signals_only',
            'non_contact_identity_signal_present',
        ])
        ->and($municipalIdentityEvidenceClasses->get('contact_signals_only'))->toMatchArray([
            'application_count' => 1,
            'unique_owner_proposal_count' => 1,
            'unique_business_proposal_count' => 1,
            'historical_released_application_count' => 0,
            'non_released_application_count' => 1,
            'collision_review_unit_count' => 1,
            'observed_collision_signal_names' => ['email'],
            'one_decision_from_rehearsal' => false,
            'decision_status' => 'bounded_municipal_identity_evidence_decision_required',
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
        ])
        ->and($municipalIdentityEvidenceClasses->get('non_contact_identity_signal_present'))->toMatchArray([
            'application_count' => 1,
            'unique_owner_proposal_count' => 1,
            'unique_business_proposal_count' => 1,
            'historical_released_application_count' => 0,
            'non_released_application_count' => 1,
            'collision_review_unit_count' => 1,
            'observed_collision_signal_names' => ['name_birth'],
            'one_decision_from_rehearsal' => false,
            'decision_status' => 'human_legal_identity_reconciliation_required',
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
        ])
        ->and($decisionCohorts->keys()->sort()->values()->all())->toBe([
            'collision_free_business_source_owner_decision_non_released',
            'collision_free_owner_business_decision',
            'group_owner_registry_policy',
        ])
        ->and($decisionCohorts->get('collision_free_business_source_owner_decision_non_released'))->toMatchArray([
            'application_count' => 2,
            'records_that_would_advance' => 2,
            'decision_status' => 'characterized_not_ready_for_acceptance',
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
        ])
        ->and($decisionCohorts->get('collision_free_owner_business_decision'))->toMatchArray([
            'application_count' => 2,
            'unique_owner_proposal_count' => 2,
            'decision_status' => 'ready_for_owner_acceptance_review',
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
        ])
        ->and($decisionCohorts->get('group_owner_registry_policy'))->toMatchArray([
            'application_count' => 2,
            'decision_status' => 'blocked_by_registry_policy',
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
        ])
        ->and(data_get($result, 'report.fingerprints.decision_cohort_set_sha256'))->toHaveLength(64)
        ->and(data_get($result, 'report.fingerprints.municipal_identity_evidence_class_set_sha256'))->toHaveLength(64)
        ->and(LegacyApplicationIdMapping::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0)
        ->and($result['report']['safety'])->toMatchArray([
            'read_only' => true,
            'similarity_based_mapping' => false,
            'identity_merge_performed' => false,
            'accepted_mappings_created' => false,
        ]);

    $arguments = [
        'financial-plan' => $financialPlan->id,
        'registry-plan' => $registryPlan->id,
        '--run-id' => 'human-identity-frontier-001',
        '--json' => true,
    ];
    $this->artisan('legacy:characterize-historical-financial-human-identities', $arguments)->assertSuccessful();
    $this->artisan('legacy:characterize-historical-financial-human-identities', $arguments)->assertSuccessful();
    $root = "legacy-migrations/{$batches['financial']->source->key}/{$batches['financial']->run_reference}/reconciliation/historical-financial-human-identity-frontier/human-identity-frontier-001";
    Storage::disk('local')->assertExists($root.'/summary.json');
    Storage::disk('local')->assertExists($root.'/classes.json');
    Storage::disk('local')->assertExists($root.'/candidate-membership.jsonl');
    Storage::disk('local')->assertExists($root.'/review.md');
    $evidence = Storage::disk('local')->get($root.'/summary.json')
        .Storage::disk('local')->get($root.'/classes.json')
        .Storage::disk('local')->get($root.'/candidate-membership.jsonl');

    expect($evidence)
        ->not->toContain('shared-owner@example.test')
        ->not->toContain('Shared Person')
        ->not->toContain('1980-01-01')
        ->not->toContain('SHARED-REGISTRATION')
        ->not->toContain('GROUP-SHARED-REGISTRATION')
        ->not->toContain('Historical group A')
        ->not->toContain('Historical group B');
});

test('priority review classes separate compound contact and non contact identity review units', function () {
    Storage::fake('local');
    $batches = mappingReadinessBatches('priority-review-classes');
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'compound-contact-a',
        owner: ['email' => 'compound-contact@example.test'],
        business: ['registrationNumber' => 'CONTACT-REGISTRATION'],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'compound-contact-b',
        owner: ['email' => 'compound-contact@example.test'],
        business: ['registrationNumber' => 'CONTACT-REGISTRATION'],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'compound-non-contact-a',
        owner: [
            'firstName' => 'Compound',
            'lastName' => 'Non Contact',
            'birthDate' => '1981-02-03',
        ],
        business: ['registrationNumber' => 'NON-CONTACT-REGISTRATION'],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'compound-non-contact-b',
        owner: [
            'firstName' => 'Compound',
            'lastName' => 'Non Contact',
            'birthDate' => '1981-02-03',
        ],
        business: ['registrationNumber' => 'NON-CONTACT-REGISTRATION'],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'non-contact-collision-free-business-a',
        owner: [
            'firstName' => 'Collision Free',
            'lastName' => 'Non Contact',
            'birthDate' => '1982-03-04',
        ],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'non-contact-collision-free-business-b',
        owner: [
            'firstName' => 'Collision Free',
            'lastName' => 'Non Contact',
            'birthDate' => '1982-03-04',
        ],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'non-contact-externally-coupled-a',
        owner: [
            'firstName' => 'Externally Coupled',
            'lastName' => 'Owner',
            'birthDate' => '1983-04-05',
        ],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'non-contact-externally-coupled-b',
        owner: [
            'firstName' => 'Externally Coupled',
            'lastName' => 'Owner',
            'birthDate' => '1983-04-05',
        ],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'compound-registration-externally-coupled-a',
        owner: [
            'firstName' => 'Compound Coupled',
            'lastName' => 'Owner',
            'birthDate' => '1984-05-06',
        ],
        business: ['registrationNumber' => 'EXTERNALLY-COUPLED-REGISTRATION'],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'compound-registration-externally-coupled-b',
        owner: [
            'firstName' => 'Compound Coupled',
            'lastName' => 'Owner',
            'birthDate' => '1984-05-06',
        ],
        business: ['registrationNumber' => 'EXTERNALLY-COUPLED-REGISTRATION'],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'soft-deleted-contact',
        owner: ['email' => 'soft-deleted-contact@example.test'],
        application: ['isDeleted' => true],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'soft-deleted-financial',
        owner: ['email' => 'soft-deleted-financial@example.test'],
        application: [
            'isDeleted' => true,
            'feeOverrides' => [['reason' => 'preserved-soft-deleted-test-evidence']],
        ],
    );
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'identity-financial-contact',
        owner: ['email' => 'identity-financial-contact@example.test'],
        application: ['feeOverrides' => [['reason' => 'preserved-test-evidence']]],
    );
    foreach ($batches as $batch) {
        mappingReadinessRecord($batch, 'business_owners', 'soft-deleted-contact-decoy', [
            'ownerType' => 'Individual',
            'firstName' => 'Soft Deleted Decoy',
            'lastName' => 'Example',
            'email' => 'soft-deleted-contact@example.test',
        ]);
        mappingReadinessRecord($batch, 'business_owners', 'identity-financial-contact-decoy', [
            'ownerType' => 'Individual',
            'firstName' => 'Identity Financial Decoy',
            'lastName' => 'Example',
            'email' => 'identity-financial-contact@example.test',
        ]);
        mappingReadinessRecord($batch, 'business_owners', 'soft-deleted-financial-decoy', [
            'ownerType' => 'Individual',
            'firstName' => 'Soft Deleted Financial Decoy',
            'lastName' => 'Example',
            'email' => 'soft-deleted-financial@example.test',
        ]);
        mappingReadinessRecord($batch, 'business_owners', 'non-contact-externally-coupled-decoy', [
            'ownerType' => 'Individual',
            'firstName' => 'Externally Coupled',
            'lastName' => 'Owner',
            'birthDate' => '1983-04-05',
        ]);
        mappingReadinessRecord($batch, 'businesses', 'compound-registration-externally-coupled-decoy', [
            'ownerId' => 'owner-compound-registration-externally-coupled-a',
            'name' => 'Externally coupled registration decoy',
            'registrationNumber' => 'EXTERNALLY-COUPLED-REGISTRATION',
        ]);
    }

    $registryPlan = app(PlanLegacyRegistryMigration::class)->handle($batches['registry'], 'registry-priority-review-classes');
    $financialPlan = app(PlanLegacyFinancialDependencies::class)->handle($batches['financial'], 'financial-priority-review-classes');
    $result = app(CharacterizeLegacyHistoricalFinancialHumanIdentityFrontier::class)->handle($financialPlan, $registryPlan);
    $repeat = app(CharacterizeLegacyHistoricalFinancialHumanIdentityFrontier::class)->handle($financialPlan, $registryPlan);
    $priorityReviewClasses = collect($result['report']['priority_review_classes'])->keyBy('key');
    $softDeletedDecisionRoutes = collect($result['report']['soft_deleted_decision_routes'])->keyBy('key');
    $arguments = [
        'financial-plan' => $financialPlan->id,
        'registry-plan' => $registryPlan->id,
        '--run-id' => 'priority-decision-unlocks-v6-001',
        '--json' => true,
    ];
    $this->artisan('legacy:characterize-historical-financial-human-identities', $arguments)->assertSuccessful();
    $root = "legacy-migrations/{$batches['financial']->source->key}/{$batches['financial']->run_reference}/reconciliation/historical-financial-human-identity-frontier/priority-decision-unlocks-v6-001";
    $evidence = Storage::disk('local')->get($root.'/summary.json')
        .Storage::disk('local')->get($root.'/classes.json')
        .Storage::disk('local')->get($root.'/candidate-membership.jsonl');
    $rawCollisionFingerprints = LegacyMappingProposal::query()
        ->where('legacy_mapping_plan_id', $registryPlan->id)
        ->get()
        ->flatMap(fn (LegacyMappingProposal $proposal): array => array_values($proposal->collision_fingerprints ?? []))
        ->unique();

    expect($result['report']['schema_version'])->toBe('bpls.historical-financial-human-identity-frontier.v6')
        ->and($result['report']['summary']['human_identity_application_count'])->toBe(13)
        ->and($result['report']['summary']['priority_review_class_count'])->toBe(6)
        ->and($result['report']['summary']['soft_deleted_decision_route_count'])->toBe(2)
        ->and($priorityReviewClasses->keys()->all())->toBe([
            'non_contact_identity_collision_free_business',
            'compound_registration_business_collision',
            'compound_contact_owner_registration_business_collision',
            'compound_non_contact_owner_registration_business_collision',
            'soft_deleted_exception_matrix',
            'identity_plus_financial_exception',
        ])
        ->and($priorityReviewClasses->get('non_contact_identity_collision_free_business'))->toMatchArray([
            'application_count' => 4,
            'unique_owner_proposal_count' => 4,
            'unique_business_proposal_count' => 4,
            'records_that_would_advance' => 4,
            'one_bounded_decision_could_make_rehearsal_ready' => false,
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
        ])
        ->and(data_get($priorityReviewClasses, 'non_contact_identity_collision_free_business.review_units.owner_non_contact_collision_groups.unique_collision_group_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'non_contact_identity_collision_free_business.review_units.owner_non_contact_collision_groups.closed_collision_group_count'))->toBe(1)
        ->and(data_get($priorityReviewClasses, 'non_contact_identity_collision_free_business.review_units.owner_non_contact_collision_groups.closed_candidate_proposal_membership_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'non_contact_identity_collision_free_business.review_units.owner_non_contact_collision_groups.closed_candidate_application_membership_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'non_contact_identity_collision_free_business.review_units.owner_non_contact_collision_groups.externally_coupled_collision_group_count'))->toBe(1)
        ->and(data_get($priorityReviewClasses, 'non_contact_identity_collision_free_business.review_units.owner_non_contact_collision_groups.externally_coupled_candidate_proposal_membership_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'non_contact_identity_collision_free_business.review_units.owner_non_contact_collision_groups.externally_coupled_candidate_application_membership_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'non_contact_identity_collision_free_business.review_units.owner_non_contact_collision_groups.external_proposal_membership_count'))->toBe(1)
        ->and(data_get($priorityReviewClasses, 'non_contact_identity_collision_free_business.review_units.owner_non_contact_collision_groups.closed_non_released_application_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'non_contact_identity_collision_free_business.review_units.owner_non_contact_collision_groups.externally_coupled_non_released_application_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'non_contact_identity_collision_free_business.review_units.owner_non_contact_collision_groups.closed_group_authoritative_disposition_could_unlock_exact_proposal_preparation'))->toBeTrue()
        ->and(data_get($priorityReviewClasses, 'non_contact_identity_collision_free_business.review_units.owner_non_contact_collision_groups.externally_coupled_group_requires_full_global_group_review'))->toBeTrue()
        ->and($priorityReviewClasses->get('compound_registration_business_collision'))->toMatchArray([
            'application_count' => 6,
            'unique_owner_proposal_count' => 6,
            'unique_business_proposal_count' => 6,
            'records_that_would_advance' => 6,
            'one_bounded_decision_could_make_rehearsal_ready' => false,
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
        ])
        ->and(data_get($priorityReviewClasses, 'compound_registration_business_collision.review_units.business_registration_collision_groups.unique_collision_group_count'))->toBe(3)
        ->and(data_get($priorityReviewClasses, 'compound_registration_business_collision.review_units.business_registration_collision_groups.closed_collision_group_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'compound_registration_business_collision.review_units.business_registration_collision_groups.closed_candidate_application_membership_count'))->toBe(4)
        ->and(data_get($priorityReviewClasses, 'compound_registration_business_collision.review_units.business_registration_collision_groups.externally_coupled_collision_group_count'))->toBe(1)
        ->and(data_get($priorityReviewClasses, 'compound_registration_business_collision.review_units.business_registration_collision_groups.externally_coupled_candidate_proposal_membership_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'compound_registration_business_collision.review_units.business_registration_collision_groups.externally_coupled_candidate_application_membership_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'compound_registration_business_collision.review_units.business_registration_collision_groups.externally_coupled_total_proposal_membership_count'))->toBe(3)
        ->and(data_get($priorityReviewClasses, 'compound_registration_business_collision.review_units.business_registration_collision_groups.external_proposal_membership_count'))->toBe(1)
        ->and(data_get($priorityReviewClasses, 'compound_registration_business_collision.review_units.business_registration_collision_groups.closed_contact_owner_application_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'compound_registration_business_collision.review_units.business_registration_collision_groups.closed_non_contact_owner_application_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'compound_registration_business_collision.review_units.business_registration_collision_groups.externally_coupled_contact_owner_application_count'))->toBe(0)
        ->and(data_get($priorityReviewClasses, 'compound_registration_business_collision.review_units.business_registration_collision_groups.externally_coupled_non_contact_owner_application_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'compound_registration_business_collision.review_units.business_registration_collision_groups.closed_group_authoritative_disposition_could_unlock_exact_proposal_preparation'))->toBeTrue()
        ->and(data_get($priorityReviewClasses, 'compound_registration_business_collision.review_units.business_registration_collision_groups.externally_coupled_group_requires_full_global_group_review'))->toBeTrue()
        ->and($priorityReviewClasses->get('compound_contact_owner_registration_business_collision'))->toMatchArray([
            'application_count' => 2,
            'unique_owner_proposal_count' => 2,
            'unique_business_proposal_count' => 2,
            'records_that_would_advance' => 2,
            'one_bounded_decision_could_make_rehearsal_ready' => false,
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
        ])
        ->and(data_get($priorityReviewClasses, 'compound_contact_owner_registration_business_collision.review_units.owner_contact_collision_groups.unique_collision_group_count'))->toBe(1)
        ->and(data_get($priorityReviewClasses, 'compound_contact_owner_registration_business_collision.review_units.business_registration_collision_groups.unique_collision_group_count'))->toBe(1)
        ->and($priorityReviewClasses->get('compound_non_contact_owner_registration_business_collision'))->toMatchArray([
            'application_count' => 4,
            'unique_owner_proposal_count' => 4,
            'unique_business_proposal_count' => 4,
            'records_that_would_advance' => 4,
            'one_bounded_decision_could_make_rehearsal_ready' => false,
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
        ])
        ->and(data_get($priorityReviewClasses, 'compound_non_contact_owner_registration_business_collision.review_units.owner_non_contact_collision_groups.unique_collision_group_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'compound_non_contact_owner_registration_business_collision.review_units.business_registration_collision_groups.unique_collision_group_count'))->toBe(2)
        ->and($priorityReviewClasses->get('soft_deleted_exception_matrix'))->toMatchArray([
            'application_count' => 2,
            'contact_signal_only_application_count' => 2,
            'non_contact_identity_signal_application_count' => 0,
            'treasury_interpretation_application_count' => 0,
            'financial_policy_authority_application_count' => 1,
            'permit_authority_semantics_application_count' => 0,
            'genuine_source_data_contradiction_application_count' => 0,
            'one_bounded_decision_could_make_rehearsal_ready' => false,
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
        ])
        ->and(data_get($priorityReviewClasses, 'soft_deleted_exception_matrix.review_units.owner_collision_groups.closed_collision_group_count'))->toBe(0)
        ->and(data_get($priorityReviewClasses, 'soft_deleted_exception_matrix.review_units.owner_collision_groups.externally_coupled_collision_group_count'))->toBe(2)
        ->and(data_get($priorityReviewClasses, 'soft_deleted_exception_matrix.review_units.owner_collision_groups.external_proposal_membership_count'))->toBe(2)
        ->and($softDeletedDecisionRoutes->keys()->all())->toBe([
            'deletion_identity_reference_only',
            'financial_policy_authority',
        ])
        ->and($softDeletedDecisionRoutes->get('deletion_identity_reference_only'))->toMatchArray([
            'application_count' => 1,
            'one_bounded_decision_would_unlock_exact_proposal_preparation' => false,
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
        ])
        ->and($softDeletedDecisionRoutes->get('financial_policy_authority'))->toMatchArray([
            'application_count' => 1,
            'one_bounded_decision_would_unlock_exact_proposal_preparation' => false,
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
        ])
        ->and($priorityReviewClasses->get('identity_plus_financial_exception'))->toMatchArray([
            'application_count' => 1,
            'identity_decision_required' => true,
            'financial_authority_decision_required' => true,
            'decisions_are_independent' => true,
            'identity_disposition_alone_could_unlock_exact_proposal_preparation' => false,
            'financial_disposition_alone_could_unlock_exact_proposal_preparation' => false,
            'full_global_owner_collision_group_review_required' => true,
            'one_bounded_decision_could_make_rehearsal_ready' => false,
            'accepted_mapping_count' => 0,
            'rehearsed_mapping_count' => 0,
            'production_applied_count' => 0,
        ])
        ->and($priorityReviewClasses->get('identity_plus_financial_exception')['blocker_categories'])->toContain('financial_policy_authority')
        ->and(data_get($result, 'report.fingerprints.priority_review_class_set_sha256'))->toHaveLength(64)
        ->and(data_get($result, 'report.fingerprints.priority_decision_unlock_set_sha256'))->toHaveLength(64)
        ->and($result['report']['preserved_v5_outputs'])->toMatchArray([
            'schema_version' => 'bpls.historical-financial-human-identity-frontier.v5',
            'human_identity_frontier_sha256' => data_get($result, 'report.fingerprints.human_identity_frontier_sha256'),
            'business_source_evidence_subclass_sha256' => data_get($result, 'report.fingerprints.business_source_evidence_subclass_sha256'),
            'decision_cohort_set_sha256' => data_get($result, 'report.fingerprints.decision_cohort_set_sha256'),
            'municipal_identity_evidence_class_set_sha256' => data_get($result, 'report.fingerprints.municipal_identity_evidence_class_set_sha256'),
            'priority_review_class_set_sha256' => data_get($result, 'report.fingerprints.priority_review_class_set_sha256'),
        ])
        ->and($result['report']['preserved_v4_outputs'])->toMatchArray([
            'schema_version' => 'bpls.historical-financial-human-identity-frontier.v4',
            'human_identity_application_count' => 13,
            'decision_cohort_count' => 4,
            'contact_signals_only_application_count' => 0,
            'non_contact_identity_signal_application_count' => 4,
            'human_identity_frontier_sha256' => data_get($result, 'report.fingerprints.human_identity_frontier_sha256'),
            'business_source_evidence_subclass_sha256' => data_get($result, 'report.fingerprints.business_source_evidence_subclass_sha256'),
            'decision_cohort_set_sha256' => data_get($result, 'report.fingerprints.decision_cohort_set_sha256'),
            'municipal_identity_evidence_class_set_sha256' => data_get($result, 'report.fingerprints.municipal_identity_evidence_class_set_sha256'),
        ])
        ->and($repeat['report']['preserved_v5_outputs'])->toBe($result['report']['preserved_v5_outputs'])
        ->and($repeat['report']['preserved_v4_outputs'])->toBe($result['report']['preserved_v4_outputs'])
        ->and($repeat['report']['fingerprints'])->toBe($result['report']['fingerprints'])
        ->and(LegacyApplicationIdMapping::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0);

    expect($evidence)
        ->not->toContain('Externally Coupled')
        ->not->toContain('1983-04-05')
        ->not->toContain('EXTERNALLY-COUPLED-REGISTRATION')
        ->not->toContain('soft-deleted-financial@example.test');
    $rawCollisionFingerprints->each(fn (string $fingerprint) => expect($evidence)->not->toContain($fingerprint));
});

test('characterization refuses cross-batch payload drift and reports structural reference breaks', function () {
    $batches = mappingReadinessBatches('drift');
    addMappingReadinessCandidate($batches['registry'], $batches['financial'], 'drift');
    $registryOwner = $batches['registry']->records()->where('dataset_key', 'business_owners')->sole();
    $registryOwner->update(['payload_hash' => hash('sha256', 'different-owner-payload')]);

    $registryPlan = app(PlanLegacyRegistryMigration::class)->handle($batches['registry'], 'registry-drift');
    $financialPlan = app(PlanLegacyFinancialDependencies::class)->handle($batches['financial'], 'financial-drift');
    $result = app(CharacterizeLegacyHistoricalFinancialApplicationMappings::class)->handle($financialPlan, $registryPlan);
    $candidate = $result['candidates'][0];

    expect($candidate['classification'])->toBe('structural_reference_break')
        ->and($candidate['structural_reasons'])->toContain('owner_payload_differs_between_bound_batches')
        ->and($result['cohort'])->toBe([]);
});

test('v1 eligibility excludes frozen census histories with unassigned failed payment evidence', function () {
    $batches = mappingReadinessBatches('unassigned-payment');
    addMappingReadinessCandidate($batches['registry'], $batches['financial'], 'compatible');
    addMappingReadinessCandidate($batches['registry'], $batches['financial'], 'incompatible');
    addMappingReadinessUnassignedFailedPayment($batches['financial'], 'incompatible');

    $registryPlan = app(PlanLegacyRegistryMigration::class)->handle($batches['registry'], 'registry-unassigned-payment');
    $financialPlan = app(PlanLegacyFinancialDependencies::class)->handle($batches['financial'], 'financial-unassigned-payment');
    $result = app(CharacterizeLegacyHistoricalFinancialApplicationMappings::class)->handle($financialPlan, $registryPlan);
    $exception = $result['exceptions'][0];

    expect($result['report']['summary'])
        ->original_frozen_candidate_census_count->toBe(2)
        ->strict_preservation_candidate_count->toBe(1)
        ->preservation_executor_compatible_count->toBe(1)
        ->preservation_executor_incompatible_count->toBe(1)
        ->incompatible_deterministic_identity_chain_count->toBe(1)
        ->unassigned_payment_event_count->toBe(1)
        ->and($result['report']['summary']['unassigned_payment_events_by_application'])->toBe(['1' => 1])
        ->and($result['report']['summary']['unassigned_payment_edge_class_counts'])->toBe(['referenced_schedule_absent_from_snapshot' => 1])
        ->and($result['report']['summary']['unassigned_payment_status_counts'])->toBe(['failed' => 1])
        ->and($result['candidates'])->toHaveCount(1)
        ->and($result['exceptions'])->toHaveCount(1)
        ->and($exception['preservation_executor_compatibility_reasons'])->toBe(['application_has_unassigned_payment_events'])
        ->and($exception['compatibility_evidence'])->toMatchArray([
            'unassigned_payment_event_count' => 1,
            'edge_class_counts' => ['referenced_schedule_absent_from_snapshot' => 1],
            'status_counts' => ['failed' => 1],
            'all_source_schedule_identifiers_present' => true,
        ])
        ->and($result['cohort'])->toHaveCount(1)
        ->and(collect($result['cohort'])->pluck('candidate_fingerprint'))->not->toContain($exception['candidate_fingerprint']);
});

test('command writes immutable payload-safe evidence and stable retry does not create mappings', function () {
    Storage::fake('local');
    $batches = mappingReadinessBatches('command');
    addMappingReadinessCandidate(
        $batches['registry'],
        $batches['financial'],
        'sensitive-source-id',
        owner: ['email' => 'private@example.test'],
        business: [
            'provinceId' => 'private-province-reference',
            'cityId' => 'private-city-reference',
            'barangayId' => 'private-barangay-reference',
        ],
        application: ['linesOfBusiness' => [['businessCategory' => 'Private line of business']]],
    );
    $registryPlan = app(PlanLegacyRegistryMigration::class)->handle($batches['registry'], 'registry-command');
    $financialPlan = app(PlanLegacyFinancialDependencies::class)->handle($batches['financial'], 'financial-command');
    $command = 'legacy:characterize-historical-financial-application-mappings';
    $arguments = [
        'financial-plan' => $financialPlan->id,
        'registry-plan' => $registryPlan->id,
        '--run-id' => 'mapping-readiness-command-001',
        '--json' => true,
    ];

    $this->artisan($command, $arguments)->assertSuccessful();
    $this->artisan($command, $arguments)->assertSuccessful();

    $root = "legacy-migrations/{$batches['financial']->source->key}/{$batches['financial']->run_reference}/reconciliation/historical-financial-application-mapping-readiness/mapping-readiness-command-001";
    Storage::disk('local')->assertExists($root.'/summary.json');
    Storage::disk('local')->assertExists($root.'/proposed-mappings.jsonl');
    Storage::disk('local')->assertExists($root.'/frozen-census-exceptions.jsonl');
    Storage::disk('local')->assertExists($root.'/recommended-first-cohort.json');
    Storage::disk('local')->assertExists($root.'/cohort-prerequisite-proposals.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $evidence = Storage::disk('local')->get($root.'/summary.json')
        .Storage::disk('local')->get($root.'/proposed-mappings.jsonl')
        .Storage::disk('local')->get($root.'/frozen-census-exceptions.jsonl')
        .Storage::disk('local')->get($root.'/recommended-first-cohort.json')
        .Storage::disk('local')->get($root.'/cohort-prerequisite-proposals.json');
    $prerequisiteProposals = json_decode(
        Storage::disk('local')->get($root.'/cohort-prerequisite-proposals.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($evidence)->not->toContain('private@example.test')
        ->not->toContain('application-sensitive-source-id')
        ->not->toContain('owner-sensitive-source-id')
        ->not->toContain('business-sensitive-source-id')
        ->not->toContain('private-province-reference')
        ->not->toContain('private-city-reference')
        ->not->toContain('private-barangay-reference')
        ->not->toContain('Private line of business')
        ->and($prerequisiteProposals['proposals'][0]['reference_data_crosswalk_proposals'])->toHaveCount(3)
        ->and($prerequisiteProposals['proposals'][0]['line_of_business_crosswalk_proposals'][0])->toMatchArray([
            'proposed_target_line_of_business_id' => null,
            'proposal_status' => 'target_evidence_required',
            'acceptance_status' => 'pending',
        ])
        ->and(LegacyApplicationIdMapping::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0);
});
