<?php

namespace App\Actions;

use App\Integrations\QrPhPaymentArtifactCache;
use App\Models\LifecycleCleanroomRun;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Models\XChangePaymentAttempt;

final class BuildClassicCashierQrPhHandoff
{
    public function __construct(private readonly QrPhPaymentArtifactCache $artifactCache) {}

    /** @return array<string, mixed>|null */
    public function handle(PaymentSchedule $schedule, ?User $viewer): ?array
    {
        $runId = data_get($schedule->permitApplication->metadata, 'lifecycle_cleanroom.run_id');
        $run = is_string($runId)
            ? LifecycleCleanroomRun::query()->where('public_id', $runId)->first()
            : null;

        if (! $run instanceof LifecycleCleanroomRun
            || ! $run->isClassicLifecycleV1()
            || $run->status !== 'active'
            || ! $viewer instanceof User
            || data_get($run->actor_manifest, 'actors.cashier.user_id') !== $viewer->id) {
            return null;
        }

        $payment = $schedule->xChangePayment()->with('attempts')->first();
        $attempt = $payment?->attempts->sortByDesc('id')->first();
        if ($payment === null || ! $attempt instanceof XChangePaymentAttempt) {
            return null;
        }

        $isCurrent = in_array($attempt->status, ['requested', 'awaiting_payment'], true)
            && $attempt->expires_at?->isFuture() === true
            && $schedule->treasuryCollections()->doesntExist();

        return [
            'payment_id' => $payment->id,
            'pay_code' => $payment->pay_code,
            'external_reference' => $payment->external_reference,
            'amount_cents' => $payment->amount_cents,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'is_current' => $isCurrent,
            'attempt' => [
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
