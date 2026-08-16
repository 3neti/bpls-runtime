<?php

use App\Actions\PlanLegacyFinancialDependencies;
use App\Enums\FeeRuleCategory;
use App\Enums\LegacyFeeRuleReconciliationStatus;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\Assessment;
use App\Models\FeeRule;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyApplicationMappingProposal;
use App\Models\LegacyFeeRuleReconciliation;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyFinancialMappingProposal;
use App\Models\LegacyImportBatch;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\PaymentSchedule;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Illuminate\Support\Facades\Storage;

/** @return array{source: LegacySource, batch: LegacyImportBatch} */
function financialBatch(string $suffix): array
{
    $source = LegacySource::factory()->create(['key' => 'LEGACY-FINANCIAL-'.$suffix]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'financial-staging-'.$suffix,
        'status' => LegacyImportBatchStatus::Staged,
    ]);

    return compact('source', 'batch');
}

/** @param array<string, mixed> $payload */
function financialRecord(LegacyImportBatch $batch, string $dataset, string $legacyId, array $payload): LegacyRecord
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

function readyFinancialApplicationMapping(LegacyImportBatch $batch, LegacyRecord $application): void
{
    $plan = LegacyApplicationMappingPlan::factory()->for($batch, 'importBatch')->create([
        'run_reference' => 'application-plan-'.$application->legacy_id,
        'proposal_count' => 1,
        'ready_count' => 1,
    ]);
    LegacyApplicationMappingProposal::factory()->for($plan, 'mappingPlan')->for($application, 'legacyRecord')->create([
        'status' => LegacyMappingProposalStatus::Ready,
    ]);
}

function acceptLegacyFee(LegacySource $source, string $legacyFeeId, FeeRule $feeRule): LegacyFeeRuleReconciliation
{
    return LegacyFeeRuleReconciliation::query()->create([
        'legacy_source_id' => $source->id,
        'source_dataset' => 'fees',
        'source_legacy_id' => $legacyFeeId,
        'fee_rule_id' => $feeRule->id,
        'status' => LegacyFeeRuleReconciliationStatus::Accepted,
        'decision_authority' => 'Municipal reconciliation fixture',
        'evidence_reference' => 'TEST-FINANCIAL-RECONCILIATION',
        'decided_at' => now(),
    ]);
}

/** @return array{application: LegacyRecord, schedule: LegacyRecord, feeRule: FeeRule} */
function cleanFinancialScheduleFixture(string $suffix, string $status = 'pending', float|int|string $paidAmount = 0): array
{
    $fixture = financialBatch($suffix);
    $application = financialRecord($fixture['batch'], 'business_permit_applications', 'application-'.$suffix, [
        'linesOfBusiness' => [],
    ]);
    readyFinancialApplicationMapping($fixture['batch'], $application);
    $feeRule = FeeRule::factory()->create(['category' => FeeRuleCategory::Tax]);
    acceptLegacyFee($fixture['source'], 'fee-'.$suffix, $feeRule);
    $schedule = financialRecord($fixture['batch'], 'payment_schedules', 'schedule-'.$suffix, [
        'applicationId' => $application->legacy_id,
        'sectionNumber' => 1,
        'dueDate' => '2026-01-20',
        'status' => $status,
        'fees' => [[
            'feeId' => 'fee-'.$suffix,
            'feeName' => 'Sensitive Fee Name',
            'feeCategory' => 'Tax',
            'originalAmount' => 350.00,
            'sectionAmount' => 350.00,
        ]],
        'surcharge' => 0,
        'penalty' => 0,
        'totalAmount' => 350.00,
        'paidAmount' => $paidAmount,
    ]);

    return compact('application', 'schedule', 'feeRule') + $fixture;
}

test('accepted fee identity and exact pending schedule produce structurally ready proposals without financial writes', function () {
    $fixture = cleanFinancialScheduleFixture('ready');

    $plan = app(PlanLegacyFinancialDependencies::class)->handle($fixture['batch'], 'financial-plan-ready-001');
    $proposals = $plan->proposals->keyBy('kind');

    expect($plan)->status->toBe(LegacyMappingPlanStatus::Planned)
        ->proposal_count->toBe(2)
        ->ready_count->toBe(2)
        ->and($proposals['payment_schedule']->status)->toBe(LegacyMappingProposalStatus::Ready)
        ->and($proposals['payment_schedule']->metadata['total_amount_cents'])->toBe(35_000)
        ->and($proposals['payment_schedule_fee']->fee_rule_id)->toBe($fixture['feeRule']->id)
        ->and($proposals['payment_schedule_fee']->metadata['section_amount_cents'])->toBe(35_000)
        ->and($proposals['payment_schedule_fee']->metadata['execution_authorized'])->toBeFalse()
        ->and(Assessment::query()->count())->toBe(0)
        ->and(PaymentSchedule::query()->count())->toBe(0)
        ->and(TreasuryCollection::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0);
});

test('legacy overrides exclusions and variables remain reviewable and never match fees by name', function () {
    $fixture = financialBatch('exceptions');
    $feeRule = FeeRule::factory()->create(['name' => 'Legacy Matching Name']);
    acceptLegacyFee($fixture['source'], 'accepted-fee', $feeRule);
    financialRecord($fixture['batch'], 'business_permit_applications', 'application-exceptions', [
        'feeOverrides' => [[
            'feeId' => 'accepted-fee', 'feeName' => 'Different Legacy Name', 'originalAmount' => 100, 'overriddenAmount' => 80,
        ]],
        'linesOfBusiness' => [[
            'excludedFees' => ['unreconciled-fee'],
            'feeVariableMappings' => [['feeId' => 'accepted-fee', 'variableName' => 'numberOfHorses']],
        ]],
    ]);

    $proposals = app(PlanLegacyFinancialDependencies::class)
        ->handle($fixture['batch'], 'financial-plan-exceptions')
        ->proposals
        ->keyBy('kind');

    expect($proposals['application_fee_override']->status)->toBe(LegacyMappingProposalStatus::ReviewRequired)
        ->and($proposals['application_fee_override']->reasons)->toContain('historical_fee_override_requires_municipal_acceptance')
        ->and($proposals['line_fee_exclusion']->status)->toBe(LegacyMappingProposalStatus::Blocked)
        ->and($proposals['line_fee_exclusion']->fee_rule_id)->toBeNull()
        ->and($proposals['line_fee_exclusion']->reasons)->toContain('accepted_fee_rule_reconciliation_missing')
        ->and($proposals['line_fee_variable_mapping']->status)->toBe(LegacyMappingProposalStatus::ReviewRequired)
        ->and($proposals['line_fee_variable_mapping']->metadata['variable_name_sha256'])->toBe(hash('sha256', 'numberOfHorses'));
});

test('application payment mode and stored total remain reconciliation evidence rather than executable schedules', function () {
    $fixture = financialBatch('summary');
    financialRecord($fixture['batch'], 'business_permit_applications', 'application-summary', [
        'modeOfPayment' => 'Quarterly',
        'totalFees' => 1_234.50,
        'linesOfBusiness' => [],
    ]);

    $proposal = app(PlanLegacyFinancialDependencies::class)
        ->handle($fixture['batch'], 'financial-plan-summary')
        ->proposals
        ->sole();

    expect($proposal->kind)->toBe('application_financial_summary')
        ->and($proposal->status)->toBe(LegacyMappingProposalStatus::ReviewRequired)
        ->and($proposal->metadata['total_fees_cents'])->toBe(123_450)
        ->and($proposal->reasons)->toContain('payment_mode_schedule_policy_requires_reconciliation', 'application_total_fees_requires_schedule_reconciliation')
        ->and(PaymentSchedule::query()->count())->toBe(0);
});

test('completed payment reconciles persisted paid total while collection and receipt authority remain explicit', function () {
    $fixture = cleanFinancialScheduleFixture('completed', 'paid', 350.00);
    $payment = financialRecord($fixture['batch'], 'payments', 'payment-completed', [
        'applicationId' => $fixture['application']->legacy_id,
        'scheduleId' => $fixture['schedule']->legacy_id,
        'transactionNumber' => 'TXN-SECRET-0001',
        'amount' => 350.00,
        'paymentMethod' => 'Cash',
        'status' => 'completed',
        'referenceNumber' => 'REFERENCE-SECRET-0001',
        'receiptNumber' => 'OR-SECRET-0001',
        'paidAt' => '2026-08-16T10:30:00+08:00',
        'processedBy' => 'legacy-user-secret',
    ]);

    $plan = app(PlanLegacyFinancialDependencies::class)->handle($fixture['batch'], 'financial-plan-completed');
    $schedule = $plan->proposals->firstWhere('kind', 'payment_schedule');
    $paymentProposal = $plan->proposals->firstWhere('kind', 'payment');
    $receipt = $plan->proposals->firstWhere('kind', 'receipt_claim');

    expect($schedule?->status)->toBe(LegacyMappingProposalStatus::Ready)
        ->and($paymentProposal?->legacy_record_id)->toBe($payment->id)
        ->and($paymentProposal?->status)->toBe(LegacyMappingProposalStatus::ReviewRequired)
        ->and($paymentProposal?->reasons)->toContain('completed_payment_collection_mapping_requires_acceptance', 'payment_processor_identity_requires_reconciliation')
        ->and($receipt?->status)->toBe(LegacyMappingProposalStatus::Blocked)
        ->and($receipt?->reasons)->toContain('receipt_numbering_authority_required')
        ->and($receipt?->metadata['receipt_writes'])->toBeFalse()
        ->and(TreasuryCollection::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0);
});

test('financial planner blocks inconsistent totals malformed cents and cross-application payment references', function () {
    $fixture = cleanFinancialScheduleFixture('invalid');
    $otherApplication = financialRecord($fixture['batch'], 'business_permit_applications', 'application-other', ['linesOfBusiness' => []]);
    readyFinancialApplicationMapping($fixture['batch'], $otherApplication);
    $fixture['schedule']->update(['payload' => [
        ...$fixture['schedule']->payload,
        'status' => 'partial',
        'fees' => [[
            'feeId' => 'fee-invalid', 'feeName' => 'Fee', 'feeCategory' => 'Tax', 'originalAmount' => 350, 'sectionAmount' => 100,
        ]],
        'totalAmount' => 350,
        'paidAmount' => 12.345,
    ]]);
    financialRecord($fixture['batch'], 'payments', 'payment-invalid', [
        'applicationId' => $otherApplication->legacy_id,
        'scheduleId' => $fixture['schedule']->legacy_id,
        'amount' => 12.345,
        'paymentMethod' => 'Cash',
        'status' => 'completed',
        'paidAt' => 'not-a-date',
        'processedBy' => '',
    ]);

    $plan = app(PlanLegacyFinancialDependencies::class)->handle($fixture['batch'], 'financial-plan-invalid');
    $schedule = $plan->proposals->firstWhere('kind', 'payment_schedule');
    $payment = $plan->proposals->firstWhere('kind', 'payment');

    expect($schedule?->status)->toBe(LegacyMappingProposalStatus::Blocked)
        ->and($schedule?->reasons)->toContain('schedule_amount_not_exact', 'schedule_total_conflicts_with_persisted_components', 'schedule_status_conflicts_with_paid_amount')
        ->and($payment?->status)->toBe(LegacyMappingProposalStatus::Blocked)
        ->and($payment?->reasons)->toContain('payment_schedule_application_mismatch', 'payment_amount_not_exact', 'payment_timestamp_invalid', 'payment_processor_identity_missing');
});

test('stable financial plan retries and interrupted runs do not duplicate proposals and reject evidence drift', function () {
    $fixture = cleanFinancialScheduleFixture('stable');
    $action = app(PlanLegacyFinancialDependencies::class);

    $first = $action->handle($fixture['batch'], 'financial-plan-stable');
    $second = $action->handle($fixture['batch'], 'financial-plan-stable');
    expect($second->id)->toBe($first->id)
        ->and(LegacyFinancialMappingPlan::query()->count())->toBe(1)
        ->and(LegacyFinancialMappingProposal::query()->count())->toBe(2);

    $first->update([
        'status' => LegacyMappingPlanStatus::Planning,
        'proposal_count' => 0,
        'ready_count' => 0,
        'review_count' => 0,
        'blocked_count' => 0,
        'completed_at' => null,
    ]);
    $resumed = $action->handle($fixture['batch'], 'financial-plan-stable');
    expect($resumed->proposal_count)->toBe(2)
        ->and(LegacyFinancialMappingProposal::query()->count())->toBe(2);

    $fixture['feeRule']->update(['is_active' => false]);
    expect(fn () => $action->handle($fixture['batch'], 'financial-plan-stable'))
        ->toThrow(RuntimeException::class, 'different source or reconciliation evidence');
});

test('financial command writes redacted evidence and requires a stable run id', function () {
    Storage::fake('local');
    $fixture = cleanFinancialScheduleFixture('command', 'paid', 350);
    financialRecord($fixture['batch'], 'payments', 'payment-command-secret', [
        'applicationId' => $fixture['application']->legacy_id,
        'scheduleId' => $fixture['schedule']->legacy_id,
        'transactionNumber' => 'TXN-COMMAND-SECRET',
        'amount' => 350,
        'paymentMethod' => 'Check',
        'status' => 'completed',
        'referenceNumber' => 'BANK-COMMAND-SECRET',
        'receiptNumber' => 'OR-COMMAND-SECRET',
        'paidAt' => '2026-08-16T12:00:00+08:00',
        'processedBy' => 'PROCESSOR-COMMAND-SECRET',
    ]);

    $this->artisan('legacy:plan-financial-dependencies', ['batch' => $fixture['batch']->id])
        ->expectsOutput('A stable --run-id is required.')
        ->assertFailed();
    $this->artisan('legacy:plan-financial-dependencies', [
        'batch' => $fixture['batch']->id,
        '--run-id' => 'financial-plan-command',
        '--json' => true,
    ])->assertSuccessful();

    $root = 'legacy-migrations/LEGACY-FINANCIAL-command/financial-staging-command/financial-mapping-plans/financial-plan-command';
    Storage::disk('local')->assertExists($root.'/financial-plan.json');
    $report = Storage::disk('local')->get($root.'/financial-plan.json');
    $decoded = json_decode($report, true, 512, JSON_THROW_ON_ERROR);
    expect($report)->not->toContain('TXN-COMMAND-SECRET', 'BANK-COMMAND-SECRET', 'OR-COMMAND-SECRET', 'PROCESSOR-COMMAND-SECRET', 'Sensitive Fee Name')
        ->and($decoded['safety'])->toMatchArray([
            'raw_transaction_references_in_report' => false,
            'raw_receipt_numbers_in_report' => false,
            'fee_name_matching' => false,
            'execution_authorized' => false,
            'liability_calculations' => false,
            'financial_domain_writes' => false,
        ]);
});
