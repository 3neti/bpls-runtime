<?php

use App\Actions\ConfirmQrPhPayment;
use App\Actions\InitiateQrPhPayment;
use App\Jobs\ProcessXChangePaymentEvent;
use App\Models\TreasuryCollection;
use App\Models\XChangePayment;
use App\Models\XChangePaymentEvent;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('x_change_payment_events.enabled', true);
    config()->set('x_change_payment_events.secret', str_repeat('fixture-only-', 4));
    config()->set('x_change_payment_events.partner_reference', 'fixture-partner');
    Queue::fake();
    Http::preventStrayRequests();
});

function signedPaymentEvent(array $overrides = [], ?int $timestamp = null, ?string $secret = null): array
{
    $body = json_encode(array_replace([
        'type' => 'payment.collected.v1', 'event_id' => (string) Str::uuid(),
        'occurred_at' => now()->toIso8601String(), 'partner_reference' => 'fixture-partner',
        'external_reference' => 'fixture-obligation', 'pay_code' => 'TEST',
        'collection_id' => 'fixture-collection', 'amount_minor' => 397500, 'currency' => 'PHP',
    ], $overrides), JSON_THROW_ON_ERROR);
    $time = (string) ($timestamp ?? now()->timestamp);

    return [$body, ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_XCHANGE_TIMESTAMP' => $time,
        'HTTP_X_XCHANGE_SIGNATURE' => 'sha256='.hash_hmac('sha256', $time.'.'.$body, $secret ?? config('x_change_payment_events.secret'))]];
}

test('signed notification is durably accepted without trusting it as payment', function () {
    [$body, $headers] = signedPaymentEvent();
    $this->call('POST', '/integrations/x-change/payment-events', [], [], [], $headers, $body)->assertStatus(202);
    expect(XChangePaymentEvent::query()->count())->toBe(1);
    expect(TreasuryCollection::query()->count())->toBe(0);
    Queue::assertPushed(ProcessXChangePaymentEvent::class);
    Http::assertNothingSent();
});

test('identical retry is idempotent and altered reuse of event ID is rejected', function () {
    $id = (string) Str::uuid();
    [$body, $headers] = signedPaymentEvent(['event_id' => $id]);
    $this->call('POST', '/integrations/x-change/payment-events', [], [], [], $headers, $body)->assertStatus(202);
    $this->call('POST', '/integrations/x-change/payment-events', [], [], [], $headers, $body)->assertOk();
    [$changed, $signed] = signedPaymentEvent(['event_id' => $id, 'amount_minor' => 1]);
    $this->call('POST', '/integrations/x-change/payment-events', [], [], [], $signed, $changed)->assertConflict();
    expect(XChangePaymentEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessXChangePaymentEvent::class, 1);
});

test('unsigned stale tampered and wrong-partner notifications cannot enqueue work', function () {
    foreach ([[[], now()->subMinutes(6)->timestamp, null], [[], null, 'wrong-secret'], [['partner_reference' => 'another-partner'], null, null]] as [$fields, $time, $secret]) {
        [$body, $headers] = signedPaymentEvent($fields, $time, $secret);
        $this->call('POST', '/integrations/x-change/payment-events', [], [], [], $headers, $body)->assertStatus(401);
    }
    $this->postJson('/integrations/x-change/payment-events', [])->assertStatus(401);
    expect(XChangePaymentEvent::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('disabled receiver does not acknowledge or mutate payment events', function () {
    config()->set('x_change_payment_events.enabled', false);
    [$body, $headers] = signedPaymentEvent();
    $this->call('POST', '/integrations/x-change/payment-events', [], [], [], $headers, $body)->assertStatus(503);
    expect(XChangePaymentEvent::query()->count())->toBe(0);
});

test('durable inbox sweep recovers a lost dispatch without trusting event amounts', function () {
    $event = XChangePaymentEvent::factory()->create();
    $this->artisan('bpls:dispatch-payment-events')->assertSuccessful();
    Queue::assertPushed(ProcessXChangePaymentEvent::class, fn ($job) => $job->eventId === $event->id);
    Http::assertNothingSent();
});

test('durable acceptance survives queue dispatch failure', function () {
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Synthetic queue unavailable'));
    [$body, $headers] = signedPaymentEvent();
    $this->call('POST', '/integrations/x-change/payment-events', [], [], [], $headers, $body)->assertStatus(202);
    expect(XChangePaymentEvent::query()->sole()->state)->toBe('accepted');
    Http::assertNothingSent();
});

test('event expiry enters review without contacting provider or changing collections', function () {
    $event = XChangePaymentEvent::factory()->create(['created_at' => now()->subDays(2)]);
    (new ProcessXChangePaymentEvent($event->id))->handle(app(ConfirmQrPhPayment::class));
    expect($event->fresh()->state)->toBe('needs_review');
    expect(TreasuryCollection::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('unsupported event kinds and currency cannot enqueue confirmation', function () {
    foreach ([['type' => 'payment.pending.v1'], ['currency' => 'USD'], ['amount_minor' => -1]] as $fields) {
        [$body, $headers] = signedPaymentEvent($fields);
        $this->call('POST', '/integrations/x-change/payment-events', [], [], [], $headers, $body)->assertUnprocessable();
    }
    expect(XChangePaymentEvent::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('provider collection identity deduplicates different event IDs and rejects contradictory claims', function () {
    [$first, $headers] = signedPaymentEvent();
    $this->call('POST', '/integrations/x-change/payment-events', [], [], [], $headers, $first)->assertStatus(202);
    [$sameCollection, $headers] = signedPaymentEvent();
    $this->call('POST', '/integrations/x-change/payment-events', [], [], [], $headers, $sameCollection)->assertOk();
    [$conflict, $headers] = signedPaymentEvent(['amount_minor' => 123]);
    $this->call('POST', '/integrations/x-change/payment-events', [], [], [], $headers, $conflict)->assertConflict();
    expect(XChangePaymentEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessXChangePaymentEvent::class, 1);
});

test('forced event identity collision returns conflict rather than server error', function () {
    XChangePaymentEvent::creating(function (XChangePaymentEvent $event) {
        DB::table('x_change_payment_events')->insert(array_replace($event->getAttributes(), [
            'provider_collection_id' => 'competing-collection',
            'created_at' => now(), 'updated_at' => now(),
        ]));
    });
    try {
        [$body, $headers] = signedPaymentEvent();
        $this->call('POST', '/integrations/x-change/payment-events', [], [], [], $headers, $body)->assertConflict();
        // This deterministic collision is inside Eloquent's savepoint, so both
        // fixture inserts roll back. Real cross-connection races need DB acceptance.
        expect(XChangePaymentEvent::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    } finally {
        XChangePaymentEvent::flushEventListeners();
    }
});

test('sweep queue outage retains accepted events and retries after bounded unique lease expiry', function () {
    $event = XChangePaymentEvent::factory()->create();
    $bus = app(Dispatcher::class);
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Synthetic queue unavailable'));
    $this->artisan('bpls:dispatch-payment-events')->assertFailed();
    expect($event->fresh()->state)->toBe('accepted');
    Bus::swap($bus);
    $this->travel(181)->seconds();
    $this->artisan('bpls:dispatch-payment-events')->assertSuccessful();
    Queue::assertPushed(ProcessXChangePaymentEvent::class, fn ($job) => $job->eventId === $event->id);
    Http::assertNothingSent();
});

test('notification workers reject inline queues and nonshared unique locks', function () {
    config()->set('queue.connections.payments.driver', 'sync');
    expect(fn () => new ProcessXChangePaymentEvent(1))->toThrow(LogicException::class);
    config()->set('queue.connections.payments.driver', 'database');
    config()->set('x_change_payment_events.lock_store', 'array');
    expect(fn () => new ProcessXChangePaymentEvent(1))->toThrow(LogicException::class);
});

test('event worker uses authoritative inquiry and duplicate processing creates only one collection', function () {
    config()->set('services.x_change', ['base_url' => 'https://x-change.example.test', 'token_endpoint' => '/oauth/token', 'client_id' => 'synthetic-client', 'client_secret' => 'synthetic-secret', 'scope' => 'pay-codes:read', 'settlement_rail' => 'INSTAPAY']);
    [, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfixture"), true, true);
    app(InitiateQrPhPayment::class)->handle($schedule);
    $payment = XChangePayment::query()->sole();
    $event = XChangePaymentEvent::factory()->create([
        'external_reference' => $payment->external_reference,
        'pay_code' => $payment->pay_code,
        'amount_minor' => $payment->amount_cents,
    ]);
    $job = new ProcessXChangePaymentEvent($event->id);
    $job->handle(app(ConfirmQrPhPayment::class));
    $job->handle(app(ConfirmQrPhPayment::class));
    expect($event->fresh()->state)->toBe('processed')
        ->and(TreasuryCollection::query()->count())->toBe(1)
        ->and($schedule->fresh()->paid_amount_cents)->toBe($payment->amount_cents);
});

test('event worker preserves synthetic collection provenance and routes it to review', function () {
    config()->set('services.x_change', ['base_url' => 'https://x-change.example.test', 'token_endpoint' => '/oauth/token', 'client_id' => 'synthetic-client', 'client_secret' => 'synthetic-secret', 'scope' => 'pay-codes:read', 'settlement_rail' => 'INSTAPAY']);
    [, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfixture"), true);
    app(InitiateQrPhPayment::class)->handle($schedule);
    app(ConfirmQrPhPayment::class)->handle($schedule);
    $collection = TreasuryCollection::query()->sole();
    $snapshot = $collection->source_snapshot;
    $snapshot['integration_evidence']['synthetic_only'] = true;
    $collection->update(['source_snapshot' => $snapshot]);
    $payment = XChangePayment::query()->sole();
    $event = XChangePaymentEvent::factory()->create([
        'external_reference' => $payment->external_reference,
        'pay_code' => $payment->pay_code,
        'amount_minor' => $payment->amount_cents,
    ]);
    (new ProcessXChangePaymentEvent($event->id))->handle(app(ConfirmQrPhPayment::class));
    expect($event->fresh()->state)->toBe('needs_review')
        ->and(TreasuryCollection::query()->count())->toBe(1)
        ->and($collection->fresh()->source_snapshot)->toBe($snapshot);
});
