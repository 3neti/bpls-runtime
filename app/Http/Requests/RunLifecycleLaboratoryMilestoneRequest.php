<?php

namespace App\Http\Requests;

use App\Actions\ExecutePersistedLifecycleScenario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RunLifecycleLaboratoryMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'scenario_id' => ['required', 'string', Rule::in(ExecutePersistedLifecycleScenario::scenarioIds())],
        ];
    }
}
