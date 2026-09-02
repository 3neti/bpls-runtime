<?php

namespace App\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BuildPermitApplicationDeclarationDraft
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        [$derivedLastName, $derivedFirstName, $derivedMiddleName] = $this->splitName($this->text($data, 'owner_name'));

        return [
            'schema_version' => 1,
            'application' => [
                'date_of_application' => $this->text($data, 'date_of_application'),
                'tax_year' => Arr::get($data, 'application_year'),
                'type' => $this->text($data, 'type'),
                'mode_of_payment' => $this->text($data, 'mode_of_payment') ?? 'annually',
                'transfer' => ['ownership' => false, 'location' => false, 'semantics' => 'unresolved'],
                'amendment' => ['selection' => null, 'semantics' => 'unresolved'],
            ],
            'registration' => [
                'number' => $this->text($data, 'registration_number'),
                'reference_number' => $this->text($data, 'reference_number'),
                'registered_on' => $this->text($data, 'registered_on'),
            ],
            'organization' => [
                'type' => $this->text($data, 'ownership_type'),
                'organization_name' => $this->text($data, 'organization_name'),
                'ctc_number' => $this->text($data, 'ctc_number'),
                'tin' => $this->text($data, 'tin'),
                'tax_incentive_enjoyed' => Arr::get($data, 'tax_incentive_enjoyed'),
                'tax_incentive_entity' => $this->text($data, 'tax_incentive_entity'),
            ],
            'taxpayer' => [
                'last_name' => $this->text($data, 'owner_last_name') ?? $derivedLastName,
                'first_name' => $this->text($data, 'owner_first_name') ?? $derivedFirstName,
                'middle_name' => $this->text($data, 'owner_middle_name') ?? $derivedMiddleName,
            ],
            'business' => [
                'name' => $this->text($data, 'business_name'),
                'plate_number' => $this->text($data, 'business_plate_number'),
                'trade_name' => $this->text($data, 'trade_name'),
            ],
            'corporate_officer' => [
                'last_name' => $this->text($data, 'corporate_officer_last_name'),
                'first_name' => $this->text($data, 'corporate_officer_first_name'),
                'middle_name' => $this->text($data, 'corporate_officer_middle_name'),
            ],
            'business_address' => $this->address($data, 'business'),
            'owner_address' => $this->address($data, 'owner'),
            'establishment' => [
                'property_index_number' => $this->text($data, 'property_index_number'),
                'business_area_square_meters' => Arr::get($data, 'business_area_square_meters'),
                'total_employees' => Arr::get($data, 'total_employee_count'),
                'employees_residing_in_lgu' => Arr::get($data, 'employees_residing_in_lgu'),
                'male_employees' => Arr::get($data, 'male_employee_count'),
                'female_employees' => Arr::get($data, 'female_employee_count'),
            ],
            'rental' => [
                'place_is_rented' => $this->text($data, 'occupancy') === 'rented',
                'monthly_rental_pesos' => Arr::get($data, 'monthly_rental_pesos'),
                'lessor' => [
                    'last_name' => $this->text($data, 'lessor_last_name'),
                    'first_name' => $this->text($data, 'lessor_first_name'),
                    'middle_name' => $this->text($data, 'lessor_middle_name'),
                    'address' => $this->address($data, 'lessor'),
                ],
            ],
            'emergency_contact' => [
                'name' => $this->text($data, 'emergency_contact_name'),
                'telephone' => $this->text($data, 'emergency_contact_telephone'),
                'mobile' => $this->text($data, 'emergency_contact_mobile'),
                'email' => $this->text($data, 'emergency_contact_email'),
            ],
            'undertaking' => [
                'accepted' => (bool) Arr::get($data, 'undertaking_accepted', false),
                'applicant_printed_name' => $this->text($data, 'applicant_printed_name'),
                'position_title' => $this->text($data, 'position_title'),
                'signature_semantics' => 'municipal_semantics_unresolved',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private function address(array $data, string $prefix): array
    {
        return [
            'house_or_building_number' => $this->text($data, $prefix.'_house_building_number'),
            'building_name' => $this->text($data, $prefix.'_building_name') ?? ($prefix === 'business' ? $this->text($data, 'building_name') : null),
            'unit_number' => $this->text($data, $prefix.'_unit_number'),
            'street' => $this->text($data, $prefix.'_street') ?? ($prefix === 'business' ? $this->text($data, 'business_address') : ($prefix === 'owner' ? $this->text($data, 'owner_address') : null)),
            'barangay' => $this->text($data, $prefix.'_barangay') ?? ($prefix === 'business' ? $this->text($data, 'barangay') : null),
            'subdivision' => $this->text($data, $prefix.'_subdivision'),
            'city_municipality' => $this->text($data, $prefix.'_city_municipality'),
            'province' => $this->text($data, $prefix.'_province'),
            'telephone' => $this->text($data, $prefix.'_telephone') ?? ($prefix === 'business' ? $this->text($data, 'business_contact_number') : ($prefix === 'owner' ? $this->text($data, 'owner_phone') : null)),
            'email' => $this->text($data, $prefix.'_email') ?? ($prefix === 'business' ? $this->text($data, 'business_email') : ($prefix === 'owner' ? $this->text($data, 'owner_email') : null)),
        ];
    }

    /** @param array<string, mixed> $data */
    private function text(array $data, string $key): ?string
    {
        $value = Arr::get($data, $key);

        return is_scalar($value) && Str::of((string) $value)->trim()->isNotEmpty()
            ? Str::of((string) $value)->trim()->toString()
            : null;
    }

    /** @return array{string|null, string|null, string|null} */
    private function splitName(?string $name): array
    {
        $parts = $name === null ? [] : (preg_split('/\s+/', trim($name)) ?: []);
        if ($parts === []) {
            return [null, null, null];
        }
        if (count($parts) === 1) {
            return [$parts[0], null, null];
        }

        $lastName = array_pop($parts);
        $firstName = array_shift($parts);

        return [$lastName, $firstName, $parts === [] ? null : implode(' ', $parts)];
    }
}
