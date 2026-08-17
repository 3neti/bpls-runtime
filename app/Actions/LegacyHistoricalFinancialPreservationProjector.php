<?php

namespace App\Actions;

use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyFinancialMappingProposal;
use App\Models\LegacyRecord;
use DateTimeImmutable;
use Illuminate\Support\Collection;

class LegacyHistoricalFinancialPreservationProjector
{
    /**
     * @param  Collection<int, LegacyFinancialMappingProposal>  $financialProposals
     * @return array{application_mapping: LegacyApplicationIdMapping|null, projection: array<string, mixed>, reasons: array<int, string>}
     */
    public function project(LegacyFinancialMappingPlan $plan, LegacyRecord $application, Collection $financialProposals): array
    {
        $reasons = collect();
        $mapping = $this->applicationMapping($application, $reasons);
        $scheduleProposals = $financialProposals
            ->where('kind', 'payment_schedule')
            ->filter(fn (LegacyFinancialMappingProposal $proposal): bool => (int) ($proposal->metadata['application_source_record_id'] ?? 0) === $application->id)
            ->sortBy(fn (LegacyFinancialMappingProposal $proposal): array => [(int) ($proposal->metadata['section'] ?? PHP_INT_MAX), $proposal->id])
            ->values();

        if ($scheduleProposals->isEmpty()) {
            $reasons->push('application_financial_history_missing');
        }

        $sections = [];
        $schedules = [];
        foreach ($scheduleProposals as $scheduleProposal) {
            $schedule = $this->schedule($scheduleProposal, $financialProposals, $reasons);
            $section = $schedule['section'];
            if (is_int($section) && in_array($section, $sections, true)) {
                $reasons->push('duplicate_schedule_section');
            }
            if (is_int($section)) {
                $sections[] = $section;
            }
            $schedules[] = $schedule;
        }

        $assignedPaymentIds = collect($schedules)
            ->flatMap(fn (array $schedule): array => array_column($schedule['payments'], 'source_record_id'))
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values();
        $applicationPaymentIds = $financialProposals
            ->where('kind', 'payment')
            ->filter(fn (LegacyFinancialMappingProposal $proposal): bool => (int) ($proposal->metadata['application_source_record_id'] ?? 0) === $application->id)
            ->pluck('legacy_record_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values();
        if ($assignedPaymentIds->all() !== $applicationPaymentIds->all()) {
            $reasons->push('application_has_unassigned_payment_events');
        }

        $totals = [
            'schedule_count' => count($schedules),
            'fee_line_count' => array_sum(array_map(fn (array $schedule): int => count($schedule['fee_lines']), $schedules)),
            'payment_count' => array_sum(array_map(fn (array $schedule): int => count($schedule['payments']), $schedules)),
            'scheduled_amount_cents' => array_sum(array_map(fn (array $schedule): int => (int) ($schedule['total_amount_cents'] ?? 0), $schedules)),
            'fee_amount_cents' => array_sum(array_map(fn (array $schedule): int => array_sum(array_column($schedule['fee_lines'], 'section_amount_cents')), $schedules)),
            'paid_amount_cents' => array_sum(array_map(fn (array $schedule): int => (int) ($schedule['paid_amount_cents'] ?? 0), $schedules)),
            'payment_amount_cents' => array_sum(array_map(fn (array $schedule): int => array_sum(array_column($schedule['payments'], 'amount_cents')), $schedules)),
        ];

        $projection = [
            'schema_version' => 'bpls.historical-financial-preservation-bundle.v1',
            'source' => [
                'legacy_source_id' => $application->legacy_source_id,
                'legacy_import_batch_id' => $application->legacy_import_batch_id,
                'application_source_record_id' => $application->id,
                'application_payload_hash' => $application->payload_hash,
                'financial_mapping_plan_id' => $plan->id,
                'financial_dependency_snapshot_hash' => $plan->dependency_snapshot_hash,
            ],
            'target' => [
                'legacy_application_id_mapping_id' => $mapping?->id,
                'permit_application_id' => $mapping?->permit_application_id,
                'mapping_basis' => $mapping?->mapping_basis,
            ],
            'financial_history' => [
                'schedules' => $schedules,
                'totals' => $totals,
            ],
            'provenance' => [
                'historical_financial_fact' => true,
                'fee_policy_provenance' => 'incomplete',
                'future_policy_executable' => false,
                'operational_financial_record' => false,
                'liability_recalculated' => false,
                'fee_identity_inferred' => false,
            ],
        ];

        return [
            'application_mapping' => $mapping,
            'projection' => $projection,
            'reasons' => $reasons->unique()->map(fn (mixed $reason): string => (string) $reason)->values()->all(),
        ];
    }

    /** @param Collection<int, string> $reasons */
    private function applicationMapping(LegacyRecord $application, Collection $reasons): ?LegacyApplicationIdMapping
    {
        $mappings = LegacyApplicationIdMapping::query()
            ->where('legacy_source_id', $application->legacy_source_id)
            ->where('legacy_import_batch_id', $application->legacy_import_batch_id)
            ->where('dataset_key', $application->dataset_key)
            ->where('legacy_id', $application->legacy_id)
            ->where('status', 'mapped')
            ->get();

        if ($mappings->count() !== 1) {
            $reasons->push($mappings->isEmpty() ? 'accepted_application_mapping_required' : 'application_mapping_ambiguous');

            return null;
        }

        return $mappings->sole();
    }

    /**
     * @param  Collection<int, LegacyFinancialMappingProposal>  $financialProposals
     * @param  Collection<int, string>  $reasons
     * @return array<string, mixed>
     */
    private function schedule(LegacyFinancialMappingProposal $proposal, Collection $financialProposals, Collection $reasons): array
    {
        $metadata = $proposal->metadata ?? [];
        $allowedScheduleReasons = ['application_mapping_not_ready'];
        $this->rejectUnexpectedReasons($proposal, $allowedScheduleReasons, 'schedule', $reasons);

        $section = $metadata['section'] ?? null;
        $status = $metadata['status'] ?? null;
        $total = $metadata['total_amount_cents'] ?? null;
        $paid = $metadata['paid_amount_cents'] ?? null;
        $surcharge = $metadata['surcharge_cents'] ?? null;
        $penalty = $metadata['penalty_cents'] ?? null;
        $dueOn = $metadata['due_on'] ?? null;

        if (! is_int($section) || $section < 1) {
            $reasons->push('schedule_section_number_invalid');
        }
        if (! in_array($status, ['pending', 'paid'], true)) {
            $reasons->push('schedule_status_not_preservable_v1');
        }
        if (! is_int($total) || $total <= 0 || ! is_int($paid) || ! is_int($surcharge) || ! is_int($penalty)) {
            $reasons->push('schedule_amount_not_exact');
        }
        if ($surcharge !== 0 || $penalty !== 0) {
            $reasons->push('late_charge_history_not_preservable_v1');
        }
        $date = is_string($dueOn) ? DateTimeImmutable::createFromFormat('!Y-m-d', $dueOn) : false;
        if (! $date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $dueOn) {
            $reasons->push('schedule_due_date_invalid');
        }

        $feeProposals = $financialProposals
            ->where('legacy_record_id', $proposal->legacy_record_id)
            ->where('kind', 'payment_schedule_fee')
            ->sortBy('item_key')
            ->values();
        if ($feeProposals->isEmpty()) {
            $reasons->push('schedule_fees_missing');
        }

        $feeLines = $feeProposals->map(function (LegacyFinancialMappingProposal $feeProposal) use ($reasons): array {
            $allowed = [
                'accepted_fee_rule_reconciliation_missing',
                'aggregated_schedule_fee_identity_requires_reconciliation',
                'fee_rule_reconciliation_not_accepted',
                'legacy_fee_identity_missing',
                'reconciled_fee_rule_inactive',
                'schedule_fee_category_requires_reconciliation',
            ];
            $this->rejectUnexpectedReasons($feeProposal, $allowed, 'fee_line', $reasons);
            $metadata = $feeProposal->metadata ?? [];
            if (! is_int($metadata['original_amount_cents'] ?? null) || ! is_int($metadata['section_amount_cents'] ?? null)) {
                $reasons->push('schedule_fee_amount_not_exact');
            }
            if (($metadata['was_edited'] ?? false) === true) {
                $reasons->push('historical_schedule_fee_edit_requires_acceptance');
            }

            return [
                'source_record_id' => $feeProposal->legacy_record_id,
                'source_item_key' => $feeProposal->item_key,
                'source_projection_hash' => $feeProposal->projection_hash,
                'original_amount_cents' => $metadata['original_amount_cents'] ?? null,
                'section_amount_cents' => $metadata['section_amount_cents'] ?? null,
                'source_category_sha256' => $metadata['source_category_sha256'] ?? null,
                'fee_policy_provenance' => 'incomplete',
                'fee_rule_id' => null,
                'future_policy_executable' => false,
            ];
        })->all();

        $feeTotal = array_sum(array_column($feeLines, 'section_amount_cents'));
        if (is_int($total) && $feeTotal !== $total) {
            $reasons->push('schedule_total_conflicts_with_persisted_components');
        }

        $payments = $this->payments($proposal, $financialProposals, $status, $paid, $reasons);

        return [
            'source_record_id' => $proposal->legacy_record_id,
            'source_payload_hash' => $proposal->legacyRecord->payload_hash,
            'source_projection_hash' => $proposal->projection_hash,
            'section' => $section,
            'due_on' => $dueOn,
            'status' => $status,
            'total_amount_cents' => $total,
            'paid_amount_cents' => $paid,
            'surcharge_cents' => $surcharge,
            'penalty_cents' => $penalty,
            'fee_lines' => $feeLines,
            'payments' => $payments,
        ];
    }

    /**
     * @param  Collection<int, LegacyFinancialMappingProposal>  $financialProposals
     * @param  Collection<int, string>  $reasons
     * @return list<array<string, mixed>>
     */
    private function payments(LegacyFinancialMappingProposal $schedule, Collection $financialProposals, mixed $status, mixed $paid, Collection $reasons): array
    {
        $payments = $financialProposals
            ->where('kind', 'payment')
            ->filter(fn (LegacyFinancialMappingProposal $proposal): bool => (int) ($proposal->metadata['schedule_source_record_id'] ?? 0) === $schedule->legacy_record_id)
            ->sortBy('id')
            ->values();

        if ($status === 'pending' && $payments->isNotEmpty()) {
            $reasons->push('pending_schedule_has_payment_events');
        }
        if ($status === 'paid' && $payments->count() !== 1) {
            $reasons->push('paid_schedule_requires_exactly_one_payment');
        }

        $result = $payments->map(function (LegacyFinancialMappingProposal $payment) use ($financialProposals, $reasons): array {
            $allowed = ['application_mapping_not_ready', 'payment_application_mapping_not_ready', 'completed_payment_collection_mapping_requires_acceptance', 'payment_processor_identity_requires_reconciliation'];
            $this->rejectUnexpectedReasons($payment, $allowed, 'payment', $reasons);
            $metadata = $payment->metadata ?? [];
            if (($metadata['status'] ?? null) !== 'completed' || ! is_int($metadata['amount_cents'] ?? null)) {
                $reasons->push('payment_not_exact_completed_event');
            }
            $paidAt = $payment->legacyRecord->payload['paidAt'] ?? null;
            try {
                $paidAt = is_string($paidAt) ? (new DateTimeImmutable($paidAt))->format(DATE_ATOM) : null;
            } catch (\Throwable) {
                $paidAt = null;
            }
            if ($paidAt === null) {
                $reasons->push('payment_timestamp_invalid');
            }

            return [
                'source_record_id' => $payment->legacy_record_id,
                'source_payload_hash' => $payment->legacyRecord->payload_hash,
                'source_projection_hash' => $payment->projection_hash,
                'status' => $metadata['status'] ?? null,
                'method' => $metadata['method'] ?? null,
                'amount_cents' => $metadata['amount_cents'] ?? null,
                'paid_at' => $paidAt,
                'receipt_claim_present' => $financialProposals
                    ->where('kind', 'receipt_claim')
                    ->contains(fn (LegacyFinancialMappingProposal $claim): bool => $claim->legacy_record_id === $payment->legacy_record_id),
            ];
        })->values()->all();

        $paymentTotal = array_sum(array_column($result, 'amount_cents'));
        if (is_int($paid) && $paymentTotal !== $paid) {
            $reasons->push('payment_total_conflicts_with_schedule_paid_amount');
        }

        return array_values($result);
    }

    /**
     * @param  list<string>  $allowed
     * @param  Collection<int, string>  $reasons
     */
    private function rejectUnexpectedReasons(LegacyFinancialMappingProposal $proposal, array $allowed, string $prefix, Collection $reasons): void
    {
        foreach ($proposal->reasons ?? [] as $reason) {
            if (! in_array($reason, $allowed, true)) {
                $reasons->push($prefix.'_'.(string) $reason);
            }
        }
    }

    /** @param array<string, mixed> $projection */
    public function hash(array $projection): string
    {
        return hash('sha256', json_encode($projection, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
