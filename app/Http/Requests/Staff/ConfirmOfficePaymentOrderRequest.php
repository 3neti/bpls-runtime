<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ConfirmOfficePaymentOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::ContributeBusinessPermitEvaluations->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'items.*.fee_rule_id' => ['required', 'integer', 'distinct', 'exists:fee_rules,id'],
            'items.*.amount_cents' => ['required', 'integer', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:1000'],
            'items.*.authority' => ['nullable', 'string', 'max:1000'],
            'signature_facsimile' => ['required', File::image()->max(2048)],
        ];
    }
}
