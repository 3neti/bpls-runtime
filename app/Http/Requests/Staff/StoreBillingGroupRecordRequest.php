<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBillingGroupRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ViewBillingGroups->value)
            && $this->user()->can(UserPermission::ViewBillingGroupRecords->value)
            && $this->user()->can(UserPermission::CreateBillingGroupRecords->value);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:1000'],
            'record_date' => ['nullable', 'date'],
            'payor_name' => ['nullable', 'string', 'max:255'],
            'field_values' => ['nullable', 'array', 'max:30'],
            'field_values.*' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
