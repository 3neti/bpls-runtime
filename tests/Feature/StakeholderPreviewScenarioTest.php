<?php

use App\Enums\UserPermission;
use App\LifecycleScenarios\LifecycleScenarioRegistry;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\Models\BillingGroup;
use App\Models\BillingGroupRecord;
use App\Models\User;
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
    putenv('STAKEHOLDER_PREVIEW_PASSWORD='.$password);

    try {
        $this->artisan('lifecycle:prepare-stakeholder-preview', [
            '--run-id' => 'stakeholder-preview-test-001',
            '--phase' => 'prepare',
        ])->assertSuccessful();
    } finally {
        foreach ([
            'STAKEHOLDER_PREVIEW_PASSWORD',
            'LIFECYCLE_BROWSER_EMAIL',
            'LIFECYCLE_BROWSER_PASSWORD',
            'LIFECYCLE_BROWSER_OPERATOR_EMAIL',
            'LIFECYCLE_BROWSER_OPERATOR_PASSWORD',
            'LIFECYCLE_BROWSER_BPLO_EMAIL',
            'LIFECYCLE_BROWSER_BPLO_PASSWORD',
            'LIFECYCLE_BROWSER_TREASURY_EMAIL',
            'LIFECYCLE_BROWSER_TREASURY_PASSWORD',
        ] as $key) {
            putenv($key);
        }
    }

    $accounts = User::query()
        ->whereIn('email', [
            'preview.citizen@example.test',
            'preview.bplo@example.test',
            'preview.treasury@example.test',
            'preview.management@example.test',
        ])
        ->with('role.permissions')
        ->get()
        ->keyBy('email');
    $store = new ScenarioArtifactStore('stakeholder_preview_cycle_1', 'stakeholder-preview-test-001');
    $manifest = $store->readJson('manifest.json');
    $encodedManifest = json_encode($manifest, JSON_THROW_ON_ERROR);
    $billingGroup = BillingGroup::query()->where('metadata->scenario_run_id', 'stakeholder-preview-test-001')->sole();
    $record = BillingGroupRecord::query()->where('source_snapshot->scenario_run_id', 'stakeholder-preview-test-001')->sole();

    expect($accounts)->toHaveCount(4)
        ->and($accounts)->each(fn ($user) => $user->password->not->toBe($password))
        ->and(Hash::check($password, $accounts['preview.citizen@example.test']->password))->toBeTrue()
        ->and($accounts['preview.citizen@example.test']->role?->code)->toBe('citizen')
        ->and($accounts['preview.bplo@example.test']->role?->code)->toBe('preview_bplo')
        ->and($accounts['preview.treasury@example.test']->role?->code)->toBe('preview_treasury')
        ->and($accounts['preview.management@example.test']->role?->code)->toBe('preview_management')
        ->and($accounts['preview.bplo@example.test']->can(UserPermission::AssessPermitApplications->value))->toBeTrue()
        ->and($accounts['preview.bplo@example.test']->can(UserPermission::ViewUsers->value))->toBeFalse()
        ->and($accounts['preview.treasury@example.test']->can(UserPermission::IssueReceipts->value))->toBeTrue()
        ->and($accounts['preview.treasury@example.test']->can(UserPermission::ViewUsers->value))->toBeFalse()
        ->and($accounts['preview.management@example.test']->can(UserPermission::ViewReports->value))->toBeTrue()
        ->and($accounts['preview.management@example.test']->can(UserPermission::ViewMunicipalityConfiguration->value))->toBeTrue()
        ->and($manifest['scenario']['key'])->toBe('stakeholder_preview_cycle_1')
        ->and($manifest['preview']['data_classification'])->toBe('synthetic_local_demo_only')
        ->and($manifest['preview']['production_migration_executed'])->toBeFalse()
        ->and($manifest['preview']['credential_delivery']['password_embedded_in_git'])->toBeFalse()
        ->and($encodedManifest)->not->toContain($password)
        ->and($billingGroup->acceptance_status->value)->toBe('provisional')
        ->and($record->status->value)->toBe('draft')
        ->and($manifest['preview']['billing_group']['financial_effect'])->toBe('none')
        ->and($manifest['resources']['ready_for_authority_review'])->toBeTrue()
        ->and($manifest['resources']['can_release'])->toBeFalse();
});

test('preview preparation refuses a missing runtime credential', function () {
    putenv('STAKEHOLDER_PREVIEW_PASSWORD');

    $this->artisan('lifecycle:prepare-stakeholder-preview', [
        '--phase' => 'prepare',
    ])->assertFailed();
});
