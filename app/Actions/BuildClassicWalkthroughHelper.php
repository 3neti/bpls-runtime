<?php

namespace App\Actions;

use App\Models\LifecycleCleanroomRun;

class BuildClassicWalkthroughHelper
{
    /** @var list<string> */
    private const array Fields = [
        'business_name', 'trade_name', 'registration_number', 'ownership_type',
        'business_activity_description', 'business_street', 'owner_street', 'owner_barangay',
        'business_barangay_psgc_code', 'business_city_municipality', 'business_province',
        'owner_first_name', 'owner_middle_name', 'owner_last_name',
        'owner_city_municipality', 'owner_province', 'occupancy',
        'business_area_square_meters', 'male_employee_count', 'female_employee_count',
        'total_employee_count', 'employees_residing_in_lgu', 'monthly_rental_pesos',
        'emergency_contact_name', 'emergency_contact_mobile',
    ];

    /**
     * Presentation only: never grants the historical pricing exception or saves data.
     *
     * @param  array<string, mixed>|null  $intake
     * @return array<string, int|float|string>|null
     */
    public function handle(?LifecycleCleanroomRun $run, ?array $intake, int $citizenId): ?array
    {
        if ($run === null || $intake === null
            || $run->status !== 'active' || $run->new_application_id !== null
            || ! $run->isClassicLifecycleV1() || ! $run->usesSourceBackedNewApplication()
            || data_get($run->actor_manifest, 'semantic_classification') !== 'synthetic_only'
            || data_get($run->actor_manifest, 'production_liability') !== false
            || data_get($run->actor_manifest, 'actors.citizen.user_id') !== $citizenId
            || ($intake['application_year'] ?? null) !== 2025
            || ($intake['type'] ?? null) !== 'new'
            || ($intake['staged_citizen_intake'] ?? null) !== true
            || ($intake['run_id'] ?? null) !== $run->public_id
            || data_get($intake, 'source_specimen.id') !== BuildSourceBackedNewApplicationIntake::SpecimenId) {
            return null;
        }

        // Preserve the complete source address in Street, as the form's existing
        // business-address fallback does. Never guess a split into house/unit fields.
        $intake['business_street'] ??= $intake['business_address'] ?? null;
        $intake['owner_street'] ??= $intake['owner_address'] ?? null;
        $fields = [];
        foreach (self::Fields as $name) {
            $value = $intake[$name] ?? null;
            if (is_string($value) || is_int($value) || is_float($value)) {
                $fields[$name] = $value;
            }
        }

        return $fields;
    }
}
