<?php

namespace App\Http\Requests\Citizen;

use App\Actions\ResolveLifecycleCleanroomIntake;
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
        $cleanroom = app(ResolveLifecycleCleanroomIntake::class)->handle($this);
        $applicationYears = $cleanroom === null ? [now()->year] : [2025];

        $rules = parent::rules();
        if ($cleanroom !== null) {
            $rules['lines.*.line_of_business_id'] = [
                'required',
                'integer',
                Rule::exists('line_of_businesses', 'id')->where(fn (Builder $query): Builder => $query->where('is_active', true)->whereIn('code', ['PRODUCT-LAB-RETAIL-TRADING', 'PRODUCT-LAB-FOOD-SERVICE'])),
            ];
        }

        return [
            ...$rules,
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
            'application_year' => ['required', 'integer', Rule::in($applicationYears)],
        ];
    }
}
