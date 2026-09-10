<?php

namespace App\Actions;

use App\Enums\TreasuryCollectionChannel;
use App\Enums\TreasuryCollectionMethod;
use App\Models\LifecycleCleanroomRun;
use App\Models\PaymentSchedule;
use App\Models\TreasuryCollection;
use App\Models\User;
use App\Models\XChangePayment;
use App\Models\XChangePaymentAttempt;
use Illuminate\Support\Facades\DB;
use LogicException;

class SimulateLifecycleQrPhPayment
{
    public function __construct(private readonly RecordPaymentScheduleCollection $recordCollection) {}

    public function handle(LifecycleCleanroomRun $run): TreasuryCollection
    {
        if ($run->status !== 'active'
            || data_get($run->actor_manifest, 'semantic_classification') !== 'synthetic_only'
            || data_get($run->actor_manifest, 'production_liability') !== false) {
            throw new LogicException('QR Ph payment simulation is available only inside an active synthetic cleanroom.');
        }

        $applicationId = $run->renewal_application_id ?? $run->new_application_id;
        if (! is_int($applicationId)) {
            throw new LogicException('The cleanroom has no lodged Application.');
        }

        $schedule = PaymentSchedule::query()
            ->where('permit_application_id', $applicationId)
            ->with(['xChangePayment.attempts', 'treasuryCollections', 'permitApplication.business.owner'])
            ->latest('sequence')
            ->first();
        if (! $schedule instanceof PaymentSchedule || ! $schedule->xChangePayment instanceof XChangePayment || blank($schedule->xChangePayment->pay_code)) {
            throw new LogicException('Generate the Pay Code and QR Ph first.');
        }
        if ($schedule->treasuryCollections->isNotEmpty()) {
            return $schedule->treasuryCollections->first();
        }

        $payment = $schedule->xChangePayment;
        $attempt = $payment->attempts()->latest('id')->first();
        if (! $attempt instanceof XChangePaymentAttempt) {
            throw new LogicException('The Pay Code has no QR Ph attempt to simulate.');
        }
        if (! in_array($attempt->status, ['requested', 'awaiting_payment'], true)
            || $attempt->expires_at?->isPast() !== false) {
            throw new LogicException('The current QR Ph attempt has expired. The Citizen must generate a fresh QR Ph request.');
        }
        $cashierId = data_get($run->actor_manifest, 'actors.cashier.user_id');
        $cashier = is_int($cashierId) ? User::query()->find($cashierId) : null;
        $inquiry = $this->successfulInquiry($payment);

        return DB::transaction(function () use ($schedule, $payment, $attempt, $cashier, $inquiry): TreasuryCollection {
            $collection = $this->recordCollection->handle($schedule, [
                'amount_cents' => $payment->amount_cents,
                'channel' => TreasuryCollectionChannel::Online->value,
                'method' => TreasuryCollectionMethod::QrPh->value,
                'payer_name' => $schedule->permitApplication->business->owner->name,
                'reference_number' => $attempt->reference ?? $payment->pay_code,
                'remarks' => 'Synthetic Lifecycle Laboratory full-payment simulation; no real funds moved.',
                'integration_evidence' => [
                    'source' => 'lifecycle_laboratory_simulator',
                    'synthetic_only' => true,
                    'real_funds_moved' => false,
                    'x_change_payment_id' => $payment->id,
                    'external_reference' => $payment->external_reference,
                    'pay_code' => $payment->pay_code,
                    'attempt_reference' => $attempt->reference,
                    'consumer_status' => $inquiry['consumer_status'],
                    'provider_status' => $inquiry['provider_status'],
                    'collected_total_cents' => $inquiry['collected_total_cents'],
                    'target_amount_cents' => $inquiry['target_amount_cents'],
                    'is_fully_collected' => $inquiry['is_fully_collected'],
                    'is_terminal' => $inquiry['is_terminal'],
                ],
            ], $cashier);

            $payment->forceFill([
                'treasury_collection_id' => $collection->id,
                'status' => 'collected',
                'consumer_status' => $inquiry['consumer_status'],
                'provider_status' => $inquiry['provider_status'],
                'collected_total_cents' => $inquiry['collected_total_cents'],
                'target_amount_cents' => $inquiry['target_amount_cents'],
                'is_fully_collected' => $inquiry['is_fully_collected'],
                'confirmed_at' => now(),
                'last_error_code' => null,
            ])->save();

            return $collection;
        });
    }

    /**
     * Mirror the normalized successful inquiry returned by XChangePartnerApiClient
     * without contacting x-change or representing that real funds moved.
     *
     * @return array{external_reference: string, consumer_status: string, provider_status: string, collected_total_cents: int, target_amount_cents: int, is_fully_collected: true, is_terminal: false}
     */
    private function successfulInquiry(XChangePayment $payment): array
    {
        return [
            'external_reference' => $payment->external_reference,
            'consumer_status' => 'paid',
            'provider_status' => 'active',
            'collected_total_cents' => $payment->amount_cents,
            'target_amount_cents' => $payment->amount_cents,
            'is_fully_collected' => true,
            'is_terminal' => false,
        ];
    }
}
