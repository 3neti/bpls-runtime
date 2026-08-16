<?php

namespace App\Actions;

use App\Enums\FeeRuleCategory;
use App\Enums\LegacyFeeRuleReconciliationStatus;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\FeeRule;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyFeeRuleReconciliation;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyImportBatch;
use App\Models\LegacyRecord;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PlanLegacyFinancialDependencies
{
    public const PlannerVersion = 'bpls.financial-mapping-plan.v1';

    public function handle(LegacyImportBatch $batch, string $runReference): LegacyFinancialMappingPlan
    {
        $this->assertReady($batch, $runReference);
        $datasets = $this->datasetKeys($batch);
        $snapshot = $this->snapshotHash($batch);
        $plan = DB::transaction(function () use ($batch, $runReference, $snapshot, $datasets): LegacyFinancialMappingPlan {
            $existing = $batch->financialMappingPlans()->where('run_reference', $runReference)->lockForUpdate()->first();

            if ($existing instanceof LegacyFinancialMappingPlan) {
                if (! hash_equals($existing->dependency_snapshot_hash, $snapshot)) {
                    throw new RuntimeException("Financial mapping plan run reference [{$runReference}] is bound to different source or reconciliation evidence.");
                }

                return $existing;
            }

            return $batch->financialMappingPlans()->create([
                'run_reference' => $runReference,
                'planner_version' => self::PlannerVersion,
                'dependency_snapshot_hash' => $snapshot,
                'status' => LegacyMappingPlanStatus::Planning,
                'started_at' => now(),
                'metadata' => [
                    'datasets' => $datasets,
                    'historical_amount_conversion_only' => true,
                    'execution_authorized' => false,
                    'liability_calculations' => false,
                    'domain_writes' => false,
                ],
            ]);
        });

        if (in_array($plan->status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
            return $this->evidence($plan);
        }

        foreach ($batch->records()->where('dataset_key', $datasets['applications'])->orderBy('id')->cursor() as $application) {
            $this->planApplicationFinancialConfiguration($plan, $application);
        }

        $completedPaymentTotals = $this->completedPaymentTotals($batch, $datasets['payments']);

        if ($datasets['schedules'] !== null) {
            foreach ($batch->records()->where('dataset_key', $datasets['schedules'])->orderBy('id')->cursor() as $schedule) {
                $this->planSchedule($plan, $batch, $schedule, $datasets, $completedPaymentTotals);
            }
        }

        if ($datasets['payments'] !== null) {
            foreach ($batch->records()->where('dataset_key', $datasets['payments'])->orderBy('id')->cursor() as $payment) {
                $this->planPayment($plan, $batch, $payment, $datasets);
            }
        }

        return $this->complete($plan);
    }

    private function planApplicationFinancialConfiguration(LegacyFinancialMappingPlan $plan, LegacyRecord $record): void
    {
        $this->planApplicationFinancialSummary($plan, $record);

        foreach ($this->items($record->payload['feeOverrides'] ?? null) as $index => $override) {
            $this->planFeeDependency($plan, $record, 'application_fee_override', "global:{$index}", $override, ['originalAmount', 'overriddenAmount'], 'historical_fee_override_requires_municipal_acceptance');
        }

        $lines = $record->payload['linesOfBusiness'] ?? [];
        if (! is_array($lines)) {
            return;
        }

        foreach (array_values($lines) as $lineIndex => $lineValue) {
            $line = is_array($lineValue) ? $lineValue : [];
            foreach ($this->items($line['feeOverrides'] ?? null) as $index => $override) {
                $this->planFeeDependency($plan, $record, 'line_fee_override', "line:{$lineIndex}:override:{$index}", $override, ['originalAmount', 'overriddenAmount'], 'historical_fee_override_requires_municipal_acceptance');
            }
            foreach ($this->items($line['excludedFees'] ?? null, true) as $index => $exclusion) {
                $this->planFeeDependency($plan, $record, 'line_fee_exclusion', "line:{$lineIndex}:exclusion:{$index}", $exclusion, [], 'historical_fee_exclusion_requires_municipal_acceptance');
            }
            foreach ($this->items($line['feeVariableMappings'] ?? null) as $index => $mapping) {
                $this->planFeeDependency($plan, $record, 'line_fee_variable_mapping', "line:{$lineIndex}:variable:{$index}", $mapping, [], 'fee_variable_semantics_require_reconciliation', true);
            }
        }
    }

    private function planApplicationFinancialSummary(LegacyFinancialMappingPlan $plan, LegacyRecord $record): void
    {
        $hasMode = array_key_exists('modeOfPayment', $record->payload);
        $hasTotal = array_key_exists('totalFees', $record->payload);
        if (! $hasMode && ! $hasTotal) {
            return;
        }

        $reasons = [];
        $blocked = false;
        $mode = $this->string($record->payload['modeOfPayment'] ?? null);
        if ($hasMode) {
            if (! in_array($mode, ['Annually', 'Semi-Annually', 'Quarterly'], true)) {
                $reasons[] = 'application_payment_mode_invalid';
                $blocked = true;
            } else {
                $reasons[] = 'payment_mode_schedule_policy_requires_reconciliation';
            }
        }

        $total = $this->money($record->payload['totalFees'] ?? null);
        if ($hasTotal) {
            if ($total === null) {
                $reasons[] = 'application_total_fees_not_exact';
                $blocked = true;
            } else {
                $reasons[] = 'application_total_fees_requires_schedule_reconciliation';
            }
        }

        $projection = ['mode' => $mode === '' ? null : $mode, 'total_fees_cents' => $total];
        $this->proposal($plan, $record, 'application_financial_summary', 'record', null, null, $blocked, $reasons, $projection, $projection);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $amountFields
     */
    private function planFeeDependency(
        LegacyFinancialMappingPlan $plan,
        LegacyRecord $record,
        string $kind,
        string $itemKey,
        array $item,
        array $amountFields,
        string $policyReason,
        bool $requiresVariable = false,
    ): void {
        $feeId = $this->string($item['feeId'] ?? null);
        [$reconciliation, $feeRule, $reasons, $blocked] = $this->feeResolution($record, $feeId);
        $amounts = [];

        foreach ($amountFields as $field) {
            $amounts[$field.'_cents'] = $this->money($item[$field] ?? null);
            if ($amounts[$field.'_cents'] === null) {
                $reasons[] = 'historical_fee_amount_not_exact';
                $blocked = true;
            }
        }

        $variable = $this->string($item['variableName'] ?? null);
        if ($requiresVariable && $variable === '') {
            $reasons[] = 'fee_variable_name_missing';
            $blocked = true;
        }

        $reasons[] = $policyReason;
        $projection = ['kind' => $kind, 'fee_rule_id' => $feeRule?->id, ...$amounts];
        $this->proposal($plan, $record, $kind, $itemKey, $reconciliation, $feeRule, $blocked, $reasons, $projection, [
            'legacy_fee_id_sha256' => $feeId === '' ? null : hash('sha256', $feeId),
            ...$amounts,
            'variable_name_sha256' => $variable === '' ? null : hash('sha256', $variable),
        ]);
    }

    /**
     * @param  array{applications: string, schedules: string|null, payments: string|null}  $datasets
     * @param  array<string, array{total: int, exact: bool}>  $completedPaymentTotals
     */
    private function planSchedule(LegacyFinancialMappingPlan $plan, LegacyImportBatch $batch, LegacyRecord $record, array $datasets, array $completedPaymentTotals): void
    {
        $payload = $record->payload;
        $applicationId = $this->string($payload['applicationId'] ?? null);
        $application = $this->sourceRecord($batch, $datasets['applications'], $applicationId);
        $reasons = [];
        $blocked = false;

        if (! $application instanceof LegacyRecord) {
            $reasons[] = 'schedule_application_reference_unresolved';
            $blocked = true;
        } elseif (! $this->applicationMappingReady($batch, $application)) {
            $reasons[] = 'application_mapping_not_ready';
            $blocked = true;
        }

        $section = $payload['sectionNumber'] ?? null;
        if (! is_int($section) || $section < 1) {
            $reasons[] = 'schedule_section_number_invalid';
            $blocked = true;
        }

        $dueDate = $this->date($payload['dueDate'] ?? null);
        if ($dueDate === null) {
            $reasons[] = 'schedule_due_date_invalid';
            $blocked = true;
        }

        $status = $this->string($payload['status'] ?? null);
        if (! in_array($status, ['pending', 'partial', 'paid'], true)) {
            $reasons[] = 'schedule_status_requires_reconciliation';
            $blocked = true;
        }

        $total = $this->money($payload['totalAmount'] ?? null);
        $paid = $this->money($payload['paidAmount'] ?? null);
        $surcharge = $this->money($payload['surcharge'] ?? 0);
        $penalty = $this->money($payload['penalty'] ?? 0);
        if ($total === null || $paid === null || $surcharge === null || $penalty === null) {
            $reasons[] = 'schedule_amount_not_exact';
            $blocked = true;
        }

        $fees = $this->items($payload['fees'] ?? null);
        $feeTotal = 0;
        $allFeeAmountsExact = true;
        if ($fees === []) {
            $reasons[] = 'schedule_fees_missing';
            $blocked = true;
        }
        foreach ($fees as $index => $fee) {
            $sectionAmount = $this->money($fee['sectionAmount'] ?? null);
            if ($sectionAmount === null) {
                $allFeeAmountsExact = false;
            } else {
                $feeTotal += $sectionAmount;
            }
            $this->planScheduleFee($plan, $record, $index, $fee);
        }

        if (! $allFeeAmountsExact) {
            $reasons[] = 'schedule_fee_amount_not_exact';
            $blocked = true;
        } elseif ($total !== null && $surcharge !== null && $penalty !== null && $total !== $feeTotal + $surcharge + $penalty) {
            $reasons[] = 'schedule_total_conflicts_with_persisted_components';
            $blocked = true;
        }

        if ($total !== null && $paid !== null && $paid > $total) {
            $reasons[] = 'schedule_paid_amount_exceeds_total';
            $blocked = true;
        }
        if (($status === 'pending' && ($paid ?? 0) !== 0)
            || ($status === 'partial' && ($paid === null || $paid <= 0 || ($total !== null && $paid >= $total)))
            || ($status === 'paid' && ($paid === null || $total === null || $paid < $total))) {
            $reasons[] = 'schedule_status_conflicts_with_paid_amount';
            $blocked = true;
        }
        if (($surcharge ?? 0) > 0 || ($penalty ?? 0) > 0) {
            $reasons[] = 'surcharge_penalty_policy_required';
        }

        $this->reconcileSchedulePaymentTotal($record, $datasets['payments'], $completedPaymentTotals, $status, $paid, $reasons, $blocked);
        $projection = [
            'application_source_record_id' => $application?->id,
            'section' => $section,
            'due_on' => $dueDate,
            'status' => $status,
            'total_amount_cents' => $total,
            'paid_amount_cents' => $paid,
            'surcharge_cents' => $surcharge,
            'penalty_cents' => $penalty,
        ];
        $this->proposal($plan, $record, 'payment_schedule', 'record', null, null, $blocked, $reasons, $projection, [
            'legacy_application_id_sha256' => $applicationId === '' ? null : hash('sha256', $applicationId),
            ...$projection,
        ]);
    }

    /** @param array<string, mixed> $fee */
    private function planScheduleFee(LegacyFinancialMappingPlan $plan, LegacyRecord $record, int $index, array $fee): void
    {
        $feeId = $this->string($fee['feeId'] ?? null);
        [$reconciliation, $feeRule, $reasons, $blocked] = $this->feeResolution($record, $feeId);
        if ($feeId === '') {
            $reasons = ['aggregated_schedule_fee_identity_requires_reconciliation'];
            $blocked = true;
        }

        $original = $this->money($fee['originalAmount'] ?? null);
        $section = $this->money($fee['sectionAmount'] ?? null);
        if ($original === null || $section === null) {
            $reasons[] = 'schedule_fee_amount_not_exact';
            $blocked = true;
        }
        if (($fee['isEdited'] ?? false) === true) {
            $reasons[] = 'historical_schedule_fee_edit_requires_acceptance';
        }

        $sourceCategory = $this->string($fee['feeCategory'] ?? null);
        if ($sourceCategory !== 'Tax' || $feeRule?->category !== FeeRuleCategory::Tax) {
            $reasons[] = 'schedule_fee_category_requires_reconciliation';
        }

        $projection = [
            'fee_rule_id' => $feeRule?->id,
            'source_category_sha256' => $sourceCategory === '' ? null : hash('sha256', $sourceCategory),
            'original_amount_cents' => $original,
            'section_amount_cents' => $section,
        ];
        $this->proposal($plan, $record, 'payment_schedule_fee', "fee:{$index}", $reconciliation, $feeRule, $blocked, $reasons, $projection, [
            'legacy_fee_id_sha256' => $feeId === '' ? null : hash('sha256', $feeId),
            ...$projection,
            'was_edited' => ($fee['isEdited'] ?? false) === true,
        ]);
    }

    /** @param array{applications: string, schedules: string|null, payments: string|null} $datasets */
    private function planPayment(LegacyFinancialMappingPlan $plan, LegacyImportBatch $batch, LegacyRecord $record, array $datasets): void
    {
        $payload = $record->payload;
        $applicationId = $this->string($payload['applicationId'] ?? null);
        $scheduleId = $this->string($payload['scheduleId'] ?? null);
        $application = $this->sourceRecord($batch, $datasets['applications'], $applicationId);
        $schedule = $datasets['schedules'] === null ? null : $this->sourceRecord($batch, $datasets['schedules'], $scheduleId);
        $reasons = [];
        $blocked = false;

        if (! $application instanceof LegacyRecord || ! $this->applicationMappingReady($batch, $application)) {
            $reasons[] = 'payment_application_mapping_not_ready';
            $blocked = true;
        }
        if (! $schedule instanceof LegacyRecord) {
            $reasons[] = 'payment_schedule_reference_unresolved';
            $blocked = true;
        } elseif ($this->string($schedule->payload['applicationId'] ?? null) !== $applicationId) {
            $reasons[] = 'payment_schedule_application_mismatch';
            $blocked = true;
        }

        $amount = $this->money($payload['amount'] ?? null);
        if ($amount === null) {
            $reasons[] = 'payment_amount_not_exact';
            $blocked = true;
        }

        $status = $this->string($payload['status'] ?? null);
        if (! in_array($status, ['pending', 'completed', 'failed', 'cancelled'], true)) {
            $reasons[] = 'payment_status_requires_reconciliation';
            $blocked = true;
        } else {
            $reasons[] = match ($status) {
                'completed' => 'completed_payment_collection_mapping_requires_acceptance',
                'pending' => 'pending_payment_semantics_require_reconciliation',
                default => 'failed_cancelled_payment_semantics_require_reconciliation',
            };
        }

        $method = $this->string($payload['paymentMethod'] ?? null);
        if (! in_array($method, ['Cash', 'Credit Card', 'Bank Transfer', 'GCash', 'PayMaya', 'Check'], true)) {
            $reasons[] = 'payment_method_requires_reconciliation';
            $blocked = true;
        }
        if ($this->dateTime($payload['paidAt'] ?? null) === null) {
            $reasons[] = 'payment_timestamp_invalid';
            $blocked = true;
        }

        $processor = $this->string($payload['processedBy'] ?? null);
        if ($processor === '') {
            $reasons[] = 'payment_processor_identity_missing';
            $blocked = true;
        } else {
            $reasons[] = 'payment_processor_identity_requires_reconciliation';
        }

        $projection = [
            'application_source_record_id' => $application?->id,
            'schedule_source_record_id' => $schedule?->id,
            'amount_cents' => $amount,
            'status' => $status,
            'method' => $method,
        ];
        $this->proposal($plan, $record, 'payment', 'record', null, null, $blocked, $reasons, $projection, [
            'legacy_application_id_sha256' => $applicationId === '' ? null : hash('sha256', $applicationId),
            'legacy_schedule_id_sha256' => $scheduleId === '' ? null : hash('sha256', $scheduleId),
            'transaction_number_sha256' => $this->hashString($payload['transactionNumber'] ?? null),
            'reference_number_sha256' => $this->hashString($payload['referenceNumber'] ?? null),
            'processor_identity_sha256' => $processor === '' ? null : hash('sha256', $processor),
            ...$projection,
        ]);

        $receiptNumber = $this->string($payload['receiptNumber'] ?? null);
        if ($receiptNumber !== '') {
            $this->proposal($plan, $record, 'receipt_claim', 'record', null, null, true, ['receipt_numbering_authority_required'], [
                'payment_source_record_id' => $record->id,
                'amount_cents' => $amount,
            ], [
                'receipt_number_sha256' => hash('sha256', $receiptNumber),
                'payment_source_record_id' => $record->id,
                'amount_cents' => $amount,
                'receipt_writes' => false,
            ]);
        }
    }

    /**
     * @param  list<string>  $reasons
     * @param  array<string, array{total: int, exact: bool}>  $completedPaymentTotals
     */
    private function reconcileSchedulePaymentTotal(
        LegacyRecord $schedule,
        ?string $paymentDataset,
        array $completedPaymentTotals,
        string $status,
        ?int $paid,
        array &$reasons,
        bool &$blocked,
    ): void {
        if ($status === 'pending' && $paid === 0) {
            return;
        }
        if ($paymentDataset === null) {
            $reasons[] = 'payment_dataset_required_for_non_pending_schedule';
            $blocked = true;

            return;
        }

        $evidence = $completedPaymentTotals[$schedule->legacy_id] ?? ['total' => 0, 'exact' => true];
        if (! $evidence['exact'] || $paid === null || $evidence['total'] !== $paid) {
            $reasons[] = 'schedule_paid_amount_conflicts_with_completed_payments';
            $blocked = true;
        }
    }

    /** @return array<string, array{total: int, exact: bool}> */
    private function completedPaymentTotals(LegacyImportBatch $batch, ?string $paymentDataset): array
    {
        if ($paymentDataset === null) {
            return [];
        }

        $totals = [];
        foreach ($batch->records()->where('dataset_key', $paymentDataset)->select(['id', 'payload'])->orderBy('id')->cursor() as $payment) {
            if ($this->string($payment->payload['status'] ?? null) !== 'completed') {
                continue;
            }
            $scheduleId = $this->string($payment->payload['scheduleId'] ?? null);
            if ($scheduleId === '') {
                continue;
            }
            $totals[$scheduleId] ??= ['total' => 0, 'exact' => true];
            $amount = $this->money($payment->payload['amount'] ?? null);
            if ($amount === null) {
                $totals[$scheduleId]['exact'] = false;
            } else {
                $totals[$scheduleId]['total'] += $amount;
            }
        }

        return $totals;
    }

    /**
     * @return array{LegacyFeeRuleReconciliation|null, FeeRule|null, list<string>, bool}
     */
    private function feeResolution(LegacyRecord $record, string $feeId): array
    {
        if ($feeId === '') {
            return [null, null, ['legacy_fee_identity_missing'], true];
        }

        $reconciliation = LegacyFeeRuleReconciliation::query()
            ->where('legacy_source_id', $record->legacy_source_id)
            ->where('source_dataset', 'fees')
            ->where('source_legacy_id', $feeId)
            ->first();
        if (! $reconciliation instanceof LegacyFeeRuleReconciliation) {
            return [null, null, ['accepted_fee_rule_reconciliation_missing'], true];
        }
        if ($reconciliation->status !== LegacyFeeRuleReconciliationStatus::Accepted
            || $reconciliation->decision_authority === null || $reconciliation->evidence_reference === null) {
            return [$reconciliation, null, ['fee_rule_reconciliation_not_accepted'], true];
        }

        $feeRule = $reconciliation->fee_rule_id === null ? null : FeeRule::query()->find($reconciliation->fee_rule_id);
        if (! $feeRule instanceof FeeRule) {
            return [$reconciliation, null, ['reconciled_fee_rule_target_missing'], true];
        }

        return [$reconciliation, $feeRule, $feeRule->is_active ? [] : ['reconciled_fee_rule_inactive'], false];
    }

    /**
     * @param  list<string>  $reasons
     * @param  array<string, mixed>  $projection
     * @param  array<string, mixed>  $metadata
     */
    private function proposal(
        LegacyFinancialMappingPlan $plan,
        LegacyRecord $record,
        string $kind,
        string $itemKey,
        ?LegacyFeeRuleReconciliation $reconciliation,
        ?FeeRule $feeRule,
        bool $blocked,
        array $reasons,
        array $projection,
        array $metadata,
    ): void {
        $reasons = array_values(array_unique($reasons));
        $status = $blocked
            ? LegacyMappingProposalStatus::Blocked
            : ($reasons === [] ? LegacyMappingProposalStatus::Ready : LegacyMappingProposalStatus::ReviewRequired);

        $plan->proposals()->updateOrCreate(
            ['legacy_record_id' => $record->id, 'kind' => $kind, 'item_key' => $itemKey],
            [
                'source_dataset' => $record->dataset_key,
                'legacy_fee_rule_reconciliation_id' => $reconciliation?->id,
                'fee_rule_id' => $feeRule?->id,
                'status' => $status,
                'projection_hash' => $this->hash($projection),
                'reasons' => $reasons,
                'metadata' => [
                    ...$metadata,
                    'historical_amount_conversion_only' => true,
                    'execution_authorized' => false,
                    'liability_calculations' => false,
                    'domain_writes' => false,
                ],
            ],
        );
    }

    private function complete(LegacyFinancialMappingPlan $plan): LegacyFinancialMappingPlan
    {
        $ready = $plan->proposals()->where('status', LegacyMappingProposalStatus::Ready)->count();
        $review = $plan->proposals()->where('status', LegacyMappingProposalStatus::ReviewRequired)->count();
        $blocked = $plan->proposals()->where('status', LegacyMappingProposalStatus::Blocked)->count();
        $plan->update([
            'status' => $review + $blocked > 0 ? LegacyMappingPlanStatus::PlannedWithExceptions : LegacyMappingPlanStatus::Planned,
            'proposal_count' => $ready + $review + $blocked,
            'ready_count' => $ready,
            'review_count' => $review,
            'blocked_count' => $blocked,
            'completed_at' => now(),
            'metadata' => [...($plan->metadata ?? []), 'payloads_in_report' => false, 'financial_domain_writes' => false],
        ]);

        return $this->evidence($plan);
    }

    private function applicationMappingReady(LegacyImportBatch $batch, LegacyRecord $application): bool
    {
        $plan = LegacyApplicationMappingPlan::query()
            ->whereBelongsTo($batch, 'importBatch')
            ->whereIn('status', [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions])
            ->latest('id')
            ->first();

        return $plan instanceof LegacyApplicationMappingPlan
            && $plan->proposals()->where('legacy_record_id', $application->id)->where('status', LegacyMappingProposalStatus::Ready)->exists();
    }

    private function sourceRecord(LegacyImportBatch $batch, string $dataset, string $legacyId): ?LegacyRecord
    {
        if ($legacyId === '') {
            return null;
        }

        return $batch->records()->where('dataset_key', $dataset)->where('legacy_id', $legacyId)->first();
    }

    /** @return array{applications: string, schedules: string|null, payments: string|null} */
    private function datasetKeys(LegacyImportBatch $batch): array
    {
        $applications = $this->datasetKey($batch, ['applications', 'business_permit_applications'], true);
        if (! is_string($applications)) {
            throw new RuntimeException('Legacy batch does not contain a declared application dataset.');
        }

        return [
            'applications' => $applications,
            'schedules' => $this->datasetKey($batch, ['payment_schedules'], false),
            'payments' => $this->datasetKey($batch, ['payments'], false),
        ];
    }

    /** @param list<string> $candidates */
    private function datasetKey(LegacyImportBatch $batch, array $candidates, bool $required): ?string
    {
        $keys = collect($candidates)->filter(fn (string $key): bool => $batch->records()->where('dataset_key', $key)->exists())->values();
        if ($keys->count() > 1 || ($required && $keys->count() !== 1)) {
            throw new RuntimeException('Legacy batch contains an ambiguous or missing financial dependency dataset.');
        }

        return $keys->first();
    }

    private function snapshotHash(LegacyImportBatch $batch): string
    {
        $parts = [];
        foreach ($batch->records()->select(['id', 'dataset_key', 'payload_hash'])->orderBy('id')->cursor() as $record) {
            $parts[] = [$record->id, $record->dataset_key, $record->payload_hash];
        }
        foreach (LegacyFeeRuleReconciliation::query()->where('legacy_source_id', $batch->legacy_source_id)->orderBy('id')->cursor() as $item) {
            $parts[] = ['fee_reconciliation', $item->id, hash('sha256', $item->source_legacy_id), $item->fee_rule_id, $item->status->value, $item->decision_authority, $item->evidence_reference, $item->updated_at?->toJSON()];
        }
        foreach (FeeRule::query()->select(['id', 'code', 'is_active', 'updated_at'])->orderBy('id')->cursor() as $feeRule) {
            $parts[] = ['fee_rule', ...$feeRule->getAttributes()];
        }
        foreach ($batch->applicationMappingPlans()->with('proposals')->orderBy('id')->cursor() as $plan) {
            $parts[] = ['application_plan', $plan->id, $plan->dependency_snapshot_hash, $plan->status->value,
                $plan->proposals->map(fn ($proposal): array => [$proposal->id, $proposal->legacy_record_id, $proposal->status->value, $proposal->projection_hash])->all()];
        }

        return $this->hash($parts);
    }

    private function assertReady(LegacyImportBatch $batch, string $runReference): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy financial planning is restricted to local and testing environments.');
        }
        if (! in_array($batch->status, [LegacyImportBatchStatus::Staged, LegacyImportBatchStatus::StagedWithExceptions], true)) {
            throw new RuntimeException('Legacy import batch must finish staging before financial planning.');
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Financial plan run reference must be 3-100 safe characters.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(mixed $value, bool $scalarIsFeeId = false): array
    {
        if ($value === null || $value === []) {
            return [];
        }
        if (! is_array($value)) {
            return [[]];
        }
        if (! array_is_list($value)) {
            return [$value];
        }

        return array_map(function (mixed $item) use ($scalarIsFeeId): array {
            if (is_array($item)) {
                return $item;
            }

            return $scalarIsFeeId && (is_string($item) || is_int($item)) ? ['feeId' => $item] : [];
        }, $value);
    }

    private function money(mixed $value): ?int
    {
        if (is_float($value)) {
            if (! is_finite($value)) {
                return null;
            }
            $value = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $normalized = str_replace([',', ' '], '', (string) $value);
        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized) !== 1) {
            return null;
        }
        [$whole, $decimal] = array_pad(explode('.', $normalized, 2), 2, '');
        if (strlen($whole) > 16) {
            return null;
        }

        return ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function dateTime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (Throwable) {
            return null;
        }
    }

    private function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }

    private function hashString(mixed $value): ?string
    {
        $value = $this->string($value);

        return $value === '' ? null : hash('sha256', $value);
    }

    /** @param array<array-key, mixed> $value */
    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function evidence(LegacyFinancialMappingPlan $plan): LegacyFinancialMappingPlan
    {
        return $plan->fresh(['importBatch.source', 'proposals']) ?? $plan;
    }
}
