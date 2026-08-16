<?php

use App\Actions\ExecuteLegacyFinancialSnapshots;
use App\Actions\PlanLegacyFinancialDependencies;
use App\Actions\RollbackLegacyFinancialSnapshots;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\LegacyFeeRuleReconciliationStatus;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingExecutionStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\Assessment;
use App\Models\FeeRule;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyApplicationMappingProposal;
use App\Models\LegacyFeeRuleReconciliation;
use App\Models\LegacyFinancialMappingExecution;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyFinancialSnapshotMapping;
use App\Models\LegacyImportBatch;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Illuminate\Support\Facades\Storage;

/** @return array{source: LegacySource, batch: LegacyImportBatch, application: LegacyRecord, target: PermitApplication, feeRule: FeeRule, schedule: LegacyRecord} */
function executableFinancialSnapshot(string $suffix, FeeRuleScope $scope = FeeRuleScope::Application): array
{
    $source = LegacySource::factory()->create(['key' => 'LEGACY-FINANCIAL-EXECUTION-'.$suffix]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'financial-execution-staging-'.$suffix,
        'status' => LegacyImportBatchStatus::Staged,
    ]);
    $application = financialExecutionRecord($batch, 'business_permit_applications', 'application-'.$suffix, [
        'modeOfPayment' => 'Annually',
        'totalFees' => 350,
        'linesOfBusiness' => [],
    ]);
    $target = PermitApplication::factory()->create();
    $applicationPlan = LegacyApplicationMappingPlan::factory()->for($batch, 'importBatch')->create([
        'run_reference' => 'financial-execution-application-plan-'.$suffix,
        'proposal_count' => 1,
        'ready_count' => 1,
    ]);
    LegacyApplicationMappingProposal::factory()->for($applicationPlan, 'mappingPlan')->for($application, 'legacyRecord')->create([
        'status' => LegacyMappingProposalStatus::Ready,
    ]);
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
    $feeRule = FeeRule::factory()->create([
        'category' => FeeRuleCategory::Tax,
        'scope' => $scope,
        'line_of_business_id' => null,
    ]);
    LegacyFeeRuleReconciliation::query()->create([
        'legacy_source_id' => $source->id,
        'source_dataset' => 'fees',
        'source_legacy_id' => 'fee-'.$suffix,
        'fee_rule_id' => $feeRule->id,
        'status' => LegacyFeeRuleReconciliationStatus::Accepted,
        'decision_authority' => 'Municipal reconciliation fixture',
        'evidence_reference' => 'TEST-FINANCIAL-SNAPSHOT-EXECUTION',
        'decided_at' => now(),
    ]);
    $schedule = financialExecutionRecord($batch, 'payment_schedules', 'schedule-'.$suffix, [
        'applicationId' => $application->legacy_id,
        'sectionNumber' => 1,
        'dueDate' => '2026-01-20',
        'status' => 'pending',
        'fees' => [[
            'feeId' => 'fee-'.$suffix,
            'feeName' => 'Sensitive historical fee label',
            'feeCategory' => 'Tax',
            'originalAmount' => 350,
            'sectionAmount' => 350,
        ]],
        'surcharge' => 0,
        'penalty' => 0,
        'totalAmount' => 350,
        'paidAmount' => 0,
        'createdAt' => '2026-01-10T09:00:00+08:00',
    ]);

    return compact('source', 'batch', 'application', 'target', 'feeRule', 'schedule');
}

/** @param array<string, mixed> $payload */
function financialExecutionRecord(LegacyImportBatch $batch, string $dataset, string $legacyId, array $payload): LegacyRecord
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
 * @param  array{batch: LegacyImportBatch, schedule: LegacyRecord}  $fixture
 * @return array{plan: LegacyFinancialMappingPlan, proposalIds: list<int>}
 */
function executableFinancialPlan(array $fixture, string $runReference): array
{
    $plan = app(PlanLegacyFinancialDependencies::class)->handle($fixture['batch'], $runReference);
    $proposalIds = array_values($plan->proposals()
        ->whereIn('kind', ['payment_schedule', 'payment_schedule_fee'])
        ->where('legacy_record_id', $fixture['schedule']->id)
        ->orderBy('id')
        ->pluck('id')
        ->map(fn (mixed $id): int => (int) $id)
        ->values()
        ->all());

    return compact('plan', 'proposalIds');
}

test('ready annual snapshot executes exact persisted amounts without calculations collections or lifecycle mutation', function () {
    $fixture = executableFinancialSnapshot('happy');
    $applicationStatus = $fixture['target']->status;
    $selection = executableFinancialPlan($fixture, 'financial-plan-execution-happy');

    $execution = app(ExecuteLegacyFinancialSnapshots::class)->handle(
        $selection['plan'],
        $selection['proposalIds'],
        'financial-execution-happy',
    );

    $assessment = Assessment::query()->with('lines')->sole();
    $schedule = PaymentSchedule::query()->with('lines')->sole();
    $mapping = LegacyFinancialSnapshotMapping::query()->sole();

    expect($execution->status)->toBe(LegacyMappingExecutionStatus::Completed)
        ->and($execution->created_count)->toBe(1)
        ->and($execution->mapping_count)->toBe(1)
        ->and($assessment->permit_application_id)->toBe($fixture['target']->id)
        ->and($assessment->total_amount_cents)->toBe(35_000)
        ->and($assessment->lines)->toHaveCount(1)
        ->and($assessment->lines->sole()->amount_cents)->toBe(35_000)
        ->and($assessment->lines->sole()->basis)->toBe('historical_persisted_amount')
        ->and($assessment->source_snapshot['liability_recalculated'])->toBeFalse()
        ->and($schedule->assessment_id)->toBe($assessment->id)
        ->and($schedule->total_amount_cents)->toBe(35_000)
        ->and($schedule->paid_amount_cents)->toBe(0)
        ->and($schedule->payment_mode)->toBe('annual')
        ->and($schedule->lines)->toHaveCount(1)
        ->and($mapping->assessment_id)->toBe($assessment->id)
        ->and($mapping->payment_schedule_id)->toBe($schedule->id)
        ->and($fixture['target']->refresh()->status)->toBe($applicationStatus)
        ->and(TreasuryCollection::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0);
});

test('stable retry reuses one execution and never duplicates financial records', function () {
    $fixture = executableFinancialSnapshot('idempotent');
    $selection = executableFinancialPlan($fixture, 'financial-plan-execution-idempotent');
    $action = app(ExecuteLegacyFinancialSnapshots::class);

    $first = $action->handle($selection['plan'], $selection['proposalIds'], 'financial-execution-idempotent');
    $second = $action->handle($selection['plan'], array_reverse($selection['proposalIds']), 'financial-execution-idempotent');

    expect($second->id)->toBe($first->id)
        ->and(LegacyFinancialMappingExecution::query()->count())->toBe(1)
        ->and(LegacyFinancialSnapshotMapping::query()->count())->toBe(1)
        ->and(Assessment::query()->count())->toBe(1)
        ->and(PaymentSchedule::query()->count())->toBe(1);

    expect(fn () => $action->handle($selection['plan'], [$selection['proposalIds'][0]], 'financial-execution-idempotent'))
        ->toThrow(RuntimeException::class, 'different proposal selection');
});

test('a later accepted fee reconciliation cannot reuse a snapshot created under the previous projection', function () {
    $fixture = executableFinancialSnapshot('reconciled-drift');
    $selection = executableFinancialPlan($fixture, 'financial-plan-execution-reconciled-drift-1');
    app(ExecuteLegacyFinancialSnapshots::class)->handle($selection['plan'], $selection['proposalIds'], 'financial-execution-reconciled-drift-1');

    $replacement = FeeRule::factory()->create([
        'category' => FeeRuleCategory::Tax,
        'scope' => FeeRuleScope::Application,
        'code' => 'RECONCILED-REPLACEMENT',
    ]);
    LegacyFeeRuleReconciliation::query()
        ->where('legacy_source_id', $fixture['source']->id)
        ->where('source_legacy_id', 'fee-reconciled-drift')
        ->update(['fee_rule_id' => $replacement->id]);
    $newSelection = executableFinancialPlan($fixture, 'financial-plan-execution-reconciled-drift-2');

    expect(fn () => app(ExecuteLegacyFinancialSnapshots::class)->handle(
        $newSelection['plan'],
        $newSelection['proposalIds'],
        'financial-execution-reconciled-drift-2',
    ))->toThrow(RuntimeException::class, 'no longer matches its authoritative targets');

    expect(Assessment::query()->count())->toBe(1)
        ->and(PaymentSchedule::query()->count())->toBe(1)
        ->and(LegacyFinancialSnapshotMapping::query()->count())->toBe(1);
});

test('executor refuses incomplete sets installments payment evidence line scoped fees and unmanaged targets', function () {
    $incomplete = executableFinancialSnapshot('incomplete');
    $incompleteSelection = executableFinancialPlan($incomplete, 'financial-plan-execution-incomplete');
    expect(fn () => app(ExecuteLegacyFinancialSnapshots::class)->handle(
        $incompleteSelection['plan'],
        [$incompleteSelection['proposalIds'][0]],
        'financial-execution-incomplete',
    ))->toThrow(RuntimeException::class, 'complete schedule proposal set');

    $installment = executableFinancialSnapshot('installment');
    financialExecutionRecord($installment['batch'], 'payment_schedules', 'schedule-installment-2', [
        'applicationId' => $installment['application']->legacy_id,
        'sectionNumber' => 2,
        'dueDate' => '2026-07-20',
        'status' => 'pending',
        'fees' => [],
        'surcharge' => 0,
        'penalty' => 0,
        'totalAmount' => 0,
        'paidAmount' => 0,
    ]);
    $installmentSelection = executableFinancialPlan($installment, 'financial-plan-execution-installment');
    expect(fn () => app(ExecuteLegacyFinancialSnapshots::class)->handle(
        $installmentSelection['plan'],
        $installmentSelection['proposalIds'],
        'financial-execution-installment',
    ))->toThrow(RuntimeException::class, 'exactly one annual schedule section');

    $payment = executableFinancialSnapshot('payment');
    financialExecutionRecord($payment['batch'], 'payments', 'payment-financial-execution', [
        'applicationId' => $payment['application']->legacy_id,
        'scheduleId' => $payment['schedule']->legacy_id,
        'amount' => 10,
        'paymentMethod' => 'Cash',
        'status' => 'pending',
        'paidAt' => '2026-01-11T09:00:00+08:00',
        'processedBy' => 'legacy-operator',
    ]);
    $paymentSelection = executableFinancialPlan($payment, 'financial-plan-execution-payment');
    expect(fn () => app(ExecuteLegacyFinancialSnapshots::class)->handle(
        $paymentSelection['plan'],
        $paymentSelection['proposalIds'],
        'financial-execution-payment',
    ))->toThrow(RuntimeException::class, 'has payment evidence');

    $lineScoped = executableFinancialSnapshot('line-scope', FeeRuleScope::LineOfBusiness);
    $lineSelection = executableFinancialPlan($lineScoped, 'financial-plan-execution-line-scope');
    expect(fn () => app(ExecuteLegacyFinancialSnapshots::class)->handle(
        $lineSelection['plan'],
        $lineSelection['proposalIds'],
        'financial-execution-line-scope',
    ))->toThrow(RuntimeException::class, 'application-scoped historical amount');

    $unmanaged = executableFinancialSnapshot('unmanaged');
    Assessment::factory()->for($unmanaged['target'], 'permitApplication')->create();
    $unmanagedSelection = executableFinancialPlan($unmanaged, 'financial-plan-execution-unmanaged');
    expect(fn () => app(ExecuteLegacyFinancialSnapshots::class)->handle(
        $unmanagedSelection['plan'],
        $unmanagedSelection['proposalIds'],
        'financial-execution-unmanaged',
    ))->toThrow(RuntimeException::class, 'unmanaged existing financial records');
});

test('rollback removes unchanged execution-created snapshots and refuses changed or collected targets', function () {
    $fixture = executableFinancialSnapshot('rollback');
    $selection = executableFinancialPlan($fixture, 'financial-plan-execution-rollback');
    $execution = app(ExecuteLegacyFinancialSnapshots::class)->handle($selection['plan'], $selection['proposalIds'], 'financial-execution-rollback');

    $rolledBack = app(RollbackLegacyFinancialSnapshots::class)->handle($execution);
    expect($rolledBack->status)->toBe(LegacyMappingExecutionStatus::RolledBack)
        ->and(LegacyFinancialSnapshotMapping::query()->count())->toBe(0)
        ->and(Assessment::query()->count())->toBe(0)
        ->and(PaymentSchedule::query()->count())->toBe(0);

    $changed = executableFinancialSnapshot('rollback-changed');
    $changedSelection = executableFinancialPlan($changed, 'financial-plan-execution-rollback-changed');
    $changedExecution = app(ExecuteLegacyFinancialSnapshots::class)->handle($changedSelection['plan'], $changedSelection['proposalIds'], 'financial-execution-rollback-changed');
    $changedExecution->mappings()->sole()->assessment()->update(['total_amount_cents' => 1]);
    expect(fn () => app(RollbackLegacyFinancialSnapshots::class)->handle($changedExecution))
        ->toThrow(RuntimeException::class, 'changed after migration');

    $collected = executableFinancialSnapshot('rollback-collected');
    $collectedSelection = executableFinancialPlan($collected, 'financial-plan-execution-rollback-collected');
    $collectedExecution = app(ExecuteLegacyFinancialSnapshots::class)->handle($collectedSelection['plan'], $collectedSelection['proposalIds'], 'financial-execution-rollback-collected');
    $collectedMapping = $collectedExecution->mappings()->with(['assessment', 'paymentSchedule'])->sole();
    TreasuryCollection::factory()->create([
        'payment_schedule_id' => $collectedMapping->payment_schedule_id,
        'permit_application_id' => $collectedMapping->paymentSchedule->permit_application_id,
        'assessment_id' => $collectedMapping->assessment_id,
    ]);
    expect(fn () => app(RollbackLegacyFinancialSnapshots::class)->handle($collectedExecution))
        ->toThrow(RuntimeException::class, 'has collection dependencies');
});

test('commands require dual confirmation and write redacted private execution and rollback evidence', function () {
    Storage::fake('local');
    $fixture = executableFinancialSnapshot('command');
    $selection = executableFinancialPlan($fixture, 'financial-plan-execution-command');
    $parameters = [
        'plan' => $selection['plan']->id,
        '--proposal' => $selection['proposalIds'],
        '--run-id' => 'financial-execution-command',
        '--json' => true,
    ];

    $this->artisan('legacy:execute-financial-snapshots', $parameters)
        ->expectsOutputToContain('Both --execute and --confirm-execute are required')
        ->assertFailed();
    $this->artisan('legacy:execute-financial-snapshots', [
        ...$parameters,
        '--execute' => true,
        '--confirm-execute' => true,
    ])->assertSuccessful();

    $execution = LegacyFinancialMappingExecution::query()->sole();
    $root = 'legacy-migrations/LEGACY-FINANCIAL-EXECUTION-command/financial-execution-staging-command/financial-mapping-plans/financial-plan-execution-command/executions/financial-execution-command';
    Storage::disk('local')->assertExists($root.'/execution.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $report = Storage::disk('local')->get($root.'/execution.json');
    expect($report)->not->toContain('schedule-command', 'Sensitive historical fee label')
        ->and(json_decode($report, true, 512, JSON_THROW_ON_ERROR)['safety'])->toMatchArray([
            'annual_single_section_only' => true,
            'liability_calculations' => false,
            'collections_created' => false,
            'receipts_created' => false,
            'raw_legacy_ids_in_report' => false,
        ]);

    $this->artisan('legacy:rollback-financial-snapshots', [
        'execution' => $execution->id,
        '--rollback' => true,
        '--confirm-rollback' => true,
        '--json' => true,
    ])->assertSuccessful();
    Storage::disk('local')->assertExists($root.'/rollback.json');
});
