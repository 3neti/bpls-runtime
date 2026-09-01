<?php

namespace App\Integrations;

use App\Exceptions\XChangePartnerApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class XChangePartnerApiClient
{
    /**
     * @return array{code: string, voucher_id: string|null, external_reference: string, consumer_status: string|null, payer_url: string}
     */
    public function issuePayable(
        int $amountCents,
        string $externalReference,
        string $bindingSecret,
        string $idempotencyKey,
    ): array {
        $response = $this->authorizedRequest(
            'POST',
            '/api/partner/v1/pay-codes',
            [
                'voucher_type' => 'payable',
                'amount' => round($amountCents / 100, 2),
                'target_amount' => round($amountCents / 100, 2),
                'currency' => 'PHP',
                'purpose' => 'BPLS payment',
                'count' => 1,
                'cash' => [
                    'amount' => 0,
                    'currency' => 'PHP',
                    'settlement_rail' => $this->requiredConfig('settlement_rail'),
                    'validation' => ['secret' => $bindingSecret],
                ],
                'inputs' => ['fields' => []],
                'feedback' => ['email' => null],
                'rider' => ['message' => 'BPLS payment'],
                'external_reference' => $externalReference,
                'metadata' => ['source' => 'bpls'],
            ],
            $idempotencyKey,
        );

        $data = $this->responseData($response, 'PAYABLE_RESPONSE_INVALID');
        $returnedReference = data_get($data, 'external_reference');

        if (! is_string($returnedReference) || ! hash_equals($externalReference, $returnedReference)) {
            throw new XChangePartnerApiException(
                'EXTERNAL_REFERENCE_MISMATCH',
                'x-change returned a different payment correlation reference.',
            );
        }

        $code = data_get($data, 'code');
        if (! is_string($code) || $code === '') {
            throw new XChangePartnerApiException('PAYABLE_RESPONSE_INVALID', 'x-change did not return a payment code.');
        }

        $payerUrl = data_get($data, 'links.pay');
        if (! is_string($payerUrl) || ! str_ends_with($payerUrl, "/x/pay/{$code}")) {
            throw new XChangePartnerApiException('PAYER_URL_MISMATCH', 'x-change did not return the payable payer route.');
        }

        return [
            'code' => $code,
            'voucher_id' => is_scalar(data_get($data, 'voucher_id')) ? (string) data_get($data, 'voucher_id') : null,
            'external_reference' => $returnedReference,
            'consumer_status' => is_string(data_get($data, 'consumer_status')) ? data_get($data, 'consumer_status') : null,
            'payer_url' => $payerUrl,
        ];
    }

    /**
     * @return array{reference: string, status: string, expires_at: string, provider: string|null, amount_cents: int, mime_type: string, base64_payload: string}
     */
    public function createPaymentAttempt(
        string $code,
        int $expectedAmountCents,
        string $idempotencyKey,
    ): array {
        $response = $this->authorizedRequest(
            'POST',
            "/api/partner/v1/pay-codes/{$code}/payment-attempts",
            [],
            $idempotencyKey,
        );
        $data = $this->responseData($response, 'PAYMENT_ATTEMPT_RESPONSE_INVALID');
        $attempt = data_get($data, 'attempt');

        if (! is_array($attempt)) {
            throw new XChangePartnerApiException('PAYMENT_ATTEMPT_RESPONSE_INVALID', 'x-change did not return a payment attempt.');
        }

        $reference = data_get($attempt, 'reference');
        $status = data_get($attempt, 'status');
        $expiresAt = data_get($attempt, 'expires_at');
        $mimeType = data_get($attempt, 'qr_code.mime_type');
        $base64Payload = data_get($attempt, 'qr_code.base64_payload');
        $amountCents = data_get($attempt, 'amount_minor');

        if (! is_string($reference) || $reference === ''
            || ! is_string($status) || $status === ''
            || ! is_string($expiresAt) || $expiresAt === '' || strtotime($expiresAt) === false
            || $mimeType !== 'image/png'
            || ! is_string($base64Payload) || ! $this->isPngPayload($base64Payload)
            || ! is_numeric($amountCents) || (int) $amountCents !== $expectedAmountCents) {
            throw new XChangePartnerApiException('PAYMENT_ATTEMPT_RESPONSE_INVALID', 'x-change returned an invalid QR Ph payment attempt.');
        }

        return [
            'reference' => $reference,
            'status' => $status,
            'expires_at' => $expiresAt,
            'provider' => is_string(data_get($attempt, 'provider')) ? data_get($attempt, 'provider') : null,
            'amount_cents' => (int) $amountCents,
            'mime_type' => $mimeType,
            'base64_payload' => $base64Payload,
        ];
    }

    /**
     * @return array{external_reference: string|null, consumer_status: string|null, provider_status: string|null, collected_total_cents: int, target_amount_cents: int, is_fully_collected: bool, is_terminal: bool}
     */
    public function inquire(string $code): array
    {
        $response = $this->authorizedRequest('GET', "/api/partner/v1/pay-codes/{$code}");
        $data = $this->responseData($response, 'INQUIRY_RESPONSE_INVALID');
        $collected = data_get($data, 'collection.collected_total_minor');
        $target = data_get($data, 'collection.target_amount_minor');

        if (! is_numeric($collected) || ! is_numeric($target)) {
            throw new XChangePartnerApiException('INQUIRY_RESPONSE_INVALID', 'x-change returned invalid collection totals.');
        }

        return [
            'external_reference' => is_string(data_get($data, 'external_reference')) ? data_get($data, 'external_reference') : null,
            'consumer_status' => is_string(data_get($data, 'consumer_status')) ? data_get($data, 'consumer_status') : null,
            'provider_status' => is_string(data_get($data, 'status.key')) ? data_get($data, 'status.key') : null,
            'collected_total_cents' => (int) $collected,
            'target_amount_cents' => (int) $target,
            'is_fully_collected' => data_get($data, 'collection.is_fully_collected') === true,
            'is_terminal' => data_get($data, 'status.is_terminal') === true,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function authorizedRequest(string $method, string $path, array $payload = [], ?string $idempotencyKey = null): Response
    {
        $refreshedAfterUnauthorized = false;
        $transientAttempts = 0;

        while (true) {
            try {
                $request = $this->request($this->accessToken($refreshedAfterUnauthorized));
                if ($idempotencyKey !== null) {
                    $request = $request->withHeader('Idempotency-Key', $idempotencyKey);
                }

                $response = $method === 'GET'
                    ? $request->get($path)
                    : $request->post($path, $payload);
            } catch (ConnectionException) {
                if (++$transientAttempts < 3) {
                    usleep(200_000 * $transientAttempts);

                    continue;
                }

                throw new XChangePartnerApiException('NETWORK_UNAVAILABLE', 'x-change could not be reached.', true);
            }

            if ($response->status() === 401 && ! $refreshedAfterUnauthorized) {
                $refreshedAfterUnauthorized = true;
                $this->forgetAccessToken();

                continue;
            }

            if ($response->serverError() && ++$transientAttempts < 3) {
                usleep(200_000 * $transientAttempts);

                continue;
            }

            if ($response->status() === 409 || ($response->status() === 422 && str_contains(
                mb_strtolower((string) data_get($response->json(), 'message')),
                'external reference',
            ))) {
                throw new XChangePartnerApiException('EXTERNAL_REFERENCE_CONFLICT', 'x-change rejected changed terms for an existing payment obligation.');
            }

            if ($response->status() === 422) {
                throw new XChangePartnerApiException('VALIDATION_ERROR', 'x-change rejected the payment request.');
            }

            if (! $response->successful()) {
                throw new XChangePartnerApiException(
                    'PARTNER_API_UNAVAILABLE',
                    'x-change could not complete the payment request.',
                    $response->serverError(),
                );
            }

            return $response;
        }
    }

    private function accessToken(bool $forceRefresh = false): string
    {
        $clientId = $this->requiredConfig('client_id');
        $cacheKey = 'x-change:partner-token:'.hash('sha256', $clientId);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $request = Http::acceptJson()
                ->asJson()
                ->connectTimeout($this->connectTimeoutSeconds())
                ->timeout($this->timeoutSeconds());
            $tokenEndpoint = $this->requiredConfig('token_endpoint');
            $response = str_starts_with($tokenEndpoint, 'http://') || str_starts_with($tokenEndpoint, 'https://')
                ? $request->post($tokenEndpoint, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $this->requiredConfig('client_secret'),
                    'scope' => $this->requiredConfig('scope'),
                ])
                : $request
                    ->baseUrl($this->requiredConfig('base_url'))
                    ->post('/'.ltrim($tokenEndpoint, '/'), [
                        'grant_type' => 'client_credentials',
                        'client_id' => $clientId,
                        'client_secret' => $this->requiredConfig('client_secret'),
                        'scope' => $this->requiredConfig('scope'),
                    ]);
        } catch (ConnectionException) {
            throw new XChangePartnerApiException('OAUTH_UNAVAILABLE', 'x-change authorization could not be reached.', true);
        }

        $token = data_get($response->json(), 'access_token');
        $expiresIn = data_get($response->json(), 'expires_in');

        if (! $response->successful() || ! is_string($token) || $token === '' || ! is_numeric($expiresIn)) {
            throw new XChangePartnerApiException('OAUTH_FAILED', 'x-change authorization failed.', $response->serverError());
        }

        $ttl = max(1, (int) $expiresIn - (int) config('services.x_change.token_refresh_leeway_seconds', 60));
        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        return $token;
    }

    private function forgetAccessToken(): void
    {
        $clientId = $this->requiredConfig('client_id');
        Cache::forget('x-change:partner-token:'.hash('sha256', $clientId));
    }

    private function request(string $accessToken): PendingRequest
    {
        return Http::baseUrl($this->requiredConfig('base_url'))
            ->acceptJson()
            ->asJson()
            ->withToken($accessToken)
            ->connectTimeout($this->connectTimeoutSeconds())
            ->timeout($this->timeoutSeconds());
    }

    /** @return array<string, mixed> */
    private function responseData(Response $response, string $errorCode): array
    {
        $data = data_get($response->json(), 'data');

        if (! is_array($data)) {
            throw new XChangePartnerApiException($errorCode, 'x-change returned an unexpected response.');
        }

        return $data;
    }

    private function isPngPayload(string $base64Payload): bool
    {
        $decoded = base64_decode($base64Payload, true);

        return is_string($decoded) && str_starts_with($decoded, "\x89PNG\r\n\x1a\n");
    }

    private function requiredConfig(string $key): string
    {
        $value = config("services.x_change.{$key}");

        if (! is_string($value) || $value === '') {
            throw new XChangePartnerApiException('CONFIGURATION_MISSING', 'QR Ph payment is not configured.');
        }

        return $key === 'base_url' ? rtrim($value, '/') : $value;
    }

    private function timeoutSeconds(): int
    {
        return max(1, (int) config('services.x_change.timeout_seconds', 15));
    }

    private function connectTimeoutSeconds(): int
    {
        return max(1, (int) config('services.x_change.connect_timeout_seconds', 5));
    }
}
