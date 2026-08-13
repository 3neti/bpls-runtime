<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateStoryboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::ManageStoryboards->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'frames' => ['required', 'array', 'min:1', 'max:60'],
            'frames.*.title' => ['required', 'string', 'max:255'],
            'frames.*.description' => ['nullable', 'string', 'max:3000'],
            'frames.*.dialogue' => ['nullable', 'string', 'max:3000'],
            'frames.*.duration_seconds' => ['required', 'integer', 'min:1', 'max:30'],
            'frames.*.existing_image_path' => ['nullable', 'string', 'max:255'],
            'frames.*.image' => ['nullable', File::image()->max(5 * 1024)],
        ];
    }
}
