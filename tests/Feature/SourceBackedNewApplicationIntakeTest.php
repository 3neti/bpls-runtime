<?php

use App\Actions\BuildLifecycleCleanroomIntake;
use App\Actions\BuildSourceBackedNewApplicationIntake;
use App\Actions\EnsureProductLabLineOfBusinessCatalog;
use App\Actions\StartLifecycleCleanroom;
use App\Models\LifecycleCleanroomRun;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Database\Seeders\MunicipalFeeCatalogSeeder;

beforeEach(function () {
    $this->seed(MunicipalFeeCatalogSeeder::class);
});

test('private CAL specimen supplies only source inputs for a reconstructed 2025 new application', function () {
    $path = tempnam(sys_get_temp_dir(), 'cal-2026-001-');
    file_put_contents($path, json_encode([
        'schema_version' => 'bpls.lifecycle-source-specimen.v1',
        'specimen_id' => BuildSourceBackedNewApplicationIntake::SpecimenId,
        'calibration_id' => 'CAL-2026-001',
        'classification' => 'source_backed_identity_reconstructed_2025_transaction',
        'source_specimen_sha256' => '892d1c07377988ab17d12e60c7bfeb80ba9227bb5f5569bc441bf81092a9f995',
        'source_snapshot_sha256' => str_repeat('a', 64),
        'intake' => [
            'application_year' => 2025,
            'type' => 'new',
            'line_of_business_code' => 'LOB-3A9A93CA46967768',
            'owner_name' => 'Source Owner',
            'owner_first_name' => 'Source',
            'owner_last_name' => 'Owner',
            'owner_address' => 'Source owner address',
            'business_name' => 'Source Business',
            'business_address' => 'Source business address',
            'barangay' => 'Don Andres',
            'business_barangay_psgc_code' => '0908305006',
            'business_area_square_meters' => '12.00',
            'male_employee_count' => 1,
            'female_employee_count' => 0,
            'total_employee_count' => 1,
            'employees_residing_in_lgu' => 1,
            'applicant_printed_name' => 'Source Owner',
        ],
    ], JSON_THROW_ON_ERROR));
    config()->set('stakeholder_preview.source_backed_2025_specimen_path', $path);

    try {
        $intake = app(BuildSourceBackedNewApplicationIntake::class)->handle();
    } finally {
        @unlink($path);
    }

    expect($intake)
        ->owner_name->toBe('Source Owner')
        ->business_name->toBe('Source Business')
        ->application_year->toBe(2025)
        ->type->toBe('new')
        ->total_employee_count->toBe(1)
        ->business_area_square_meters->toBe('12.00')
        ->source_specimen->id->toBe(BuildSourceBackedNewApplicationIntake::SpecimenId)
        ->source_specimen->chronology->toBe('reconstructed_2025_new_application')
        ->source_specimen->external_payment_simulation_only->toBeTrue()
        ->source_specimen->expected_treasury_line_of_business_code->toBe('LOB-3A9A93CA46967768')
        ->lines->toBe([]);
});

test('cleanroom intake selects the private source specimen only when the run explicitly requests it', function () {
    $sourceIntake = [
        'application_year' => 2025,
        'type' => 'new',
        'owner_name' => 'Source Owner',
        'business_name' => 'Source Business',
        'source_specimen' => ['id' => BuildSourceBackedNewApplicationIntake::SpecimenId],
        'lines' => [],
    ];
    $this->mock(BuildSourceBackedNewApplicationIntake::class)
        ->shouldReceive('handle')->once()->andReturn($sourceIntake);
    $run = LifecycleCleanroomRun::factory()->make([
        'public_id' => '01SOURCEBACKED2025RUN0000',
        'actor_manifest' => [
            'ceremony' => LifecycleCleanroomRun::CeremonyNelsonReconciliationV1,
            'source_specimen' => ['id' => LifecycleCleanroomRun::SourceSpecimenCal2026001New2025],
        ],
    ]);

    $intake = app(BuildLifecycleCleanroomIntake::class)->handle($run);

    expect($intake)
        ->owner_name->toBe('Source Owner')
        ->business_name->toBe('Source Business')
        ->ceremony->toBe(LifecycleCleanroomRun::CeremonyNelsonReconciliationV1)
        ->run_id->toBe('01SOURCEBACKED2025RUN0000');
});

test('an active cleanroom cannot silently replace a requested source-backed profile', function () {
    $this->mock(StakeholderPreviewSafety::class)
        ->shouldReceive('ensureReady')->once();
    $this->mock(EnsureProductLabLineOfBusinessCatalog::class)
        ->shouldReceive('handle')->once();
    $startedBy = User::factory()->create();
    $this->mock(BuildSourceBackedNewApplicationIntake::class)
        ->shouldReceive('handle')->once()->andReturn([
            'source_specimen' => ['id' => LifecycleCleanroomRun::SourceSpecimenCal2026001New2025],
        ]);
    LifecycleCleanroomRun::factory()->create([
        'status' => 'active',
        'started_by_id' => $startedBy->id,
        'actor_manifest' => [
            'ceremony' => LifecycleCleanroomRun::CeremonyNelsonReconciliationV1,
            'actors' => [],
        ],
    ]);

    expect(fn () => app(StartLifecycleCleanroom::class)->handle(
        $startedBy,
        LifecycleCleanroomRun::CeremonyNelsonReconciliationV1,
        LifecycleCleanroomRun::SourceSpecimenCal2026001New2025,
    ))->toThrow(RuntimeException::class, 'Close the active lifecycle cleanroom');
});
