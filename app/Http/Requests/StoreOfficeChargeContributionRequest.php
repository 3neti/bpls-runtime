<?php

namespace App\Http\Requests;

use App\Enums\StakeholderPreviewPersona;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Foundation\Http\FormRequest;

class StoreOfficeChargeContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $persona = app(StakeholderPreviewSafety::class)->personaFor($this->user());

        return $persona instanceof StakeholderPreviewPersona && $persona->isConcernedOffice();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'is_applicable' => ['required', 'boolean'],
            'amount_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
        ];
    }
}
