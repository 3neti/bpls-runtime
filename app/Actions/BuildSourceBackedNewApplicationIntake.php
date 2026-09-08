<?php

namespace App\Actions;

use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\Models\LineOfBusiness;
use JsonException;
use RuntimeException;

class BuildSourceBackedNewApplicationIntake
{
    public const string SpecimenId = 'cal-2026-001-2025-new';

    private const string DefaultPrivatePath = 'app/private/lifecycle-laboratory/'.self::SpecimenId.'.json';

    /** @return array<string, mixed> */
    public function handle(): array
    {
        $configuredPath = config('stakeholder_preview.source_backed_2025_specimen_path');
        $path = is_string($configuredPath) && trim($configuredPath) !== ''
            ? $configuredPath
            : storage_path(self::DefaultPrivatePath);

        if (! app()->environment(['local', 'testing']) || ! is_file($path)) {
            throw new RuntimeException('The private CAL-2026-001 source-backed 2025 specimen is unavailable.');
        }

        try {
            $specimen = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The private CAL-2026-001 source-backed 2025 specimen is invalid JSON.', 0, $exception);
        }

        if (! is_array($specimen)
            || ($specimen['schema_version'] ?? null) !== 'bpls.lifecycle-source-specimen.v1'
            || ($specimen['specimen_id'] ?? null) !== self::SpecimenId
            || ($specimen['calibration_id'] ?? null) !== 'CAL-2026-001'
            || ($specimen['source_specimen_sha256'] ?? null) !== '892d1c07377988ab17d12e60c7bfeb80ba9227bb5f5569bc441bf81092a9f995'
            || ($specimen['classification'] ?? null) !== 'source_backed_identity_reconstructed_2025_transaction') {
            throw new RuntimeException('The private CAL-2026-001 source-backed 2025 specimen contract is invalid.');
        }

        $intake = $specimen['intake'] ?? null;
        if (! is_array($intake) || array_is_list($intake)) {
            throw new RuntimeException('The private CAL-2026-001 source-backed 2025 intake is unavailable.');
        }

        $expected = [
            'application_year' => NewApplicationHappyPathDefinition::ApplicationYear,
            'type' => 'new',
            'line_of_business_code' => 'LOB-3A9A93CA46967768',
            'total_employee_count' => 1,
            'business_area_square_meters' => '12.00',
        ];
        foreach ($expected as $key => $value) {
            if (($intake[$key] ?? null) !== $value) {
                throw new RuntimeException("The private CAL-2026-001 source-backed 2025 intake disagrees on [{$key}].");
            }
        }

        foreach (['owner_name', 'owner_first_name', 'owner_last_name', 'owner_address', 'business_name', 'business_address', 'barangay', 'applicant_printed_name'] as $key) {
            if (! is_string($intake[$key] ?? null) || trim($intake[$key]) === '') {
                throw new RuntimeException("The private CAL-2026-001 source-backed 2025 intake requires [{$key}].");
            }
        }

        LineOfBusiness::query()
            ->availableToMunicipalCatalog()
            ->where('code', $expected['line_of_business_code'])
            ->sole();
        $payload = $intake;
        unset($payload['line_of_business_code']);
        $payload['lines'] = [];

        return [
            ...$payload,
            'source_specimen' => [
                'id' => self::SpecimenId,
                'calibration_id' => 'CAL-2026-001',
                'source_specimen_sha256' => $specimen['source_specimen_sha256'],
                'classification' => $specimen['classification'],
                'source_snapshot_sha256' => $specimen['source_snapshot_sha256'] ?? null,
                'chronology' => 'reconstructed_2025_new_application',
                'expected_treasury_line_of_business_code' => $expected['line_of_business_code'],
                'external_payment_simulation_only' => true,
                'production_liability' => false,
            ],
        ];
    }
}
