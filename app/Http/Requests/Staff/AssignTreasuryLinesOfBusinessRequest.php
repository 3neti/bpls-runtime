<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserPermission;
use Illuminate\Foundation\Http\FormRequest;

class AssignTreasuryLinesOfBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::CorrectEvaluationLinesOfBusiness->value) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'selections' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'selections.*.line_of_business_id' => ['required', 'integer', 'distinct', 'exists:line_of_businesses,id'],
            'selections.*.enterprise_classification' => ['nullable', 'string', 'in:Micro,Cottage,Small,Medium,Large'],
            'selections.*.manual_amount_cents' => ['sometimes', 'integer', 'min:1', 'max:1000000000'],
            'selections.*.manual_basis' => ['sometimes', 'string', 'max:1000'],
            'selections.*.enterprise_schedule_fingerprint' => ['nullable', 'string', 'size:64'],
            'selections.*.items' => ['sometimes', 'array', 'list', 'max:50'],
            'selections.*.items.*.fee_rule_id' => ['required', 'integer', 'distinct', 'exists:fee_rules,id'],
            'selections.*.items.*.amount_cents' => ['required', 'integer', 'min:0'],
            'selections.*.items.*.reason' => ['nullable', 'string', 'max:1000'],
            'selections.*.items.*.authority' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
