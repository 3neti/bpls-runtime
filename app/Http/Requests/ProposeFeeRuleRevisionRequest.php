<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use Illuminate\Foundation\Http\FormRequest;

final class ProposeFeeRuleRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::ManageFeeRules->value) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'proposed_amount_minor' => ['required', 'integer', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reason' => ['required', 'string', 'max:2000'],
            'authority' => ['required', 'string', 'max:2000'],
        ];
    }
}
