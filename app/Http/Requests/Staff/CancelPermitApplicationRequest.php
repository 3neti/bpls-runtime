<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserPermission;
use Illuminate\Foundation\Http\FormRequest;

class CancelPermitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::UpdatePermitApplicationStatus->value) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
