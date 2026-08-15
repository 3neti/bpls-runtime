<?php

namespace App\Actions;

final class DescribeAllAbstractReportBoundary
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
                'key' => 'all_abstract_of_collection',
                'title' => 'All Abstract of Collection',
                'scope' => 'All permit and non-permit LGU collections classified under approved revenue accounts and funds.',
                'date_basis' => 'Accepted collection date across every included Treasury source',
                'grain' => 'One row per authoritative receipted collection with dynamic revenue-account columns',
            ],
            'base_columns' => [
                ['position' => 1, 'key' => 'receipt_number', 'label' => 'OR #', 'source_status' => 'permit_receipt_available'],
                ['position' => 2, 'key' => 'payer_name', 'label' => 'NAME OF PAYOR', 'source_status' => 'permit_payer_available'],
                ['position' => 3, 'key' => 'revenue_accounts', 'label' => 'DYNAMIC REVENUE ACCOUNT COLUMNS', 'source_status' => 'approved_mapping_unresolved'],
                ['position' => 4, 'key' => 'row_total', 'label' => 'TOTAL', 'source_status' => 'permit_total_available'],
                ['position' => 5, 'key' => 'column_totals', 'label' => 'COLUMN TOTALS / GRAND TOTAL', 'source_status' => 'complete_coverage_blocked'],
            ],
            'coverage' => [
                ['key' => 'business_permit_collections', 'label' => 'Business permit collections', 'status' => 'available'],
                ['key' => 'miscellaneous_fees', 'label' => 'Miscellaneous fee collections', 'status' => 'not_implemented'],
                ['key' => 'government_stall_rentals', 'label' => 'Government stall rental collections', 'status' => 'not_implemented'],
                ['key' => 'franchise_payments', 'label' => 'Franchise payment collections', 'status' => 'not_implemented'],
                ['key' => 'other_treasury_collections', 'label' => 'Other non-permit Treasury collections', 'status' => 'not_implemented'],
            ],
            'reconciliation_controls' => [
                ['key' => 'transaction_deduplication', 'label' => 'Transactions are not duplicated', 'status' => 'permit_domain_available'],
                ['key' => 'payment_classification', 'label' => 'Payments are correctly classified', 'status' => 'approved_mapping_unresolved'],
                ['key' => 'revenue_account_identity', 'label' => 'Revenue accounts are correctly identified', 'status' => 'approved_mapping_unresolved'],
                ['key' => 'collection_date', 'label' => 'Collection dates are accurate', 'status' => 'permit_domain_available'],
                ['key' => 'cashier_identity', 'label' => 'Cashier information is recorded', 'status' => 'permit_domain_available'],
                ['key' => 'fund_classification', 'label' => 'Fund classification is correct', 'status' => 'not_collected'],
                ['key' => 'total_reconciliation', 'label' => 'Report totals reconcile with actual transactions', 'status' => 'complete_coverage_blocked'],
            ],
            'blocked_by' => [
                'non_permit_treasury_collection_model',
                'billing_group_acceptance',
                'miscellaneous_collection_coverage',
                'stall_rental_collection_coverage',
                'franchise_collection_coverage',
                'approved_revenue_account_mapping',
                'fund_classification',
                'receipt_void_and_reversal_policy',
                'production_configuration_reconciliation',
            ],
            'completeness_boundary' => [
                'permit_projection_available_elsewhere' => true,
                'all_sources_available' => false,
                'partial_report_may_be_labeled_all' => false,
                'reason' => 'An All Abstract containing only permit receipts would materially overstate Treasury coverage and could not satisfy the TOR requirement that totals reconcile across every included LGU revenue source.',
            ],
            'legacy_evidence' => [
                'combined_sources' => 'The legacy report unioned permit payments with every active billing group and generated dynamic fee columns.',
                'custom_field_inference' => 'Billing-group OR number and payor identity were inferred from custom-field names rather than governed mappings.',
                'mixed_date_basis' => 'Permit rows used payment paidAt while billing-group rows used record date or creation time.',
                'cancelled_rows' => 'Failed or cancelled permit payments and cancelled billing records remained as zero-valued rows.',
                'classification' => 'Fee names became report columns; approved revenue-account and fund mappings were not proven in source.',
            ],
            'scope_note' => 'The legacy base shape and dynamic-column behavior are preserved as evidence. Existing permit reports remain available, but no All Abstract row or export is produced until complete Treasury coverage can be reconciled.',
            'policy_note' => 'This is an operational completeness boundary, not a financial calculation path. It does not recalculate liability or infer missing non-permit transactions, revenue accounts, funds, voids, or reversals.',
        ];
    }
}
