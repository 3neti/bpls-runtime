<?php

namespace App\Actions;

use App\Models\BillingGroupField;
use App\Models\BillingGroupReconciliation;
use App\Models\BillingGroupRecord;

class DescribeBillingGroupFinancialReadiness
{
    /** @return array<string, mixed> */
    public function handle(BillingGroupRecord $record): array
    {
        $record->loadMissing(['billingGroup.fields', 'billingGroup.currentReconciliation']);

        $fieldValues = $record->field_values ?? [];
        $requiredFields = collect($record->schema_snapshot)
            ->filter(fn (array $field): bool => ($field['is_required'] ?? false) === true);
        $missingRequiredFields = $requiredFields
            ->filter(function (array $field) use ($fieldValues): bool {
                $key = $field['key'] ?? null;

                return ! is_string($key)
                    || ! array_key_exists($key, $fieldValues)
                    || trim($fieldValues[$key]) === '';
            })
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key))
            ->values()
            ->all();
        $uniqueFields = collect($record->schema_snapshot)
            ->filter(fn (array $field): bool => ($field['is_unique'] ?? false) === true)
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key))
            ->values()
            ->all();
        $schemaMatches = $record->schema_snapshot === $record->billingGroup->fields
            ->map(fn (BillingGroupField $field): array => [
                'field_id' => $field->id,
                'key' => $field->key,
                'name' => $field->name,
                'field_type' => $field->field_type->value,
                'is_required' => $field->is_required,
                'is_unique' => $field->is_unique,
                'sort_order' => $field->sort_order,
                'options' => $field->options,
                'default_value' => $field->default_value,
            ])
            ->values()
            ->all();
        $currentReconciliation = $record->billingGroup->currentReconciliation;

        $requirements = [
            $this->requirement(
                'billing_group_acceptance',
                'Municipal acceptance',
                false,
                'The definition remains provisional and has not been accepted as a Treasury collection module.',
            ),
            $this->requirement(
                'municipal_reconciliation_evidence',
                'Municipal reconciliation evidence',
                $currentReconciliation instanceof BillingGroupReconciliation,
                $currentReconciliation instanceof BillingGroupReconciliation
                    ? "Evidence version {$currentReconciliation->version} is recorded; an authorized municipal decision is still required."
                    : 'No versioned municipal reconciliation evidence is recorded for this definition.',
            ),
            $this->requirement(
                'active_definition',
                'Active definition',
                $record->billingGroup->is_active,
                $record->billingGroup->is_active
                    ? 'The definition is active.'
                    : 'Financial execution is unavailable for an inactive definition.',
            ),
            $this->requirement(
                'schema_reconciliation',
                'Schema reconciliation',
                $schemaMatches,
                $schemaMatches
                    ? 'The draft still matches its current billing-group schema.'
                    : 'The definition changed after this draft was prepared and requires explicit reconciliation.',
            ),
            $this->requirement(
                'required_field_readiness',
                'Required field readiness',
                $missingRequiredFields === [],
                $missingRequiredFields === []
                    ? 'Every field marked required in the draft snapshot is recorded.'
                    : 'Required values remain missing: '.implode(', ', $missingRequiredFields).'.',
            ),
            $this->requirement(
                'unique_field_policy',
                'Unique field policy',
                $uniqueFields === [],
                $uniqueFields === []
                    ? 'This draft schema does not declare unique fields.'
                    : 'Uniqueness scope and conflict handling remain unresolved for: '.implode(', ', $uniqueFields).'.',
            ),
            $this->requirement('fee_amount_authority', 'Fee and amount authority', false, 'No accepted fee basis or amount authority is linked to this definition.'),
            $this->requirement('payor_identity_policy', 'Payor identity policy', false, 'Recorded payor text is not yet an accepted Treasury payor identity.'),
            $this->requirement('revenue_account_mapping', 'Revenue account mapping', false, 'No approved revenue-account mapping is recorded.'),
            $this->requirement('fund_classification', 'Fund classification', false, 'No approved fund classification is recorded.'),
            $this->requirement('receipt_numbering_authority', 'Receipt numbering authority', false, 'Official receipt numbering authority remains unresolved.'),
            $this->requirement('receipt_void_and_reversal_policy', 'Receipt void and reversal policy', false, 'Void, reversal, and correction policy remains unresolved.'),
            $this->requirement('production_configuration_reconciliation', 'Production configuration reconciliation', false, 'Production billing-group and Treasury configuration has not been reconciled.'),
        ];
        $blockedBy = collect($requirements)
            ->where('status', 'blocked')
            ->pluck('key')
            ->values()
            ->all();

        return [
            'status' => $blockedBy === [] ? 'ready' : 'blocked',
            'can_create_liability' => $blockedBy === [],
            'can_collect' => false,
            'can_issue_receipt' => false,
            'blocked_by' => $blockedBy,
            'missing_required_fields' => $missingRequiredFields,
            'unique_fields_requiring_policy' => $uniqueFields,
            'schema_matches_current_definition' => $schemaMatches,
            'current_reconciliation_version' => $currentReconciliation?->version,
            'reconciliation_status' => $currentReconciliation?->reconciliation_status->value,
            'requirements' => $requirements,
            'reason' => $blockedBy === []
                ? 'The draft is ready for an accepted financial execution action.'
                : 'Financial execution is refused until every record-readiness and municipal-policy requirement is satisfied.',
        ];
    }

    /** @return array{key: string, label: string, status: string, reason: string} */
    private function requirement(string $key, string $label, bool $satisfied, string $reason): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $satisfied ? 'satisfied' : 'blocked',
            'reason' => $reason,
        ];
    }
}
