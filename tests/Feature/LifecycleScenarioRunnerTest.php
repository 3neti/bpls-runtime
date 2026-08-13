<?php

use App\Enums\PermitApplicationStatus;
use App\Enums\StoryboardExportFormat;
use App\Enums\StoryboardExportStatus;
use App\Enums\UserPermission;
use App\Jobs\GenerateStoryboardVideo;
use App\LifecycleScenarios\LifecycleScenarioRegistry;
use App\LifecycleScenarios\PermitApplicationCancelledVisibilityScenario;
use App\LifecycleScenarios\PermitApplicationPendingPaymentVisibilityScenario;
use App\LifecycleScenarios\ScenarioActorResolver;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\LifecycleScenarios\StoryboardTerminalStateVisibilityScenario;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\Role;
use App\Models\Storyboard;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('scenario registry discovers the storyboard terminal visibility scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('storyboard_terminal_state_visibility');

    expect($scenario)
        ->key->toBe('storyboard_terminal_state_visibility')
        ->label->toBe('Storyboard terminal export visibility')
        ->risk->toBe('local transactional')
        ->and($scenario->safety['external_integrations'])->toBeFalse()
        ->and($scenario->safety['irreversible_actions'])->toBeFalse();
});

test('scenario registry discovers the permit application cancelled visibility scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('permit_application_cancelled_visibility');

    expect($scenario)
        ->key->toBe('permit_application_cancelled_visibility')
        ->label->toBe('Permit application cancelled visibility')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['canonical_state'])->toBe('cancelled')
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('scenario registry discovers the permit application pending payment visibility scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('permit_application_pending_payment_visibility');

    expect($scenario)
        ->key->toBe('permit_application_pending_payment_visibility')
        ->label->toBe('Permit application pending payment visibility')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['canonical_state'])->toBe('pending_payment')
        ->and($scenario->expectations['payment_schedule_status'])->toBe('pending')
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('actor resolver resolves configured users through roles and permissions', function () {
    $user = configuredScenarioUser('operator@example.test');

    config()->set('lifecycle_scenarios.actors.primary_operator.email', $user->email);
    config()->set('lifecycle_scenarios.actors.sample_recipient.email', $user->email);

    $actors = app(ScenarioActorResolver::class)
        ->resolve(app(LifecycleScenarioRegistry::class)->get('storyboard_terminal_state_visibility'));

    expect($actors)
        ->toHaveKeys(['operator', 'recipient'])
        ->and($actors['operator']->is($user))->toBeTrue();
});

test('actor resolver fails clearly when an expected user is absent', function () {
    config()->set('lifecycle_scenarios.actors.primary_operator.email', 'missing@example.test');
    config()->set('lifecycle_scenarios.actors.sample_recipient.email', 'missing@example.test');

    app(ScenarioActorResolver::class)
        ->resolve(app(LifecycleScenarioRegistry::class)->get('storyboard_terminal_state_visibility'));
})->throws(RuntimeException::class, 'was not found');

test('prepare creates deterministic storyboard evidence and is idempotent for a run id', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::fake('public');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('storyboard_terminal_state_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'storyboard-terminal-test-001');
    $runner = app(StoryboardTerminalStateVisibilityScenario::class);

    $firstManifest = $runner->prepare($scenario, 'storyboard-terminal-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'storyboard-terminal-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);

    expect($firstManifest['resources']['record_id'])
        ->toBe($secondManifest['resources']['record_id'])
        ->and(Storyboard::query()->count())->toBe(1)
        ->and($firstManifest['schema_version'])->toBe('application.lifecycle-evidence.v1')
        ->and($firstManifest['actors']['operator']['email'])->toBe('o***@example.test')
        ->and($artifactStore->exists('manifest.json'))->toBeTrue()
        ->and($artifactStore->exists('terminal/prepare.json'))->toBeTrue()
        ->and($artifactStore->exists('terminal/execution.json'))->toBeTrue()
        ->and($artifactStore->exists('storyboard/storyboard.html'))->toBeTrue()
        ->and($artifactStore->exists('storyboard/storyboard.pdf'))->toBeTrue()
        ->and($artifactStore->exists('review.md'))->toBeTrue();

    Queue::assertPushed(GenerateStoryboardVideo::class);
});

test('audit merges browser report and compares visible evidence to canonical state', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::fake('public');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('storyboard_terminal_state_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'storyboard-terminal-test-002');
    $runner = app(StoryboardTerminalStateVisibilityScenario::class);

    $manifest = $runner->prepare($scenario, 'storyboard-terminal-test-002', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $artifactStore->putJson('browser/report.json', [
        'result' => [
            'passed' => true,
        ],
        'checks' => [
            [
                'key' => 'detail-title-visible',
                'passed' => true,
            ],
        ],
        'artifacts' => [
            'screenshots' => [
                'detail' => 'browser/screenshots/02-detail.png',
            ],
        ],
    ]);

    $audited = $runner->audit($manifest, $artifactStore);
    $storyboard = Storyboard::query()->with('exports')->findOrFail($manifest['resources']['record_id']);

    expect($audited['result'])
        ->terminal->toBe('passed')
        ->browser->toBe('passed')
        ->audit->toBe('passed')
        ->passed->toBeTrue()
        ->and($storyboard->exports->where('format', StoryboardExportFormat::Pdf)->where('status', StoryboardExportStatus::Completed))->toHaveCount(1)
        ->and($storyboard->exports->where('format', StoryboardExportFormat::Video)->where('status', StoryboardExportStatus::Pending))->toHaveCount(1)
        ->and($artifactStore->exists('terminal/audit.json'))->toBeTrue()
        ->and($artifactStore->exists('summary.html'))->toBeTrue();
});

test('permit application cancellation scenario executes real domain action and is idempotent', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('permit_application_cancelled_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'permit-cancelled-test-001');
    $runner = app(PermitApplicationCancelledVisibilityScenario::class);

    $firstManifest = $runner->prepare($scenario, 'permit-cancelled-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'permit-cancelled-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);

    $application = PermitApplication::query()->findOrFail($firstManifest['resources']['record_id']);

    expect($firstManifest['resources']['record_type'])->toBe('permit_application')
        ->and($firstManifest['resources']['record_id'])->toBe($secondManifest['resources']['record_id'])
        ->and(PermitApplication::query()->count())->toBe(1)
        ->and($application->status)->toBe(PermitApplicationStatus::Cancelled)
        ->and($application->metadata['terminal_state']['can_continue'])->toBeFalse()
        ->and($artifactStore->exists('terminal/prepare.json'))->toBeTrue()
        ->and($artifactStore->exists('storyboard/storyboard.json'))->toBeTrue();
});

test('permit application cancellation scenario audit compares browser evidence with canonical state', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('permit_application_cancelled_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'permit-cancelled-test-002');
    $runner = app(PermitApplicationCancelledVisibilityScenario::class);

    $manifest = $runner->prepare($scenario, 'permit-cancelled-test-002', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $artifactStore->putJson('browser/report.json', [
        'result' => [
            'passed' => true,
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '01-list' => 'browser/screenshots/01-list.png',
            ],
        ],
    ]);

    $audited = $runner->audit($manifest, $artifactStore);

    expect($audited['result'])
        ->terminal->toBe('passed')
        ->browser->toBe('passed')
        ->audit->toBe('passed')
        ->passed->toBeTrue()
        ->and($artifactStore->exists('terminal/audit.json'))->toBeTrue()
        ->and($artifactStore->exists('summary.html'))->toBeTrue();
});

test('permit application pending payment scenario executes assessment and payment schedule actions idempotently', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('permit_application_pending_payment_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'permit-pending-payment-test-001');
    $runner = app(PermitApplicationPendingPaymentVisibilityScenario::class);

    $firstManifest = $runner->prepare($scenario, 'permit-pending-payment-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'permit-pending-payment-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);

    $application = PermitApplication::query()->findOrFail($firstManifest['resources']['record_id']);

    expect($firstManifest['resources']['record_type'])->toBe('permit_application')
        ->and($firstManifest['resources']['record_id'])->toBe($secondManifest['resources']['record_id'])
        ->and($firstManifest['resources']['payment_schedule_id'])->toBe($secondManifest['resources']['payment_schedule_id'])
        ->and(PermitApplication::query()->count())->toBe(1)
        ->and($application->status)->toBe(PermitApplicationStatus::PendingPayment)
        ->and($application->paymentSchedules()->count())->toBe(1)
        ->and($artifactStore->exists('terminal/prepare.json'))->toBeTrue()
        ->and($artifactStore->exists('storyboard/storyboard.json'))->toBeTrue();
});

test('permit application pending payment scenario audit compares browser evidence with canonical state', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('permit_application_pending_payment_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'permit-pending-payment-test-002');
    $runner = app(PermitApplicationPendingPaymentVisibilityScenario::class);

    $manifest = $runner->prepare($scenario, 'permit-pending-payment-test-002', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $artifactStore->putJson('browser/report.json', [
        'result' => [
            'passed' => true,
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '01-list' => 'browser/screenshots/01-list.png',
            ],
        ],
    ]);

    $audited = $runner->audit($manifest, $artifactStore);

    expect($audited['result'])
        ->terminal->toBe('passed')
        ->browser->toBe('passed')
        ->audit->toBe('passed')
        ->passed->toBeTrue()
        ->and($artifactStore->exists('terminal/audit.json'))->toBeTrue()
        ->and($artifactStore->exists('summary.html'))->toBeTrue();
});

test('command refuses unsafe environments before preparing records', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('lifecycle:scenario', [
        '--run-id' => 'unsafe-env-test',
        '--phase' => 'prepare',
    ])->assertFailed();

    expect(Storyboard::query()->count())->toBe(0);
});

function configuredScenarioUser(string $email): User
{
    $permissions = collect([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::CreatePermitApplications,
        UserPermission::AssessPermitApplications,
        UserPermission::PreparePaymentSchedules,
        UserPermission::ViewPaymentSchedules,
        UserPermission::UpdatePermitApplicationStatus,
        UserPermission::ManageStoryboards,
    ])->map(fn (UserPermission $permission): int => Permission::factory()->create([
        'code' => $permission->value,
    ])->id);
    $role = Role::factory()->create();
    $role->permissions()->sync($permissions->all());

    return User::factory()->create([
        'role_id' => $role->id,
        'email' => $email,
    ]);
}
