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
            'owner_last_name' => ['required', 'string', 'max:255'],
            'owner_first_name' => ['required', 'string', 'max:255'],
            'owner_middle_name' => ['nullable', 'string', 'max:255'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'owner_phone' => ['nullable', 'string', 'max:255'],
            'owner_address' => ['nullable', 'string', 'max:1000'],
            'business_name' => ['required', 'string', 'max:255'],
            'business_plate_number' => ['nullable', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'ctc_number' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:255'],
            'tax_incentive_enjoyed' => ['nullable', 'boolean'],
            'tax_incentive_entity' => ['nullable', 'required_if:tax_incentive_enjoyed,1', 'string', 'max:255'],
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
            'date_of_application' => ['required', 'date', 'before_or_equal:today'],
            'mode_of_payment' => ['required', Rule::in(['annually', 'semi_annually', 'quarterly'])],
            'corporate_officer_last_name' => ['nullable', 'string', 'max:255'],
            'corporate_officer_first_name' => ['nullable', 'string', 'max:255'],
            'corporate_officer_middle_name' => ['nullable', 'string', 'max:255'],
            ...$this->addressRules('business'),
            ...$this->addressRules('owner'),
            'total_employee_count' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'employees_residing_in_lgu' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'monthly_rental_pesos' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'lessor_last_name' => ['nullable', 'string', 'max:255'],
            'lessor_first_name' => ['nullable', 'string', 'max:255'],
            'lessor_middle_name' => ['nullable', 'string', 'max:255'],
            ...$this->addressRules('lessor'),
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_telephone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_mobile' => ['nullable', 'string', 'max:50'],
            'emergency_contact_email' => ['nullable', 'email', 'max:255'],
            'undertaking_accepted' => ['accepted'],
            'applicant_printed_name' => ['required', 'string', 'max:255'],
            'position_title' => ['required', 'string', 'max:255'],
            'application_number' => ['nullable', 'string', 'max:255', Rule::unique('permit_applications', 'application_number')],
            'type' => ['required', Rule::enum(PermitApplicationType::class)],
            'application_year' => ['required', 'integer', 'min:2020', 'max:'.(now()->year + 1)],
            'lines' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'lines.*' => ['required', 'array:line_of_business_id,declared_gross_sales_pesos,essential_gross_sales_pesos,non_essential_gross_sales_pesos,capital_investment_pesos,quantity,started_on'],
            'lines.*.line_of_business_id' => [
                'required',
                'integer',
                Rule::exists('line_of_businesses', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->whereNull('metadata->scenario_id'),
                ),
            ],
            'lines.*.declared_gross_sales_pesos' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'lines.*.essential_gross_sales_pesos' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'lines.*.non_essential_gross_sales_pesos' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
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
            function (Validator $validator): void {
                $lines = $this->input('lines', []);
                if (! is_array($lines)) {
                    return;
                }

                foreach ($lines as $index => $line) {
                    if (! is_array($line)) {
                        continue;
                    }
                    $hasAggregate = filled($line['declared_gross_sales_pesos'] ?? null);
                    $hasEssential = filled($line['essential_gross_sales_pesos'] ?? null);
                    $hasNonEssential = filled($line['non_essential_gross_sales_pesos'] ?? null);
                    if (! $hasAggregate && ! ($hasEssential && $hasNonEssential)) {
                        $validator->errors()->add("lines.{$index}.essential_gross_sales_pesos", 'Record both Essential and Non-Essential Gross Sales.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedForPersistence(): array
    {
        $validated = $this->validated();
        $lines = $validated['lines'] ?? null;
        if (! is_array($lines)) {
            return $validated;
        }

        $validated['lines'] = array_values(array_map(
            function (mixed $line): array {
                if (! is_array($line)) {
                    throw new \UnexpectedValueException('A validated line of business must be an array.');
                }

                $essential = array_key_exists('essential_gross_sales_pesos', $line)
                    ? $this->pesosToCents($line['essential_gross_sales_pesos'] ?? 0)
                    : null;
                $nonEssential = array_key_exists('non_essential_gross_sales_pesos', $line)
                    ? $this->pesosToCents($line['non_essential_gross_sales_pesos'] ?? 0)
                    : null;
                $aggregate = filled($line['declared_gross_sales_pesos'] ?? null)
                    ? $this->pesosToCents($line['declared_gross_sales_pesos'])
                    : (int) $essential + (int) $nonEssential;

                return [
                    'line_of_business_id' => $line['line_of_business_id'],
                    'declared_gross_sales_cents' => $aggregate,
                    'essential_gross_sales_cents' => $essential,
                    'non_essential_gross_sales_cents' => $nonEssential,
                    'capital_investment_cents' => $this->pesosToCents($line['capital_investment_pesos']),
                    'quantity' => $line['quantity'],
                    'started_on' => $line['started_on'] ?? null,
                ];
            },
            $lines,
        ));

        return $validated;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareDocumentDefaults();

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

    private function prepareDocumentDefaults(): void
    {
        $name = str((string) $this->input('owner_name'))->squish()->toString();
        $parts = $name === '' ? [] : (preg_split('/\s+/', $name) ?: []);
        $lastName = count($parts) > 1 ? array_pop($parts) : ($parts[0] ?? null);
        $firstName = count($parts) > 0 ? array_shift($parts) : null;
        $middleName = $parts === [] ? null : implode(' ', $parts);
        $ownerLastName = $this->input('owner_last_name', $lastName);
        $ownerFirstName = $this->input('owner_first_name', $firstName);
        $ownerMiddleName = $this->input('owner_middle_name', $middleName);
        $printedName = collect([$ownerFirstName, $ownerMiddleName, $ownerLastName])->filter()->join(' ');

        $this->merge([
            'owner_last_name' => $ownerLastName,
            'owner_first_name' => $ownerFirstName,
            'owner_middle_name' => $ownerMiddleName,
            'owner_name' => $name !== '' ? $name : collect([$ownerFirstName, $ownerMiddleName, $ownerLastName])->filter()->join(' '),
            'date_of_application' => $this->input('date_of_application', now()->toDateString()),
            'mode_of_payment' => $this->input('mode_of_payment', 'annually'),
            'undertaking_accepted' => $this->input('undertaking_accepted', true),
            'applicant_printed_name' => $this->input('applicant_printed_name', $printedName),
            'position_title' => $this->input('position_title', 'Owner'),
        ]);
    }

    /** @return array<string, array<int, string>> */
    private function addressRules(string $prefix): array
    {
        return collect([
            'house_building_number', 'building_name', 'unit_number', 'street', 'barangay',
            'subdivision', 'city_municipality', 'province', 'telephone', 'email',
        ])->mapWithKeys(fn (string $field): array => [
            $prefix.'_'.$field => $field === 'email'
                ? ['nullable', 'email', 'max:255']
                : ['nullable', 'string', 'max:255'],
        ])->all();
    }

    private function pesosToCents(mixed $amount): int
    {
        [$pesos, $centavos] = array_pad(explode('.', (string) $amount, 2), 2, '0');

        return ((int) $pesos * 100) + (int) str_pad(substr($centavos, 0, 2), 2, '0');
    }
}
