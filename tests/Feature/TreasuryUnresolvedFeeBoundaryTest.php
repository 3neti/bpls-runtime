<?php

use App\Actions\AssignTreasuryLinesOfBusiness;
use App\Actions\BuildBploRoutingTask;
use App\Assessment\TreasuryFeeResolution;
use App\Enums\UserPermission;
use App\Models\FeeRule;
use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;
use App\Models\SignatureEvidence;
use Database\Seeders\MunicipalFeeCatalogSeeder;
use Illuminate\Validation\ValidationException;

test('ordinary Treasury projects unresolved canonical identity rather than a resolved zero', function (): void {
    $this->seed(MunicipalFeeCatalogSeeder::class);
    $application = PermitApplication::factory()->create(['application_year' => 2026, 'type' => 'new']);
    PermitApplicationDeclaration::factory()->for($application)->create([
        'snapshot' => ['schema_version' => 1, 'establishment' => [
            'male_employees' => 1, 'female_employees' => 0, 'business_area_square_meters' => '12.00',
        ]],
    ]);
    $lob = collect(app(BuildBploRoutingTask::class)->handle($application, null)->toArray()['financial_editor']['line_of_business_options'])
        ->firstWhere('code', 'LOB-3A9A93CA46967768');
    $items = collect($lob['default_items']);
    $mayor = $items->firstWhere('code', 'IPIL-LEGACY-5F028B76EEBEF485');
    expect($mayor['resolution_status'])->toBe('unresolved')
        ->and($mayor['resolution_message'])->toBe('TBD — enterprise classification required')
        ->and($mayor['fee_rule_id'])->toBe(FeeRule::query()->where('code', $mayor['code'])->sole()->id)
        ->and($items->pluck('name')->filter(fn (string $name): bool => str_contains(strtolower($name), 'business tax')))->toBeEmpty();
});

test('zero nonzero and omitted unresolved Treasury defaults fail before creating canonical records', function (string $mode): void {
    config(['treasury_enterprise.provisional_uat_enabled' => false]);

    $this->seed(MunicipalFeeCatalogSeeder::class);
    $application = PermitApplication::factory()->create([
        'application_year' => 2026, 'type' => 'new',
        'metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => true]],
    ]);
    $rule = FeeRule::query()->where('code', 'IPIL-LEGACY-5F028B76EEBEF485')->sole();
    $known = FeeRule::query()->where('code', 'IPIL-LEGACY-A9B730041C0AE6F6')->sole();
    $actor = userWithPermissions([UserPermission::AccessStaff, UserPermission::CorrectEvaluationLinesOfBusiness]);
    $before = $application->fresh()->toJson();
    $catalogue = FeeRule::query()->orderBy('id')->get()->toJson();
    $items = [['fee_rule_id' => $mode === 'omitted' ? $known->id : $rule->id, 'amount_cents' => $mode === 'zero' ? 0 : 100_000]];
    expect(fn () => app(AssignTreasuryLinesOfBusiness::class)->handle($application, [[
        'line_of_business_id' => $rule->line_of_business_id, 'items' => $items,
    ]], $actor))->toThrow(ValidationException::class, 'unresolved');
    expect($application->treasuryLineOfBusinessAssignments()->count())->toBe(0)
        ->and($application->lines()->count())->toBe(0)
        ->and($application->assessments()->count())->toBe(0)
        ->and(SignatureEvidence::query()->count())->toBe(0)
        ->and($application->fresh()->toJson())->toBe($before)
        ->and(FeeRule::query()->orderBy('id')->get()->toJson())->toBe($catalogue);
    $this->actingAs($actor)->postJson('/staff/permit-applications/'.$application->id.'/treasury-lines-of-business', [
        'selections' => [['line_of_business_id' => $rule->line_of_business_id, 'items' => $items]],
    ])->assertUnprocessable()->assertJsonValidationErrors('selections');
    expect($application->treasuryLineOfBusinessAssignments()->count())->toBe(0);
})->with(['zero', 'nonzero', 'omitted']);

test('a resolved zero is distinct from legacy unresolved and ordinary 2025 gets no replay exception', function (): void {
    $application = PermitApplication::factory()->create(['application_year' => 2025]);
    $resolved = FeeRule::factory()->create(['amount_cents' => 0, 'basis' => 'none']);
    $unresolved = FeeRule::factory()->create(['amount_cents' => 0, 'basis' => 'legacy_unresolved', 'code' => 'IPIL-LEGACY-5F028B76EEBEF485']);
    expect(app(TreasuryFeeResolution::class)->unresolved($resolved, $application))->toBeFalse()
        ->and(app(TreasuryFeeResolution::class)->unresolved($unresolved, $application))->toBeTrue();
});

test('Classic Treasury reference requires the existing exact historical replay binding', function (string $brokenBinding): void {
    $fee = FeeRule::factory()->create([
        'code' => 'IPIL-LEGACY-5F028B76EEBEF485',
        'basis' => 'legacy_unresolved',
        'effective_from' => '2025-01-01',
    ]);
    $run = LifecycleCleanroomRun::factory()->create([
        'actor_manifest' => [
            'ceremony' => LifecycleCleanroomRun::CeremonyClassicLifecycleV1,
            'semantic_classification' => 'synthetic_only',
            'production_liability' => false,
            'source_specimen' => ['id' => LifecycleCleanroomRun::SourceSpecimenCal2026001New2025],
        ],
    ]);
    $metadata = [
        'lifecycle_cleanroom' => [
            'ceremony' => LifecycleCleanroomRun::CeremonyClassicLifecycleV1,
            'run_id' => $run->public_id,
            'scenario_id' => 'classic-reference-test',
            'semantic_classification' => 'synthetic_only',
            'production_liability' => false,
            'source_specimen' => ['id' => LifecycleCleanroomRun::SourceSpecimenCal2026001New2025],
        ],
        'business_permit_evaluation' => [
            'cleanroom_run_id' => $run->public_id,
            'scenario_id' => 'classic-reference-test',
            'semantic_classification' => 'provisional_uat',
            'production_liability' => false,
        ],
    ];
    if (str_starts_with($brokenBinding, 'metadata:')) {
        data_set($metadata, substr($brokenBinding, 9), 'invalid');
    }
    $application = PermitApplication::factory()->create([
        'application_year' => $brokenBinding === 'year' ? 2026 : 2025,
        'type' => 'new',
        'metadata' => $metadata,
    ]);
    $run->update(['new_application_id' => $brokenBinding === 'application' ? null : $application->id]);
    if (str_starts_with($brokenBinding, 'manifest:')) {
        $manifest = $run->actor_manifest;
        data_set($manifest, substr($brokenBinding, 9), 'invalid');
        $run->update(['actor_manifest' => $manifest]);
    }
    if ($brokenBinding === 'fee') {
        $fee->update(['code' => 'OTHER-FEE']);
    }

    $before = $application->fresh()->toJson();
    $editor = app(BuildBploRoutingTask::class)->handle($application, null)->financial_editor;

    expect($editor['classic_walkthrough_reference'])->toBe($brokenBinding === 'none')
        ->and($application->fresh()->toJson())->toBe($before)
        ->and($application->treasuryLineOfBusinessAssignments()->count())->toBe(0);
})->with([
    'none', 'year', 'application', 'fee',
    'metadata:lifecycle_cleanroom.ceremony',
    'metadata:lifecycle_cleanroom.run_id',
    'metadata:lifecycle_cleanroom.scenario_id',
    'metadata:lifecycle_cleanroom.source_specimen.id',
    'metadata:lifecycle_cleanroom.semantic_classification',
    'metadata:lifecycle_cleanroom.production_liability',
    'metadata:business_permit_evaluation.semantic_classification',
    'metadata:business_permit_evaluation.production_liability',
    'manifest:source_specimen.id',
    'manifest:semantic_classification',
    'manifest:production_liability',
]);
