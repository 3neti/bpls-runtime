<?php

use App\Enums\StakeholderPreviewPersona;
use App\Enums\UserPermission;
use App\LifecycleScenarios\LifecycleScenarioRegistry;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\Models\BillingGroup;
use App\Models\BillingGroupRecord;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('stakeholder preview is a local synthetic composition of existing lifecycle behavior', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('stakeholder_preview_cycle_1');

    expect($scenario)
        ->key->toBe('stakeholder_preview_cycle_1')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['ready_for_authority_review'])->toBeTrue()
        ->and($scenario->expectations['can_release'])->toBeFalse()
        ->and($scenario->expectations['official_application_number'])->toBeNull()
        ->and($scenario->safety['external_integrations'])->toBeFalse()
        ->and($scenario->safety['irreversible_actions'])->toBeFalse();
});

test('preview preparation creates synthetic role accounts and policy-bound evidence without storing credentials', function () {
    Storage::fake('local');
    $password = 'Stakeholder-Preview-Only-2026';
    configureSafeStakeholderPreview($password);

    try {
        $exitCode = Artisan::call('lifecycle:prepare-stakeholder-preview', [
            '--run-id' => 'stakeholder-preview-test-001',
            '--phase' => 'prepare',
        ]);
        if ($exitCode !== 0) {
            $debugStore = new ScenarioArtifactStore('stakeholder_preview_cycle_1', 'stakeholder-preview-test-001');
            $failure = $debugStore->readJson('failure.json');
            $debugManifest = $debugStore->readJson('manifest.json');
            $failedSteps = collect(data_get($debugManifest, 'steps', []))->where('passed', false)->values()->all();
            throw new RuntimeException((string) data_get($failure, 'message', json_encode($failedSteps, JSON_THROW_ON_ERROR)));
        }
        $this->assertSame(0, $exitCode, Artisan::output());
    } finally {
        foreach ([
            'LIFECYCLE_BROWSER_EMAIL',
            'LIFECYCLE_BROWSER_PASSWORD',
            'LIFECYCLE_BROWSER_OPERATOR_EMAIL',
            'LIFECYCLE_BROWSER_OPERATOR_PASSWORD',
            'LIFECYCLE_BROWSER_BPLO_EMAIL',
            'LIFECYCLE_BROWSER_BPLO_PASSWORD',
            'LIFECYCLE_BROWSER_TREASURY_EMAIL',
            'LIFECYCLE_BROWSER_TREASURY_PASSWORD',
            'LIFECYCLE_ASSESSMENT_PREPARER_EMAIL',
            'LIFECYCLE_ASSESSMENT_APPROVER_EMAIL',
            'LIFECYCLE_PREVIEW_ENGINEERING_EMAIL',
            'LIFECYCLE_PREVIEW_MPDO_EMAIL',
            'LIFECYCLE_PREVIEW_ASSESSOR_EMAIL',
            'LIFECYCLE_PREVIEW_HEALTH_EMAIL',
            'LIFECYCLE_PREVIEW_MENRO_EMAIL',
            'LIFECYCLE_PREVIEW_MAYOR_OFFICE_EMAIL',
            'LIFECYCLE_PREVIEW_RELEASING_EMAIL',
        ] as $key) {
            putenv($key);
        }
    }

    $accounts = User::query()
        ->whereIn('email', collect(StakeholderPreviewPersona::cases())->map->approvedEmail())
        ->with('role.permissions')
        ->get()
        ->keyBy('email');
    $store = new ScenarioArtifactStore('stakeholder_preview_cycle_1', 'stakeholder-preview-test-001');
    $manifest = $store->readJson('manifest.json');
    $encodedManifest = json_encode($manifest, JSON_THROW_ON_ERROR);
    $billingGroup = BillingGroup::query()->where('metadata->scenario_run_id', 'stakeholder-preview-test-001')->sole();
    $record = BillingGroupRecord::query()->where('source_snapshot->scenario_run_id', 'stakeholder-preview-test-001')->sole();

    expect($accounts)->toHaveCount(11)
        ->and($accounts)->each(fn ($user) => $user->password->not->toBe($password))
        ->and(Hash::check($password, $accounts['stakeholder.preview.citizen@example.test']->password))->toBeTrue()
        ->and($accounts['stakeholder.preview.citizen@example.test']->role?->code)->toBe('preview_citizen')
        ->and($accounts['stakeholder.preview.bplo@example.test']->role?->code)->toBe('preview_bplo')
        ->and($accounts['stakeholder.preview.treasury@example.test']->role?->code)->toBe('preview_treasury')
        ->and($accounts['stakeholder.preview.management@example.test']->role?->code)->toBe('preview_management')
        ->and($accounts['stakeholder.preview.bplo@example.test']->can(UserPermission::AssessPermitApplications->value))->toBeTrue()
        ->and($accounts['stakeholder.preview.bplo@example.test']->can(UserPermission::ViewUsers->value))->toBeFalse()
        ->and($accounts['stakeholder.preview.treasury@example.test']->can(UserPermission::IssueReceipts->value))->toBeTrue()
        ->and($accounts['stakeholder.preview.treasury@example.test']->can(UserPermission::ViewUsers->value))->toBeFalse()
        ->and($accounts['stakeholder.preview.management@example.test']->can(UserPermission::ViewReports->value))->toBeTrue()
        ->and($accounts['stakeholder.preview.management@example.test']->can(UserPermission::ViewMunicipalityConfiguration->value))->toBeTrue()
        ->and($manifest['scenario']['key'])->toBe('stakeholder_preview_cycle_1')
        ->and($manifest['preview']['data_classification'])->toBe('synthetic_uat_only')
        ->and($manifest['preview']['production_migration_executed'])->toBeFalse()
        ->and($manifest['preview']['credential_delivery']['password_embedded_in_git'])->toBeFalse()
        ->and($encodedManifest)->not->toContain($password)
        ->and($billingGroup->acceptance_status->value)->toBe('provisional')
        ->and($record->status->value)->toBe('draft')
        ->and($manifest['preview']['billing_group']['financial_effect'])->toBe('none')
        ->and($manifest['resources']['ready_for_authority_review'])->toBeTrue()
        ->and($manifest['resources']['can_release'])->toBeFalse()
        ->and($manifest['resources']['assessment_prepared_by_id'])->toBe($accounts['stakeholder.preview.bplo@example.test']->id)
        ->and($manifest['resources']['assessment_approved_by_id'])->toBe($accounts['stakeholder.preview.treasury@example.test']->id)
        ->and($manifest['resources']['assessment_approver_distinct_from_preparer'])->toBeTrue()
        ->and($manifest['resources']['office_charge_contribution_count'])->toBe(5)
        ->and($manifest['resources']['provisional_uat_permit_status'])->toBe('released_in_preview');

    $screenshotPath = $store->rootRelativePath().'/browser/screenshots/preview.png';
    Storage::disk('local')->put($screenshotPath, 'synthetic screenshot evidence');
    $store->putJson('browser/managed-report.json', [
        'checks' => [['key' => 'synthetic-browser-check', 'passed' => true]],
        'result' => [
            'passed' => true,
            'check_count' => 1,
            'screenshot_count' => 1,
            'application_console_error_or_warning_count' => 0,
            'failed_internal_request_count' => 0,
            'unexpected_external_resource_count' => 0,
            'horizontal_overflow_count' => 0,
        ],
        'artifacts' => [
            'screenshots' => ['preview' => 'browser/screenshots/preview.png'],
        ],
    ]);

    $this->artisan('lifecycle:finalize-stakeholder-preview-evidence', [
        'run-id' => 'stakeholder-preview-test-001',
    ])->assertSuccessful();

    $finalManifest = $store->readJson('manifest.json');

    expect($finalManifest['result']['browser'])->toBe('passed')
        ->and($finalManifest['result']['audit'])->toBe('passed')
        ->and($finalManifest['result']['passed'])->toBeTrue()
        ->and($finalManifest['preview']['managed_acceptance']['check_count'])->toBe(1)
        ->and($store->readJson('terminal/managed-audit.json')['passed'])->toBeTrue()
        ->and(Storage::disk('local')->exists($store->rootRelativePath().'/preview-summary.md'))->toBeTrue();
});

test('preview preparation refuses a missing runtime credential', function () {
    configureSafeStakeholderPreview();
    config()->set('stakeholder_preview.password');

    $this->artisan('lifecycle:prepare-stakeholder-preview', [
        '--phase' => 'prepare',
    ])->assertFailed();
});

function configureSafeStakeholderPreview(?string $password = null): void
{
    config()->set([
        'stakeholder_preview.mode' => true,
        'stakeholder_preview.profile' => 'stakeholder_preview_weekend_v1',
        'stakeholder_preview.data_classification' => 'synthetic_only',
        'stakeholder_preview.pii_mode' => 'synthetic_only',
        'stakeholder_preview.production_migration_enabled' => false,
        'stakeholder_preview.production_integrations' => 'disabled',
        'stakeholder_preview.password' => $password,
    ]);
}
