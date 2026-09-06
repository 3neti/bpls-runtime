<?php

namespace App\Http\Requests\Citizen;

use App\Enums\UserPermission;
use App\Models\PermitApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SubmitPermitApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::SubmitOwnPermitApplications->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $application = PermitApplication::query()->find($this->route('permitApplication'));
        $signatureRequired = data_get($application?->metadata, 'nelson_reconciliation_v1.commissioned_path') === true;

        return [
            'undertaking_accepted' => ['required', 'accepted'],
            'signature_facsimile' => [Rule::requiredIf($signatureRequired), 'nullable', File::image()->max(2048)],
        ];
    }
}
