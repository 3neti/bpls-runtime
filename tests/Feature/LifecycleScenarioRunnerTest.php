<?php

use App\Actions\DescribePermitVerificationBoundary;
use App\Actions\DescribeReceiptVoidBoundary;
use App\Enums\PermitApplicationStatus;
use App\Enums\ReceiptStatus;
use App\Enums\StoryboardExportFormat;
use App\Enums\StoryboardExportStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Jobs\GenerateStoryboardVideo;
use App\LifecycleScenarios\LifecycleScenarioRegistry;
use App\LifecycleScenarios\ManualCollectionReceiptVisibilityScenario;
use App\LifecycleScenarios\PermitApplicationCancelledVisibilityScenario;
use App\LifecycleScenarios\PermitApplicationPendingPaymentVisibilityScenario;
use App\LifecycleScenarios\ScenarioActorResolver;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\LifecycleScenarios\StoryboardTerminalStateVisibilityScenario;
use App\Models\Assessment;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\Storyboard;
use App\Models\TreasuryCollection;
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

test('scenario registry discovers the manual collection receipt visibility scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('manual_collection_receipt_visibility');

    expect($scenario)
        ->key->toBe('manual_collection_receipt_visibility')
        ->label->toBe('Manual collection receipt visibility')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['payment_schedule_status'])->toBe('paid')
        ->and($scenario->expectations['collection_status'])->toBe('receipted')
        ->and($scenario->expectations['receipt_status'])->toBe('issued')
        ->and($scenario->expectations['receipt_void_status'])->toBe('blocked')
        ->and($scenario->safety['external_integrations'])->toBeFalse();
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

test('manual collection receipt scenario executes treasury actions idempotently', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('manual_collection_receipt_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'manual-receipt-test-001');
    $runner = app(ManualCollectionReceiptVisibilityScenario::class);

    $firstManifest = $runner->prepare($scenario, 'manual-receipt-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'manual-receipt-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);

    $receipt = Receipt::query()->findOrFail($firstManifest['resources']['record_id']);
    $collection = TreasuryCollection::query()->findOrFail($firstManifest['resources']['collection_id']);

    expect($firstManifest['resources']['record_type'])->toBe('receipt')
        ->and($firstManifest['resources']['record_id'])->toBe($secondManifest['resources']['record_id'])
        ->and($firstManifest['resources']['collection_id'])->toBe($secondManifest['resources']['collection_id'])
        ->and($firstManifest['resources']['payment_schedule_queue_url'])->toContain('q=APP-SCENARIO-MANUAL-RECEIPT-TEST-001')
        ->and($firstManifest['resources']['payment_schedule_queue_url'])->toContain('status=paid')
        ->and($firstManifest['resources']['receipt_queue_url'])->toContain('q=SCENARIO-OR-MANUAL-RECEIPT-TEST-001')
        ->and($firstManifest['resources']['receipt_queue_url'])->toContain('status=issued')
        ->and($firstManifest['resources']['application_form_pdf_url'])->toBe('/staff/permit-applications/'.$firstManifest['resources']['permit_application_id'].'/application-form.pdf')
        ->and($firstManifest['resources']['assessment_pdf_url'])->toBe('/staff/assessments/'.$firstManifest['resources']['assessment_id'].'/pdf')
        ->and($firstManifest['resources']['assessment_total_amount_cents'])->toBe(PermitApplication::query()->findOrFail($firstManifest['resources']['permit_application_id'])->assessments()->firstOrFail()->total_amount_cents)
        ->and($firstManifest['resources']['permit_verification_reference'])->toStartWith('PVA-'.$firstManifest['resources']['permit_application_id'].'-')
        ->and($firstManifest['resources']['permit_verification_url'])->toContain($firstManifest['resources']['permit_verification_reference'])
        ->and($firstManifest['resources']['receipt_void_boundary_reference'])->toStartWith('RVB-'.$firstManifest['resources']['record_id'].'-')
        ->and(Receipt::query()->count())->toBe(1)
        ->and(TreasuryCollection::query()->count())->toBe(1)
        ->and($receipt->status)->toBe(ReceiptStatus::Issued)
        ->and($receipt->numbering_authority)->toBe('manual')
        ->and($collection->status)->toBe(TreasuryCollectionStatus::Receipted)
        ->and(PermitApplication::query()->findOrFail($firstManifest['resources']['permit_application_id'])->metadata['release_policy_boundary']['blocked_transition'])->toBe(PermitApplicationStatus::Released->value)
        ->and($artifactStore->exists('terminal/prepare.json'))->toBeTrue()
        ->and($artifactStore->exists('storyboard/storyboard.json'))->toBeTrue();
});

test('manual collection receipt scenario audit compares browser evidence with canonical treasury state', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('manual_collection_receipt_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'manual-receipt-test-002');
    $runner = app(ManualCollectionReceiptVisibilityScenario::class);

    $manifest = $runner->prepare($scenario, 'manual-receipt-test-002', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $verification = app(DescribePermitVerificationBoundary::class)->handle(
        PermitApplication::query()->findOrFail($manifest['resources']['permit_application_id']),
    );
    $assessment = Assessment::query()->findOrFail($manifest['resources']['assessment_id']);
    $voidBoundary = app(DescribeReceiptVoidBoundary::class)->handle(
        Receipt::query()->findOrFail($manifest['resources']['record_id']),
    );
    $artifactStore->putJson('browser/report.json', [
        'result' => [
            'passed' => true,
        ],
        'receipt_void_boundary' => [
            'reference' => $voidBoundary['reference'],
            'status' => 'blocked',
            'can_void' => false,
            'receipt_status' => 'issued',
            'collection_status' => 'receipted',
        ],
        'verification' => [
            'reference' => $verification['reference'],
            'public_status' => 'artifact_only',
            'can_verify_release' => false,
            'released' => false,
        ],
        'documents' => [
            'application_form' => [
                'available' => true,
                'application_number' => $manifest['resources']['application_number'],
                'status' => 200,
                'content_type' => 'application/pdf',
            ],
            'assessment' => [
                'available' => true,
                'assessment_id' => $manifest['resources']['assessment_id'],
                'total_amount_cents' => $assessment->total_amount_cents,
                'status' => 200,
                'content_type' => 'application/pdf',
            ],
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '01-payment-schedule' => 'browser/screenshots/01-payment-schedule.png',
            ],
        ],
    ]);

    $audited = $runner->audit($manifest, $artifactStore);

    expect($audited['result'])
        ->terminal->toBe('passed')
        ->browser->toBe('passed')
        ->audit->toBe('passed')
        ->passed->toBeTrue()
        ->and($audited['resources']['permit_verification_reference'])->toBe($verification['reference'])
        ->and($audited['resources']['receipt_void_boundary_reference'])->toBe($voidBoundary['reference'])
        ->and($artifactStore->exists('terminal/audit.json'))->toBeTrue()
        ->and($artifactStore->exists('summary.html'))->toBeTrue();
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
        ->and($firstManifest['resources']['assessment_url'])->toBe('/staff/assessments/'.$firstManifest['resources']['assessment_id'])
        ->and($firstManifest['resources']['assessment_total_amount_cents'])->toBe(30_000)
        ->and($firstManifest['resources']['range_fee_rule_code'])->toBe('SCENARIO-BUSINESS-TAX')
        ->and($firstManifest['resources']['range_calculation_type'])->toBe('range')
        ->and($firstManifest['resources']['range_basis'])->toBe('declared_gross_sales')
        ->and($firstManifest['resources']['range_basis_amount_cents'])->toBe(12_500_000)
        ->and($firstManifest['resources']['range_amount_cents'])->toBe(20_000)
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
        'assessment' => [
            'range_line' => [
                'code' => $manifest['resources']['range_fee_rule_code'],
                'calculation_type' => $manifest['resources']['range_calculation_type'],
                'basis' => $manifest['resources']['range_basis'],
                'basis_amount_cents' => $manifest['resources']['range_basis_amount_cents'],
                'amount_cents' => $manifest['resources']['range_amount_cents'],
            ],
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
        UserPermission::RecordCollections,
        UserPermission::ViewCollections,
        UserPermission::IssueReceipts,
        UserPermission::ViewReceipts,
        UserPermission::VoidReceipts,
        UserPermission::UpdatePermitApplicationStatus,
        UserPermission::CompletePermitClearances,
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
