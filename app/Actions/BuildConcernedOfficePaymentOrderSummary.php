<?php

namespace App\Actions;

use App\Models\PaperlessPaymentOrder;
use App\Models\PermitApplication;

class BuildConcernedOfficePaymentOrderSummary
{
    /** @return array<string, mixed> */
    public function handle(PermitApplication $permitApplication): array
    {
        $application = $permitApplication->loadMissing([
            'bploRoutingDetermination.works.paymentOrders.issuedBy',
            'bploRoutingDetermination.works.paymentOrders.lines',
        ]);
        $determination = $application->bploRoutingDetermination;
        $works = $determination === null ? collect() : $determination->works;

        $offices = $works->map(function ($work): array {
            $currentOrders = $work->paymentOrders
                ->filter(fn (PaperlessPaymentOrder $order): bool => $order->status === 'issued' && $order->superseded_at === null)
                ->sortByDesc('sequence')
                ->values();
            $order = $currentOrders->count() === 1 ? $currentOrders->first() : null;
            $lineTotal = $order instanceof PaperlessPaymentOrder
                ? (int) $order->lines->sum('amount_cents')
                : null;
            $financiallyReconciled = $order instanceof PaperlessPaymentOrder
                && $lineTotal === $order->total_amount_cents;
            $status = match (true) {
                $currentOrders->count() === 1 && $financiallyReconciled => 'finalized',
                $currentOrders->count() > 1 => 'conflict',
                $currentOrders->count() === 1 => 'conflict',
                default => 'awaiting_payment_order',
            };

            return [
                'routing_work_id' => $work->id,
                'office_code' => $work->office_code,
                'office_label' => $work->office_label,
                'status' => $status,
                'payment_order_id' => $order?->id,
                'sequence' => $order?->sequence,
                'total_amount_cents' => $order?->total_amount_cents,
                'line_total_amount_cents' => $lineTotal,
                'financially_reconciled' => $financiallyReconciled,
                'issued_at' => $order?->issued_at?->toIso8601String(),
                'issued_by' => $order?->issuedBy?->name,
                'lines' => $order?->lines->map(fn ($line): array => [
                    'id' => $line->id,
                    'code' => $line->code,
                    'name' => $line->name,
                    'amount_cents' => $line->amount_cents,
                ])->values()->all() ?? [],
            ];
        })->values();
        $allFinalized = $offices->isNotEmpty()
            && $offices->every(fn (array $office): bool => $office['status'] === 'finalized');
        $recordedSubtotal = (int) $offices->sum(fn (array $office): int => $office['status'] === 'finalized'
            && is_int($office['total_amount_cents'])
            ? $office['total_amount_cents']
            : 0);

        return [
            'schema_version' => 'bpls.concerned-office-payment-orders.v1',
            'status' => $allFinalized ? 'finalized' : 'in_progress',
            'currency' => 'PHP',
            'required_office_count' => $offices->count(),
            'finalized_office_count' => $offices->where('status', 'finalized')->count(),
            'all_finalized' => $allFinalized,
            'offices' => $offices->all(),
            'recorded_subtotal_amount_cents' => $recordedSubtotal,
            'finalized_subtotal_amount_cents' => $allFinalized ? $recordedSubtotal : null,
            'assessment_total_amount_cents' => null,
            'assessment_total_status' => 'deferred_until_treasury_lob_classification',
            'next_stage' => 'treasury_lob_classification',
        ];
    }
}
