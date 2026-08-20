<?php

use App\Actions\BuildNelsonWalkthroughEvidence;
use App\LifecycleScenarios\LifecycleScenarioRegistry;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('nelson walkthrough is a local transactional profile of the proven citizen lifecycle', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('nelson_walkthrough');

    expect($scenario)
        ->key->toBe('nelson_walkthrough')
        ->label->toBe('Nelson municipal workflow walkthrough')
        ->risk->toBe('local transactional')
        ->and($scenario->actors)->toBe([
            'applicant' => 'citizen_applicant',
            'operator' => 'primary_operator',
            'approver' => 'assessment_approver',
            'recipient' => 'sample_recipient',
        ])
        ->and($scenario->expectations['ready_for_authority_review'])->toBeTrue()
        ->and($scenario->expectations['can_release'])->toBeFalse()
        ->and($scenario->expectations['official_application_number'])->toBeNull()
        ->and($scenario->safety['external_integrations'])->toBeFalse()
        ->and($scenario->safety['irreversible_actions'])->toBeFalse();
});

test('walkthrough migration evidence is payload safe and preserves the accepted aggregate facts', function () {
    $evidence = app(BuildNelsonWalkthroughEvidence::class)->handle();
    $encoded = json_encode($evidence, JSON_THROW_ON_ERROR);

    expect($evidence['snapshot']['status'])->toBe('Immutable and read only')
        ->and($evidence['calibration']['reference'])->toBe('CAL-2026-001')
        ->and($evidence['historical_evidence'])->toMatchArray([
            'application_count' => 407,
            'schedule_count' => 696,
            'fee_line_count' => 3_007,
            'completed_payment_count' => 660,
            'unpaid_schedule_count' => 36,
            'scheduled_amount_cents' => 412_770_810,
            'paid_amount_cents' => 397_445_008,
            'operational_financial_mutation_count' => 0,
        ])
        ->and($evidence['historical_evidence']['rehearsal_phases'])->each->toBe('passed')
        ->and($evidence['identity_frontier']['reconciliation_required_count'])->toBe(736)
        ->and($encoded)->not->toContain('sha256')
        ->and($encoded)->not->toContain('legacy_record_id')
        ->and($encoded)->not->toContain('storage_id')
        ->and($encoded)->not->toContain('taxpayer');
});

test('local preparation creates runtime-only demo credentials and a resumable walkthrough package', function () {
    Storage::fake('local');
    $password = 'Nelson-Walkthrough-Only-2026';
    putenv('NELSON_WALKTHROUGH_PASSWORD='.$password);

    try {
        $this->artisan('lifecycle:prepare-nelson-walkthrough', [
            '--run-id' => 'nelson-walkthrough-test-001',
            '--phase' => 'prepare',
        ])->assertSuccessful();
    } finally {
        putenv('NELSON_WALKTHROUGH_PASSWORD');
        putenv('LIFECYCLE_BROWSER_EMAIL');
        putenv('LIFECYCLE_BROWSER_PASSWORD');
        putenv('LIFECYCLE_BROWSER_OPERATOR_EMAIL');
        putenv('LIFECYCLE_BROWSER_OPERATOR_PASSWORD');
        putenv('LIFECYCLE_ASSESSMENT_APPROVER_EMAIL');
    }

    $citizen = User::query()->where('email', 'nelson.walkthrough.citizen@example.test')->firstOrFail();
    $operator = User::query()->where('email', 'nelson.walkthrough.operator@example.test')->firstOrFail();
    $approver = User::query()->where('email', 'nelson.walkthrough.treasurer@example.test')->firstOrFail();
    $artifactStore = new ScenarioArtifactStore('nelson_walkthrough', 'nelson-walkthrough-test-001');
    $manifest = $artifactStore->readJson('manifest.json');
    $presenterScript = Storage::disk('local')->get($artifactStore->rootRelativePath().'/walkthrough/presenter-script.md');

    expect(Hash::check($password, $citizen->password))->toBeTrue()
        ->and(Hash::check($password, $operator->password))->toBeTrue()
        ->and($citizen->role?->code)->toBe('citizen')
        ->and($operator->role?->code)->toBe('admin')
        ->and($approver->id)->not->toBe($operator->id)
        ->and($manifest['resources']['assessment_approved_by_id'])->toBe($approver->id)
        ->and($manifest['resources']['assessment_prepared_by_id'])->toBe($operator->id)
        ->and($manifest['scenario']['key'])->toBe('nelson_walkthrough')
        ->and($manifest['result']['terminal'])->toBe('passed')
        ->and($manifest['resources']['application_number'])->toBeNull()
        ->and($manifest['resources']['ready_for_authority_review'])->toBeTrue()
        ->and($manifest['resources']['can_release'])->toBeFalse()
        ->and($manifest['walkthrough']['evidence']['historical_evidence']['application_count'])->toBe(407)
        ->and($artifactStore->exists('walkthrough/evidence.json'))->toBeTrue()
        ->and($artifactStore->exists('walkthrough/migration-evidence.html'))->toBeTrue()
        ->and($artifactStore->exists('walkthrough/what-nelson-is-seeing.html'))->toBeTrue()
        ->and($artifactStore->exists('walkthrough/what-nelson-is-seeing.md'))->toBeTrue()
        ->and($artifactStore->exists('walkthrough/presenter-script.md'))->toBeTrue()
        ->and($presenterScript)->toContain($manifest['resources']['detail_url'])
        ->and($presenterScript)->not->toContain($password);
});

test('walkthrough user preparation refuses missing credentials and non-local environments', function () {
    putenv('NELSON_WALKTHROUGH_PASSWORD');

    $this->artisan('lifecycle:prepare-nelson-walkthrough', [
        '--phase' => 'prepare',
    ])->assertFailed();

    putenv('NELSON_WALKTHROUGH_PASSWORD=Nelson-Walkthrough-Only-2026');
    app()->detectEnvironment(fn (): string => 'production');

    try {
        $this->artisan('lifecycle:prepare-nelson-walkthrough', [
            '--phase' => 'prepare',
        ])->assertFailed();
    } finally {
        putenv('NELSON_WALKTHROUGH_PASSWORD');
    }
});
