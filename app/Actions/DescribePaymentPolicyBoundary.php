<?php

namespace App\Actions;

use App\Models\PaymentSchedule;

class DescribePaymentPolicyBoundary
{
    /**
     * @return array<string, mixed>
     */
    public function handle(PaymentSchedule $paymentSchedule): array
    {
        return [
            'status' => 'policy_boundary',
            'can_calculate_surcharge' => false,
            'can_calculate_interest' => false,
            'can_validate_pil' => false,
            'can_calculate_deficiency_tax' => false,
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_schedule_status' => $paymentSchedule->status->value,
            'blocked_calculations' => [
                'surcharge',
                'interest',
                'presumptive_income_level',
                'deficiency_tax',
            ],
            'software_knows' => [
                'payment_schedule_exists' => true,
                'assessment_snapshot_total_cents' => $paymentSchedule->total_amount_cents,
                'paid_amount_cents' => $paymentSchedule->paid_amount_cents,
                'balance_due_cents' => max(0, $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents),
                'assessment_lines_are_snapshotted' => $paymentSchedule->lines()->exists(),
            ],
            'unresolved_policy' => [
                'late-payment surcharge trigger date and base amount',
                'interest timing, rate, compounding, and rounding',
                'PIL validation threshold and refusal workflow',
                'deficiency-tax discovery, assessment, and collection authority',
                'relationship between penalties, receipt issuance, and Treasury reconciliation',
            ],
            'artifact_statement' => 'Payment schedule evidence shows the assessed amount and collections only. Surcharge, interest, PIL, and deficiency-tax behavior remain explicit policy boundaries and are not calculated here.',
        ];
    }
}
