<?php

use App\Integrations\XChangePartnerApiClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('live x-change testing issues a payable QR and supports inquiry', function () {
    if (env('XCHANGE_LIVE_TEST') !== '1') {
        $this->markTestSkipped('Set XCHANGE_LIVE_TEST=1 only for an authorized testing-environment smoke test.');
    }

    $externalReference = 'bpls-live-smoke-'.Str::lower((string) Str::ulid());
    $client = app(XChangePartnerApiClient::class);
    $issued = $client->issuePayable(
        10_000,
        $externalReference,
        (string) random_int(100000, 999999),
        (string) Str::uuid(),
    );
    $attempt = $client->createPaymentAttempt(
        $issued['code'],
        10_000,
        (string) Str::uuid(),
    );
    $inquiry = $client->inquire($issued['code']);

    expect($issued['external_reference'])->toBe($externalReference)
        ->and($attempt['mime_type'])->toBe('image/png')
        ->and($attempt['amount_cents'])->toBe(10_000)
        ->and($inquiry['external_reference'])->toBe($externalReference)
        ->and($inquiry['target_amount_cents'])->toBe(10_000);

    $evidence = [
        'recorded_at' => now()->toIso8601String(),
        'environment' => 'x-change-testing',
        'external_reference' => $externalReference,
        'pay_code' => $issued['code'],
        'amount_cents' => 10_000,
        'attempt_reference' => $attempt['reference'],
        'attempt_status' => $attempt['status'],
        'attempt_expires_at' => $attempt['expires_at'],
        'qr_png_returned' => true,
        'inquiry_succeeded' => true,
        'is_fully_collected' => $inquiry['is_fully_collected'],
        'result' => 'passed',
        'contains_credentials_or_tokens' => false,
    ];

    Storage::disk('local')->put(
        'x-change-live-smoke/'.$externalReference.'.json',
        json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
    );
});
