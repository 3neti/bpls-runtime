<?php

namespace App\Http\Requests;

use App\LifecycleScenarios\LifecycleCleanroomDefinition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunLifecycleCleanroomMilestoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'step_key' => ['required', 'string', Rule::in(collect(app(LifecycleCleanroomDefinition::class)->steps())->pluck('key')->all())],
        ];
    }
}
