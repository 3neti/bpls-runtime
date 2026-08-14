<?php

namespace App\Http\Requests\Citizen;

use App\Enums\UserPermission;
use App\Http\Requests\PermitApplicationDocumentRequest;
use App\Models\PermitApplication;

class StorePermitApplicationDocumentRequest extends PermitApplicationDocumentRequest
{
    public function authorize(): bool
    {
        $permitApplicationId = $this->route('permitApplication');

        return $this->user()?->can(UserPermission::UploadOwnPermitApplicationDocuments->value) === true
            && PermitApplication::query()
                ->whereKey($permitApplicationId)
                ->whereBelongsTo($this->user(), 'submittedBy')
                ->exists();
    }
}
