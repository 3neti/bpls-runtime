<?php

namespace App\Actions;

use App\Integrations\QrPhPaymentArtifactCache;
use App\Models\PaymentSchedule;
use App\Models\XChangePaymentAttempt;

final class BuildCitizenCurrentQrPhAttempt
{
    public function __construct(
        private readonly QrPhPaymentArtifactCache $artifactCache,
        private readonly ResolveActivePaymentAttempt $resolveAttempt,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(PaymentSchedule $paymentSchedule): ?array
    {
        if ($paymentSchedule->treasuryCollections()->exists()) {
            return null;
        }

        $payment = $paymentSchedule->xChangePayment()->with('attempts')->first();
        $resolution = $this->resolveAttempt->handle($payment);
        $attempt = $resolution['attempt'];

        if (! $attempt instanceof XChangePaymentAttempt
            || $resolution['state'] !== 'active') {
            return null;
        }

        return [
            'payment_id' => $payment->id,
            'attempt_id' => $attempt->id,
            'external_reference' => $payment->external_reference,
            'server_now' => $resolution['server_now'],
            'amount_cents' => $attempt->amount_cents,
            'status' => $attempt->status,
            'expires_at' => $attempt->expires_at->toIso8601String(),
            'qr_data_url' => $this->artifactCache->dataUrl($attempt),
        ];
    }
}
