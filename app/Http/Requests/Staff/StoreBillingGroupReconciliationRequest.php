<?php

namespace App\Http\Requests\Staff;

use App\Enums\BillingGroupEvidenceType;
use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillingGroupReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ViewBillingGroups->value)
            && $this->user()->can(UserPermission::RecordBillingGroupReconciliationEvidence->value);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'evidence_type' => ['required', Rule::enum(BillingGroupEvidenceType::class)],
            'evidence_reference' => ['required', 'string', 'max:500'],
            'source_excerpt' => ['nullable', 'string', 'max:10000'],
            'operational_interpretation' => ['nullable', 'string', 'max:5000'],
            'unresolved_questions' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'unresolved_questions.*' => ['required', 'string', 'max:1000', 'distinct:strict'],
        ];
    }

    /** @return array{evidence_type: string, evidence_reference: string, source_excerpt?: string|null, operational_interpretation?: string|null, unresolved_questions: list<string>} */
    public function validatedForEvidence(): array
    {
        $questions = [];

        foreach ($this->array('unresolved_questions') as $question) {
            if (is_string($question)) {
                $questions[] = $question;
            }
        }

        return [
            'evidence_type' => $this->string('evidence_type')->toString(),
            'evidence_reference' => $this->string('evidence_reference')->toString(),
            'source_excerpt' => $this->filled('source_excerpt') ? $this->string('source_excerpt')->toString() : null,
            'operational_interpretation' => $this->filled('operational_interpretation') ? $this->string('operational_interpretation')->toString() : null,
            'unresolved_questions' => $questions,
        ];
    }
}
