<?php

namespace App\Console\Commands;

use App\Jobs\ProcessXChangePaymentEvent;
use App\Models\XChangePaymentEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('bpls:dispatch-payment-events')]
#[Description('Recover durably accepted payment event dispatches without recording payment directly')]
class DispatchXChangePaymentEvents extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('x_change_payment_events.enabled')) {
            return self::SUCCESS;
        }
        XChangePaymentEvent::query()->where('state', 'accepted')->where('created_at', '<', now()->subHours((int) config('x_change_payment_events.review_after_hours')))->update(['state' => 'needs_review', 'failure_code' => 'EVENT_RECONCILIATION_WINDOW_EXCEEDED']);
        foreach (XChangePaymentEvent::query()->where('state', 'accepted')->orderBy('updated_at')->orderBy('id')->limit(100)->get() as $event) {
            try {
                ProcessXChangePaymentEvent::dispatch($event->id)->afterCommit();
                $event->touch();
            } catch (Throwable) {
                Log::warning('Payment inbox dispatch unavailable; accepted events retained.', ['event_record_id' => $event->id]);

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
