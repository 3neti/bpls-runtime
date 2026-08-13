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
                'max:255',
                Rule::unique('receipts', 'receipt_number')->where('numbering_authority', 'manual'),
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{receipt_number: string, numbering_authority: string, remarks?: string|null}
     */
    public function validatedForReceipt(): array
    {
        $validated = $this->validated();

        return [
            'receipt_number' => $validated['receipt_number'],
            'numbering_authority' => 'manual',
            'remarks' => $validated['remarks'] ?? null,
        ];
    }
}
