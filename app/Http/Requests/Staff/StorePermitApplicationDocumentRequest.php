<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserPermission;
use App\Http\Requests\PermitApplicationDocumentRequest;
use App\Models\PermitApplication;

class StorePermitApplicationDocumentRequest extends PermitApplicationDocumentRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $permitApplication = $this->route('permitApplication');

        return $permitApplication instanceof PermitApplication
            && $permitApplication->canContinue()
            && ($this->user()?->can(UserPermission::CreatePermitApplications->value) ?? false);
    }
}
