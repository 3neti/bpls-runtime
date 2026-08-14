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
            'can_split_installments' => false,
            'can_assign_statutory_due_dates' => false,
            'payment_schedule_id' => $paymentSchedule->id,
            'payment_schedule_status' => $paymentSchedule->status->value,
            'supported_payment_modes' => [
                'single',
            ],
            'blocked_calculations' => [
                'annual_payment_split',
                'semiannual_payment_split',
                'quarterly_payment_split',
                'statutory_due_dates',
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
                'annual, semiannual, and quarterly payment splitting rules',
                'statutory due dates and renewal-specific due-date adjustments',
                'late-payment surcharge trigger date and base amount',
                'interest timing, rate, compounding, and rounding',
                'PIL validation threshold and refusal workflow',
                'deficiency-tax discovery, assessment, and collection authority',
                'relationship between penalties, receipt issuance, and Treasury reconciliation',
            ],
            'artifact_statement' => 'Payment schedule evidence shows the assessed amount and collections only. Installment splitting, statutory due dates, surcharge, interest, PIL, and deficiency-tax behavior remain explicit policy boundaries and are not calculated here.',
        ];
    }
}
