<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessXChangePaymentEvent;
use App\Models\XChangePaymentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class XChangePaymentEventController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('x_change_payment_events.secret');
        $partner = config('x_change_payment_events.partner_reference');
        abort_unless(config('x_change_payment_events.enabled') && is_string($secret) && strlen($secret) >= 32 && is_string($partner) && $partner !== '', 503);
        $body = $request->getContent();
        abort_if(strlen($body) > 16384, 413);
        $timestamp = $request->header('X-XChange-Timestamp', '');
        $signature = $request->header('X-XChange-Signature', '');
        abort_unless(preg_match('/^[0-9]{10}$/D', $timestamp) === 1 && abs(now()->getTimestamp() - (int) $timestamp) <= (int) config('x_change_payment_events.timestamp_tolerance_seconds'), 401);
        abort_unless(hash_equals('sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret), $signature), 401);
        $data = $request->validate([
            'type' => ['required', 'in:payment.collected.v1'],
            'event_id' => ['required', 'uuid'],
            'occurred_at' => ['required', 'date'],
            'partner_reference' => ['required', 'string', 'max:255'],
            'external_reference' => ['required', 'string', 'max:255'],
            'pay_code' => ['required', 'string', 'max:255'],
            'collection_id' => ['required', 'string', 'max:255'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'in:PHP'],
        ]);
        abort_unless(hash_equals($partner, $data['partner_reference']), 401);
        $event = XChangePaymentEvent::query()->where('event_id', $data['event_id'])->first();
        $event ??= XChangePaymentEvent::query()->firstOrCreate([
            'partner_reference' => $data['partner_reference'],
            'provider_collection_id' => $data['collection_id'],
        ], [
            'event_id' => $data['event_id'],
            'payload_hash' => hash('sha256', $body),
            'partner_reference' => $data['partner_reference'],
            'external_reference' => $data['external_reference'],
            'pay_code' => $data['pay_code'],
            'provider_collection_id' => $data['collection_id'],
            'amount_minor' => $data['amount_minor'],
            'currency' => $data['currency'],
            'occurred_at' => $data['occurred_at'],
        ]);
        if ($event->event_id === $data['event_id']) {
            abort_unless(hash_equals($event->payload_hash, hash('sha256', $body)), 409);
        } else {
            abort_unless($event->external_reference === $data['external_reference']
                && $event->pay_code === $data['pay_code']
                && $event->amount_minor === $data['amount_minor']
                && $event->currency === $data['currency'], 409);
        }
        if ($event->wasRecentlyCreated) {
            try {
                ProcessXChangePaymentEvent::dispatch($event->id)->afterCommit();
            } catch (Throwable) {
                Log::warning('Payment event saved; queue dispatch requires recovery.', ['event_record_id' => $event->id]);
            }
        }

        return response()->json(['accepted' => true, 'event_id' => $event->event_id], $event->wasRecentlyCreated ? 202 : 200);
    }
}
