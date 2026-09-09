<?php

namespace App\Actions;

final class DescribeTaxpayerAccountCardReportBoundary
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        return [
            'status' => 'blocked',
            'can_generate' => false,
            'can_export' => false,
            'row_count' => 0,
            'rows' => [],
            'report' => [
                'key' => 'taxpayer_account_card',
                'title' => 'Taxpayer Account Card',
                'grain' => 'One taxpayer account across tax years',
            ],
            'sections' => [
                ['title' => 'Account', 'fields' => ['Taxpayer', 'Business', 'Address', 'Permit and application references']],
                ['title' => 'Annual assessment', 'fields' => ['Tax year', 'Business tax', 'Regulatory fees', 'Total assessed']],
                ['title' => 'Quarterly payments', 'fields' => ['Quarter', 'Amount paid', 'Official Receipt number', 'Receipt date']],
                ['title' => 'Adjustments', 'fields' => ['Surcharge', 'Interest', 'Fine', 'Reversal or correction']],
            ],
            'blocked_by' => [
                'accepted_taxpayer_account_identity',
                'quarter_and_installment_rules',
                'receipt_reversal_validity',
                'surcharge_interest_and_fine_policy',
                'regulatory_fee_classification',
                'official_print_format',
                'production_reconciliation',
            ],
            'policy_note' => 'The template is available for review, but no official account card is generated until its accounting and print rules are accepted.',
        ];
    }
}
