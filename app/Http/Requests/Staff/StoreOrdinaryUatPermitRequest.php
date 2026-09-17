<?php

namespace App\Http\Requests\Staff;

use App\Actions\OrdinaryUatPermitAuthority;
use App\Models\PermitApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrdinaryUatPermitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $application = $this->route('permitApplication');

        return $application instanceof PermitApplication
            && app(OrdinaryUatPermitAuthority::class)->allows($application, $this->user(), $this->input('ceremony') === 'release' ? 'releasing' : 'mayor');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ceremony' => ['required', Rule::in(['authorize', 'issue', 'release'])],
        ];
    }
}
