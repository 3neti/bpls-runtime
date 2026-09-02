<?php

namespace App\Actions;

use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\Models\LifecycleCleanroomRun;
use App\Models\LineOfBusiness;

class BuildLifecycleCleanroomIntake
{
    public function __construct(private readonly NewApplicationHappyPathDefinition $definition) {}

    /** @return array<string, mixed> */
    public function handle(LifecycleCleanroomRun $run): array
    {
        $linesByCode = LineOfBusiness::query()
            ->whereIn('code', collect($this->definition->linesOfBusiness())->pluck('code'))
            ->get(['id', 'code'])
            ->keyBy('code');

        return [
            'run_id' => $run->public_id,
            'application_year' => NewApplicationHappyPathDefinition::ApplicationYear,
            'owner_name' => 'Cleanroom Synthetic Owner '.str($run->public_id)->substr(-6)->upper(),
            'owner_first_name' => 'Cleanroom',
            'owner_middle_name' => 'Synthetic',
            'owner_last_name' => 'Owner '.str($run->public_id)->substr(-6)->upper(),
            'owner_address' => 'Synthetic Ipil cleanroom address',
            'business_name' => 'Cleanroom Market and Kitchen '.str($run->public_id)->substr(-6)->upper(),
            'trade_name' => 'Cleanroom Product Laboratory',
            'registration_number' => 'CLEANROOM-'.str($run->public_id)->substr(-10)->upper(),
            'business_address' => 'Synthetic Ipil cleanroom address',
            'barangay' => 'Synthetic Barangay',
            'ownership_type' => 'sole-proprietorship',
            'date_of_application' => '2025-01-15',
            'mode_of_payment' => 'annually',
            'business_city_municipality' => 'Ipil',
            'business_province' => 'Zamboanga Sibugay',
            'owner_city_municipality' => 'Ipil',
            'owner_province' => 'Zamboanga Sibugay',
            'occupancy' => 'rented',
            'business_area_square_meters' => '84.50',
            'male_employee_count' => 3,
            'female_employee_count' => 4,
            'total_employee_count' => 7,
            'employees_residing_in_lgu' => 7,
            'monthly_rental_pesos' => '12000.00',
            'emergency_contact_name' => 'Cleanroom Emergency Contact',
            'emergency_contact_mobile' => '09990000000',
            'applicant_printed_name' => 'Cleanroom Synthetic Owner',
            'position_title' => 'Owner',
            'undertaking_accepted' => true,
            'lines' => collect($this->definition->linesOfBusiness())->map(fn (array $line): array => [
                'line_of_business_id' => $linesByCode->get($line['code'])?->id,
                'declared_gross_sales_pesos' => (string) ($line['declared_gross_sales_cents'] / 100),
                'essential_gross_sales_pesos' => '0.00',
                'non_essential_gross_sales_pesos' => (string) ($line['declared_gross_sales_cents'] / 100),
                'capital_investment_pesos' => (string) ($line['capital_investment_cents'] / 100),
                'quantity' => 1,
            ])->values()->all(),
        ];
    }
}
