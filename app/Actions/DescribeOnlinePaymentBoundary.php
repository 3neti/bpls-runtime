<?php

namespace App\Actions;

use App\Models\PaymentSchedule;

class DescribeOnlinePaymentBoundary
{
    /**
     * @return array<string, mixed>
     */
    public function handle(PaymentSchedule $paymentSchedule): array
    {
        return [
            'status' => 'blocked',
            'can_pay_online' => false,
            'can_reconcile_online' => false,
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_schedule_status' => $paymentSchedule->status->value,
            'blocked_transitions' => [
                'initiate_online_payment',
                'record_gateway_callback',
                'reconcile_gateway_settlement',
                'refund_or_reverse_gateway_payment',
            ],
            'software_knows' => [
                'payment_schedule_exists' => true,
                'balance_due_cents' => max(0, $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents),
                'otc_collection_is_available' => true,
                'gateway_adapter_is_not_configured' => true,
                'reconciliation_policy_is_not_resolved' => true,
            ],
            'unresolved_policy' => [
                'whether online payment is required for first delivery',
                'payment gateway provider, credentials, sandbox, and production cutover',
                'gateway callback, idempotency, and duplicate-payment handling',
                'settlement, reconciliation, refunds, chargebacks, and reversals',
                'relationship between electronic payment confirmation and official receipt issuance',
                'Treasury acceptance rules for failed, cancelled, refunded, or disputed payments',
            ],
            'artifact_statement' => 'Online payment and reconciliation are visible as a Treasury boundary only; no gateway call, callback handling, settlement, refund, reversal, or automatic receipt behavior is executed.',
        ];
    }
}
