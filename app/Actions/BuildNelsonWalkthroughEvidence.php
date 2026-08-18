<?php

namespace App\Actions;

class BuildNelsonWalkthroughEvidence
{
    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        return [
            'snapshot' => [
                'label' => 'Authenticated production snapshot',
                'status' => 'Immutable and read only',
                'summary' => 'The legacy production database and stored files were captured through the authorized administrative export path. The source remains unchanged.',
            ],
            'calibration' => [
                'reference' => 'CAL-2026-001',
                'label' => 'Municipality-supplied assessment calibration',
                'summary' => 'One actual assessment was traced across the Revenue Code, deployed configuration, legacy calculation behavior, persisted schedules, and the printed municipal specimen. Historical reproduction is proven; unresolved future policy remains blocked.',
            ],
            'historical_evidence' => [
                'application_count' => 407,
                'schedule_count' => 696,
                'fee_line_count' => 3_007,
                'completed_payment_count' => 660,
                'unpaid_schedule_count' => 36,
                'scheduled_amount_cents' => 412_770_810,
                'paid_amount_cents' => 397_445_008,
                'rehearsal_phases' => [
                    'execute' => 'passed',
                    'audit' => 'passed',
                    'rollback' => 'passed',
                    'restoration' => 'passed',
                ],
                'operational_financial_mutation_count' => 0,
                'summary' => 'Every exact historical-evidence application was rehearsed into an isolated preservation boundary, audited against its source, rolled back, and audited again for exact restoration.',
            ],
            'identity_frontier' => [
                'exact_application_count' => 407,
                'reconciliation_required_count' => 736,
                'summary' => '407 historical applications are already in the exact-evidence migration class. 736 additional applications require identity reconciliation before they can be safely mapped. They remain quarantined rather than guessed.',
            ],
            'authority_boundary' => [
                'configured_official_is_authorized_signatory' => false,
                'authorized_signatory_is_issuance_authority' => false,
                'issuance_authority_establishes_legal_effect' => false,
                'summary' => 'Configured official does not mean authorized signatory. Authorized signatory does not mean permit issuance authority. Permit issuance authority does not by itself establish legal effect.',
            ],
            'not_demonstrated' => [
                'Official application-number allocation',
                'Online payment or automated reconciliation',
                'Unaccepted tax, surcharge, penalty, installment, or rounding policy',
                'Receipt voiding or reversal',
                'Legal permit issuance, release, validity, or effect',
                'Production migration or cutover',
                'Automatic matching of the 736 identity-reconciliation applications',
            ],
        ];
    }
}
