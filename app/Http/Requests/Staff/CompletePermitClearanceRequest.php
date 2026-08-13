<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserPermission;
use Illuminate\Foundation\Http\FormRequest;

class CompletePermitClearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::CompletePermitClearances->value) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
