<?php

use App\Exceptions\XChangePartnerApiException;
use App\Integrations\XChangePartnerApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('cache.default', 'array');
    config()->set('services.x_change', [
        'base_url' => 'https://x-change.example.test',
        'token_endpoint' => '/oauth/token',
        'client_id' => 'synthetic-client',
        'client_secret' => 'synthetic-secret',
        'scope' => 'pay-codes:estimate pay-codes:issue pay-codes:read pay-codes:pay',
        'settlement_rail' => 'INSTAPAY',
        'token_refresh_leeway_seconds' => 60,
        'connect_timeout_seconds' => 2,
        'timeout_seconds' => 5,
    ]);
    Cache::flush();
});

test('client caches OAuth and implements the live payable QR and inquiry contract', function () {
    $png = base64_encode("\x89PNG\r\n\x1a\nsynthetic");
    Http::fake([
        'x-change.example.test/oauth/token' => Http::response([
            'token_type' => 'Bearer',
            'expires_in' => 900,
            'access_token' => 'synthetic-token',
        ]),
        'x-change.example.test/api/partner/v1/pay-codes' => Http::response([
            'success' => true,
            'data' => [
                'voucher_id' => 192,
                'code' => 'TEST',
                'external_reference' => 'bpls-obligation-1',
                'consumer_status' => 'payable',
                'links' => ['pay' => 'https://x-change.example.test/x/pay/TEST'],
            ],
        ], 201),
        'x-change.example.test/api/partner/v1/pay-codes/TEST/payment-attempts' => Http::response([
            'success' => true,
            'data' => [
                'attempt' => [
                    'reference' => 'attempt-1',
                    'status' => 'awaiting_payment',
                    'provider' => 'synthetic-provider',
                    'amount_minor' => 12_550,
                    'expires_at' => now()->addMinutes(10)->toIso8601String(),
                    'qr_code' => [
                        'mime_type' => 'image/png',
                        'base64_payload' => $png,
                    ],
                ],
            ],
        ], 201),
        'x-change.example.test/api/partner/v1/pay-codes/TEST' => Http::response([
            'success' => true,
            'data' => [
                'external_reference' => 'bpls-obligation-1',
                'consumer_status' => 'processing',
                'status' => ['key' => 'active', 'is_terminal' => false],
                'collection' => [
                    'collected_total_minor' => 0,
                    'target_amount_minor' => 12_550,
                    'is_fully_collected' => false,
                ],
            ],
        ]),
    ]);

    $client = app(XChangePartnerApiClient::class);
    $issued = $client->issuePayable(12_550, 'bpls-obligation-1', '123456', 'issue-key');
    $attempt = $client->createPaymentAttempt('TEST', 12_550, 'attempt-key');
    $inquiry = $client->inquire('TEST');

    expect($issued)->toMatchArray([
        'code' => 'TEST',
        'voucher_id' => '192',
        'external_reference' => 'bpls-obligation-1',
        'payer_url' => 'https://x-change.example.test/x/pay/TEST',
    ])->and($attempt['base64_payload'])->toBe($png)
        ->and($attempt['amount_cents'])->toBe(12_550)
        ->and($inquiry['target_amount_cents'])->toBe(12_550)
        ->and($inquiry['is_fully_collected'])->toBeFalse();

    Http::assertSentCount(4);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://x-change.example.test/api/partner/v1/pay-codes'
        && $request->header('Idempotency-Key') === ['issue-key']
        && $request['voucher_type'] === 'payable'
        && $request['amount'] === 125.5
        && $request['target_amount'] === 125.5
        && $request['currency'] === 'PHP'
        && $request['purpose'] === 'BPLS payment'
        && $request['count'] === 1
        && $request['cash'] === [
            'amount' => 0,
            'currency' => 'PHP',
            'settlement_rail' => 'INSTAPAY',
            'validation' => ['secret' => '123456'],
        ]
        && $request['inputs'] === ['fields' => []]
        && $request['feedback'] === ['email' => null]
        && $request['rider'] === ['message' => 'BPLS payment']
        && $request['external_reference'] === 'bpls-obligation-1'
        && $request['metadata'] === ['source' => 'bpls']);
});

test('client refreshes once after 401 and reuses the same POST idempotency key', function () {
    Http::fakeSequence()
        ->push(['expires_in' => 900, 'access_token' => 'first-token'])
        ->push([], 401)
        ->push(['expires_in' => 900, 'access_token' => 'second-token'])
        ->push(['data' => [
            'code' => 'RETRY',
            'external_reference' => 'bpls-obligation-2',
            'links' => ['pay' => 'https://x-change.example.test/x/pay/RETRY'],
        ]], 201);

    app(XChangePartnerApiClient::class)->issuePayable(10_000, 'bpls-obligation-2', '123456', 'same-key');

    $payableRequests = collect(Http::recorded())
        ->filter(fn (array $record): bool => str_ends_with($record[0]->url(), '/api/partner/v1/pay-codes'));

    expect($payableRequests)->toHaveCount(2)
        ->and($payableRequests->pluck('0')->every(
            fn (Request $request): bool => $request->header('Idempotency-Key') === ['same-key'],
        ))->toBeTrue();
});

test('client retries server failures with the same idempotency key', function () {
    Http::fakeSequence()
        ->push(['expires_in' => 900, 'access_token' => 'synthetic-token'])
        ->push([], 500)
        ->push(['data' => [
            'code' => 'RETRY',
            'external_reference' => 'bpls-obligation-3',
            'links' => ['pay' => 'https://x-change.example.test/x/pay/RETRY'],
        ]], 201);

    app(XChangePartnerApiClient::class)->issuePayable(10_000, 'bpls-obligation-3', '123456', 'same-key');

    $payableRequests = collect(Http::recorded())
        ->filter(fn (array $record): bool => str_ends_with($record[0]->url(), '/api/partner/v1/pay-codes'));

    expect($payableRequests)->toHaveCount(2)
        ->and($payableRequests->pluck('0')->every(
            fn (Request $request): bool => $request->header('Idempotency-Key') === ['same-key'],
        ))->toBeTrue();
});

test('client fails closed on validation external reference conflict and correlation mismatch', function (int $status, array $response, string $expectedCode) {
    Http::fakeSequence()
        ->push(['expires_in' => 900, 'access_token' => 'synthetic-token'])
        ->push($response, $status);

    try {
        app(XChangePartnerApiClient::class)->issuePayable(
            10_000,
            'bpls-obligation-4',
            '123456',
            'issue-key',
        );
        $this->fail('Expected the x-change request to fail closed.');
    } catch (XChangePartnerApiException $exception) {
        expect($exception->errorCode)->toBe($expectedCode);
    }
})->with([
    'validation' => [422, ['message' => 'Malformed request'], 'VALIDATION_ERROR'],
    'conflict' => [409, ['message' => 'External reference conflict'], 'EXTERNAL_REFERENCE_CONFLICT'],
    'mismatch' => [201, ['data' => ['code' => 'TEST', 'external_reference' => 'different']], 'EXTERNAL_REFERENCE_MISMATCH'],
]);

test('client rejects a claim-only response for a payable Pay Code', function () {
    Http::fakeSequence()
        ->push(['expires_in' => 900, 'access_token' => 'synthetic-token'])
        ->push(['data' => [
            'code' => 'CLAIM-ONLY',
            'external_reference' => 'bpls-obligation-5',
            'links' => ['redeem' => 'https://x-change.example.test/x/claim/CLAIM-ONLY'],
        ]], 201);

    expect(fn () => app(XChangePartnerApiClient::class)->issuePayable(
        10_000,
        'bpls-obligation-5',
        '123456',
        'issue-key',
    ))->toThrow(
        XChangePartnerApiException::class,
        'x-change did not return the payable payer route.',
    );
});
