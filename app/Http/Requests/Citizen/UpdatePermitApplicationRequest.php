<?php

namespace App\Http\Requests\Citizen;

use App\Enums\UserPermission;
use App\Models\PermitApplication;
use Illuminate\Validation\Rule;

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
        $rules = parent::rules();
        $applicationYear = PermitApplication::query()
            ->whereKey((int) $this->route('permit_application'))
            ->whereHas('business', fn ($query) => $query->where('business_owner_id', $this->user()?->business_owner_id))
            ->value('application_year');

        return [
            ...$rules,
            'application_year' => ['required', 'integer', Rule::in([$applicationYear])],
            'draft_version' => ['required', 'date'],
            'application_documents' => ['prohibited'],
        ];
    }
}
