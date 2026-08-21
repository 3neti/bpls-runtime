<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::ApproveAssessments->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assessment_snapshot_hash' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/i'],
        ];
    }
}
