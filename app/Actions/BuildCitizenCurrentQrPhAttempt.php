<?php

namespace App\Actions;

use App\Integrations\QrPhPaymentArtifactCache;
use App\Models\PaymentSchedule;
use App\Models\XChangePaymentAttempt;

final class BuildCitizenCurrentQrPhAttempt
{
    public function __construct(private readonly QrPhPaymentArtifactCache $artifactCache) {}

    /**
     * @return array{amount_cents: int, status: string, expires_at: string, qr_data_url: string|null}|null
     */
    public function handle(PaymentSchedule $paymentSchedule): ?array
    {
        if ($paymentSchedule->treasuryCollections()->exists()) {
            return null;
        }

        $payment = $paymentSchedule->xChangePayment()->with('attempts')->first();
        $attempt = $payment?->attempts->sortByDesc('id')->first();

        if (! $attempt instanceof XChangePaymentAttempt
            || ! in_array($attempt->status, ['requested', 'awaiting_payment'], true)
            || $attempt->expires_at?->isFuture() !== true) {
            return null;
        }

        return [
            'amount_cents' => $attempt->amount_cents,
            'status' => $attempt->status,
            'expires_at' => $attempt->expires_at->toIso8601String(),
            'qr_data_url' => $this->artifactCache->dataUrl($attempt),
        ];
    }
}
