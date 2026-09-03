<?php

namespace App\Actions;

use App\Models\LineOfBusiness;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use UnexpectedValueException;

class BuildCitizenPermitApplicationLabFixture
{
    private const string FixturePath = 'seeders/data/ipil_citizen_permit_application.yaml';

    /** @var list<string> */
    private const array AllowedFields = [
        'registration_number',
        'reference_number',
        'registered_on',
        'ownership_type',
        'organization_name',
        'ctc_number',
        'tin',
        'tax_incentive_enjoyed',
        'tax_incentive_entity',
        'business_name',
        'trade_name',
        'business_plate_number',
        'corporate_officer_last_name',
        'corporate_officer_first_name',
        'corporate_officer_middle_name',
        'business_house_building_number',
        'business_building_name',
        'business_unit_number',
        'business_street',
        'business_barangay',
        'business_subdivision',
        'business_city_municipality',
        'business_province',
        'business_telephone',
        'business_email',
        'owner_house_building_number',
        'owner_building_name',
        'owner_unit_number',
        'owner_street',
        'owner_barangay',
        'owner_subdivision',
        'owner_city_municipality',
        'owner_province',
        'owner_telephone',
        'owner_email',
        'property_index_number',
        'business_area_square_meters',
        'total_employee_count',
        'employees_residing_in_lgu',
        'male_employee_count',
        'female_employee_count',
        'occupancy',
        'monthly_rental_pesos',
        'lessor_last_name',
        'lessor_first_name',
        'lessor_middle_name',
        'lessor_house_building_number',
        'lessor_street',
        'lessor_barangay',
        'lessor_subdivision',
        'lessor_city_municipality',
        'lessor_province',
        'lessor_telephone',
        'lessor_email',
        'emergency_contact_name',
        'emergency_contact_telephone',
        'emergency_contact_mobile',
        'emergency_contact_email',
    ];

    public function __construct(
        private readonly ResolveLegacyCitizenPermitApplicationLabPool $resolveLegacyPool,
    ) {}

    /**
     * @return array{
     *     fixture_id: string,
     *     label: string,
     *     classification: string,
     *     source_kind: string,
     *     source_reference: string,
     *     source_business_category: string|null,
     *     source_note: string,
     *     reset_fields: list<string>,
     *     fields: array<string, bool|float|int|string|null>,
     *     lines: list<array{
     *         line_of_business_id: int,
     *         line_of_business_code: string,
     *         quantity: int,
     *         capital_investment_pesos: string,
     *         essential_gross_sales_pesos: string,
     *         non_essential_gross_sales_pesos: string,
     *         started_on: string|null
     *     }>
     * }
     */
    public function handle(): array
    {
        return $this->pool()[0];
    }

    /**
     * @return list<array{
     *     fixture_id: string,
     *     label: string,
     *     classification: string,
     *     source_kind: string,
     *     source_reference: string,
     *     source_business_category: string|null,
     *     source_note: string,
     *     reset_fields: list<string>,
     *     fields: array<string, bool|float|int|string|null>,
     *     lines: list<array{
     *         line_of_business_id: int,
     *         line_of_business_code: string,
     *         quantity: int,
     *         capital_investment_pesos: string,
     *         essential_gross_sales_pesos: string,
     *         non_essential_gross_sales_pesos: string,
     *         started_on: string|null
     *     }>
     * }>
     */
    public function pool(): array
    {
        $fallback = $this->buildSyntheticFixture();
        $legacyPool = $this->resolveLegacyPool->handle();

        if ($legacyPool === []) {
            return [$fallback];
        }

        return array_map(function (array $legacy) use ($fallback): array {
            $fallbackLine = $fallback['lines'][0];

            if ($legacy['activity']['line_of_business_code'] !== $fallbackLine['line_of_business_code']) {
                throw new UnexpectedValueException('The legacy laboratory specimen uses an unavailable catalog translation.');
            }

            return [
                'fixture_id' => $legacy['fixture_id'],
                'label' => $legacy['label'],
                'classification' => $legacy['classification'],
                'source_kind' => $legacy['source_kind'],
                'source_reference' => $legacy['source_reference'],
                'source_business_category' => $legacy['source_business_category'],
                'source_note' => $legacy['source_note'],
                'reset_fields' => $fallback['reset_fields'],
                'fields' => $legacy['fields'],
                'lines' => [[
                    ...$fallbackLine,
                    ...$legacy['activity'],
                ]],
            ];
        }, $legacyPool);
    }

    /**
     * @return array{
     *     fixture_id: string,
     *     label: string,
     *     classification: string,
     *     source_kind: string,
     *     source_reference: string,
     *     source_business_category: string|null,
     *     source_note: string,
     *     reset_fields: list<string>,
     *     fields: array<string, bool|float|int|string|null>,
     *     lines: list<array{
     *         line_of_business_id: int,
     *         line_of_business_code: string,
     *         quantity: int,
     *         capital_investment_pesos: string,
     *         essential_gross_sales_pesos: string,
     *         non_essential_gross_sales_pesos: string,
     *         started_on: string|null
     *     }>
     * }
     */
    private function buildSyntheticFixture(): array
    {
        $fixture = Yaml::parseFile(
            database_path(self::FixturePath),
            Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE,
        );

        if (! is_array($fixture)
            || ($fixture['schema_version'] ?? null) !== 1
            || ($fixture['classification'] ?? null) !== 'synthetic_uat_only') {
            throw new UnexpectedValueException('The citizen permit laboratory fixture has an unsupported schema or classification.');
        }

        $fields = $fixture['fields'] ?? null;
        if (! is_array($fields) || array_is_list($fields)) {
            throw new UnexpectedValueException('The citizen permit laboratory fixture fields must be a keyed map.');
        }

        foreach ($fields as $key => $value) {
            if (! is_string($key)
                || ! in_array($key, self::AllowedFields, true)
                || (! is_scalar($value) && $value !== null)) {
                throw new UnexpectedValueException('The citizen permit laboratory fixture contains an unsupported form field.');
            }
        }

        $activities = $fixture['activities'] ?? null;
        if (! is_array($activities) || ! array_is_list($activities) || $activities === [] || count($activities) > 20) {
            throw new UnexpectedValueException('The citizen permit laboratory fixture must contain between one and twenty activities.');
        }

        $codes = collect($activities)
            ->map(fn (mixed $activity): mixed => is_array($activity) ? ($activity['line_of_business_code'] ?? null) : null)
            ->filter(fn (mixed $code): bool => is_string($code) && $code !== '')
            ->values();

        if ($codes->count() !== count($activities) || $codes->duplicates()->isNotEmpty()) {
            throw new UnexpectedValueException('Each citizen permit laboratory activity must name one unique catalog code.');
        }

        $linesByCode = LineOfBusiness::query()
            ->availableToMunicipalCatalog()
            ->whereIn('code', $codes->all())
            ->get(['id', 'code'])
            ->keyBy('code');
        $missingCodes = $codes->reject(fn (string $code): bool => $linesByCode->has($code));

        if ($missingCodes->isNotEmpty()) {
            throw new RuntimeException('Citizen permit laboratory fixture catalog code is unavailable: '.$missingCodes->join(', '));
        }

        $resolvedActivities = [];
        foreach ($activities as $activity) {
            if (! is_array($activity)) {
                throw new UnexpectedValueException('Citizen permit laboratory activities must be keyed maps.');
            }

            $code = $activity['line_of_business_code'] ?? null;
            $line = is_string($code) ? $linesByCode->get($code) : null;

            if (! $line instanceof LineOfBusiness) {
                throw new UnexpectedValueException('Citizen permit laboratory activity catalog resolution failed.');
            }

            $quantity = $activity['quantity'] ?? null;
            $capital = $activity['capital_investment_pesos'] ?? null;
            $essential = $activity['essential_gross_sales_pesos'] ?? null;
            $nonEssential = $activity['non_essential_gross_sales_pesos'] ?? null;
            $startedOn = $activity['started_on'] ?? null;

            if (! is_int($quantity)
                || $quantity < 1
                || ! is_string($capital)
                || ! is_string($essential)
                || ! is_string($nonEssential)
                || (! is_string($startedOn) && $startedOn !== null)) {
                throw new UnexpectedValueException('Citizen permit laboratory activity values are incomplete.');
            }

            $resolvedActivities[] = [
                'line_of_business_id' => $line->id,
                'line_of_business_code' => $code,
                'quantity' => $quantity,
                'capital_investment_pesos' => $capital,
                'essential_gross_sales_pesos' => $essential,
                'non_essential_gross_sales_pesos' => $nonEssential,
                'started_on' => $startedOn,
            ];
        }

        return [
            'fixture_id' => $this->requiredString($fixture, 'fixture_id'),
            'label' => $this->requiredString($fixture, 'label'),
            'classification' => 'synthetic_uat_only',
            'source_kind' => 'synthetic_yaml_fallback',
            'source_reference' => $this->requiredString($fixture, 'fixture_id'),
            'source_business_category' => null,
            'source_note' => $this->requiredString($fixture, 'source_note'),
            'reset_fields' => array_keys($fields),
            'fields' => $fields,
            'lines' => $resolvedActivities,
        ];
    }

    /** @param array<mixed> $fixture */
    private function requiredString(array $fixture, string $key): string
    {
        $value = $fixture[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new UnexpectedValueException("The citizen permit laboratory fixture requires [{$key}].");
        }

        return $value;
    }
}
