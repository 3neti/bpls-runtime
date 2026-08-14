<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

abstract class PermitApplicationDocumentRequest extends FormRequest
{
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
