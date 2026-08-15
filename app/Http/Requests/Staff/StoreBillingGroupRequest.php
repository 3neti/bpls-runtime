<?php

namespace App\Http\Requests\Staff;

use App\Enums\BillingGroupFieldType;
use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBillingGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ViewBillingGroups->value)
            && $this->user()->can(UserPermission::ManageBillingGroups->value);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('billing_groups', 'name')],
            'description' => ['nullable', 'string', 'max:2000'],
            'fields' => ['required', 'array', 'list', 'min:1', 'max:30'],
            'fields.*' => ['required', 'array:key,name,field_type,is_required,is_unique,options,placeholder,default_value'],
            'fields.*.key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct:strict'],
            'fields.*.name' => ['required', 'string', 'max:255'],
            'fields.*.field_type' => ['required', Rule::enum(BillingGroupFieldType::class)],
            'fields.*.is_required' => ['required', 'boolean'],
            'fields.*.is_unique' => ['required', 'boolean'],
            'fields.*.options' => ['nullable', 'array', 'list', 'max:50'],
            'fields.*.options.*' => ['required', 'string', 'max:255', 'distinct:strict'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.default_value' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->array('fields') as $index => $field) {
                    if (($field['field_type'] ?? null) === BillingGroupFieldType::Dropdown->value && empty($field['options'])) {
                        $validator->errors()->add("fields.{$index}.options", 'Dropdown fields require at least one option.');
                    }
                }
            },
        ];
    }

    /** @return array{name: string, description?: string|null, fields: list<array{key: string, name: string, field_type: string, is_required: bool, is_unique: bool, options?: list<string>|null, placeholder?: string|null, default_value?: string|null}>} */
    public function validatedForBillingGroup(): array
    {
        $fields = [];

        foreach ($this->array('fields') as $field) {
            if (! is_array($field)) {
                continue;
            }

            $options = [];
            $fieldOptions = $field['options'] ?? [];

            if (is_array($fieldOptions)) {
                foreach ($fieldOptions as $option) {
                    if (is_string($option)) {
                        $options[] = $option;
                    }
                }
            }

            $fields[] = [
                'key' => (string) $field['key'],
                'name' => (string) $field['name'],
                'field_type' => (string) $field['field_type'],
                'is_required' => (bool) $field['is_required'],
                'is_unique' => (bool) $field['is_unique'],
                'options' => $options,
                'placeholder' => isset($field['placeholder']) ? (string) $field['placeholder'] : null,
                'default_value' => isset($field['default_value']) ? (string) $field['default_value'] : null,
            ];
        }

        return [
            'name' => $this->string('name')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'fields' => $fields,
        ];
    }
}
