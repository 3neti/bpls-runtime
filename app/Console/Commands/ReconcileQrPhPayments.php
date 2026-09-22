<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileQrPhPayment;
use App\Models\XChangePayment;
use Illuminate\Console\Command;

class ReconcileQrPhPayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Queue due QR Ph inquiries without relying on an open browser';

    public function handle(): int
    {
        if (! config('payment_reconciliation.enabled')) {
            return self::SUCCESS;
        }
        $startsAt = ReconcileQrPhPayment::startsAt();
        XChangePayment::query()->whereNotNull('pay_code')
            ->when($startsAt !== null, fn ($query) => $query->where('created_at', '>=', $startsAt))
            ->whereIn('reconciliation_state', ['pending', 'error'])
            ->where(fn ($query) => $query->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))
            ->orderBy('next_check_at')->orderBy('id')->limit(100)->get()
            ->each(fn (XChangePayment $payment) => ReconcileQrPhPayment::dispatch($payment->id));

        return self::SUCCESS;
    }
}
