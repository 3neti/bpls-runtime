<?php

namespace App\Actions;

use App\Enums\TreasuryCollectionChannel;
use App\Enums\TreasuryCollectionMethod;
use App\Exceptions\XChangePartnerApiException;
use App\Integrations\XChangePartnerApiClient;
use App\Models\PaymentSchedule;
use App\Models\TreasuryCollection;
use App\Models\XChangePayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ConfirmQrPhPayment
{
    public function __construct(
        private readonly EnsureQrPhPaymentEligible $ensureEligible,
        private readonly XChangePartnerApiClient $client,
        private readonly RecordPaymentScheduleCollection $recordCollection,
    ) {}

    /**
     * @return array{paid: bool, status: string, collection_id: int|null, receipt_id: int|null}
     */
    public function handle(PaymentSchedule $paymentSchedule): array
    {
        return Cache::lock("qr-ph:payment-schedule:{$paymentSchedule->id}", 20)->block(10, function () use ($paymentSchedule): array {
            $payment = XChangePayment::query()
                ->where('payment_schedule_id', $paymentSchedule->id)
                ->with(['treasuryCollection.receipt', 'attempts'])
                ->first();

            if (! $payment instanceof XChangePayment || $payment->pay_code === null) {
                return ['paid' => false, 'status' => 'not_started', 'collection_id' => null, 'receipt_id' => null];
            }

            if ($payment->treasuryCollection instanceof TreasuryCollection) {
                return $this->paidResult($payment->treasuryCollection);
            }

            $inquiry = $this->client->inquire($payment->pay_code);

            if ($inquiry['external_reference'] === null
                || ! hash_equals($payment->external_reference, $inquiry['external_reference'])) {
                throw new XChangePartnerApiException('EXTERNAL_REFERENCE_MISMATCH', 'x-change returned a different payment correlation reference.');
            }

            $payment->forceFill([
                'consumer_status' => $inquiry['consumer_status'],
                'provider_status' => $inquiry['provider_status'],
                'collected_total_cents' => $inquiry['collected_total_cents'],
                'target_amount_cents' => $inquiry['target_amount_cents'],
                'is_fully_collected' => $inquiry['is_fully_collected'],
                'last_error_code' => null,
            ])->save();

            if (! $inquiry['is_fully_collected']) {
                return [
                    'paid' => false,
                    'status' => $inquiry['is_terminal'] ? 'expired' : ($inquiry['provider_status'] ?? 'awaiting_payment'),
                    'collection_id' => null,
                    'receipt_id' => null,
                ];
            }

            if ($inquiry['target_amount_cents'] !== $payment->amount_cents
                || $inquiry['collected_total_cents'] !== $payment->amount_cents) {
                $payment->forceFill(['last_error_code' => 'COLLECTION_AMOUNT_MISMATCH'])->save();
                throw new XChangePartnerApiException('COLLECTION_AMOUNT_MISMATCH', 'x-change collection totals do not match the approved BPLS obligation.');
            }

            $this->ensureEligible->handle($paymentSchedule->fresh());

            return DB::transaction(function () use ($payment, $paymentSchedule, $inquiry): array {
                $payment = XChangePayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $payment->load('treasuryCollection.receipt');

                if ($payment->treasuryCollection instanceof TreasuryCollection) {
                    return $this->paidResult($payment->treasuryCollection);
                }

                $attempt = $payment->attempts()->latest('id')->first();
                $collection = $this->recordCollection->handle($paymentSchedule, [
                    'amount_cents' => $payment->amount_cents,
                    'channel' => TreasuryCollectionChannel::Online->value,
                    'method' => TreasuryCollectionMethod::QrPh->value,
                    'reference_number' => $attempt->reference ?? $payment->pay_code,
                    'remarks' => 'Authoritative QR Ph collection confirmation.',
                    'integration_evidence' => [
                        'x_change_payment_id' => $payment->id,
                        'external_reference' => $payment->external_reference,
                        'pay_code' => $payment->pay_code,
                        'attempt_reference' => $attempt?->reference,
                        'collected_total_cents' => $inquiry['collected_total_cents'],
                        'target_amount_cents' => $inquiry['target_amount_cents'],
                    ],
                ]);

                $payment->forceFill([
                    'treasury_collection_id' => $collection->id,
                    'status' => 'collected',
                    'is_fully_collected' => true,
                    'confirmed_at' => now(),
                ])->save();

                return $this->paidResult($collection);
            });
        });
    }

    /** @return array{paid: true, status: string, collection_id: int, receipt_id: int|null} */
    private function paidResult(TreasuryCollection $collection): array
    {
        $collection->loadMissing('receipt');

        return [
            'paid' => true,
            'status' => $collection->status->value,
            'collection_id' => $collection->id,
            'receipt_id' => $collection->receipt?->id,
        ];
    }
}
