<?php

namespace App\Actions;

use App\Enums\UserPermission;
use App\Integrations\QrPhPaymentArtifactCache;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Models\XChangePaymentAttempt;

final class BuildClassicCashierQrPhHandoff
{
    public function __construct(
        private readonly QrPhPaymentArtifactCache $artifactCache,
        private readonly ResolveActivePaymentAttempt $resolveAttempt,
    ) {}

    /** @return array<string, mixed>|null */
    public function handle(PaymentSchedule $schedule, ?User $viewer): ?array
    {
        if (! $viewer instanceof User || ! $viewer->can(UserPermission::ViewPaymentSchedules->value)) {
            return null;
        }

        $payment = $schedule->xChangePayment()->with('attempts')->first();
        if ($payment === null) {
            return null;
        }
        $resolution = $this->resolveAttempt->handle($payment);
        $attempt = $resolution['attempt'];
        $collection = $schedule->treasuryCollections()->latest('received_at')->first();
        $isSettled = $collection !== null || $schedule->paid_amount_cents >= $schedule->total_amount_cents;
        $isCurrent = ! $isSettled && $resolution['state'] === 'active';

        return [
            'payment_id' => $payment->id,
            'pay_code' => $payment->pay_code,
            'external_reference' => $payment->external_reference,
            'amount_cents' => $payment->amount_cents,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'is_current' => $isCurrent,
            'is_settled' => $isSettled,
            'collection' => $collection === null ? null : [
                'id' => $collection->id,
                'amount_cents' => $collection->amount_cents,
                'reference' => $collection->reference_number,
                'received_at' => $collection->received_at?->toIso8601String(),
            ],
            'resolution' => $resolution['state'],
            'server_now' => $resolution['server_now'],
            'history' => $payment->attempts->sortBy('id')->values()->map(fn (XChangePaymentAttempt $item): array => [
                'id' => $item->id,
                'reference' => $item->reference,
                'status' => $item->status,
                'expires_at' => $item->expires_at?->toIso8601String(),
                'expired' => $item->expires_at?->isFuture() === false,
            ])->all(),
            'attempt' => $attempt === null ? null : [
                'id' => $attempt->id,
                'reference' => $attempt->reference,
                'provider' => $attempt->provider,
                'status' => $attempt->status,
                'amount_cents' => $attempt->amount_cents,
                'expires_at' => $attempt->expires_at?->toIso8601String(),
                'qr_data_url' => $isCurrent ? $this->artifactCache->dataUrl($attempt) : null,
            ],
        ];
    }
}
