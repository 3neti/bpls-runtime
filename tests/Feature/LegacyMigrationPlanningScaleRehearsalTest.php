<?php

use App\Actions\GenerateLegacyMigrationScaleFixture;
use App\Actions\RehearseLegacyMigrationPlanningScale;
use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyDeclarationMappingPlan;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyPermitEvidencePlan;
use App\Models\LegacyRecord;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\PermitApplicationLine;
use App\Models\PermitClearance;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** @return array<string, mixed> */
function smallLegacyScaleProfile(string $key = 'test-scale-profile'): array
{
    return [
        'key' => $key,
        'counts' => [
            'business_owners' => 3,
            'businesses' => 4,
            'business_permit_applications' => 5,
            'payment_schedules' => 5,
            'payments' => 10,
            'permit_clearances' => 5,
            'permits' => 4,
        ],
        'lines_per_application' => 2,
        'evidence' => [
            'exact_observations' => ['business_owners' => 3],
            'synthetic_assumptions' => ['isolated test profile'],
        ],
    ];
}

test('scale fixture generation is deterministic relational and contains no production records', function () {
    Storage::fake('local');
    $action = app(GenerateLegacyMigrationScaleFixture::class);
    $first = $action->handle('scale-fixture-deterministic', smallLegacyScaleProfile());
    $second = $action->handle('scale-fixture-deterministic', smallLegacyScaleProfile());
    $manifest = json_decode((string) file_get_contents($first['manifest_path']), true, flags: JSON_THROW_ON_ERROR);
    $profile = json_decode(Storage::disk('local')->get($first['artifact_root'].'/profile.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($second['profile_hash'])->toBe($first['profile_hash'])
        ->and($second['manifest_path'])->toBe($first['manifest_path'])
        ->and($manifest['source']['source_type'])->toBe('synthetic_scale_fixture')
        ->and($manifest['source']['provenance']['production_data'])->toBeFalse()
        ->and($manifest['datasets'])->toHaveCount(7)
        ->and($first['dataset_counts'])->toBe(smallLegacyScaleProfile()['counts'])
        ->and($profile['evidence']['personal_data_in_fixture'])->toBeFalse();

    foreach ($manifest['datasets'] as $dataset) {
        $path = dirname($first['manifest_path']).'/'.$dataset['file'];
        expect(hash_file('sha256', $path))->toBe($dataset['sha256']);
    }

    expect(fn () => $action->handle('scale-fixture-deterministic', smallLegacyScaleProfile('different-profile')))
        ->toThrow(RuntimeException::class, 'different profile');
});

test('planning scale rehearsal invokes real staging and planners without domain writes', function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    Notification::fake();
    $fixture = app(GenerateLegacyMigrationScaleFixture::class)->handle('scale-real-actions', smallLegacyScaleProfile());
    $result = app(RehearseLegacyMigrationPlanningScale::class)->handle($fixture['manifest_path'], 'scale-real-actions');

    expect($result['batch']->source_record_count)->toBe(36)
        ->and($result['batch']->staged_record_count)->toBe(36)
        ->and(LegacyRecord::query()->count())->toBe(36)
        ->and($result['registry_plan']->owner_proposal_count)->toBe(3)
        ->and($result['registry_plan']->business_proposal_count)->toBe(4)
        ->and($result['application_plan']->proposal_count)->toBe(5)
        ->and($result['declaration_plan']->proposal_count)->toBe(10)
        ->and($result['financial_plan']->proposal_count)->toBeGreaterThanOrEqual(15)
        ->and($result['permit_evidence_plan']->proposal_count)->toBeGreaterThanOrEqual(9)
        ->and($result['phases'])->toHaveCount(7)
        ->and(collect($result['phases'])->pluck('records_processed')->every(fn (int $count): bool => $count > 0))->toBeTrue()
        ->and($result['domain_writes'])->toBeFalse()
        ->and($result['domain_counts_before'])->toBe($result['domain_counts_after'])
        ->and(BusinessOwner::query()->count())->toBe(0)
        ->and(Business::query()->count())->toBe(0)
        ->and(PermitApplication::query()->count())->toBe(0)
        ->and(PermitApplicationLine::query()->count())->toBe(0)
        ->and(Assessment::query()->count())->toBe(0)
        ->and(PaymentSchedule::query()->count())->toBe(0)
        ->and(TreasuryCollection::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0)
        ->and(PermitClearance::query()->count())->toBe(0)
        ->and(PermitApplicationDocument::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('scale rehearsal command requires dual confirmation and preserves redacted immutable evidence', function () {
    Storage::fake('local');
    $arguments = [
        '--profile' => 'smoke',
        '--run-id' => 'scale-command-smoke',
        '--json' => true,
    ];

    $this->artisan('legacy:rehearse-planning-scale', $arguments)->assertFailed();
    $this->artisan('legacy:rehearse-planning-scale', [...$arguments, '--rehearse' => true, '--confirm-rehearse' => true])->assertSuccessful();
    $this->artisan('legacy:rehearse-planning-scale', [...$arguments, '--rehearse' => true, '--confirm-rehearse' => true])->assertSuccessful();

    $root = 'legacy-migrations/scale-fixtures/scale-command-smoke';
    Storage::disk('local')->assertExists($root.'/manifest.json');
    Storage::disk('local')->assertExists($root.'/profile.json');
    Storage::disk('local')->assertExists($root.'/scale-rehearsal.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $report = Storage::disk('local')->get($root.'/scale-rehearsal.json');
    $decoded = json_decode($report, true, flags: JSON_THROW_ON_ERROR);

    expect($decoded['result']['passed'])->toBeTrue()
        ->and($decoded['result']['source_records'])->toBe(44)
        ->and($decoded['safety'])->toMatchArray([
            'domain_writes' => false,
            'external_integrations' => false,
            'production_data_used' => false,
            'personal_data_recorded' => false,
            'production_parity_claimed' => false,
            'cutover_authorized' => false,
        ])
        ->and($report)->not->toContain('@', 'password', 'token', 'cookie')
        ->and(LegacyImportBatch::query()->count())->toBe(1)
        ->and(LegacyMappingPlan::query()->count())->toBe(1)
        ->and(LegacyApplicationMappingPlan::query()->count())->toBe(1)
        ->and(LegacyDeclarationMappingPlan::query()->count())->toBe(1)
        ->and(LegacyFinancialMappingPlan::query()->count())->toBe(1)
        ->and(LegacyPermitEvidencePlan::query()->count())->toBe(1);
});

test('scale fixture rejects unsafe volume and impossible payment topology', function (array $profile, string $message) {
    Storage::fake('local');

    expect(fn () => app(GenerateLegacyMigrationScaleFixture::class)->handle('scale-invalid-profile', $profile))
        ->toThrow(RuntimeException::class, $message);
})->with([
    'too many records' => [
        fn (): array => [
            ...smallLegacyScaleProfile(),
            'counts' => [
                ...smallLegacyScaleProfile()['counts'],
                'payments' => 100_000,
                'permit_clearances' => 50_000,
            ],
        ],
        'limited to 150,000',
    ],
    'payments without schedules' => [
        fn (): array => [
            ...smallLegacyScaleProfile(),
            'counts' => [
                ...smallLegacyScaleProfile()['counts'],
                'payment_schedules' => 0,
            ],
        ],
        'require at least one payment schedule',
    ],
]);
