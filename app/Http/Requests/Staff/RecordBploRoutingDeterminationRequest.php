<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordBploRoutingDeterminationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::DetermineBploRouting->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'situational_context' => ['required', 'string', 'max:4000'],
            'selected_work' => ['required', 'array', 'min:1', 'max:30'],
            'selected_work.*.office_code' => ['required', 'string', 'max:80'],
            'selected_work.*.office_label' => ['required', 'string', 'max:160'],
            'selected_work.*.situational_reason' => ['required', 'string', 'max:2000'],
            'selected_work.*.required_work' => ['required', 'string', 'max:2000'],
            'selected_work.*.permit_application_line_id' => ['nullable', 'integer'],
        ];
    }
}
