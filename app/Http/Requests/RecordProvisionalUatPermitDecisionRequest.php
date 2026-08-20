<?php

namespace App\Http\Requests;

use App\Enums\StakeholderPreviewPersona;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordProvisionalUatPermitDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(app(StakeholderPreviewSafety::class)->personaFor($this->user()), [
            StakeholderPreviewPersona::MayorOffice,
            StakeholderPreviewPersona::Releasing,
        ], true);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['go', 'no_go', 'release'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
