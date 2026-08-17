<?php

use App\Actions\AuditLegacyHistoricalFinancialPreservation;
use App\Actions\ExecuteLegacyHistoricalFinancialPreservation;
use App\Actions\PlanLegacyFinancialDependencies;
use App\Actions\PlanLegacyHistoricalFinancialPreservation;
use App\Actions\RollbackLegacyHistoricalFinancialPreservation;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingExecutionStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\Assessment;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyApplicationMappingProposal;
use App\Models\LegacyHistoricalFinancialPreservationExecution;
use App\Models\LegacyHistoricalFinancialPreservationPlan;
use App\Models\LegacyHistoricalFinancialPreservedBundle;
use App\Models\LegacyImportBatch;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** @return array{batch: LegacyImportBatch, application: LegacyRecord, target: PermitApplication} */
function historicalPreservationFixture(string $suffix, bool $withMapping = true): array
{
    $source = LegacySource::factory()->create(['key' => 'HISTORICAL-PRESERVATION-'.$suffix]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'historical-preservation-staging-'.$suffix,
        'status' => LegacyImportBatchStatus::Staged,
    ]);
    $application = historicalPreservationRecord($batch, 'business_permit_applications', 'application-'.$suffix, [
        'modeOfPayment' => 'Semi-Annually',
        'totalFees' => 150,
        'linesOfBusiness' => [],
    ]);
    $target = PermitApplication::factory()->create();
    $applicationPlan = LegacyApplicationMappingPlan::factory()->for($batch, 'importBatch')->create([
        'run_reference' => 'historical-preservation-application-plan-'.$suffix,
        'proposal_count' => 1,
        'ready_count' => 1,
    ]);
    LegacyApplicationMappingProposal::factory()->for($applicationPlan, 'mappingPlan')->for($application, 'legacyRecord')->create([
        'status' => LegacyMappingProposalStatus::Ready,
    ]);
    if ($withMapping) {
        LegacyApplicationIdMapping::query()->create([
            'legacy_source_id' => $source->id,
            'legacy_import_batch_id' => $batch->id,
            'permit_application_id' => $target->id,
            'dataset_key' => $application->dataset_key,
            'legacy_id' => $application->legacy_id,
            'status' => 'mapped',
            'mapping_basis' => 'approved_create_proposal',
            'metadata' => [],
        ]);
    }

    $paidSchedule = historicalPreservationRecord($batch, 'payment_schedules', 'schedule-paid-'.$suffix, [
        'applicationId' => $application->legacy_id,
        'sectionNumber' => 1,
        'dueDate' => '2026-01-20',
        'status' => 'paid',
        'fees' => [
            ['feeName' => 'Unidentified historical line A', 'feeCategory' => 'Fee', 'originalAmount' => 60, 'sectionAmount' => 60],
            ['feeName' => 'Unidentified historical line B', 'feeCategory' => 'Fee', 'originalAmount' => 40, 'sectionAmount' => 40],
        ],
        'surcharge' => 0,
        'penalty' => 0,
        'totalAmount' => 100,
        'paidAmount' => 100,
    ]);
    historicalPreservationRecord($batch, 'payment_schedules', 'schedule-pending-'.$suffix, [
        'applicationId' => $application->legacy_id,
        'sectionNumber' => 2,
        'dueDate' => '2026-07-20',
        'status' => 'pending',
        'fees' => [
            ['feeName' => 'Unidentified historical line C', 'feeCategory' => 'Fee', 'originalAmount' => 100, 'sectionAmount' => 50],
        ],
        'surcharge' => 0,
        'penalty' => 0,
        'totalAmount' => 50,
        'paidAmount' => 0,
    ]);
    historicalPreservationRecord($batch, 'payments', 'payment-'.$suffix, [
        'applicationId' => $application->legacy_id,
        'scheduleId' => $paidSchedule->legacy_id,
        'transactionNumber' => 'SENSITIVE-'.$suffix,
        'amount' => 100,
        'paymentMethod' => 'Cash',
        'status' => 'completed',
        'referenceNumber' => null,
        'receiptNumber' => null,
        'paidAt' => '2026-01-20T10:30:00+08:00',
        'processedBy' => 'historical-operator-'.$suffix,
    ]);

    return compact('batch', 'application', 'target');
}

/** @param array<string, mixed> $payload */
function historicalPreservationRecord(LegacyImportBatch $batch, string $dataset, string $legacyId, array $payload): LegacyRecord
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

/** @return array{plan: LegacyHistoricalFinancialPreservationPlan, proposalId: int} */
function historicalPreservationPlan(array $fixture, string $suffix): array
{
    $financialPlan = app(PlanLegacyFinancialDependencies::class)->handle($fixture['batch'], 'financial-plan-'.$suffix);
    $plan = app(PlanLegacyHistoricalFinancialPreservation::class)->handle($financialPlan, 'preservation-plan-'.$suffix);

    return ['plan' => $plan, 'proposalId' => $plan->proposals()->sole()->id];
}

test('complete historical application history is preserved atomically without operational financial writes', function () {
    $fixture = historicalPreservationFixture('happy');
    $selection = historicalPreservationPlan($fixture, 'happy');
    $status = $fixture['target']->status;

    $execution = app(ExecuteLegacyHistoricalFinancialPreservation::class)->handle($selection['plan'], [$selection['proposalId']], 'preservation-execution-happy');
    $bundle = LegacyHistoricalFinancialPreservedBundle::query()->sole();
    $totals = $bundle->snapshot['financial_history']['totals'];

    expect($execution->status)->toBe(LegacyMappingExecutionStatus::Completed)
        ->and($execution->created_count)->toBe(1)
        ->and($bundle->permit_application_id)->toBe($fixture['target']->id)
        ->and($bundle->snapshot['provenance'])->toMatchArray([
            'fee_policy_provenance' => 'incomplete',
            'future_policy_executable' => false,
            'operational_financial_record' => false,
            'liability_recalculated' => false,
            'fee_identity_inferred' => false,
        ])
        ->and($totals)->toMatchArray([
            'schedule_count' => 2,
            'fee_line_count' => 3,
            'payment_count' => 1,
            'scheduled_amount_cents' => 15_000,
            'fee_amount_cents' => 15_000,
            'paid_amount_cents' => 10_000,
            'payment_amount_cents' => 10_000,
        ])
        ->and(collect($bundle->snapshot['financial_history']['schedules'])->flatMap(fn (array $schedule): array => $schedule['fee_lines'])->every(fn (array $line): bool => $line['fee_rule_id'] === null))->toBeTrue()
        ->and(Assessment::query()->count())->toBe(0)
        ->and(PaymentSchedule::query()->count())->toBe(0)
        ->and(TreasuryCollection::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0)
        ->and($fixture['target']->refresh()->status)->toBe($status);
});

test('audit reproduces source counts and centavo totals and stable retry does not duplicate bundles', function () {
    $fixture = historicalPreservationFixture('audit');
    $selection = historicalPreservationPlan($fixture, 'audit');
    $action = app(ExecuteLegacyHistoricalFinancialPreservation::class);
    $first = $action->handle($selection['plan'], [$selection['proposalId']], 'preservation-execution-audit');
    $second = $action->handle($selection['plan'], [$selection['proposalId']], 'preservation-execution-audit');
    $audit = app(AuditLegacyHistoricalFinancialPreservation::class)->handle($first);

    expect($second->id)->toBe($first->id)
        ->and(LegacyHistoricalFinancialPreservationExecution::query()->count())->toBe(1)
        ->and(LegacyHistoricalFinancialPreservedBundle::query()->count())->toBe(1)
        ->and($audit['passed'])->toBeTrue()
        ->and($audit['source_totals'])->toBe($audit['target_totals'])
        ->and($audit['source_totals'])->toMatchArray(['applications' => 1, 'schedules' => 2, 'fee_lines' => 3, 'payments' => 1])
        ->and($audit['operational_financial_counts_unchanged'])->toBeTrue();
});

test('rollback restores exact pre rehearsal bundle state and preserves source mapping and operational records', function () {
    $fixture = historicalPreservationFixture('rollback');
    $selection = historicalPreservationPlan($fixture, 'rollback');
    $execution = app(ExecuteLegacyHistoricalFinancialPreservation::class)->handle($selection['plan'], [$selection['proposalId']], 'preservation-execution-rollback');
    $sourceCount = LegacyRecord::query()->count();
    $mappingCount = LegacyApplicationIdMapping::query()->count();

    $rolledBack = app(RollbackLegacyHistoricalFinancialPreservation::class)->handle($execution);

    expect($rolledBack->status)->toBe(LegacyMappingExecutionStatus::RolledBack)
        ->and(LegacyHistoricalFinancialPreservedBundle::query()->count())->toBe(0)
        ->and(LegacyRecord::query()->count())->toBe($sourceCount)
        ->and(LegacyApplicationIdMapping::query()->count())->toBe($mappingCount)
        ->and(Assessment::query()->count())->toBe(0)
        ->and(PaymentSchedule::query()->count())->toBe(0);
});

test('planner blocks complete history without an accepted exact application mapping', function () {
    $fixture = historicalPreservationFixture('missing-mapping', false);
    $financialPlan = app(PlanLegacyFinancialDependencies::class)->handle($fixture['batch'], 'financial-plan-missing-mapping');
    $plan = app(PlanLegacyHistoricalFinancialPreservation::class)->handle($financialPlan, 'preservation-plan-missing-mapping');
    $proposal = $plan->proposals()->sole();

    expect($proposal->status)->toBe(LegacyMappingProposalStatus::Blocked)
        ->and($proposal->reasons)->toBe(['accepted_application_mapping_required']);
});

test('planner blocks partial histories edited fee lines and late charge evidence', function () {
    $fixture = historicalPreservationFixture('unsafe');
    $schedule = $fixture['batch']->records()->where('dataset_key', 'payment_schedules')->where('legacy_id', 'schedule-pending-unsafe')->sole();
    $payload = [
        ...$schedule->payload,
        'status' => 'partial',
        'paidAmount' => 10,
        'surcharge' => 5,
        'totalAmount' => 55,
        'fees' => [[...$schedule->payload['fees'][0], 'isEdited' => true]],
    ];
    $schedule->update(['payload' => $payload, 'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR))]);
    $financialPlan = app(PlanLegacyFinancialDependencies::class)->handle($fixture['batch'], 'financial-plan-unsafe');
    $plan = app(PlanLegacyHistoricalFinancialPreservation::class)->handle($financialPlan, 'preservation-plan-unsafe');
    $proposal = $plan->proposals()->sole();

    expect($proposal->status)->toBe(LegacyMappingProposalStatus::Blocked)
        ->and($proposal->reasons)->toContain('schedule_status_not_preservable_v1', 'late_charge_history_not_preservable_v1');
});

test('rollback refuses changed or reviewed preserved evidence', function () {
    $fixture = historicalPreservationFixture('rollback-refusal');
    $selection = historicalPreservationPlan($fixture, 'rollback-refusal');
    $execution = app(ExecuteLegacyHistoricalFinancialPreservation::class)->handle($selection['plan'], [$selection['proposalId']], 'preservation-execution-rollback-refusal');
    $bundle = LegacyHistoricalFinancialPreservedBundle::query()->sole();
    $bundle->update(['metadata' => [...$bundle->metadata, 'reviewer_disposition' => 'accepted']]);

    expect(fn () => app(RollbackLegacyHistoricalFinancialPreservation::class)->handle($execution))
        ->toThrow(RuntimeException::class, 'has review or downstream evidence');
});

test('preserved source projection and identity are immutable while reviewer metadata remains attachable', function () {
    $fixture = historicalPreservationFixture('immutable');
    $selection = historicalPreservationPlan($fixture, 'immutable');
    app(ExecuteLegacyHistoricalFinancialPreservation::class)->handle($selection['plan'], [$selection['proposalId']], 'preservation-execution-immutable');
    $bundle = LegacyHistoricalFinancialPreservedBundle::query()->sole();

    expect(fn () => $bundle->update(['snapshot' => [...$bundle->snapshot, 'tampered' => true]]))
        ->toThrow(RuntimeException::class, 'bundle evidence is immutable');

    $bundle->refresh()->update(['metadata' => [...$bundle->metadata, 'reviewer_disposition' => 'pending']]);
    expect($bundle->refresh()->metadata['reviewer_disposition'])->toBe('pending');
});

test('preservation storage has no operational financial foreign keys or model relations', function () {
    $columns = Schema::getColumnListing('legacy_historical_financial_preserved_bundles');

    expect($columns)->not->toContain('assessment_id', 'assessment_line_id', 'payment_schedule_id', 'payment_schedule_line_id', 'treasury_collection_id', 'receipt_id')
        ->and(method_exists(LegacyHistoricalFinancialPreservedBundle::class, 'assessment'))->toBeFalse()
        ->and(method_exists(LegacyHistoricalFinancialPreservedBundle::class, 'paymentSchedule'))->toBeFalse()
        ->and(method_exists(LegacyHistoricalFinancialPreservedBundle::class, 'treasuryCollection'))->toBeFalse()
        ->and(method_exists(LegacyHistoricalFinancialPreservedBundle::class, 'receipt'))->toBeFalse();
});

test('execution refuses production environments', function () {
    $fixture = historicalPreservationFixture('production-refusal');
    $selection = historicalPreservationPlan($fixture, 'production-refusal');
    $environment = app()->environment();
    app()->detectEnvironment(fn (): string => 'production');

    try {
        expect(fn () => app(ExecuteLegacyHistoricalFinancialPreservation::class)->handle($selection['plan'], [$selection['proposalId']], 'preservation-execution-production-refusal'))
            ->toThrow(RuntimeException::class, 'restricted to local and testing environments')
            ->and(LegacyHistoricalFinancialPreservedBundle::query()->count())->toBe(0);
    } finally {
        app()->detectEnvironment(fn (): string => $environment);
    }
});

test('commands require dual confirmation and write payload safe audit evidence', function () {
    Storage::fake('local');
    $fixture = historicalPreservationFixture('command');
    $selection = historicalPreservationPlan($fixture, 'command');

    $this->artisan('legacy:execute-historical-financial-preservation', [
        'plan' => $selection['plan']->id,
        '--proposal' => [$selection['proposalId']],
        '--run-id' => 'preservation-execution-command',
    ])->expectsOutput('Both --execute and --confirm-execute are required for historical preservation writes.')->assertFailed();

    $this->artisan('legacy:execute-historical-financial-preservation', [
        'plan' => $selection['plan']->id,
        '--proposal' => [$selection['proposalId']],
        '--run-id' => 'preservation-execution-command',
        '--execute' => true,
        '--confirm-execute' => true,
        '--json' => true,
    ])->assertSuccessful();

    $execution = LegacyHistoricalFinancialPreservationExecution::query()->sole();
    $this->artisan('legacy:audit-historical-financial-preservation', ['execution' => $execution->id, '--json' => true])->assertSuccessful();
    $root = 'legacy-migrations/HISTORICAL-PRESERVATION-command/historical-preservation-staging-command/historical-financial-preservation/preservation-plan-command/executions/preservation-execution-command';
    Storage::disk('local')->assertExists($root.'/execution.json');
    Storage::disk('local')->assertExists($root.'/audit.json');
    expect(Storage::disk('local')->get($root.'/execution.json').Storage::disk('local')->get($root.'/audit.json'))
        ->not->toContain('SENSITIVE-command', 'historical-operator-command', 'Unidentified historical line');
});
