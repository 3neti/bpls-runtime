<?php

namespace App\Http\Requests\Staff;

use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::CreatePermitApplications->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'owner_phone' => ['nullable', 'string', 'max:255'],
            'owner_address' => ['nullable', 'string', 'max:1000'],
            'business_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'business_address' => ['nullable', 'string', 'max:1000'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'application_number' => ['nullable', 'string', 'max:255', Rule::unique('permit_applications', 'application_number')],
            'type' => ['required', Rule::enum(PermitApplicationType::class)],
            'application_year' => ['required', 'integer', 'min:2020', 'max:'.(now()->year + 1)],
            'line_of_business_id' => ['required', 'integer', Rule::exists('line_of_businesses', 'id')],
            'declared_gross_sales_pesos' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'capital_investment_pesos' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedForCreation(): array
    {
        $validated = $this->validated();

        $validated['declared_gross_sales_cents'] = $this->pesosToCents($validated['declared_gross_sales_pesos']);
        $validated['capital_investment_cents'] = $this->pesosToCents($validated['capital_investment_pesos']);

        unset($validated['declared_gross_sales_pesos'], $validated['capital_investment_pesos']);

        return $validated;
    }

    private function pesosToCents(mixed $amount): int
    {
        [$pesos, $centavos] = array_pad(explode('.', (string) $amount, 2), 2, '0');

        return ((int) $pesos * 100) + (int) str_pad(substr($centavos, 0, 2), 2, '0');
    }
}
