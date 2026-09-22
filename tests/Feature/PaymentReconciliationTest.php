<?php

use App\Actions\ConfirmQrPhPayment;
use App\Actions\InitiateQrPhPayment;
use App\Jobs\ReconcileQrPhPayment;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use App\Models\XChangePayment;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.x_change', ['base_url' => 'https://x-change.example.test', 'token_endpoint' => '/oauth/token', 'client_id' => 'synthetic-client', 'client_secret' => 'synthetic-secret', 'scope' => 'pay-codes:read', 'settlement_rail' => 'INSTAPAY']);
    Cache::flush();
    config()->set('payment_reconciliation.starts_at', null);
});

test('rollout cutoff preserves historical requests even when their queued jobs predate activation', function (int $ageHours) {
    Queue::fake();
    [, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfixture"));
    app(InitiateQrPhPayment::class)->handle($schedule);
    $payment = XChangePayment::query()->sole();
    $payment->forceFill(['created_at' => now()->subHours($ageHours)])->save();
    $job = unserialize(serialize(new ReconcileQrPhPayment($payment->id)));
    $before = $payment->getRawOriginal();
    $attemptBefore = $payment->attempts()->sole()->getRawOriginal();
    $scheduleBefore = $schedule->fresh()->getRawOriginal();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    config()->set(['payment_reconciliation.enabled' => true, 'payment_reconciliation.starts_at' => now()->toDateTimeString()]);

    $this->artisan('payments:reconcile')->assertSuccessful();
    $job->handle(app(ConfirmQrPhPayment::class));

    Queue::assertNothingPushed();
    Http::assertNothingSent();
    expect($payment->fresh()->getRawOriginal())->toBe($before)
        ->and($payment->attempts()->sole()->getRawOriginal())->toBe($attemptBefore)
        ->and($schedule->fresh()->getRawOriginal())->toBe($scheduleBefore)
        ->and(TreasuryCollection::query()->count())->toBe(0);
})->with(['recent history' => 1, 'expired history' => 96]);

test('requests at or after rollout cutoff remain eligible for sweep and background settlement', function (int $secondsAfter) {
    Queue::fake();
    $this->freezeSecond();
    [, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfixture"));
    app(InitiateQrPhPayment::class)->handle($schedule);
    $payment = XChangePayment::query()->sole();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*/api/partner/v1/pay-codes/*' => Http::response(qrPhInquiry($schedule, true, false))]);
    config()->set(['payment_reconciliation.enabled' => true, 'payment_reconciliation.starts_at' => now()->subSeconds($secondsAfter)->toDateTimeString()]);

    $this->artisan('payments:reconcile')->assertSuccessful();
    Queue::assertPushed(ReconcileQrPhPayment::class, fn (ReconcileQrPhPayment $job) => $job->paymentId === $payment->id);
    (new ReconcileQrPhPayment($payment->id))->handle(app(ConfirmQrPhPayment::class));

    Http::assertSentCount(1);
    expect($payment->fresh()->reconciliation_state)->toBe('confirmed')
        ->and(TreasuryCollection::query()->count())->toBe(1);
})->with(['inclusive boundary' => 0, 'future request' => 60]);

test('invalid rollout cutoff fails closed before sweep or stale queued job can mutate a request', function (mixed $cutoff) {
    Queue::fake();
    [, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfixture"));
    app(InitiateQrPhPayment::class)->handle($schedule);
    $payment = XChangePayment::query()->sole();
    $payment->forceFill(['created_at' => now()->subDays(4)])->save();
    $before = $payment->getRawOriginal();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    config()->set(['payment_reconciliation.enabled' => true, 'payment_reconciliation.starts_at' => $cutoff]);

    expect(fn () => Artisan::call('payments:reconcile'))->toThrow(InvalidArgumentException::class);
    expect(fn () => (new ReconcileQrPhPayment($payment->id))->handle(app(ConfirmQrPhPayment::class)))
        ->toThrow(InvalidArgumentException::class);

    Queue::assertNothingPushed();
    Http::assertNothingSent();
    expect($payment->fresh()->getRawOriginal())->toBe($before)
        ->and(TreasuryCollection::query()->count())->toBe(0);
})->with(['garbage' => 'invalid', 'relative date' => 'tomorrow', 'calendar overflow' => '2026-02-30 12:00:00', 'whitespace' => ' ', 'wrong type' => false]);

test('background job confirms late settlement without any authenticated browser and remains idempotent', function () {
    [, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfixture"), true, true);
    app(InitiateQrPhPayment::class)->handle($schedule);
    $payment = XChangePayment::query()->sole();
    $payment->attempts()->update(['expires_at' => now()->subHour()]);
    config()->set('payment_reconciliation.enabled', true);
    $job = new ReconcileQrPhPayment($payment->id);
    $job->handle(app(ConfirmQrPhPayment::class));
    $job->handle(app(ConfirmQrPhPayment::class));
    expect(TreasuryCollection::query()->count())->toBe(1)
        ->and(Receipt::query()->count())->toBe(0)
        ->and($payment->fresh()->reconciliation_state)->toBe('confirmed')
        ->and($payment->fresh()->last_checked_at)->not->toBeNull()
        ->and($payment->attempts()->sole()->status)->toBe('collected');
});

test('currency mismatch is persisted as review with no collection', function () {
    [, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfixture"));
    app(InitiateQrPhPayment::class)->handle($schedule);
    $data = qrPhInquiry($schedule, true, false);
    $data['data']['currency'] = 'USD';
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*/api/partner/v1/pay-codes/*' => Http::response($data)]);
    $result = app(ConfirmQrPhPayment::class)->handle($schedule);
    expect($result['paid'])->toBeFalse()->and($result['reconciliation_state'])->toBe('needs_review')
        ->and(TreasuryCollection::query()->count())->toBe(0);
});

test('integrity review cannot be cleared by a later clean inquiry from any check source', function (string $source) {
    [, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfixture"));
    app(InitiateQrPhPayment::class)->handle($schedule);
    $clean = qrPhInquiry($schedule, true, false);
    $mismatch = $clean;
    $mismatch['data']['external_reference'] = 'different-obligation';
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*/api/partner/v1/pay-codes/*' => Http::sequence()->push($mismatch)->push($clean)]);
    $confirm = app(ConfirmQrPhPayment::class);
    expect($confirm->handle($schedule)['reconciliation_state'])->toBe('needs_review');
    $checkedAt = XChangePayment::query()->sole()->last_checked_at;
    $this->travel(5)->minutes();
    $result = $confirm->handle($schedule, $source);
    expect($result['paid'])->toBeFalse()
        ->and($result['reconciliation_state'])->toBe('needs_review')
        ->and(XChangePayment::query()->sole()->last_error_code)->toBe('EXTERNAL_REFERENCE_MISMATCH')
        ->and(XChangePayment::query()->sole()->last_checked_at->equalTo($checkedAt))->toBeTrue()
        ->and(TreasuryCollection::query()->count())->toBe(0);
    Http::assertSentCount(1);
})->with(['manual', 'background', 'partner_notification', 'simulation_precheck']);

test('a synthetic historical collection never becomes authoritative payment evidence', function () {
    [, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfixture"), true);
    app(InitiateQrPhPayment::class)->handle($schedule);
    app(ConfirmQrPhPayment::class)->handle($schedule);
    $collection = TreasuryCollection::query()->sole();
    $snapshot = $collection->source_snapshot;
    $snapshot['integration_evidence']['synthetic_only'] = true;
    $collection->update(['source_snapshot' => $snapshot]);
    $result = app(ConfirmQrPhPayment::class)->handle($schedule);
    expect($result['paid'])->toBeFalse()->and($result['reconciliation_state'])->toBe('needs_review')
        ->and($collection->fresh()->source_snapshot)->toBe($snapshot)
        ->and(TreasuryCollection::query()->count())->toBe(1);
});

test('pending sweep defaults off and dispatches due requests only when enabled', function () {
    Queue::fake();
    [, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfixture"));
    app(InitiateQrPhPayment::class)->handle($schedule);
    $this->artisan('payments:reconcile')->assertSuccessful();
    Queue::assertNothingPushed();
    config()->set('payment_reconciliation.enabled', true);
    $this->artisan('payments:reconcile')->assertSuccessful();
    Queue::assertPushed(ReconcileQrPhPayment::class);
});

test('a failed queue submission cannot suppress sweep recovery beyond three minutes', function () {
    [, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfixture"));
    app(InitiateQrPhPayment::class)->handle($schedule);
    $payment = XChangePayment::query()->sole();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    config()->set('payment_reconciliation.enabled', true);

    $submissions = 0;
    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')->twice()->andReturnUsing(function (ReconcileQrPhPayment $job) use (&$submissions, $payment): void {
        expect($job->paymentId)->toBe($payment->id);
        if (++$submissions === 1) {
            throw new RuntimeException('Synthetic queue outage');
        }
    });
    app()->instance(Dispatcher::class, $dispatcher);

    expect(fn () => Artisan::call('payments:reconcile'))
        ->toThrow(RuntimeException::class, 'Synthetic queue outage');
    $this->travel(179)->seconds();
    $this->artisan('payments:reconcile')->assertSuccessful();
    expect($submissions)->toBe(1);
    $this->travel(2)->seconds();
    $this->artisan('payments:reconcile')->assertSuccessful();
    expect($submissions)->toBe(2)
        ->and($payment->fresh()->reconciliation_state)->toBe('pending')
        ->and(TreasuryCollection::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('unsafe references amounts and partial settlement require review', function (string $field, mixed $value) {
    [, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfixture"));
    app(InitiateQrPhPayment::class)->handle($schedule);
    $data = qrPhInquiry($schedule, true, false);
    data_set($data, $field, $value);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*/api/partner/v1/pay-codes/*' => Http::response($data)]);
    $result = app(ConfirmQrPhPayment::class)->handle($schedule);
    expect($result['paid'])->toBeFalse()->and($result['reconciliation_state'])->toBe('needs_review')
        ->and(TreasuryCollection::query()->count())->toBe(0);
})->with([
    ['data.external_reference', 'different-obligation'],
    ['data.collection.collected_total_minor', 1],
    ['data.collection.collected_total_minor', 12551],
    ['data.collection.target_amount_minor', 12551],
    ['data.currency', null],
]);

test('provider outage persists safe evidence and recovers on a later sweep', function () {
    [, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfixture"));
    app(InitiateQrPhPayment::class)->handle($schedule);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*/api/partner/v1/pay-codes/*' => Http::sequence()->push([], 503)->push([], 503)->push([], 503)->push(qrPhInquiry($schedule, true, false))]);
    $confirm = app(ConfirmQrPhPayment::class);
    expect($confirm->handle($schedule)['reconciliation_state'])->toBe('error')
        ->and(XChangePayment::query()->sole()->last_error_code)->toBe('PARTNER_API_UNAVAILABLE')
        ->and(TreasuryCollection::query()->count())->toBe(0);
    expect($confirm->handle($schedule)['paid'])->toBeTrue()
        ->and(TreasuryCollection::query()->count())->toBe(1);
});

test('expired review window stops background inquiries without changing liability', function () {
    [, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfixture"));
    app(InitiateQrPhPayment::class)->handle($schedule);
    $payment = XChangePayment::query()->sole();
    $payment->forceFill(['created_at' => now()->subDays(4)])->save();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    config()->set('payment_reconciliation.enabled', true);
    (new ReconcileQrPhPayment($payment->id))->handle(app(ConfirmQrPhPayment::class));
    expect($payment->fresh()->reconciliation_state)->toBe('needs_review')
        ->and($schedule->fresh()->paid_amount_cents)->toBe(0);
    $result = app(ConfirmQrPhPayment::class)->handle($schedule);
    expect($result['paid'])->toBeFalse()
        ->and($result['reconciliation_state'])->toBe('needs_review')
        ->and($payment->fresh()->last_error_code)->toBe('RECONCILIATION_WINDOW_EXHAUSTED');
    Http::assertNothingSent();
});
