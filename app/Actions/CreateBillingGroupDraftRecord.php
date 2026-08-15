<?php

namespace App\Actions;

use App\Enums\BillingGroupFieldType;
use App\Enums\BillingGroupRecordStatus;
use App\Models\BillingGroup;
use App\Models\BillingGroupField;
use App\Models\BillingGroupRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateBillingGroupDraftRecord
{
    /**
     * @param  array{description?: string|null, record_date?: string|null, payor_name?: string|null, field_values?: array<string, string|null>}  $data
     * @param  array<string, mixed>  $provenance
     */
    public function handle(BillingGroup $billingGroup, User $actor, array $data, array $provenance = []): BillingGroupRecord
    {
        if (! $billingGroup->is_active) {
            throw ValidationException::withMessages([
                'billing_group' => 'Draft records cannot be created for an inactive billing group.',
            ]);
        }

        $billingGroup->loadMissing('fields');
        $fieldValues = collect($data['field_values'] ?? [])
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): string => (string) $value)
            ->all();

        $unknownKeys = collect(array_keys($fieldValues))->diff($billingGroup->fields->pluck('key'));

        if ($unknownKeys->isNotEmpty()) {
            throw ValidationException::withMessages([
                'field_values' => 'The draft contains fields that are not part of this billing group: '.$unknownKeys->join(', ').'.',
            ]);
        }

        foreach ($billingGroup->fields as $field) {
            if (! array_key_exists($field->key, $fieldValues)) {
                continue;
            }

            $this->validateFieldValue($field, $fieldValues[$field->key]);
        }

        $schemaSnapshot = $billingGroup->fields
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

        return $billingGroup->records()->create([
            'created_by_id' => $actor->id,
            'draft_reference' => 'BGRD-'.Str::ulid(),
            'status' => BillingGroupRecordStatus::Draft,
            'description' => $data['description'] ?? null,
            'record_date' => $data['record_date'] ?? null,
            'payor_name' => $data['payor_name'] ?? null,
            'field_values' => $fieldValues,
            'schema_snapshot' => $schemaSnapshot,
            'source_snapshot' => [
                ...$provenance,
                'action' => self::class,
                'actor_id' => $actor->id,
                'billing_group_id' => $billingGroup->id,
                'billing_group_acceptance_status' => $billingGroup->acceptance_status->value,
                'financial_effect' => 'none',
            ],
        ]);
    }

    private function validateFieldValue(BillingGroupField $field, string $value): void
    {
        $valid = match ($field->field_type) {
            BillingGroupFieldType::Text => true,
            BillingGroupFieldType::Number, BillingGroupFieldType::Currency => is_numeric($value),
            BillingGroupFieldType::Date => Carbon::hasFormat($value, 'Y-m-d'),
            BillingGroupFieldType::Dropdown => in_array($value, $field->options ?? [], true),
            BillingGroupFieldType::Checkbox => in_array($value, ['0', '1'], true),
        };

        if (! $valid) {
            throw ValidationException::withMessages([
                "field_values.{$field->key}" => "The {$field->name} value does not match its configured {$field->field_type->value} field type.",
            ]);
        }
    }
}
