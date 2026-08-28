<?php

namespace App\Actions;

use App\Models\PaymentSchedule;
use App\Models\XChangePayment;
use LogicException;

class DescribeOnlinePaymentBoundary
{
    public function __construct(private readonly EnsureQrPhPaymentEligible $ensureEligible) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(PaymentSchedule $paymentSchedule): array
    {
        $configured = collect(['base_url', 'client_id', 'client_secret'])
            ->every(fn (string $key): bool => is_string(config("services.x_change.{$key}")) && config("services.x_change.{$key}") !== '');
        $paymentSchedule->loadMissing(['xChangePayment.attempts', 'xChangePayment.treasuryCollection.receipt']);
        $payment = $paymentSchedule->xChangePayment;

        $eligible = false;
        if ($configured) {
            try {
                $this->ensureEligible->handle($paymentSchedule);
                $eligible = true;
            } catch (LogicException) {
                $eligible = false;
            }
        }

        $attempt = $payment instanceof XChangePayment ? $payment->attempts->sortByDesc('id')->first() : null;

        return [
            'status' => $payment?->treasury_collection_id !== null ? 'paid' : ($eligible ? 'available' : 'blocked'),
            'can_pay_online' => $eligible,
            'can_reconcile_online' => $configured,
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_schedule_status' => $paymentSchedule->status->value,
            'payment_status' => $payment?->status,
            'attempt_status' => $attempt?->status,
            'attempt_expires_at' => $attempt?->expires_at?->toIso8601String(),
            'blocked_transitions' => [
                'refund_or_reverse_gateway_payment',
            ],
            'software_knows' => [
                'payment_schedule_exists' => true,
                'balance_due_cents' => max(0, $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents),
                'otc_collection_is_available' => true,
                'gateway_adapter_is_not_configured' => ! $configured,
                'authoritative_confirmation_required' => true,
                'reconciliation_policy_is_not_resolved' => true,
            ],
            'unresolved_policy' => [
                'production provider credentials and production cutover',
                'refunds, chargebacks, reversals, and settlement reconciliation',
                'relationship between electronic payment confirmation and official receipt issuance',
                'Treasury acceptance rules for failed, cancelled, refunded, or disputed payments',
            ],
            'artifact_statement' => $eligible
                ? 'QR Ph is available for this exact Treasurer-approved unpaid obligation. Payment is recorded only after authoritative full-collection confirmation.'
                : 'QR Ph is unavailable unless the exact current Treasurer-approved obligation remains unpaid and the testing integration is configured.',
        ];
    }
}
