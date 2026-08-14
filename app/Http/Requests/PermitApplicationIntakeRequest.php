<?php

namespace App\Http\Requests;

use App\Enums\PermitApplicationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class PermitApplicationIntakeRequest extends FormRequest
{
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
            'ownership_type' => ['nullable', Rule::in(['sole-proprietorship', 'partnership', 'corporation', 'cooperative', 'religious', 'non-profit'])],
            'organization_name' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('ownership_type'), ['partnership', 'corporation', 'cooperative'], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'occupancy' => ['nullable', Rule::in(['owned', 'rented'])],
            'building_name' => ['nullable', 'string', 'max:255'],
            'property_index_number' => ['nullable', 'string', 'max:255'],
            'business_area_square_meters' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'male_employee_count' => ['nullable', 'required_with:female_employee_count', 'integer', 'min:0', 'max:999999'],
            'female_employee_count' => ['nullable', 'required_with:male_employee_count', 'integer', 'min:0', 'max:999999'],
            'business_contact_number' => ['nullable', 'string', 'max:50'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'established_on' => ['nullable', 'date', 'before_or_equal:today'],
            'started_on' => ['nullable', 'date', 'before_or_equal:today'],
            'registered_on' => ['nullable', 'date', 'before_or_equal:today'],
            'application_number' => ['nullable', 'string', 'max:255', Rule::unique('permit_applications', 'application_number')],
            'type' => ['required', Rule::enum(PermitApplicationType::class)],
            'application_year' => ['required', 'integer', 'min:2020', 'max:'.(now()->year + 1)],
            'lines' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'lines.*' => ['required', 'array:line_of_business_id,declared_gross_sales_pesos,capital_investment_pesos,quantity,started_on'],
            'lines.*.line_of_business_id' => ['required', 'integer', Rule::exists('line_of_businesses', 'id')],
            'lines.*.declared_gross_sales_pesos' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'lines.*.capital_investment_pesos' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'lines.*.started_on' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('male_employee_count') && ! $this->filled('female_employee_count')) {
                    return;
                }

                if ($this->integer('male_employee_count') + $this->integer('female_employee_count') < 1) {
                    $validator->errors()->add('male_employee_count', 'At least one employee must be recorded when employee counts are provided.');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedForCreation(): array
    {
        $validated = $this->validated();

        $validated['lines'] = collect($validated['lines'])
            ->map(fn (array $line): array => [
                'line_of_business_id' => $line['line_of_business_id'],
                'declared_gross_sales_cents' => $this->pesosToCents($line['declared_gross_sales_pesos']),
                'capital_investment_cents' => $this->pesosToCents($line['capital_investment_pesos']),
                'quantity' => $line['quantity'],
                'started_on' => $line['started_on'] ?? null,
            ])
            ->all();

        return $validated;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('lines') !== null || ! $this->hasAny([
            'line_of_business_id',
            'declared_gross_sales_pesos',
            'capital_investment_pesos',
            'quantity',
        ])) {
            return;
        }

        $this->merge([
            'lines' => [[
                'line_of_business_id' => $this->input('line_of_business_id'),
                'declared_gross_sales_pesos' => $this->input('declared_gross_sales_pesos'),
                'capital_investment_pesos' => $this->input('capital_investment_pesos'),
                'quantity' => $this->input('quantity'),
                'started_on' => $this->input('line_started_on'),
            ]],
        ]);
    }

    private function pesosToCents(mixed $amount): int
    {
        [$pesos, $centavos] = array_pad(explode('.', (string) $amount, 2), 2, '0');

        return ((int) $pesos * 100) + (int) str_pad(substr($centavos, 0, 2), 2, '0');
    }
}
