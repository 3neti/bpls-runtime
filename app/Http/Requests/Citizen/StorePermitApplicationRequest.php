<?php

namespace App\Http\Requests\Citizen;

use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Http\Requests\PermitApplicationIntakeRequest;
use Illuminate\Validation\Rule;

class StorePermitApplicationRequest extends PermitApplicationIntakeRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::CreateOwnPermitApplications->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'application_number' => ['prohibited'],
            'type' => ['required', Rule::in([PermitApplicationType::New->value])],
            'application_year' => ['required', 'integer', Rule::in([now()->year])],
        ];
    }
}
