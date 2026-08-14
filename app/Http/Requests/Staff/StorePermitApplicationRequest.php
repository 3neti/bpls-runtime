<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserPermission;
use App\Http\Requests\PermitApplicationIntakeRequest;

class StorePermitApplicationRequest extends PermitApplicationIntakeRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::CreatePermitApplications->value) ?? false;
    }
}
