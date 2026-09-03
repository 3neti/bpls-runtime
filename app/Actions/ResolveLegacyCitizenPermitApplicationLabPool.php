<?php

namespace App\Actions;

use JsonException;
use RuntimeException;
use SplFileObject;

class ResolveLegacyCitizenPermitApplicationLabPool
{
    private const string DefaultTablesPath = 'app/private/legacy-migrations/convex-snapshots/prod-convex-20260816-224400/tables';

    private const string CatalogCode = 'MRC-2A-02-B-WHOLESALE-RETAIL';

    /** @var list<string> */
    private const array SourceBusinessCategories = [
        'REC- SARISARI STORE',
        'REC- GROCERY',
        'RNEC- DRY GOODS RETAILER',
        'REC- FOOD PRODUCTS',
        'REC- SCHOOL SUPPLIES',
        'RNEC- HARDWARE RETAILER',
    ];

    /**
     * @return list<array{
     *     fixture_id: string,
     *     label: string,
     *     classification: string,
     *     source_kind: string,
     *     source_reference: string,
     *     source_business_category: string,
     *     source_note: string,
     *     historical_assessment: array<string, mixed>,
     *     fields: array<string, bool|float|int|string|null>,
     *     activity: array{
     *         line_of_business_code: string,
     *         quantity: int,
     *         capital_investment_pesos: string,
     *         essential_gross_sales_pesos: string,
     *         non_essential_gross_sales_pesos: string,
     *         started_on: string|null
     *     }
     * }>
     */
    public function handle(): array
    {
        $tablesPath = $this->tablesPath();

        if ($tablesPath === null) {
            return [];
        }

        $businesses = $this->keyedRecords($tablesPath.'/businesses.jsonl');
        $owners = $this->keyedRecords($tablesPath.'/business_owners.jsonl');
        $barangays = $this->keyedRecords($tablesPath.'/barangays.jsonl');
        $cities = $this->keyedRecords($tablesPath.'/cities.jsonl');
        $provinces = $this->keyedRecords($tablesPath.'/provinces.jsonl');
        $applications = $this->records($tablesPath.'/business_permit_applications.jsonl');
        $paymentSchedules = collect($this->records($tablesPath.'/payment_schedules.jsonl'))
            ->groupBy(fn (array $schedule): string => $this->text($schedule['applicationId'] ?? null));

        usort($applications, fn (array $left, array $right): int => [
            $this->number($right['_creationTime'] ?? null),
            $this->text($right['_id'] ?? null),
        ] <=> [
            $this->number($left['_creationTime'] ?? null),
            $this->text($left['_id'] ?? null),
        ]);

        $pool = [];
        $selectedBusinessIds = [];

        foreach (self::SourceBusinessCategories as $category) {
            foreach ($applications as $application) {
                $businessId = $this->text($application['businessId'] ?? null);
                $applicationId = $this->text($application['_id'] ?? null);

                if ($businessId === '' || isset($selectedBusinessIds[$businessId])) {
                    continue;
                }

                $resolved = $this->resolveCandidate(
                    $application,
                    $category,
                    $businesses,
                    $owners,
                    $barangays,
                    $cities,
                    $provinces,
                    $paymentSchedules->get($applicationId, collect())->values()->all(),
                );

                if ($resolved === null) {
                    continue;
                }

                $pool[] = $resolved;
                $selectedBusinessIds[$businessId] = true;

                break;
            }
        }

        if ($pool === []) {
            throw new RuntimeException('The authorized legacy snapshot contains no complete New retail specimen in Ipil.');
        }

        return $pool;
    }

    private function tablesPath(): ?string
    {
        $configured = config('stakeholder_preview.legacy_lab_snapshot_tables');

        if (is_string($configured) && trim($configured) !== '') {
            $path = $configured;
        } elseif (app()->environment('local')) {
            $path = storage_path(self::DefaultTablesPath);
        } else {
            return null;
        }

        return is_dir($path) ? rtrim($path, DIRECTORY_SEPARATOR) : null;
    }

    /**
     * @param  array<string, mixed>  $application
     * @param  array<string, array<string, mixed>>  $businesses
     * @param  array<string, array<string, mixed>>  $owners
     * @param  array<string, array<string, mixed>>  $barangays
     * @param  array<string, array<string, mixed>>  $cities
     * @param  array<string, array<string, mixed>>  $provinces
     * @param  array<int, array<string, mixed>>  $paymentSchedules
     * @return array{
     *     fixture_id: string,
     *     label: string,
     *     classification: string,
     *     source_kind: string,
     *     source_reference: string,
     *     source_business_category: string,
     *     source_note: string,
     *     historical_assessment: array<string, mixed>,
     *     fields: array<string, bool|float|int|string|null>,
     *     activity: array{
     *         line_of_business_code: string,
     *         quantity: int,
     *         capital_investment_pesos: string,
     *         essential_gross_sales_pesos: string,
     *         non_essential_gross_sales_pesos: string,
     *         started_on: string|null
     *     }
     * }|null
     */
    private function resolveCandidate(
        array $application,
        string $category,
        array $businesses,
        array $owners,
        array $barangays,
        array $cities,
        array $provinces,
        array $paymentSchedules,
    ): ?array {
        if (($application['isDeleted'] ?? false) === true
            || $this->text($application['permitApplicationType'] ?? null) !== 'New') {
            return null;
        }

        $sourceLine = null;
        $lines = $application['linesOfBusiness'] ?? null;

        if (is_array($lines)) {
            foreach ($lines as $line) {
                if (is_array($line)
                    && $this->text($line['businessCategory'] ?? null) === $category) {
                    $sourceLine = $line;

                    break;
                }
            }
        }
        $business = $businesses[$this->text($application['businessId'] ?? null)] ?? null;

        if (! is_array($sourceLine)
            || ! is_array($business)
            || ($business['isDeleted'] ?? false) === true
            || ($business['isBlacklisted'] ?? false) === true) {
            return null;
        }

        $businessOwnerId = $this->text($business['ownerId'] ?? null);
        $applicationOwnerId = $this->text($application['businessOwnerId'] ?? null);
        $owner = $owners[$businessOwnerId] ?? null;

        if ($businessOwnerId === ''
            || ($applicationOwnerId !== '' && $applicationOwnerId !== $businessOwnerId)
            || ! is_array($owner)
            || ($owner['isDeleted'] ?? false) === true) {
            return null;
        }

        $barangay = $barangays[$this->text($business['barangayId'] ?? null)] ?? null;
        $city = $cities[$this->text($business['cityId'] ?? null)] ?? null;
        $province = $provinces[$this->text($business['provinceId'] ?? null)] ?? null;
        $ownerBarangay = $barangays[$this->text($owner['barangayId'] ?? null)] ?? null;
        $ownerCity = $cities[$this->text($owner['cityId'] ?? null)] ?? null;
        $ownerProvince = $provinces[$this->text($owner['provinceId'] ?? null)] ?? null;

        if (! is_array($barangay)
            || ! is_array($city)
            || ! is_array($province)
            || $this->text($barangay['cityId'] ?? null) !== $this->text($business['cityId'] ?? null)
            || $this->text($city['provinceId'] ?? null) !== $this->text($business['provinceId'] ?? null)
            || strcasecmp($this->text($city['name'] ?? null), 'Ipil') !== 0
            || strcasecmp($this->text($province['name'] ?? null), 'Zamboanga Sibugay') !== 0
            || ! is_array($ownerBarangay)
            || ! is_array($ownerCity)
            || ! is_array($ownerProvince)
            || $this->text($ownerBarangay['cityId'] ?? null) !== $this->text($owner['cityId'] ?? null)
            || $this->text($ownerCity['provinceId'] ?? null) !== $this->text($owner['provinceId'] ?? null)) {
            return null;
        }

        $businessName = $this->text($business['name'] ?? null);
        $businessAddress = $this->text($business['address'] ?? null);
        $applicationNumber = $this->text($application['applicationNumber'] ?? null);
        $capital = $this->money($sourceLine['capitalInvestment'] ?? null);
        $gross = $this->money($sourceLine['grossSales'] ?? null);
        $ownerFirstName = $this->text($owner['firstName'] ?? null);
        $ownerLastName = $this->text($owner['lastName'] ?? null);
        $ownerAddress = $this->text($owner['address'] ?? null);
        $historicalAssessment = $this->historicalAssessment($application, $paymentSchedules);

        if ($businessName === ''
            || $businessAddress === ''
            || $applicationNumber === ''
            || $capital === null
            || $ownerFirstName === ''
            || $ownerLastName === ''
            || $ownerAddress === ''
            || $historicalAssessment === null) {
            return null;
        }

        $fields = array_filter([
            'reference_number' => $applicationNumber,
            'registration_number' => $this->nullableText($business['registrationNumber'] ?? null),
            'registered_on' => $this->date($business['registrationDate'] ?? null),
            'ownership_type' => $this->enum($business['ownershipType'] ?? null, [
                'sole proprietorship' => 'sole-proprietorship',
                'sole-proprietorship' => 'sole-proprietorship',
                'partnership' => 'partnership',
                'corporation' => 'corporation',
                'cooperative' => 'cooperative',
                'non-profit' => 'non-profit',
            ]),
            'business_name' => $businessName,
            'business_building_name' => $this->nullableText($business['buildingName'] ?? null),
            'business_street' => $businessAddress,
            'business_barangay' => $this->text($barangay['name'] ?? null),
            'business_city_municipality' => $this->text($city['name'] ?? null),
            'business_province' => $this->text($province['name'] ?? null),
            'business_telephone' => $this->nullableText($business['contactNumber'] ?? null),
            'business_email' => $this->nullableText($business['email'] ?? null),
            'business_area_square_meters' => $this->nullableScalar($business['businessArea'] ?? null),
            'total_employee_count' => $this->nullableScalar($business['numberOfEmployees'] ?? null),
            'male_employee_count' => $this->nullableScalar($business['maleEmployeeCount'] ?? null),
            'female_employee_count' => $this->nullableScalar($business['femaleEmployeeCount'] ?? null),
            'occupancy' => $this->enum($business['occupancy'] ?? null, [
                'owned' => 'owned',
                'rented' => 'rented',
            ]),
            'owner_last_name' => $ownerLastName,
            'owner_first_name' => $ownerFirstName,
            'owner_middle_name' => $this->nullableText($owner['middleName'] ?? null),
            'owner_street' => $ownerAddress,
            'owner_barangay' => $this->text($ownerBarangay['name'] ?? null),
            'owner_city_municipality' => $this->text($ownerCity['name'] ?? null),
            'owner_province' => $this->text($ownerProvince['name'] ?? null),
            'owner_telephone' => $this->nullableText($owner['mobile'] ?? null),
            'owner_email' => $this->nullableText($owner['email'] ?? null),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            'fixture_id' => 'legacy-ipil-'.substr(hash('sha256', $applicationNumber), 0, 12),
            'label' => $businessName.' · '.$this->text($barangay['name'] ?? null),
            'classification' => 'authorized_legacy_source_lab_only',
            'source_kind' => 'immutable_production_backup',
            'source_reference' => $applicationNumber,
            'source_business_category' => $category,
            'source_note' => 'Taxpayer, owner address, business, registration, contact, workforce, and declaration values are copied from one exact owner-business-application chain in the immutable legacy Ipil backup. The synthetic Preview Citizen remains only the logged-in testing actor. The legacy retail category is provisionally translated to the current wholesale/retail catalog, and gross sales are placed in non-essential sales for Nelson to verify.',
            'historical_assessment' => $historicalAssessment,
            'fields' => $fields,
            'activity' => [
                'line_of_business_code' => self::CatalogCode,
                'quantity' => 1,
                'capital_investment_pesos' => $capital,
                'essential_gross_sales_pesos' => '0.00',
                'non_essential_gross_sales_pesos' => $gross ?? '0.00',
                'started_on' => $this->date($business['dateStarted'] ?? null),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $application
     * @param  array<int, array<string, mixed>>  $paymentSchedules
     * @return array<string, mixed>|null
     */
    private function historicalAssessment(array $application, array $paymentSchedules): ?array
    {
        if ($paymentSchedules === [] || $this->text($application['assessedAt'] ?? null) === '') {
            return null;
        }

        usort($paymentSchedules, fn (array $left, array $right): int => [
            $this->number($left['sectionNumber'] ?? null),
            $this->number($left['_creationTime'] ?? null),
        ] <=> [
            $this->number($right['sectionNumber'] ?? null),
            $this->number($right['_creationTime'] ?? null),
        ]);

        $recordedTotalAmountCents = 0;
        $componentTotalAmountCents = 0;
        $scheduleEvidence = [];

        foreach ($paymentSchedules as $schedule) {
            $totalAmountCents = $this->cents($schedule['totalAmount'] ?? null);
            $paidAmountCents = $this->cents($schedule['paidAmount'] ?? 0);
            $surchargeAmountCents = $this->cents($schedule['surcharge'] ?? 0);
            $penaltyAmountCents = $this->cents($schedule['penalty'] ?? 0);
            $fees = $schedule['fees'] ?? null;

            if ($totalAmountCents === null
                || $paidAmountCents === null
                || $surchargeAmountCents === null
                || $penaltyAmountCents === null
                || ! is_array($fees)
                || ! array_is_list($fees)) {
                return null;
            }

            $feeEvidence = [];
            $feeTotalAmountCents = 0;

            foreach ($fees as $fee) {
                if (! is_array($fee) || array_is_list($fee)) {
                    return null;
                }

                $name = $this->text($fee['feeName'] ?? null);
                $category = $this->text($fee['feeCategory'] ?? null);
                $amountCents = $this->cents($fee['sectionAmount'] ?? null);

                if ($name === '' || $category === '' || $amountCents === null) {
                    return null;
                }

                $feeEvidence[] = [
                    'name' => $name,
                    'category' => $category,
                    'amount_cents' => $amountCents,
                ];
                $feeTotalAmountCents += $amountCents;
            }

            $recordedTotalAmountCents += $totalAmountCents;
            $componentTotalAmountCents += $feeTotalAmountCents + $surchargeAmountCents + $penaltyAmountCents;
            $scheduleEvidence[] = [
                'section' => (int) $this->number($schedule['sectionNumber'] ?? null),
                'status' => $this->text($schedule['status'] ?? null),
                'total_amount_cents' => $totalAmountCents,
                'paid_amount_cents' => $paidAmountCents,
                'fee_total_amount_cents' => $feeTotalAmountCents,
                'surcharge_amount_cents' => $surchargeAmountCents,
                'penalty_amount_cents' => $penaltyAmountCents,
                'fees' => $feeEvidence,
            ];
        }

        $evidence = [
            'source_status' => $this->text($application['status'] ?? null),
            'source_assessed_at' => $this->text($application['assessedAt'] ?? null),
            'recorded_total_amount_cents' => $recordedTotalAmountCents,
            'component_total_amount_cents' => $componentTotalAmountCents,
            'source_internal_reconciles' => $recordedTotalAmountCents === $componentTotalAmountCents,
            'schedules' => $scheduleEvidence,
        ];

        return [
            ...$evidence,
            'source_evidence_hash' => $this->fingerprint($evidence),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function keyedRecords(string $path): array
    {
        $records = [];

        foreach ($this->records($path) as $record) {
            $id = $this->text($record['_id'] ?? null);

            if ($id !== '') {
                $records[$id] = $record;
            }
        }

        return $records;
    }

    /** @return list<array<string, mixed>> */
    private function records(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Authorized legacy snapshot table is missing: {$path}");
        }

        $records = [];
        $file = new SplFileObject($path, 'rb');

        while (! $file->eof()) {
            $line = trim((string) $file->fgets());

            if ($line === '') {
                continue;
            }

            try {
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException("Authorized legacy snapshot table contains invalid JSON: {$path}", 0, $exception);
            }

            if (! is_array($record) || array_is_list($record)) {
                throw new RuntimeException("Authorized legacy snapshot table contains a non-record row: {$path}");
            }

            $records[] = $record;
        }

        return $records;
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function nullableText(mixed $value): ?string
    {
        $value = $this->text($value);

        return $value === '' ? null : $value;
    }

    private function nullableScalar(mixed $value): string|int|float|null
    {
        return is_string($value) || is_int($value) || is_float($value) ? $value : null;
    }

    /** @param array<string, string> $values */
    private function enum(mixed $value, array $values): ?string
    {
        return $values[strtolower($this->text($value))] ?? null;
    }

    private function date(mixed $value): ?string
    {
        $value = $this->text($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1
            ? substr($value, 0, 10)
            : null;
    }

    private function money(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $normalized = str_replace([',', '₱', 'PHP', ' '], '', (string) $value);

        return is_numeric($normalized) ? $normalized : null;
    }

    private function cents(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $normalized = trim((string) $value);
        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized) !== 1) {
            return null;
        }

        [$pesos, $centavos] = array_pad(explode('.', $normalized, 2), 2, '');

        return ((int) $pesos * 100) + (int) str_pad($centavos, 2, '0');
    }

    /** @param array<string, mixed> $value */
    private function fingerprint(array $value): string
    {
        return hash('sha256', json_encode(
            $this->normalize($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        ));
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
