<?php

use App\Actions\AcceptLegacyHistoricalFinancialCohortMappings;
use App\Actions\AuditLegacyHistoricalFinancialPreservation;
use App\Actions\AuditLegacyHistoricalFinancialPreservationRestoration;
use App\Actions\BuildLegacyHistoricalFinancialRehearsalAuthorizationPacket;
use App\Actions\CharacterizeLegacyHistoricalFinancialApplicationMappings;
use App\Actions\CharacterizeLegacyHistoricalFinancialCohortPrerequisites;
use App\Actions\ExecuteLegacyHistoricalFinancialPreservation;
use App\Actions\PlanLegacyFinancialDependencies;
use App\Actions\PlanLegacyRegistryMigration;
use App\Actions\RollbackLegacyHistoricalFinancialPreservation;
use App\Enums\LegacyImportBatchStatus;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyHistoricalFinancialMappingSet;
use App\Models\LegacyHistoricalFinancialPreservationExecution;
use App\Models\LegacyHistoricalFinancialPreservedBundle;
use App\Models\LegacyIdMapping;
use App\Models\LegacyImportBatch;
use App\Models\LegacyLineOfBusinessReconciliation;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\Storage;

/** @param array<string, mixed> $payload */
function cohortPrerequisiteRecord(LegacyImportBatch $batch, string $dataset, string $legacyId, array $payload): LegacyRecord
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

/** @return array{registry: LegacyImportBatch, financial: LegacyImportBatch} */
function cohortPrerequisiteBatches(string $suffix): array
{
    $source = LegacySource::factory()->create([
        'key' => 'COHORT-PREREQUISITES-'.$suffix,
        'archive_checksum' => hash('sha256', 'cohort-prerequisites-'.$suffix),
    ]);

    return [
        'registry' => LegacyImportBatch::factory()->for($source, 'source')->create([
            'run_reference' => 'cohort-prerequisites-registry-'.$suffix,
            'manifest_checksum' => hash('sha256', 'registry-'.$suffix),
            'status' => LegacyImportBatchStatus::Staged,
        ]),
        'financial' => LegacyImportBatch::factory()->for($source, 'source')->create([
            'run_reference' => 'cohort-prerequisites-financial-'.$suffix,
            'manifest_checksum' => hash('sha256', 'financial-'.$suffix),
            'status' => LegacyImportBatchStatus::Staged,
        ]),
    ];
}

/** @param array{registry: LegacyImportBatch, financial: LegacyImportBatch} $batches */
function addCohortPrerequisiteFixtures(array $batches): void
{
    cohortPrerequisiteRecord($batches['registry'], 'provinces', 'province-private', [
        'name' => 'Private Province',
        'code' => 'PRV',
    ]);
    cohortPrerequisiteRecord($batches['registry'], 'cities', 'city-private', [
        'name' => 'Private City',
        'provinceId' => 'province-private',
        'code' => 'CTY',
    ]);
    cohortPrerequisiteRecord($batches['registry'], 'majors', 'major-private', [
        'name' => 'Private Major',
        'createdAt' => '2026-01-01T00:00:00+08:00',
    ]);

    foreach (range(1, 5) as $number) {
        $key = (string) $number;
        $ownerId = 'owner-private-'.$key;
        $businessId = 'business-private-'.$key;
        $applicationId = 'application-private-'.$key;
        $barangayId = 'barangay-private-'.$key;
        $groupId = 'group-private-'.$key;
        $divisionId = 'division-private-'.$key;
        $businessPayload = [
            'ownerId' => $ownerId,
            'name' => 'Private Business '.$key,
            'provinceId' => 'province-private',
            'cityId' => 'city-private',
            'barangayId' => $barangayId,
        ];
        $ownerPayload = [
            'ownerType' => 'Individual',
            'firstName' => 'Private',
            'lastName' => 'Owner '.$key,
        ];
        $applicationPayload = [
            'businessOwnerId' => $ownerId,
            'businessId' => $businessId,
            'applicationNumber' => 'PRIVATE-APP-'.$key,
            'status' => 'Assessment',
            'permitApplicationType' => 'New',
            'submittedAt' => '2026-01-10T08:00:00+08:00',
            'linesOfBusiness' => [[
                'businessCategory' => 'Private Line '.$key,
                'permitApplicationType' => 'New',
                'capitalInvestment' => '1000.00',
            ]],
        ];

        foreach ([$batches['registry'], $batches['financial']] as $batch) {
            cohortPrerequisiteRecord($batch, 'business_owners', $ownerId, $ownerPayload);
            cohortPrerequisiteRecord($batch, 'businesses', $businessId, $businessPayload);
        }
        cohortPrerequisiteRecord($batches['registry'], 'business_permit_applications', $applicationId, $applicationPayload);
        cohortPrerequisiteRecord($batches['financial'], 'business_permit_applications', $applicationId, $applicationPayload);
        cohortPrerequisiteRecord($batches['financial'], 'payment_schedules', 'schedule-private-'.$key, [
            'applicationId' => $applicationId,
            'sectionNumber' => 1,
            'dueDate' => '2026-01-20',
            'status' => 'pending',
            'fees' => [[
                'feeName' => 'Private historical fee '.$key,
                'feeCategory' => 'Fee',
                'originalAmount' => 100,
                'sectionAmount' => 100,
            ]],
            'surcharge' => 0,
            'penalty' => 0,
            'totalAmount' => 100,
            'paidAmount' => 0,
        ]);
        cohortPrerequisiteRecord($batches['registry'], 'barangays', $barangayId, [
            'name' => 'Private Barangay '.$key,
            'cityId' => 'city-private',
            'code' => 'BRG-'.$key,
        ]);
        cohortPrerequisiteRecord($batches['registry'], 'groups', $groupId, [
            'name' => 'Private Line '.$key,
            'createdAt' => '2026-01-01T00:00:00+08:00',
        ]);
        cohortPrerequisiteRecord($batches['registry'], 'divisions', $divisionId, [
            'name' => 'Private Division '.$key,
            'majorId' => 'major-private',
            'createdAt' => '2026-01-01T00:00:00+08:00',
        ]);
        cohortPrerequisiteRecord($batches['registry'], 'division_groups', 'division-group-private-'.$key, [
            'divisionId' => $divisionId,
            'groupId' => $groupId,
            'createdAt' => '2026-01-01T00:00:00+08:00',
        ]);
    }
}

/** @return array{financial: mixed, registry: mixed, cohort_sha256: string} */
function plannedCohortPrerequisites(string $suffix): array
{
    $batches = cohortPrerequisiteBatches($suffix);
    addCohortPrerequisiteFixtures($batches);
    $registryPlan = app(PlanLegacyRegistryMigration::class)->handle($batches['registry'], 'registry-'.$suffix);
    $financialPlan = app(PlanLegacyFinancialDependencies::class)->handle($batches['financial'], 'financial-'.$suffix);
    $readiness = app(CharacterizeLegacyHistoricalFinancialApplicationMappings::class)->handle($financialPlan, $registryPlan);

    return [
        'financial' => $financialPlan,
        'registry' => $registryPlan,
        'cohort_sha256' => $readiness['report']['fingerprints']['recommended_cohort_sha256'],
    ];
}

test('five-record cohort prerequisite evidence resolves exact source hierarchies without accepting mappings', function () {
    $plans = plannedCohortPrerequisites('evidence');
    $beforeRecordCount = LegacyRecord::query()->count();

    $result = app(CharacterizeLegacyHistoricalFinancialCohortPrerequisites::class)->handle(
        $plans['financial'],
        $plans['registry'],
        $plans['cohort_sha256'],
    );

    expect($result['report']['summary'])
        ->cohort_size->toBe(5)
        ->location_reference_count->toBe(15)
        ->exact_location_hierarchy_count->toBe(5)
        ->line_of_business_declaration_count->toBe(5)
        ->exact_legacy_group_hierarchy_count->toBe(5)
        ->existing_exact_target_count->toBe(0)
        ->source_backed_target_creation_proposal_count->toBe(5)
        ->evidence_complete_acceptance_pending_count->toBe(5)
        ->accepted_reconciliation_count->toBe(0)
        ->accepted_application_mapping_count->toBe(0)
        ->accepted_registry_mapping_count->toBe(0)
        ->production_rehearsal_authorized->toBeFalse()
        ->and($result['location_proposals'])->toHaveCount(5)
        ->and(collect($result['location_proposals'])->pluck('source_chain_status')->unique()->all())->toBe(['exact_hierarchy_resolved'])
        ->and($result['line_of_business_proposals'])->toHaveCount(5)
        ->and(collect($result['line_of_business_proposals'])->pluck('proposed_target_action')->unique()->all())->toBe(['create_target_from_exact_source_group_after_acceptance'])
        ->and(collect($result['exact_mapping_proposals'])->pluck('proposal_status')->unique()->all())->toBe(['evidence_complete_acceptance_pending'])
        ->and(LegacyRecord::query()->count())->toBe($beforeRecordCount)
        ->and(LegacyLineOfBusinessReconciliation::query()->count())->toBe(0)
        ->and(LegacyApplicationIdMapping::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0);

    $encoded = json_encode($result, JSON_THROW_ON_ERROR);
    expect($encoded)
        ->not->toContain('Private Province')
        ->not->toContain('Private City')
        ->not->toContain('Private Barangay')
        ->not->toContain('Private Line')
        ->not->toContain('application-private')
        ->not->toContain('owner-private')
        ->not->toContain('business-private');
});

test('cohort prerequisite characterization refuses a different frozen cohort fingerprint', function () {
    $plans = plannedCohortPrerequisites('fingerprint');

    expect(fn () => app(CharacterizeLegacyHistoricalFinancialCohortPrerequisites::class)->handle(
        $plans['financial'],
        $plans['registry'],
        str_repeat('0', 64),
    ))->toThrow(RuntimeException::class, 'recomputed cohort does not match');
});

test('command writes immutable payload-safe proposal artifacts without creating accepted state', function () {
    Storage::fake('local');
    $plans = plannedCohortPrerequisites('command');
    $arguments = [
        'financial-plan' => $plans['financial']->id,
        'registry-plan' => $plans['registry']->id,
        '--cohort-sha256' => $plans['cohort_sha256'],
        '--run-id' => 'cohort-prerequisites-command-001',
        '--json' => true,
    ];

    $this->artisan('legacy:characterize-historical-financial-cohort-prerequisites', $arguments)->assertSuccessful();
    $this->artisan('legacy:characterize-historical-financial-cohort-prerequisites', $arguments)->assertSuccessful();

    $root = "legacy-migrations/{$plans['financial']->importBatch->source->key}/{$plans['financial']->importBatch->run_reference}/reconciliation/historical-financial-application-mapping-prerequisites/cohort-prerequisites-command-001";
    foreach ([
        'summary.json',
        'proposed-location-crosswalks.jsonl',
        'proposed-line-of-business-targets.jsonl',
        'proposed-exact-application-mappings.jsonl',
        'review.md',
    ] as $artifact) {
        Storage::disk('local')->assertExists($root.'/'.$artifact);
    }

    $evidence = Storage::disk('local')->get($root.'/summary.json')
        .Storage::disk('local')->get($root.'/proposed-location-crosswalks.jsonl')
        .Storage::disk('local')->get($root.'/proposed-line-of-business-targets.jsonl')
        .Storage::disk('local')->get($root.'/proposed-exact-application-mappings.jsonl');
    expect($evidence)
        ->not->toContain('Private Province')
        ->not->toContain('Private City')
        ->not->toContain('Private Barangay')
        ->not->toContain('Private Line')
        ->not->toContain('application-private')
        ->not->toContain('owner-private')
        ->not->toContain('business-private')
        ->and(LegacyLineOfBusinessReconciliation::query()->count())->toBe(0)
        ->and(LegacyApplicationIdMapping::query()->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0);
});

test('board accepted five-record prerequisites create one frozen idempotent mapping set without operational finance', function () {
    $plans = plannedCohortPrerequisites('acceptance');
    $characterization = app(CharacterizeLegacyHistoricalFinancialCohortPrerequisites::class)->handle(
        $plans['financial'],
        $plans['registry'],
        $plans['cohort_sha256'],
    );
    $package = $characterization['report']['fingerprints']['prerequisite_proposals_sha256'];
    $action = app(AcceptLegacyHistoricalFinancialCohortMappings::class);

    $first = $action->handle(
        $plans['financial'],
        $plans['registry'],
        $plans['cohort_sha256'],
        $package,
        'five-record-acceptance-001',
        'Architecture Review Board',
        'BOARD-FIVE-RECORD-ACCEPTANCE',
    );
    $second = $action->handle(
        $plans['financial'],
        $plans['registry'],
        $plans['cohort_sha256'],
        $package,
        'five-record-acceptance-001',
        'Architecture Review Board',
        'BOARD-FIVE-RECORD-ACCEPTANCE',
    );

    expect($first->status)->toBe('frozen')
        ->and($first->accepted_mapping_set_sha256)->toMatch('/^[a-f0-9]{64}$/')
        ->and($second->id)->toBe($first->id)
        ->and(LegacyHistoricalFinancialMappingSet::query()->count())->toBe(1)
        ->and(LineOfBusiness::query()->count())->toBe(5)
        ->and(LegacyLineOfBusinessReconciliation::query()->where('status', 'accepted')->count())->toBe(5)
        ->and(LegacyIdMapping::query()->count())->toBe(10)
        ->and(LegacyApplicationIdMapping::query()->count())->toBe(5)
        ->and(PermitApplication::query()->count())->toBe(5)
        ->and(data_get($first->metadata, 'operational_counts_after'))->toBe([
            'assessments' => 0,
            'assessment_lines' => 0,
            'payment_schedules' => 0,
            'payment_schedule_lines' => 0,
            'treasury_collections' => 0,
            'receipts' => 0,
        ])
        ->and(data_get($first->manifest, 'safety.production_rehearsal_authorized'))->toBeFalse();
});

test('accepted cohort rejects divergent retry and rolls back incomplete prerequisite evidence', function () {
    $plans = plannedCohortPrerequisites('divergent');
    $characterization = app(CharacterizeLegacyHistoricalFinancialCohortPrerequisites::class)->handle($plans['financial'], $plans['registry'], $plans['cohort_sha256']);
    $package = $characterization['report']['fingerprints']['prerequisite_proposals_sha256'];
    $action = app(AcceptLegacyHistoricalFinancialCohortMappings::class);
    $action->handle($plans['financial'], $plans['registry'], $plans['cohort_sha256'], $package, 'five-record-divergent-001', 'Architecture Review Board', 'BOARD-DIVERGENT');

    expect(fn () => $action->handle($plans['financial'], $plans['registry'], $plans['cohort_sha256'], $package, 'five-record-divergent-002', 'Architecture Review Board', 'BOARD-DIVERGENT'))
        ->toThrow(RuntimeException::class, 'different acceptance decision');

    $freshPlans = plannedCohortPrerequisites('changed-source');
    $freshCharacterization = app(CharacterizeLegacyHistoricalFinancialCohortPrerequisites::class)->handle($freshPlans['financial'], $freshPlans['registry'], $freshPlans['cohort_sha256']);
    $barangay = $freshPlans['registry']->importBatch->records()->where('dataset_key', 'barangays')->firstOrFail();
    $barangay->update(['payload_hash' => str_repeat('0', 64)]);

    expect(fn () => $action->handle(
        $freshPlans['financial'],
        $freshPlans['registry'],
        $freshPlans['cohort_sha256'],
        $freshCharacterization['report']['fingerprints']['prerequisite_proposals_sha256'],
        'five-record-changed-source-001',
        'Architecture Review Board',
        'BOARD-CHANGED-SOURCE',
    ))->toThrow(RuntimeException::class);
});

test('frozen mapping audit refuses changed source or target evidence', function () {
    $plans = plannedCohortPrerequisites('audit-refusal');
    $characterization = app(CharacterizeLegacyHistoricalFinancialCohortPrerequisites::class)->handle($plans['financial'], $plans['registry'], $plans['cohort_sha256']);
    $mappingSet = app(AcceptLegacyHistoricalFinancialCohortMappings::class)->handle(
        $plans['financial'],
        $plans['registry'],
        $plans['cohort_sha256'],
        $characterization['report']['fingerprints']['prerequisite_proposals_sha256'],
        'five-record-audit-refusal-001',
        'Architecture Review Board',
        'BOARD-AUDIT-REFUSAL',
    );
    $targetId = (int) data_get($mappingSet->manifest, 'line_of_business_targets.0.target_id');
    LineOfBusiness::query()->findOrFail($targetId)->update(['is_active' => false]);

    expect(fn () => app(AcceptLegacyHistoricalFinancialCohortMappings::class)->audit($mappingSet))
        ->toThrow(RuntimeException::class, 'target or reconciliation has changed');
});

test('authorization packet freezes exact five-record totals and commands without executing preservation', function () {
    $plans = plannedCohortPrerequisites('packet');
    $characterization = app(CharacterizeLegacyHistoricalFinancialCohortPrerequisites::class)->handle($plans['financial'], $plans['registry'], $plans['cohort_sha256']);
    $mappingSet = app(AcceptLegacyHistoricalFinancialCohortMappings::class)->handle(
        $plans['financial'],
        $plans['registry'],
        $plans['cohort_sha256'],
        $characterization['report']['fingerprints']['prerequisite_proposals_sha256'],
        'five-record-packet-acceptance-001',
        'Architecture Review Board',
        'BOARD-PACKET',
    );

    $result = app(BuildLegacyHistoricalFinancialRehearsalAuthorizationPacket::class)->handle($mappingSet, 'five-record-packet-plan-001');
    $report = $result['report'];

    expect($report['recommendation'])->toBe('READY FOR FIVE-RECORD REHEARSAL AUTHORIZATION')
        ->and($report['applications'])->toHaveCount(5)
        ->and($report['expected_totals'])->toMatchArray([
            'historical_bundle_count' => 5,
            'schedule_count' => 5,
            'fee_line_count' => 5,
            'completed_payment_count' => 0,
            'unpaid_schedule_count' => 5,
            'scheduled_amount_cents' => 50_000,
            'fee_amount_cents' => 50_000,
            'paid_amount_cents' => 0,
            'payment_amount_cents' => 0,
        ])
        ->and($report['proposed_commands_not_executed']['execute'])->toContain('--execute --confirm-execute')
        ->and($report['proposed_commands_not_executed']['restoration_audit'])->toContain('--mapping-set='.$mappingSet->id)
        ->and(LegacyHistoricalFinancialPreservationExecution::query()->count())->toBe(0)
        ->and(LegacyHistoricalFinancialPreservedBundle::query()->count())->toBe(0);
});

test('synthetic five-record preservation audit and rollback restore the exact pre-rehearsal state', function () {
    $plans = plannedCohortPrerequisites('restoration');
    $characterization = app(CharacterizeLegacyHistoricalFinancialCohortPrerequisites::class)->handle($plans['financial'], $plans['registry'], $plans['cohort_sha256']);
    $mappingSet = app(AcceptLegacyHistoricalFinancialCohortMappings::class)->handle(
        $plans['financial'],
        $plans['registry'],
        $plans['cohort_sha256'],
        $characterization['report']['fingerprints']['prerequisite_proposals_sha256'],
        'five-record-restoration-acceptance-001',
        'Architecture Review Board',
        'BOARD-RESTORATION',
    );
    $packet = app(BuildLegacyHistoricalFinancialRehearsalAuthorizationPacket::class)->handle($mappingSet, 'five-record-restoration-plan-001');
    $proposalIds = $packet['plan']->proposals()->where('status', 'ready')->pluck('id')->all();
    $sourceCount = LegacyRecord::query()->count();
    $mappingCount = LegacyApplicationIdMapping::query()->count();

    $execution = app(ExecuteLegacyHistoricalFinancialPreservation::class)->handle(
        $packet['plan'],
        $proposalIds,
        'five-record-restoration-execute-001',
    );
    $audit = app(AuditLegacyHistoricalFinancialPreservation::class)->handle($execution);
    $rolledBack = app(RollbackLegacyHistoricalFinancialPreservation::class)->handle($execution);
    $restoration = app(AuditLegacyHistoricalFinancialPreservationRestoration::class)->handle($rolledBack, $mappingSet);

    expect($audit['passed'])->toBeTrue()
        ->and($audit['bundle_count'])->toBe(5)
        ->and($restoration['passed'])->toBeTrue()
        ->and($restoration['remaining_bundle_count'])->toBe(0)
        ->and($restoration['rollback_bundle_count'])->toBe(5)
        ->and(LegacyRecord::query()->count())->toBe($sourceCount)
        ->and(LegacyApplicationIdMapping::query()->count())->toBe($mappingCount)
        ->and(LegacyHistoricalFinancialPreservedBundle::query()->count())->toBe(0);
});

test('acceptance and authorization commands write immutable evidence and require explicit confirmation', function () {
    Storage::fake('local');
    $plans = plannedCohortPrerequisites('acceptance-command');
    $characterization = app(CharacterizeLegacyHistoricalFinancialCohortPrerequisites::class)->handle($plans['financial'], $plans['registry'], $plans['cohort_sha256']);
    $package = $characterization['report']['fingerprints']['prerequisite_proposals_sha256'];
    $arguments = [
        'financial-plan' => $plans['financial']->id,
        'registry-plan' => $plans['registry']->id,
        '--cohort-sha256' => $plans['cohort_sha256'],
        '--proposal-package-sha256' => $package,
        '--run-id' => 'five-record-command-001',
        '--authority' => 'Architecture Review Board',
        '--evidence' => 'BOARD-COMMAND',
        '--accept' => true,
        '--confirm-accept' => true,
        '--json' => true,
    ];

    $this->artisan('legacy:accept-historical-financial-cohort-mappings', $arguments)->assertSuccessful();
    $this->artisan('legacy:accept-historical-financial-cohort-mappings', $arguments)->assertSuccessful();
    $mappingSet = LegacyHistoricalFinancialMappingSet::query()->sole();
    $this->artisan('legacy:build-five-record-historical-preservation-authorization-packet', [
        'mapping-set' => $mappingSet->id,
        '--run-id' => 'five-record-command-packet-001',
        '--json' => true,
    ])->assertSuccessful();

    $batch = $plans['financial']->importBatch;
    Storage::disk('local')->assertExists("legacy-migrations/{$batch->source->key}/{$batch->run_reference}/reconciliation/historical-financial-application-mapping-acceptance/five-record-command-001/accepted-mapping-set.json");
    Storage::disk('local')->assertExists("legacy-migrations/{$batch->source->key}/{$batch->run_reference}/historical-financial-preservation-authorization/five-record-command-packet-001/authorization-packet.json");
    expect(LegacyHistoricalFinancialPreservationExecution::query()->count())->toBe(0);
});
