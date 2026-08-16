<?php

namespace App\Actions;

use App\Enums\BillingGroupRecordStatus;
use App\Models\BillingGroup;
use App\Models\BillingGroupReconciliation;

final class DescribeBillingGroupAbstractReportBoundary
{
    /** @return array<string, mixed> */
    public function handle(BillingGroup $billingGroup): array
    {
        $billingGroup->loadMissing(['fields', 'currentReconciliation'])->loadCount('records');

        $draftRecordCount = $billingGroup->records()
            ->where('status', BillingGroupRecordStatus::Draft)
            ->count();
        $currentReconciliation = $billingGroup->currentReconciliation;

        return [
            'status' => 'blocked',
            'can_generate' => false,
            'can_export' => false,
            'official_row_count' => 0,
            'rows' => [],
            'billing_group' => [
                'id' => $billingGroup->id,
                'name' => $billingGroup->name,
                'description' => $billingGroup->description,
                'acceptance_status' => $billingGroup->acceptance_status->value,
                'is_active' => $billingGroup->is_active,
                'field_count' => $billingGroup->fields->count(),
                'record_count' => $billingGroup->records_count,
                'draft_record_count' => $draftRecordCount,
            ],
            'report' => [
                'key' => 'billing_group_abstract',
                'title' => $billingGroup->name.' Abstract Report',
                'scope' => 'Authoritative receipted collections for one accepted Treasury billing group.',
                'date_basis' => 'Accepted collection date for the selected billing group',
                'grain' => 'One row per authoritative receipted billing-group collection with fee columns and a row total',
            ],
            'base_columns' => [
                ['position' => 1, 'key' => 'receipt_number', 'label' => 'OR #', 'source_status' => 'receipt_authority_unresolved'],
                ['position' => 2, 'key' => 'payor_name', 'label' => 'NAME OF PAYOR', 'source_status' => 'payor_identity_unresolved'],
                ['position' => 3, 'key' => 'fee_columns', 'label' => 'DYNAMIC FEE COLUMNS', 'source_status' => 'fee_mapping_unresolved'],
                ['position' => 4, 'key' => 'row_total', 'label' => 'TOTAL', 'source_status' => 'collection_amount_unavailable'],
                ['position' => 5, 'key' => 'column_totals', 'label' => 'COLUMN TOTALS / GRAND TOTAL', 'source_status' => 'authoritative_rows_unavailable'],
            ],
            'readiness' => [
                $this->requirement(
                    'billing_group_acceptance',
                    'Municipal acceptance',
                    false,
                    'The billing-group definition remains provisional and is not an accepted Treasury collection module.',
                ),
                $this->requirement(
                    'municipal_reconciliation_evidence',
                    'Municipal reconciliation evidence',
                    $currentReconciliation instanceof BillingGroupReconciliation,
                    $currentReconciliation instanceof BillingGroupReconciliation
                        ? "Evidence version {$currentReconciliation->version} is recorded, but it does not authorize financial execution."
                        : 'No versioned municipal reconciliation evidence is recorded for this billing group.',
                ),
                $this->requirement(
                    'authoritative_collection_records',
                    'Authoritative collection records',
                    false,
                    'The Laravel billing-group boundary currently stores draft declarations only; it does not create liabilities or collections.',
                ),
                $this->requirement(
                    'fee_line_items',
                    'Accepted fee line items',
                    false,
                    'No accepted fee library or persisted billing-group collection line items exist in the runtime.',
                ),
                $this->requirement(
                    'receipt_mapping',
                    'Official receipt mapping',
                    false,
                    'Draft references are not official receipt numbers and no billing-group receipt relationship is authorized.',
                ),
                $this->requirement(
                    'revenue_account_and_fund_mapping',
                    'Revenue account and fund mapping',
                    false,
                    'Approved accounting classification for this billing group has not been reconciled.',
                ),
                $this->requirement(
                    'production_configuration_reconciliation',
                    'Production configuration reconciliation',
                    false,
                    'The authenticated production snapshot and accepted Treasury configuration remain external dependencies.',
                ),
            ],
            'current_reconciliation' => $currentReconciliation instanceof BillingGroupReconciliation
                ? [
                    'version' => $currentReconciliation->version,
                    'status' => $currentReconciliation->reconciliation_status->value,
                    'execution_status' => $currentReconciliation->execution_status,
                ]
                : null,
            'blocked_by' => [
                'billing_group_acceptance',
                'authoritative_collection_records',
                'fee_line_items',
                'receipt_mapping',
                'revenue_account_and_fund_mapping',
                'receipt_void_and_reversal_policy',
                'production_configuration_reconciliation',
            ],
            'legacy_evidence' => [
                'source' => 'Legacy ad-hoc reporting generated one report per billing-group identifier from ClickHouse-synchronized records and line items.',
                'field_inference' => 'OR number and payor identity were inferred from custom-field names when explicit mappings were unavailable.',
                'amount_handling' => 'Fee names became dynamic columns; cancelled records remained as zero-valued rows.',
                'date_handling' => 'The report used record date when available and otherwise fell back to record creation time.',
            ],
            'scope_note' => "{$draftRecordCount} draft record(s) are visible as preparation evidence but are intentionally excluded from the official report row count.",
            'policy_note' => 'This boundary does not calculate fees, infer collection completion, promote draft references to receipt numbers, or treat reconciliation evidence as municipal acceptance.',
        ];
    }

    /** @return array{key: string, label: string, status: string, reason: string} */
    private function requirement(string $key, string $label, bool $satisfied, string $reason): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $satisfied ? 'recorded' : 'blocked',
            'reason' => $reason,
        ];
    }
}
