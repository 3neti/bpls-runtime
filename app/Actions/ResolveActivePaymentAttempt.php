<?php

namespace App\Actions;

use App\Models\XChangePayment;
use App\Models\XChangePaymentAttempt;

final class ResolveActivePaymentAttempt
{
    /** @return array{state: string, attempt: XChangePaymentAttempt|null, server_now: string} */
    public function handle(?XChangePayment $payment): array
    {
        $now = now();
        $result = ['state' => 'none', 'attempt' => null, 'server_now' => $now->toIso8601String()];
        if ($payment === null || $payment->treasury_collection_id !== null || $payment->status === 'collected') {
            return $result;
        }

        $payment->loadMissing('attempts');
        $candidates = $payment->attempts->filter(fn (XChangePaymentAttempt $attempt): bool => in_array($attempt->status, ['requested', 'awaiting_payment'], true)
            && ($attempt->expires_at === null || $attempt->expires_at->greaterThan($now)));
        if ($candidates->isEmpty()) {
            return $result;
        }
        if ($candidates->count() !== 1) {
            return [...$result, 'state' => 'needs_review'];
        }
        $attempt = $candidates->first();
        if ($attempt->amount_cents !== $payment->amount_cents) {
            return [...$result, 'state' => 'needs_review'];
        }
        if ($attempt->expires_at === null || blank($attempt->reference)) {
            return [...$result, 'state' => 'pending_request', 'attempt' => $attempt];
        }

        return [...$result, 'state' => 'active', 'attempt' => $attempt];
    }
}
