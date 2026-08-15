<?php

namespace App\Actions;

use App\Models\PaymentSchedule;
use App\Models\TreasuryCollection;

final class DescribeCitizenPaymentSchedule
{
    public function __construct(
        private readonly DescribeOnlinePaymentBoundary $describeOnlinePaymentBoundary,
        private readonly DescribePaymentPolicyBoundary $describePaymentPolicyBoundary,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(PaymentSchedule $paymentSchedule): array
    {
        $paymentSchedule->loadMissing([
            'assessment',
            'permitApplication.business',
            'lines.lineOfBusiness',
            'treasuryCollections.allocations.paymentScheduleLine',
            'treasuryCollections.receipt',
        ]);

        return [
            'id' => $paymentSchedule->id,
            'sequence' => $paymentSchedule->sequence,
            'status' => $paymentSchedule->status->value,
            'payment_mode' => $paymentSchedule->payment_mode,
            'due_on' => $paymentSchedule->due_on?->toDateString(),
            'total_amount_cents' => $paymentSchedule->total_amount_cents,
            'paid_amount_cents' => $paymentSchedule->paid_amount_cents,
            'balance_amount_cents' => max(0, $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents),
            'created_at' => $paymentSchedule->created_at?->toIso8601String(),
            'assessment' => [
                'id' => $paymentSchedule->assessment->id,
                'sequence' => $paymentSchedule->assessment->sequence,
                'status' => $paymentSchedule->assessment->status->value,
                'total_amount_cents' => $paymentSchedule->assessment->total_amount_cents,
            ],
            'permit_application' => [
                'id' => $paymentSchedule->permitApplication->id,
                'display_reference' => $paymentSchedule->permitApplication->application_number ?? 'Draft #'.$paymentSchedule->permitApplication->id,
                'application_number' => $paymentSchedule->permitApplication->application_number,
                'type' => $paymentSchedule->permitApplication->type->value,
                'status' => $paymentSchedule->permitApplication->status->value,
                'application_year' => $paymentSchedule->permitApplication->application_year,
                'business_name' => $paymentSchedule->permitApplication->business->name,
                'trade_name' => $paymentSchedule->permitApplication->business->trade_name,
            ],
            'lines' => $paymentSchedule->lines
                ->sortBy('code')
                ->values()
                ->map(fn ($line): array => [
                    'id' => $line->id,
                    'code' => $line->code,
                    'name' => $line->name,
                    'category' => $line->category->value,
                    'status' => $line->status->value,
                    'due_on' => $line->due_on?->toDateString(),
                    'amount_cents' => $line->amount_cents,
                    'paid_amount_cents' => $line->paid_amount_cents,
                    'balance_amount_cents' => max(0, $line->amount_cents - $line->paid_amount_cents),
                    'line_of_business' => $line->lineOfBusiness?->name,
                ]),
            'collections' => $paymentSchedule->treasuryCollections
                ->sortBy('id')
                ->values()
                ->map(fn (TreasuryCollection $collection): array => [
                    'id' => $collection->id,
                    'status' => $collection->status->value,
                    'channel' => $collection->channel->value,
                    'method' => $collection->method->value,
                    'amount_cents' => $collection->amount_cents,
                    'received_at' => $collection->received_at->toIso8601String(),
                    'receipt' => $collection->receipt === null ? null : [
                        'id' => $collection->receipt->id,
                        'status' => $collection->receipt->status->value,
                        'numbering_authority' => $collection->receipt->numbering_authority,
                        'receipt_number' => $collection->receipt->receipt_number,
                        'amount_cents' => $collection->receipt->amount_cents,
                        'issued_at' => $collection->receipt->issued_at->toIso8601String(),
                    ],
                    'allocations' => $collection->allocations
                        ->sortBy('payment_schedule_line_id')
                        ->values()
                        ->map(fn ($allocation): array => [
                            'id' => $allocation->id,
                            'payment_schedule_line_id' => $allocation->payment_schedule_line_id,
                            'code' => $allocation->paymentScheduleLine->code,
                            'name' => $allocation->paymentScheduleLine->name,
                            'amount_cents' => $allocation->amount_cents,
                        ]),
                ]),
            'payment_policy_boundary' => $this->describePaymentPolicyBoundary->handle($paymentSchedule),
            'online_payment_boundary' => $this->describeOnlinePaymentBoundary->handle($paymentSchedule),
            'artifact_statement' => 'This page reports persisted assessment, schedule, collection, allocation, and receipt evidence. It does not execute payment, reconciliation, reversal, or receipt issuance.',
        ];
    }
}
