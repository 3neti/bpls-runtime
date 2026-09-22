<?php

namespace App\Actions;

use App\Enums\TreasuryCollectionChannel;
use App\Enums\TreasuryCollectionMethod;
use App\Models\PaymentSchedule;
use App\Models\TreasuryCollection;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;

final class SimulateAuthorizedQrPhPayment
{
    public function __construct(
        private readonly AuthorizeUatQrPhSimulation $authorizeSimulation,
        private readonly ResolveActivePaymentAttempt $resolveAttempt,
        private readonly EnsureQrPhPaymentEligible $ensureEligible,
        private readonly RecordPaymentScheduleCollection $recordCollection,
        private readonly ConfirmQrPhPayment $confirmPayment,
    ) {}

    public function handle(PaymentSchedule $schedule, User $actor, int $attemptId): TreasuryCollection
    {
        if (! $this->authorizeSimulation->handle($schedule, $actor)) {
            abort(403, 'This payment is not authorized for UAT simulation.');
        }

        $payment = $schedule->xChangePayment;
        if ($payment === null || ! $payment->synthetic_only || $payment->pay_code !== null || $payment->voucher_id !== null) {
            $result = $this->confirmPayment->handle($schedule, 'simulation_precheck');
            if ($result['paid'] && $result['collection_id'] !== null) {
                return TreasuryCollection::query()->findOrFail($result['collection_id']);
            }

            throw new LogicException('Simulation is unavailable for a provider payable. Check payment status; unresolved provider evidence must be reviewed.');
        }

        return Cache::lock("qr-ph:payment-schedule:{$schedule->id}", 20)->block(10, fn (): TreasuryCollection => DB::transaction(function () use ($schedule, $actor, $attemptId): TreasuryCollection {
            $schedule = PaymentSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            $payment = $schedule->xChangePayment()->lockForUpdate()->first();
            if (! $this->authorizeSimulation->handle($schedule, $actor->fresh())) {
                abort(403, 'This payment is not authorized for UAT simulation.');
            }
            if ($payment === null || ! $payment->synthetic_only || $payment->pay_code !== null || $payment->voucher_id !== null) {
                throw new LogicException('The Citizen must create the QR Ph request first.');
            }
            if ($payment->treasury_collection_id !== null) {
                $collection = $payment->treasuryCollection;
                if (data_get($collection->source_snapshot, 'integration_evidence.attempt_id') !== $attemptId) {
                    throw new LogicException('This is not the attempt that was collected.');
                }

                return $collection;
            }
            $this->ensureEligible->handle($schedule);
            if (! $this->authorizeSimulation->available($schedule, $actor->fresh())) {
                throw new LogicException('QR Ph simulation is unavailable. The request may have expired; review the current Citizen request. No Collection was created.');
            }
            $resolution = $this->resolveAttempt->handle($payment);
            $attempt = $resolution['attempt'];
            if ($resolution['state'] !== 'active' || $attempt?->id !== $attemptId) {
                throw new LogicException('The selected request is not the unique active QR Ph attempt. Refresh to review the current Citizen request.');
            }
            if ($payment->assessment_id !== $schedule->assessment_id
                || $payment->amount_cents !== $schedule->total_amount_cents
                || $payment->currency !== 'PHP') {
                throw new LogicException('The payment does not match the approved obligation.');
            }
            $collection = $this->recordCollection->handle($schedule, [
                'amount_cents' => $payment->amount_cents,
                'channel' => TreasuryCollectionChannel::Online->value,
                'method' => TreasuryCollectionMethod::QrPh->value,
                'payer_name' => $schedule->permitApplication->business->owner->name,
                'reference_number' => $attempt->reference,
                'remarks' => 'Commissioned UAT QR Ph simulation; no real funds moved.',
                'integration_evidence' => [
                    'source' => 'commissioned_uat_qr_ph_simulator',
                    'synthetic_only' => true,
                    'real_funds_moved' => false,
                    'x_change_payment_id' => $payment->id,
                    'external_reference' => $payment->external_reference,
                    'pay_code' => $payment->pay_code,
                    'attempt_id' => $attempt->id,
                    'attempt_reference' => $attempt->reference,
                    'attempt_provider' => $attempt->provider,
                    'payment_rail' => 'qr_ph',
                    'consumer_status' => 'paid',
                    'provider_status' => 'active',
                    'collected_total_cents' => $payment->amount_cents,
                    'target_amount_cents' => $payment->amount_cents,
                    'is_fully_collected' => true,
                    'is_terminal' => false,
                ],
            ], $actor);
            $payment->forceFill([
                'treasury_collection_id' => $collection->id,
                'status' => 'collected',
                'consumer_status' => 'paid',
                'provider_status' => 'active',
                'collected_total_cents' => $payment->amount_cents,
                'target_amount_cents' => $payment->amount_cents,
                'is_fully_collected' => true,
                'confirmed_at' => now(),
                'last_error_code' => null,
            ])->save();

            $attempt->update(['status' => 'collected']);

            return $collection;
        }));
    }
}
