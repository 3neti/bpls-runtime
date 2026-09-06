<?php

namespace App\Http\Requests\Citizen;

use App\Actions\BuildCitizenPermitApplicationLabFixture;
use App\Actions\ResolveLifecycleCleanroomIntake;
use App\Enums\PermitApplicationType;
use App\Enums\StakeholderPreviewPersona;
use App\Enums\UserPermission;
use App\Http\Requests\PermitApplicationIntakeRequest;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

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
        $hasLaboratoryAccess = $cleanroom !== null
            || app(StakeholderPreviewSafety::class)->personaFor($this->user()) === StakeholderPreviewPersona::Citizen;
        $legacyFixtureIds = $hasLaboratoryAccess
            ? collect(app(BuildCitizenPermitApplicationLabFixture::class)->pool())
                ->where('source_kind', 'immutable_production_backup')
                ->whereNotNull('historical_assessment')
                ->pluck('fixture_id')
                ->all()
            : [];

        $rules = parent::rules();
        $nelsonPath = $this->has('business_activity_description');
        if ($nelsonPath) {
            $barangayCodes = collect(config('ipil_references.barangays.items', []))->pluck('code')->all();
            $rules['business_activity_description'] = ['required', 'string', 'max:4000'];
            $rules['business_barangay_psgc_code'] = ['required', 'string', Rule::in($barangayCodes)];
            $rules['lines'] = ['prohibited'];
        } elseif ($cleanroom !== null) {
            $rules['lines.*.line_of_business_id'] = [
                'required',
                'integer',
                Rule::exists('line_of_businesses', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('is_active', true)
                        ->where(fn (Builder $lines): Builder => $lines
                            ->whereNull('metadata->scenario_id')
                            ->orWhereIn('code', ['PRODUCT-LAB-RETAIL-TRADING', 'PRODUCT-LAB-FOOD-SERVICE'])),
                ),
            ];
        }

        return [
            ...$rules,
            'lifecycle_cleanroom_run_id' => $cleanroom === null
                ? ['prohibited']
                : ['nullable', 'string', Rule::in([$cleanroom->public_id])],
            'owner_name' => [Rule::requiredIf(! $hasRegistryOwner), 'nullable', 'string', 'max:255'],
            'business_id' => [
                'nullable',
                'integer',
                Rule::exists('businesses', 'id')->where(
                    fn (Builder $query): Builder => $query->where('business_owner_id', $this->user()?->business_owner_id),
                ),
            ],
            'business_name' => [Rule::requiredIf(! $usesExistingBusiness), 'nullable', 'string', 'max:255'],
            'lab_fixture_id' => $hasLaboratoryAccess
                ? ['nullable', 'string', Rule::in($legacyFixtureIds)]
                : ['prohibited'],
            'application_number' => ['prohibited'],
            'type' => ['required', Rule::in([PermitApplicationType::New->value])],
            'application_year' => ['required', 'integer', Rule::in($applicationYears)],
            'signature_facsimile' => $cleanroom === null
                ? ['prohibited']
                : ['nullable', File::image()->max(2048)],
        ];
    }

    /** @return array<string, mixed> */
    public function validatedForPersistence(): array
    {
        $validated = parent::validatedForPersistence();
        unset($validated['lifecycle_cleanroom_run_id']);
        unset($validated['signature_facsimile']);

        return $validated;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $barangays = collect(config('ipil_references.barangays.items', []))->keyBy('code');
        $barangay = $barangays->get((string) $this->input('business_barangay_psgc_code'));
        if (is_array($barangay)) {
            $this->merge([
                'business_barangay' => $barangay['name'],
                'barangay' => $barangay['name'],
            ]);
        }
        if ($this->has('business_activity_description')) {
            $this->request->remove('lines');
        }
    }
}
