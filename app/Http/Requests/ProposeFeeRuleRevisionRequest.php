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
            'proposed_amount_minor' => ['required', 'integer', 'min:0', 'max:99999999999999'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reason' => ['required', 'string', 'max:2000'],
            'authority' => ['required', 'string', 'max:2000'],
            'definition' => ['sometimes', 'array:revenue_account_id,group_id,ranges'],
            'definition.revenue_account_id' => ['nullable', 'integer', 'exists:revenue_accounts,id'],
            'definition.group_id' => ['nullable', 'integer', 'exists:pricing_charge_groups,id'],
            'definition.ranges' => ['sometimes', 'array', 'min:1', 'max:100'],
            'definition.ranges.*.min_basis_cents' => ['required', 'integer', 'min:0', 'max:99999999999999'],
            'definition.ranges.*.max_basis_cents' => ['nullable', 'integer', 'min:0', 'max:99999999999999'],
            'definition.ranges.*.amount_cents' => ['required', 'integer', 'min:0', 'max:99999999999999'],
        ];
    }
}
