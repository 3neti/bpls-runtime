<?php

namespace App\Actions;

use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\TreasuryCollectionChannel;
use App\Enums\TreasuryCollectionMethod;
use App\Exceptions\XChangePartnerApiException;
use App\Integrations\XChangePartnerApiClient;
use App\Models\PaymentSchedule;
use App\Models\XChangePayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;

final class ConfirmQrPhPayment
{
    public function __construct(
        private readonly EnsureQrPhPaymentEligible $ensureEligible,
        private readonly XChangePartnerApiClient $client,
        private readonly RecordPaymentScheduleCollection $recordCollection,
        private readonly AssessmentSnapshotFingerprint $fingerprint,
    ) {}

    /** @return array{paid: bool, status: string, collection_id: int|null, receipt_id: int|null, last_checked_at: string|null, reconciliation_state: string} */
    public function handle(PaymentSchedule $paymentSchedule, string $source = 'manual'): array
    {
        return Cache::lock("qr-ph:payment-schedule:{$paymentSchedule->id}", 120)->block(10, function () use ($paymentSchedule, $source): array {
            return DB::transaction(function () use ($paymentSchedule, $source): array {
                $schedule = PaymentSchedule::query()->lockForUpdate()->findOrFail($paymentSchedule->id);
                $payment = $schedule->xChangePayment()->lockForUpdate()->first();
                if ($payment === null || $payment->pay_code === null) {
                    return ['paid' => false, 'status' => 'not_started', 'collection_id' => null, 'receipt_id' => null, 'last_checked_at' => null, 'reconciliation_state' => 'pending'];
                }
                $payment->load('treasuryCollection.receipt');
                if ($payment->treasuryCollection !== null) {
                    $evidence = $payment->treasuryCollection->source_snapshot;
                    if (data_get($evidence, 'integration_evidence.synthetic_only') === true
                        || data_get($evidence, 'integration_evidence.real_funds_moved') === false) {
                        return $this->review($payment, 'SYNTHETIC_COLLECTION_CONFLICT');
                    }
                    $payment->forceFill(['reconciliation_state' => 'confirmed'])->save();
                    $payment->attempts()->update(['status' => 'collected']);

                    return $this->result($payment, true, $payment->treasuryCollection->status->value);
                }
                $payment->forceFill([
                    'last_checked_at' => now(),
                    'reconciliation_source' => in_array($source, ['manual', 'background', 'partner_notification', 'simulation_precheck'], true) ? $source : 'manual',
                    'reconciliation_attempts' => $payment->reconciliation_attempts + 1,
                    'next_check_at' => now()->addSeconds((int) config('payment_reconciliation.interval_seconds', 300)),
                ])->save();
                try {
                    $inquiry = $this->client->inquire($payment->pay_code);
                } catch (XChangePartnerApiException $exception) {
                    $payment->forceFill(['reconciliation_state' => 'error', 'last_error_code' => $exception->errorCode])->save();

                    return $this->result($payment, false, 'error');
                }
                if ($inquiry['external_reference'] === null || ! hash_equals($payment->external_reference, $inquiry['external_reference'])) {
                    return $this->review($payment, 'EXTERNAL_REFERENCE_MISMATCH');
                }
                if ($inquiry['currency'] !== $payment->currency || $payment->currency !== 'PHP') {
                    return $this->review($payment, 'CURRENCY_MISMATCH');
                }
                if ($payment->assessment_id !== $schedule->assessment_id || $payment->amount_cents !== $schedule->total_amount_cents
                    || $inquiry['target_amount_cents'] !== $payment->amount_cents
                    || ($inquiry['collected_total_cents'] !== 0 && $inquiry['collected_total_cents'] !== $payment->amount_cents)
                    || ($inquiry['is_fully_collected'] && $inquiry['collected_total_cents'] !== $payment->amount_cents)) {
                    return $this->review($payment, 'COLLECTION_AMOUNT_MISMATCH');
                }
                $payment->forceFill([
                    'consumer_status' => $inquiry['consumer_status'], 'provider_status' => $inquiry['provider_status'],
                    'collected_total_cents' => $inquiry['collected_total_cents'], 'target_amount_cents' => $inquiry['target_amount_cents'],
                    'is_fully_collected' => $inquiry['is_fully_collected'], 'last_error_code' => null, 'reconciliation_state' => 'pending',
                ])->save();
                if (! $inquiry['is_fully_collected']) {
                    if ($inquiry['collected_total_cents'] > 0) {
                        return $this->review($payment, 'COLLECTION_STATUS_CONFLICT');
                    }

                    return $this->result($payment, false, $inquiry['is_terminal'] ? 'expired' : 'awaiting_payment');
                }
                try {
                    $this->ensureEligible->handle($schedule);
                } catch (LogicException) {
                    return $this->review($payment, 'OBLIGATION_REVIEW_REQUIRED');
                }
                $termsHash = hash('sha256', implode('|', [$schedule->id, $schedule->assessment_id,
                    $this->fingerprint->hash($schedule->assessment), $schedule->total_amount_cents, 'PHP']));
                if (! hash_equals($payment->terms_hash, $termsHash)) {
                    return $this->review($payment, 'PAYMENT_TERMS_CONFLICT');
                }
                $attempt = $payment->attempts()->latest('id')->first();
                $collection = $this->recordCollection->handle($schedule, [
                    'amount_cents' => $payment->amount_cents,
                    'channel' => TreasuryCollectionChannel::Online->value,
                    'method' => TreasuryCollectionMethod::QrPh->value,
                    'reference_number' => $payment->pay_code,
                    'remarks' => 'Authoritative QR Ph collection confirmation.',
                    'integration_evidence' => [
                        'source' => 'authoritative_partner_inquiry', 'synthetic_only' => false,
                        'x_change_payment_id' => $payment->id, 'external_reference' => $payment->external_reference,
                        'pay_code' => $payment->pay_code, 'attempt_reference' => $attempt?->reference,
                        'currency' => $inquiry['currency'], 'collected_total_cents' => $inquiry['collected_total_cents'],
                        'target_amount_cents' => $inquiry['target_amount_cents'],
                    ],
                ]);
                $payment->forceFill(['treasury_collection_id' => $collection->id, 'status' => 'collected', 'confirmed_at' => now(), 'reconciliation_state' => 'confirmed', 'next_check_at' => null])->save();
                $payment->attempts()->update(['status' => 'collected']);
                $payment->setRelation('treasuryCollection', $collection);

                return $this->result($payment, true, $collection->status->value);
            });
        });
    }

    /** @return array{paid: bool, status: string, collection_id: int|null, receipt_id: int|null, last_checked_at: string|null, reconciliation_state: string} */
    private function review(XChangePayment $payment, string $code): array
    {
        $payment->forceFill(['reconciliation_state' => 'needs_review', 'last_error_code' => $code, 'next_check_at' => null])->save();

        return $this->result($payment, false, 'needs_review');
    }

    /** @return array{paid: bool, status: string, collection_id: int|null, receipt_id: int|null, last_checked_at: string|null, reconciliation_state: string} */
    private function result(XChangePayment $payment, bool $paid, string $status): array
    {
        return ['paid' => $paid, 'status' => $status, 'collection_id' => $payment->treasury_collection_id,
            'receipt_id' => $payment->treasuryCollection?->receipt?->id,
            'last_checked_at' => $payment->last_checked_at?->toIso8601String(), 'reconciliation_state' => $payment->reconciliation_state];
    }
}
