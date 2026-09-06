<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCollectionReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::IssueReceipts->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'receipt_number' => [
                'required',
                'string',
                'digits:7',
                Rule::unique('receipts', 'receipt_number'),
            ],
            'receipt_group_key' => ['nullable', 'string', 'max:255'],
            'series' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{receipt_number: string, numbering_authority: string, receipt_group_key?: string|null, series?: string|null, remarks?: string|null}
     */
    public function validatedForReceipt(): array
    {
        $validated = $this->validated();

        return [
            'receipt_number' => $validated['receipt_number'],
            'numbering_authority' => 'manual',
            'receipt_group_key' => $validated['receipt_group_key'] ?? null,
            'series' => $validated['series'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ];
    }
}
