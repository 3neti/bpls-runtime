<?php

namespace App\Jobs;

use App\Actions\ConfirmQrPhPayment;
use App\Models\XChangePayment;
use App\Models\XChangePaymentEvent;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

class ProcessXChangePaymentEvent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    public int $timeout = 105;

    public int $uniqueFor = 180;

    public function __construct(public int $eventId)
    {
        $store = (string) config('x_change_payment_events.lock_store', 'database');
        if (config('queue.connections.payments.driver') !== 'database'
            || ! in_array(config("cache.stores.{$store}.driver"), ['database', 'redis'], true)) {
            throw new LogicException('Payment events require a durable database queue and shared lock store.');
        }
        $this->onConnection('payments');
        $this->onQueue((string) config('x_change_payment_events.queue', 'payments'));
        $this->afterCommit();
    }

    public function uniqueVia(): Repository
    {
        return Cache::store((string) config('x_change_payment_events.lock_store', 'database'));
    }

    public function uniqueId(): string
    {
        return 'payment-event:'.$this->eventId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function handle(ConfirmQrPhPayment $confirm): void
    {
        if (! config('x_change_payment_events.enabled')) {
            return;
        }
        $event = XChangePaymentEvent::query()->find($this->eventId);
        if ($event === null || $event->state !== 'accepted') {
            return;
        }
        if ($event->created_at->lt(now()->subHours((int) config('x_change_payment_events.review_after_hours')))) {
            $this->transition(['state' => 'needs_review', 'failure_code' => 'EVENT_RECONCILIATION_WINDOW_EXCEEDED']);

            return;
        }
        $payment = XChangePayment::query()->where('external_reference', $event->external_reference)->first();
        if ($payment === null || $payment->pay_code === null) {
            $this->release(60);

            return;
        }
        $startsAt = ReconcileQrPhPayment::startsAt();
        if ($startsAt !== null && $payment->created_at->lt($startsAt)) {
            $this->transition(['state' => 'needs_review', 'failure_code' => 'EVENT_BEFORE_RECONCILIATION_CUTOFF']);

            return;
        }
        if (! hash_equals($payment->pay_code, $event->pay_code) || $payment->amount_cents !== $event->amount_minor || $payment->currency !== $event->currency) {
            $this->transition(['state' => 'needs_review', 'failure_code' => 'EVENT_OBLIGATION_MISMATCH']);

            return;
        }
        $result = $confirm->handle($payment->paymentSchedule, 'partner_notification');
        if ($result['status'] === 'needs_review') {
            $this->transition(['state' => 'needs_review', 'failure_code' => 'AUTHORITATIVE_RECONCILIATION_REVIEW']);
        } elseif ($result['paid']) {
            $this->transition(['state' => 'processed', 'processed_at' => now()]);
        } else {
            $this->release(60);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function transition(array $attributes): void
    {
        XChangePaymentEvent::query()->whereKey($this->eventId)->where('state', 'accepted')->update($attributes);
    }

    public function failed(?Throwable $exception): void
    {
        XChangePaymentEvent::query()->whereKey($this->eventId)->where('state', 'accepted')->update(['state' => 'needs_review', 'failure_code' => 'EVENT_RECONCILIATION_FAILED']);
        Log::warning('Payment event reconciliation requires review.', ['event_record_id' => $this->eventId]);
    }
}
