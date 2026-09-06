<?php

namespace App\Http\Requests\Citizen;

use App\Enums\UserPermission;
use App\Http\Requests\PermitApplicationDocumentRequest;
use App\Models\PermitApplication;
use App\Support\ApplicationDocumentTypeCatalog;
use Illuminate\Validation\Rule;

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

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'label' => ['prohibited'],
            'document_type' => ['required', 'string', Rule::in(app(ApplicationDocumentTypeCatalog::class)->activeCodes())],
            'remarks' => ['prohibited'],
            'return_to' => ['nullable', Rule::in(['show', 'edit'])],
        ];
    }
}
