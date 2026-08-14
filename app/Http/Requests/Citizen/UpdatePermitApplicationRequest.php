<?php

namespace App\Http\Requests\Citizen;

use App\Enums\UserPermission;

class UpdatePermitApplicationRequest extends StorePermitApplicationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::EditOwnPermitApplications->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'draft_version' => ['required', 'date'],
        ];
    }
}
