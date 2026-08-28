<?php

namespace App\Actions;

use App\Assessment\AssessmentSnapshotFingerprint;
use App\Exceptions\XChangePartnerApiException;
use App\Integrations\XChangePartnerApiClient;
use App\Models\PaymentSchedule;
use App\Models\XChangePayment;
use App\Models\XChangePaymentAttempt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class InitiateQrPhPayment
{
    public function __construct(
        private readonly EnsureQrPhPaymentEligible $ensureEligible,
        private readonly AssessmentSnapshotFingerprint $fingerprint,
        private readonly XChangePartnerApiClient $client,
    ) {}

    /**
     * @return array{amount_cents: int, status: string, expires_at: string, qr_data_url: string}
     */
    public function handle(PaymentSchedule $paymentSchedule): array
    {
        return Cache::lock("qr-ph:payment-schedule:{$paymentSchedule->id}", 20)->block(10, function () use ($paymentSchedule): array {
            $paymentSchedule = $this->ensureEligible->handle($paymentSchedule->fresh());
            $payment = $this->payment($paymentSchedule);

            try {
                if ($payment->pay_code === null) {
                    $issued = $this->client->issuePayable(
                        $payment->amount_cents,
                        $payment->external_reference,
                        $payment->binding_secret,
                        $payment->issue_idempotency_key,
                    );

                    $payment->forceFill([
                        'status' => 'issued',
                        'pay_code' => $issued['code'],
                        'voucher_id' => $issued['voucher_id'],
                        'consumer_status' => $issued['consumer_status'],
                        'last_error_code' => null,
                    ])->save();
                }

                $attempt = $this->attempt($payment);
                $created = $this->client->createPaymentAttempt(
                    $payment->pay_code,
                    $payment->amount_cents,
                    $attempt->idempotency_key,
                );

                $attempt->forceFill([
                    'reference' => $created['reference'],
                    'status' => $created['status'],
                    'provider' => $created['provider'],
                    'amount_cents' => $created['amount_cents'],
                    'expires_at' => $created['expires_at'],
                ])->save();
                $payment->forceFill([
                    'status' => 'awaiting_payment',
                    'provider_status' => $created['status'],
                    'last_error_code' => null,
                ])->save();

                return [
                    'amount_cents' => $payment->amount_cents,
                    'status' => $created['status'],
                    'expires_at' => $created['expires_at'],
                    'qr_data_url' => 'data:'.$created['mime_type'].';base64,'.$created['base64_payload'],
                ];
            } catch (XChangePartnerApiException $exception) {
                $payment->forceFill(['last_error_code' => $exception->errorCode])->save();
                throw $exception;
            }
        });
    }

    private function payment(PaymentSchedule $paymentSchedule): XChangePayment
    {
        $snapshotHash = $this->fingerprint->hash($paymentSchedule->assessment);
        $termsHash = hash('sha256', implode('|', [
            $paymentSchedule->id,
            $paymentSchedule->assessment_id,
            $snapshotHash,
            $paymentSchedule->total_amount_cents,
            'PHP',
        ]));
        $payment = XChangePayment::query()->firstOrCreate(
            ['payment_schedule_id' => $paymentSchedule->id],
            [
                'assessment_id' => $paymentSchedule->assessment_id,
                'external_reference' => "bpls-ps-{$paymentSchedule->id}-".Str::lower((string) Str::ulid()),
                'issue_idempotency_key' => (string) Str::uuid(),
                'terms_hash' => $termsHash,
                'amount_cents' => $paymentSchedule->total_amount_cents,
                'currency' => 'PHP',
                'binding_secret' => (string) random_int(100000, 999999),
                'status' => 'pending_issuance',
            ],
        );

        if ($payment->assessment_id !== $paymentSchedule->assessment_id
            || $payment->amount_cents !== $paymentSchedule->total_amount_cents
            || ! hash_equals($payment->terms_hash, $termsHash)) {
            throw new XChangePartnerApiException(
                'PAYMENT_TERMS_CONFLICT',
                'The saved payment request no longer matches the approved obligation.',
            );
        }

        return $payment;
    }

    private function attempt(XChangePayment $payment): XChangePaymentAttempt
    {
        $attempt = $payment->attempts()->latest('id')->first();

        if ($attempt instanceof XChangePaymentAttempt
            && ($attempt->expires_at === null || $attempt->expires_at->isFuture())
            && in_array($attempt->status, ['requested', 'awaiting_payment'], true)) {
            return $attempt;
        }

        return $payment->attempts()->create([
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'requested',
            'amount_cents' => $payment->amount_cents,
        ]);
    }
}
