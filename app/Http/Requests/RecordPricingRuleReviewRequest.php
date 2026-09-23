<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use Illuminate\Foundation\Http\FormRequest;

class RecordPricingRuleReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can(UserPermission::ManageFeeRules->value)
            && $user->can(UserPermission::ViewFeeRules->value);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'snapshot_sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'reconciliation_id' => ['required', 'integer', 'min:1'],
            'review_reference' => ['required', 'string', 'max:1000'],
        ];
    }
}
