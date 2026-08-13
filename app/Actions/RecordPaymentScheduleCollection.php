<?php

namespace App\Actions;

use App\Enums\PaymentScheduleLineStatus;
use App\Enums\PaymentScheduleStatus;
use App\Enums\TreasuryCollectionChannel;
use App\Enums\TreasuryCollectionMethod;
use App\Enums\TreasuryCollectionStatus;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLine;
use App\Models\TreasuryCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class RecordPaymentScheduleCollection
{
    /**
     * @param  array{amount_cents: int, method: string, payer_name?: string|null, reference_number?: string|null, remarks?: string|null}  $data
     */
    public function handle(PaymentSchedule $paymentSchedule, array $data, ?User $receivedBy = null): TreasuryCollection
    {
        return DB::transaction(function () use ($paymentSchedule, $data, $receivedBy): TreasuryCollection {
            $paymentSchedule = PaymentSchedule::query()
                ->whereKey($paymentSchedule->id)
                ->with(['permitApplication.business.owner', 'assessment', 'lines' => fn ($query) => $query->orderBy('due_on')->orderBy('id')])
                ->lockForUpdate()
                ->firstOrFail();

            if ($paymentSchedule->status === PaymentScheduleStatus::Voided) {
                throw new LogicException("Collection cannot be recorded for voided payment schedule [{$paymentSchedule->id}].");
            }

            if ($paymentSchedule->status === PaymentScheduleStatus::Paid) {
                throw new LogicException("Collection cannot be recorded for paid payment schedule [{$paymentSchedule->id}].");
            }

            $balanceCents = $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents;

            if ($balanceCents <= 0) {
                throw new LogicException("Collection cannot be recorded for payment schedule [{$paymentSchedule->id}] with no balance.");
            }

            if ($data['amount_cents'] > $balanceCents) {
                throw new LogicException("Collection amount cannot exceed payment schedule [{$paymentSchedule->id}] balance.");
            }

            $collection = TreasuryCollection::query()->create([
                'payment_schedule_id' => $paymentSchedule->id,
                'permit_application_id' => $paymentSchedule->permit_application_id,
                'assessment_id' => $paymentSchedule->assessment_id,
                'received_by_id' => $receivedBy?->id,
                'status' => TreasuryCollectionStatus::PendingReceipt,
                'channel' => TreasuryCollectionChannel::OverTheCounter,
                'method' => TreasuryCollectionMethod::from($data['method']),
                'amount_cents' => $data['amount_cents'],
                'payer_name' => $data['payer_name'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'received_at' => now(),
                'source_snapshot' => $this->sourceSnapshot($paymentSchedule, $data),
            ]);

            $remainingCents = $data['amount_cents'];

            $paymentSchedule->lines
                ->filter(fn (PaymentScheduleLine $line): bool => in_array($line->status, [
                    PaymentScheduleLineStatus::Pending,
                    PaymentScheduleLineStatus::PartiallyPaid,
                ], true))
                ->each(function (PaymentScheduleLine $line) use (&$remainingCents, $collection): void {
                    if ($remainingCents <= 0) {
                        return;
                    }

                    $lineBalanceCents = $line->amount_cents - $line->paid_amount_cents;

                    if ($lineBalanceCents <= 0) {
                        return;
                    }

                    $allocatedCents = min($remainingCents, $lineBalanceCents);

                    $collection->allocations()->create([
                        'payment_schedule_line_id' => $line->id,
                        'amount_cents' => $allocatedCents,
                        'source_snapshot' => $this->allocationSnapshot($line, $allocatedCents),
                    ]);

                    $line->paid_amount_cents += $allocatedCents;
                    $line->status = $line->paid_amount_cents >= $line->amount_cents
                        ? PaymentScheduleLineStatus::Paid
                        : PaymentScheduleLineStatus::PartiallyPaid;
                    $line->save();

                    $remainingCents -= $allocatedCents;
                });

            if ($remainingCents !== 0) {
                throw new LogicException("Collection amount could not be fully allocated for payment schedule [{$paymentSchedule->id}].");
            }

            $paymentSchedule->paid_amount_cents += $data['amount_cents'];
            $paymentSchedule->status = $paymentSchedule->paid_amount_cents >= $paymentSchedule->total_amount_cents
                ? PaymentScheduleStatus::Paid
                : PaymentScheduleStatus::PartiallyPaid;
            $paymentSchedule->save();

            return $collection->load(['receivedBy', 'allocations.paymentScheduleLine']);
        });
    }

    /**
     * @param  array{amount_cents: int, method: string, payer_name?: string|null, reference_number?: string|null, remarks?: string|null}  $data
     * @return array<string, mixed>
     */
    private function sourceSnapshot(PaymentSchedule $paymentSchedule, array $data): array
    {
        return [
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_schedule_status' => $paymentSchedule->status->value,
            'payment_mode' => $paymentSchedule->payment_mode,
            'balance_before_cents' => $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents,
            'amount_cents' => $data['amount_cents'],
            'method' => $data['method'],
            'permit_application' => [
                'id' => $paymentSchedule->permitApplication->id,
                'application_number' => $paymentSchedule->permitApplication->application_number,
                'business_name' => $paymentSchedule->permitApplication->business->name,
                'owner_name' => $paymentSchedule->permitApplication->business->owner->name,
            ],
            'policy' => [
                'channel' => TreasuryCollectionChannel::OverTheCounter->value,
                'status' => TreasuryCollectionStatus::PendingReceipt->value,
                'note' => 'Collection was recorded as over-the-counter and pending receipt. Receipt numbering, receipt issuance, online payment, and reconciliation policy remain explicit later decisions.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function allocationSnapshot(PaymentScheduleLine $line, int $allocatedCents): array
    {
        return [
            'payment_schedule_line_id' => $line->id,
            'assessment_line_id' => $line->assessment_line_id,
            'code' => $line->code,
            'name' => $line->name,
            'category' => $line->category->value,
            'amount_cents' => $line->amount_cents,
            'paid_before_cents' => $line->paid_amount_cents,
            'allocated_cents' => $allocatedCents,
        ];
    }
}
