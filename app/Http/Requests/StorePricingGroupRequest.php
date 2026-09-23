<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePricingGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ($this->user()?->can(UserPermission::ManageFeeRules->value) ?? false)
            && $this->user()->can(UserPermission::ViewFeeRules->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9_-]+$/', 'unique:pricing_charge_groups,code'],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:pricing_charge_groups,id'],
        ];
    }
}
