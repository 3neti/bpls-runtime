<?php

namespace App\Jobs;

use App\Actions\ConfirmQrPhPayment;
use App\Models\XChangePayment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class ReconcileQrPhPayment implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 105;

    public function __construct(public int $paymentId)
    {
        $this->onConnection('payments')->onQueue('payments')->afterCommit();
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(ConfirmQrPhPayment $confirm): void
    {
        if (! config('payment_reconciliation.enabled')) {
            return;
        }
        Cache::lock('qr-ph:reconciliation-job:'.$this->paymentId, 120)->get(function () use ($confirm): void {
            $payment = XChangePayment::query()->find($this->paymentId);
            if ($payment === null || $payment->pay_code === null || in_array($payment->reconciliation_state, ['confirmed', 'needs_review'], true)) {
                return;
            }
            if ($payment->created_at?->lt(now()->subHours((int) config('payment_reconciliation.review_after_hours', 72)))
                || $payment->reconciliation_attempts >= (int) config('payment_reconciliation.max_checks', 864)) {
                $payment->forceFill(['reconciliation_state' => 'needs_review', 'last_error_code' => 'RECONCILIATION_WINDOW_EXHAUSTED', 'next_check_at' => null])->save();

                return;
            }
            if ($payment->next_check_at?->isFuture()) {
                return;
            }
            RateLimiter::attempt('qr-ph:background-inquiry', (int) config('payment_reconciliation.requests_per_minute', 30), function () use ($payment, $confirm): void {
                $confirm->handle($payment->paymentSchedule, 'background');
            }, 60);
        });
    }
}
