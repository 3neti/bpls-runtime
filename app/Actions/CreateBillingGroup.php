<?php

namespace App\Actions;

use App\Enums\BillingGroupAcceptanceStatus;
use App\Enums\BillingGroupFieldType;
use App\Models\BillingGroup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBillingGroup
{
    /**
     * @param  array{name: string, description?: string|null, fields: list<array{key: string, name: string, field_type: string, is_required: bool, is_unique: bool, options?: list<string>|null, placeholder?: string|null, default_value?: string|null}>}  $data
     * @param  array<string, mixed>  $provenance
     */
    public function handle(array $data, array $provenance = []): BillingGroup
    {
        $this->validateFields($data['fields']);

        return DB::transaction(function () use ($data, $provenance): BillingGroup {
            $billingGroup = BillingGroup::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'acceptance_status' => BillingGroupAcceptanceStatus::Provisional,
                'is_active' => true,
                'metadata' => [
                    ...$provenance,
                    'legacy_contract' => 'dynamic_billing_group',
                    'policy_boundary' => 'not_accepted_as_a_tor_treasury_module',
                ],
            ]);

            $billingGroup->fields()->createMany(
                collect($data['fields'])->map(fn (array $field, int $index): array => [
                    ...$field,
                    'options' => $field['field_type'] === 'dropdown' ? ($field['options'] ?? []) : null,
                    'sort_order' => $index + 1,
                ])->all(),
            );

            return $billingGroup->load('fields');
        });
    }

    /** @param list<array{key: string, name: string, field_type: string, is_required: bool, is_unique: bool, options?: list<string>|null, placeholder?: string|null, default_value?: string|null}> $fields */
    private function validateFields(array $fields): void
    {
        if ($fields === []) {
            throw ValidationException::withMessages([
                'fields' => 'A billing group requires at least one field.',
            ]);
        }

        if (collect($fields)->pluck('key')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fields' => 'Billing group field keys must be unique.',
            ]);
        }

        foreach ($fields as $index => $field) {
            $type = BillingGroupFieldType::tryFrom($field['field_type']);

            if (! $type instanceof BillingGroupFieldType) {
                throw ValidationException::withMessages([
                    "fields.{$index}.field_type" => 'The billing group field type is not supported.',
                ]);
            }

            $options = $field['options'] ?? [];

            if ($type === BillingGroupFieldType::Dropdown && $options === []) {
                throw ValidationException::withMessages([
                    "fields.{$index}.options" => 'Dropdown fields require at least one option.',
                ]);
            }

            $defaultValue = $field['default_value'] ?? null;

            if ($defaultValue === null || $defaultValue === '') {
                continue;
            }

            $validDefault = match ($type) {
                BillingGroupFieldType::Text => true,
                BillingGroupFieldType::Number, BillingGroupFieldType::Currency => is_numeric($defaultValue),
                BillingGroupFieldType::Date => Carbon::hasFormat($defaultValue, 'Y-m-d'),
                BillingGroupFieldType::Dropdown => in_array($defaultValue, $options, true),
                BillingGroupFieldType::Checkbox => in_array($defaultValue, ['0', '1'], true),
            };

            if (! $validDefault) {
                throw ValidationException::withMessages([
                    "fields.{$index}.default_value" => 'The default value does not match the configured field type.',
                ]);
            }
        }
    }
}
