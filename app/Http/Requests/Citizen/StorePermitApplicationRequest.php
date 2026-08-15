<?php

namespace App\Http\Requests\Citizen;

use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Http\Requests\PermitApplicationIntakeRequest;
use Illuminate\Database\Query\Builder;
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
        $hasRegistryOwner = $this->user()?->business_owner_id !== null;
        $usesExistingBusiness = $this->filled('business_id');

        return [
            ...parent::rules(),
            'owner_name' => [Rule::requiredIf(! $hasRegistryOwner), 'nullable', 'string', 'max:255'],
            'business_id' => [
                'nullable',
                'integer',
                Rule::exists('businesses', 'id')->where(
                    fn (Builder $query): Builder => $query->where('business_owner_id', $this->user()?->business_owner_id),
                ),
            ],
            'business_name' => [Rule::requiredIf(! $usesExistingBusiness), 'nullable', 'string', 'max:255'],
            'application_number' => ['prohibited'],
            'type' => ['required', Rule::in([PermitApplicationType::New->value])],
            'application_year' => ['required', 'integer', Rule::in([now()->year])],
        ];
    }
}
