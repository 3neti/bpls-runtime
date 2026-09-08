<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReconcileLegacyFeeCatalogCandidateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::ManageFeeRules->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['map_existing', 'create_proposed', 'quarantine'])],
            'fee_rule_id' => ['nullable', 'required_if:action,map_existing', 'integer', 'exists:fee_rules,id'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
