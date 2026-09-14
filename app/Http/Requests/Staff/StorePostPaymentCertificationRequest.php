<?php

namespace App\Http\Requests\Staff;

use App\Actions\AuthorizePostPaymentCertification;
use App\Models\PostPaymentOfficeCertification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostPaymentCertificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $certification = $this->route('certification');

        return $certification instanceof PostPaymentOfficeCertification
            && app(AuthorizePostPaymentCertification::class)->allows($certification, $this->user());
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['result' => ['required', Rule::in(['certified', 'returned'])], 'remarks' => ['nullable', 'string', 'max:2000']];
    }
}
