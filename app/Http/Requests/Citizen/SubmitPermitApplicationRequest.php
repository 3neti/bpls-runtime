<?php

namespace App\Http\Requests\Citizen;

use App\Enums\UserPermission;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPermitApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::SubmitOwnPermitApplications->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
