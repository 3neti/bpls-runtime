<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserPermission;
use App\Models\PermitApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StorePermitApplicationDocumentRequest extends FormRequest
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'file' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(10 * 1024)],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
