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
use App\LifecycleScenarios\AssessmentPolicyBoundaryVisibilityScenario;
use App\LifecycleScenarios\LifecycleScenarioRegistry;
use App\LifecycleScenarios\ManualCollectionReceiptVisibilityScenario;
use App\LifecycleScenarios\PermitApplicationCancelledVisibilityScenario;
use App\LifecycleScenarios\PermitApplicationPendingPaymentVisibilityScenario;
use App\LifecycleScenarios\RevenueCodeFeeCatalogVisibilityScenario;
use App\LifecycleScenarios\ScenarioActorResolver;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\LifecycleScenarios\StoryboardTerminalStateVisibilityScenario;
use App\Models\Assessment;
use App\Models\FeeRule;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\Storyboard;
use App\Models\TreasuryCollection;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function paymentPolicyBoundaryBrowserReport(): array
{
    return [
        'payment_policy_boundary' => [
            'status' => 'policy_boundary',
            'can_calculate_surcharge' => false,
            'can_calculate_interest' => false,
            'can_validate_pil' => false,
            'can_calculate_deficiency_tax' => false,
            'can_split_installments' => false,
            'can_assign_statutory_due_dates' => false,
            'installment_visible' => true,
            'due_date_visible' => true,
            'surcharge_visible' => true,
            'pil_visible' => true,
        ],
    ];
}

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

test('scenario registry discovers the new permit lifecycle authority boundary scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('new_permit_lifecycle_authority_boundary');

    expect($scenario)
        ->key->toBe('new_permit_lifecycle_authority_boundary')
        ->label->toBe('New permit lifecycle to authority boundary')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['payment_schedule_status'])->toBe('paid')
        ->and($scenario->expectations['collection_status'])->toBe('receipted')
        ->and($scenario->expectations['receipt_status'])->toBe('issued')
        ->and($scenario->expectations['clearances_completed'])->toBeTrue()
        ->and($scenario->expectations['ready_for_authority_review'])->toBeTrue()
        ->and($scenario->expectations['can_release'])->toBeFalse()
        ->and($scenario->expectations['permit_release_status'])->toBe('blocked')
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

test('scenario registry discovers the renewal permit lifecycle foundation scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('renewal_permit_lifecycle_foundation');

    expect($scenario)
        ->key->toBe('renewal_permit_lifecycle_foundation')
        ->label->toBe('Renewal permit lifecycle foundation')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['application_type'])->toBe('renewal')
        ->and($scenario->expectations['canonical_state'])->toBe('pending_payment')
        ->and($scenario->expectations['payment_schedule_status'])->toBe('pending')
        ->and($scenario->expectations['renewal_policy_status'])->toBe('policy_boundary')
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('scenario registry discovers the amendment permit lifecycle foundation scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('amendment_permit_lifecycle_foundation');

    expect($scenario)
        ->key->toBe('amendment_permit_lifecycle_foundation')
        ->label->toBe('Amendment permit lifecycle foundation')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['application_type'])->toBe('amendment')
        ->and($scenario->expectations['canonical_state'])->toBe('pending_payment')
        ->and($scenario->expectations['payment_schedule_status'])->toBe('pending')
        ->and($scenario->expectations['amendment_policy_status'])->toBe('policy_boundary')
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('scenario registry discovers the transfer permit lifecycle foundation scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('transfer_permit_lifecycle_foundation');

    expect($scenario)
        ->key->toBe('transfer_permit_lifecycle_foundation')
        ->label->toBe('Transfer permit lifecycle foundation')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['application_type'])->toBe('transfer')
        ->and($scenario->expectations['canonical_state'])->toBe('pending_payment')
        ->and($scenario->expectations['payment_schedule_status'])->toBe('pending')
        ->and($scenario->expectations['transfer_policy_status'])->toBe('policy_boundary')
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('scenario registry discovers the retirement permit lifecycle foundation scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('retirement_permit_lifecycle_foundation');

    expect($scenario)
        ->key->toBe('retirement_permit_lifecycle_foundation')
        ->label->toBe('Retirement permit lifecycle foundation')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['application_type'])->toBe('retirement')
        ->and($scenario->expectations['canonical_state'])->toBe('pending_payment')
        ->and($scenario->expectations['payment_schedule_status'])->toBe('pending')
        ->and($scenario->expectations['retirement_policy_status'])->toBe('policy_boundary')
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('scenario registry discovers the revenue code fee catalog visibility scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('revenue_code_fee_catalog_visibility');

    expect($scenario)
        ->key->toBe('revenue_code_fee_catalog_visibility')
        ->label->toBe('Revenue Code fee catalog visibility')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['fee_rule_code'])->toBe('MRC-2A-02-B-RETAIL-BUSINESS-TAX')
        ->and($scenario->expectations['range_count'])->toBe(23)
        ->and($scenario->expectations['policy_boundary'])->toBe('new_business_initial_local_business_tax_exemption')
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('scenario registry discovers the assessment policy boundary visibility scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('assessment_policy_boundary_visibility');

    expect($scenario)
        ->key->toBe('assessment_policy_boundary_visibility')
        ->label->toBe('Assessment policy boundary visibility')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['assessment_policy_status'])->toBe('blocked')
        ->and($scenario->expectations['assessment_count'])->toBe(0)
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
    $permitApplication = PermitApplication::query()->findOrFail($firstManifest['resources']['permit_application_id']);
    $expectedTimelineKeys = [
        "application-recorded:{$permitApplication->id}",
        "assessment-computed:{$firstManifest['resources']['assessment_id']}",
        "payment-schedule-prepared:{$firstManifest['resources']['payment_schedule_id']}",
        'status-transition:0',
        "collection-recorded:{$firstManifest['resources']['collection_id']}",
        "receipt-issued:{$receipt->id}",
        ...$permitApplication->clearances()->orderBy('id')->pluck('id')->map(fn (int $clearanceId): string => "clearance-completed:{$clearanceId}"),
        "release-blocked:{$permitApplication->id}",
    ];

    expect($firstManifest['resources']['record_type'])->toBe('receipt')
        ->and($firstManifest['resources']['record_id'])->toBe($secondManifest['resources']['record_id'])
        ->and($firstManifest['resources']['collection_id'])->toBe($secondManifest['resources']['collection_id'])
        ->and($firstManifest['resources']['payment_schedule_queue_url'])->toContain('q=APP-SCENARIO-MANUAL-RECEIPT-TEST-001')
        ->and($firstManifest['resources']['payment_schedule_queue_url'])->toContain('status=paid')
        ->and($firstManifest['resources']['online_payment_boundary_status'])->toBe('blocked')
        ->and($firstManifest['resources']['receipt_queue_url'])->toContain('q=SCENARIO-OR-MANUAL-RECEIPT-TEST-001')
        ->and($firstManifest['resources']['receipt_queue_url'])->toContain('status=issued')
        ->and($firstManifest['resources']['application_form_pdf_url'])->toBe('/staff/permit-applications/'.$firstManifest['resources']['permit_application_id'].'/application-form.pdf')
        ->and($firstManifest['resources']['assessment_pdf_url'])->toBe('/staff/assessments/'.$firstManifest['resources']['assessment_id'].'/pdf')
        ->and($firstManifest['resources']['assessment_total_amount_cents'])->toBe(PermitApplication::query()->findOrFail($firstManifest['resources']['permit_application_id'])->assessments()->firstOrFail()->total_amount_cents)
        ->and($firstManifest['resources']['permit_artifact_status'])->toBe('generated_artifact_available')
        ->and($firstManifest['resources']['permit_verification_reference'])->toStartWith('PVA-'.$firstManifest['resources']['permit_application_id'].'-')
        ->and($firstManifest['resources']['permit_verification_url'])->toContain($firstManifest['resources']['permit_verification_reference'])
        ->and($firstManifest['resources']['permit_verification_view_url'])->toBe($firstManifest['resources']['permit_verification_url'].'/view')
        ->and($firstManifest['resources']['receipt_void_boundary_reference'])->toStartWith('RVB-'.$firstManifest['resources']['record_id'].'-')
        ->and($firstManifest['resources']['permit_timeline_event_count'])->toBe(10)
        ->and($firstManifest['resources']['permit_timeline_event_keys'])->toBe($expectedTimelineKeys)
        ->and(Receipt::query()->count())->toBe(1)
        ->and(TreasuryCollection::query()->count())->toBe(1)
        ->and($receipt->status)->toBe(ReceiptStatus::Issued)
        ->and($receipt->numbering_authority)->toBe('manual')
        ->and($collection->status)->toBe(TreasuryCollectionStatus::Receipted)
        ->and(PermitApplication::query()->findOrFail($firstManifest['resources']['permit_application_id'])->metadata['release_policy_boundary']['blocked_transition'])->toBe(PermitApplicationStatus::Released->value)
        ->and($artifactStore->exists('terminal/prepare.json'))->toBeTrue()
        ->and($artifactStore->exists('storyboard/storyboard.json'))->toBeTrue();
});

test('new permit lifecycle scenario executes real domain actions to authority boundary idempotently', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('new_permit_lifecycle_authority_boundary');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'new-permit-lifecycle-test-001');
    $runner = app(ManualCollectionReceiptVisibilityScenario::class);

    $firstManifest = $runner->prepare($scenario, 'new-permit-lifecycle-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'new-permit-lifecycle-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);

    $permitApplication = PermitApplication::query()->findOrFail($firstManifest['resources']['permit_application_id']);
    $receipt = Receipt::query()->findOrFail($firstManifest['resources']['record_id']);
    $collection = TreasuryCollection::query()->findOrFail($firstManifest['resources']['collection_id']);
    $storyboard = $artifactStore->readJson('storyboard/storyboard.json');

    expect($firstManifest['scenario']['key'])->toBe('new_permit_lifecycle_authority_boundary')
        ->and($firstManifest['resources']['record_id'])->toBe($secondManifest['resources']['record_id'])
        ->and($firstManifest['resources']['permit_application_id'])->toBe($secondManifest['resources']['permit_application_id'])
        ->and($firstManifest['resources']['collection_id'])->toBe($secondManifest['resources']['collection_id'])
        ->and($firstManifest['resources']['permit_artifact_status'])->toBe('generated_artifact_available')
        ->and($firstManifest['resources']['permit_verification_reference'])->toStartWith('PVA-'.$firstManifest['resources']['permit_application_id'].'-')
        ->and($firstManifest['resources']['permit_verification_view_url'])->toBe($firstManifest['resources']['permit_verification_url'].'/view')
        ->and($firstManifest['resources']['receipt_void_boundary_reference'])->toStartWith('RVB-'.$firstManifest['resources']['record_id'].'-')
        ->and($permitApplication->status)->toBe(PermitApplicationStatus::PendingPayment)
        ->and($permitApplication->metadata['release_policy_boundary']['blocked_transition'])->toBe(PermitApplicationStatus::Released->value)
        ->and($permitApplication->clearances()->count())->toBe(3)
        ->and($permitApplication->clearances()->where('status', 'completed')->count())->toBe(3)
        ->and($receipt->status)->toBe(ReceiptStatus::Issued)
        ->and($receipt->numbering_authority)->toBe('manual')
        ->and($collection->status)->toBe(TreasuryCollectionStatus::Receipted)
        ->and($storyboard['title'])->toBe('New permit lifecycle to authority boundary')
        ->and($storyboard['record']['type'])->toBe('permit_lifecycle')
        ->and($artifactStore->exists('terminal/prepare.json'))->toBeTrue()
        ->and($artifactStore->exists('storyboard/storyboard.html'))->toBeTrue();
});

test('revenue code fee catalog visibility scenario prepares deterministic catalog evidence idempotently', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('revenue_code_fee_catalog_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'fee-catalog-test-001');
    $runner = app(RevenueCodeFeeCatalogVisibilityScenario::class);

    $firstManifest = $runner->prepare($scenario, 'fee-catalog-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'fee-catalog-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);

    $feeRule = FeeRule::query()->with('ranges')->findOrFail($firstManifest['resources']['record_id']);
    $storyboard = $artifactStore->readJson('storyboard/storyboard.json');

    expect($firstManifest['scenario']['key'])->toBe('revenue_code_fee_catalog_visibility')
        ->and($firstManifest['resources']['record_id'])->toBe($secondManifest['resources']['record_id'])
        ->and($firstManifest['resources']['fee_rule_code'])->toBe('MRC-2A-02-B-RETAIL-BUSINESS-TAX')
        ->and($firstManifest['resources']['detail_url'])->toBe('/staff/fee-rules/'.$firstManifest['resources']['record_id'])
        ->and($firstManifest['resources']['range_count'])->toBe(23)
        ->and($firstManifest['resources']['first_range_amount_cents'])->toBe(2266)
        ->and($firstManifest['resources']['policy_boundaries'])->toContain('new_business_initial_local_business_tax_exemption')
        ->and($feeRule->ranges)->toHaveCount(23)
        ->and($storyboard['title'])->toBe('Revenue Code fee catalog visibility')
        ->and($storyboard['record']['type'])->toBe('fee_rule')
        ->and($artifactStore->exists('terminal/prepare.json'))->toBeTrue()
        ->and($artifactStore->exists('terminal/execution.json'))->toBeTrue()
        ->and($artifactStore->exists('storyboard/storyboard.html'))->toBeTrue();
});

test('assessment policy boundary visibility scenario prepares formula boundary precondition idempotently', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('assessment_policy_boundary_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'assessment-policy-boundary-test-001');
    $runner = app(AssessmentPolicyBoundaryVisibilityScenario::class);

    $firstManifest = $runner->prepare($scenario, 'assessment-policy-boundary-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'assessment-policy-boundary-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);

    $application = PermitApplication::query()->findOrFail($firstManifest['resources']['record_id']);
    $feeRule = FeeRule::query()->findOrFail($firstManifest['resources']['fee_rule_id']);
    $storyboard = $artifactStore->readJson('storyboard/storyboard.json');

    expect($firstManifest['scenario']['key'])->toBe('assessment_policy_boundary_visibility')
        ->and($firstManifest['resources']['record_id'])->toBe($secondManifest['resources']['record_id'])
        ->and($firstManifest['resources']['application_number'])->toBe($application->application_number)
        ->and($firstManifest['resources']['expected_policy_message'])->toBe('Formula assessment policy is not implemented for fee rule ['.$feeRule->code.'].')
        ->and($application->assessments()->count())->toBe(0)
        ->and($feeRule->calculation_type->value)->toBe('formula')
        ->and($storyboard['title'])->toBe('Assessment policy boundary visibility')
        ->and($storyboard['record']['type'])->toBe('permit_application')
        ->and($artifactStore->exists('terminal/prepare.json'))->toBeTrue()
        ->and($artifactStore->exists('terminal/execution.json'))->toBeTrue()
        ->and($artifactStore->exists('storyboard/storyboard.html'))->toBeTrue();
});

test('command prepares the revenue code fee catalog visibility scenario', function () {
    Storage::fake('local');

    configuredScenarioUser('test@example.com');

    $this->artisan('lifecycle:scenario', [
        'scenario' => 'revenue_code_fee_catalog_visibility',
        '--run-id' => 'fee-catalog-command-test-001',
        '--phase' => 'prepare',
    ])->assertSuccessful();

    $artifactStore = new ScenarioArtifactStore('revenue_code_fee_catalog_visibility', 'fee-catalog-command-test-001');
    $manifest = $artifactStore->readJson('manifest.json');

    expect($manifest['scenario']['key'])->toBe('revenue_code_fee_catalog_visibility')
        ->and($manifest['result']['terminal'])->toBe('passed')
        ->and($manifest['resources']['public_reference'])->toBe('MRC-2A-02-B-RETAIL-BUSINESS-TAX')
        ->and($artifactStore->exists('storyboard/storyboard.json'))->toBeTrue();
});

test('command prepares the assessment policy boundary visibility scenario', function () {
    Storage::fake('local');

    configuredScenarioUser('test@example.com');

    $this->artisan('lifecycle:scenario', [
        'scenario' => 'assessment_policy_boundary_visibility',
        '--run-id' => 'assessment-policy-boundary-command-test-001',
        '--phase' => 'prepare',
    ])->assertSuccessful();

    $artifactStore = new ScenarioArtifactStore('assessment_policy_boundary_visibility', 'assessment-policy-boundary-command-test-001');
    $manifest = $artifactStore->readJson('manifest.json');

    expect($manifest['scenario']['key'])->toBe('assessment_policy_boundary_visibility')
        ->and($manifest['result']['terminal'])->toBe('passed')
        ->and($manifest['resources']['expected_policy_message'])->toContain('Formula assessment policy is not implemented')
        ->and($artifactStore->exists('storyboard/storyboard.json'))->toBeTrue();
});

test('command prepares the new permit lifecycle authority boundary scenario', function () {
    Storage::fake('local');

    configuredScenarioUser('test@example.com');

    $this->artisan('lifecycle:scenario', [
        'scenario' => 'new_permit_lifecycle_authority_boundary',
        '--run-id' => 'new-permit-command-test-001',
        '--phase' => 'prepare',
    ])->assertSuccessful();

    $artifactStore = new ScenarioArtifactStore('new_permit_lifecycle_authority_boundary', 'new-permit-command-test-001');
    $manifest = $artifactStore->readJson('manifest.json');

    expect($manifest['scenario']['key'])->toBe('new_permit_lifecycle_authority_boundary')
        ->and($manifest['result']['terminal'])->toBe('passed')
        ->and($manifest['resources']['application_number'])->toBe('APP-SCENARIO-NEW-PERMIT-COMMAND-TEST-001')
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
        'online_payment_boundary' => [
            'status' => 'blocked',
            'can_pay_online' => false,
            'can_reconcile_online' => false,
            'unresolved_visible' => true,
        ],
        'reports' => [
            'daily_collection' => [
                'receipt_number' => $manifest['resources']['public_reference'],
                'amount_cents' => $manifest['resources']['assessment_total_amount_cents'],
                'scope_visible' => true,
                'csv_export_visible' => true,
            ],
            'revenue_source' => [
                'source_code' => $manifest['resources']['revenue_source_code'],
                'source_visible' => true,
                'csv_export_visible' => true,
            ],
            'paid_establishments' => [
                'application_number' => $manifest['resources']['application_number'],
                'business_name' => $manifest['resources']['paid_establishment_business_name'],
                'application_visible' => true,
                'csv_export_visible' => true,
            ],
        ],
        'verification' => [
            'reference' => $verification['reference'],
            'api_url' => $manifest['resources']['permit_verification_url'],
            'public_page_url' => $manifest['resources']['permit_verification_view_url'],
            'public_status' => 'artifact_only',
            'can_verify_release' => false,
            'released' => false,
            'public_page_visible' => true,
            'public_page_mobile_visible' => true,
        ],
        'timeline' => [
            'event_count' => $manifest['resources']['permit_timeline_event_count'],
            'event_keys' => $manifest['resources']['permit_timeline_event_keys'],
        ],
        'permit_artifact' => [
            'permit_pdf_url' => $manifest['resources']['permit_pdf_url'],
            'verification_reference' => $verification['reference'],
            'panel_visible' => true,
            'not_legally_effective_visible' => true,
            'open_affordance_visible' => true,
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
        ->and($audited['resources']['permit_artifact_status'])->toBe('generated_artifact_available')
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
        ->and($firstManifest['resources']['business_tax_code'])->toBe('SCENARIO-BUSINESS-TAX')
        ->and($firstManifest['resources']['business_tax_name'])->toBe('Scenario Business Tax')
        ->and($firstManifest['resources']['business_tax_category'])->toBe('tax')
        ->and($firstManifest['resources']['business_tax_line_of_business'])->toBe('Scenario Retail')
        ->and($firstManifest['resources']['business_tax_basis'])->toBe('declared_gross_sales')
        ->and($firstManifest['resources']['business_tax_declared_gross_sales_cents'])->toBe(12_500_000)
        ->and($firstManifest['resources']['business_tax_amount_cents'])->toBe(20_000)
        ->and($firstManifest['resources']['unpaid_establishments_report_url'])->toContain('q=APP-SCENARIO-PERMIT-PENDING-PAYMENT-TEST-001')
        ->and($firstManifest['resources']['unpaid_establishment_business_name'])->toBe('Scenario Payment Business permit-pending-payment-test-001')
        ->and($firstManifest['resources']['top_tax_due_report_url'])->toContain('q=APP-SCENARIO-PERMIT-PENDING-PAYMENT-TEST-001')
        ->and($firstManifest['resources']['top_tax_due_business_name'])->toBe('Scenario Payment Business permit-pending-payment-test-001')
        ->and($firstManifest['resources']['top_tax_due_cents'])->toBe(20_000)
        ->and(PermitApplication::query()->count())->toBe(1)
        ->and($application->status)->toBe(PermitApplicationStatus::PendingPayment)
        ->and($application->paymentSchedules()->count())->toBe(1)
        ->and($artifactStore->exists('terminal/prepare.json'))->toBeTrue()
        ->and($artifactStore->exists('storyboard/storyboard.json'))->toBeTrue();
});

test('renewal permit lifecycle foundation scenario executes through pending payment with policy boundary idempotently', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('renewal_permit_lifecycle_foundation');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'renewal-foundation-test-001');
    $runner = app(PermitApplicationPendingPaymentVisibilityScenario::class);

    $firstManifest = $runner->prepare($scenario, 'renewal-foundation-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'renewal-foundation-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);

    $application = PermitApplication::query()->findOrFail($firstManifest['resources']['record_id']);
    $storyboard = $artifactStore->readJson('storyboard/storyboard.json');

    expect($firstManifest['scenario']['key'])->toBe('renewal_permit_lifecycle_foundation')
        ->and($firstManifest['resources']['record_id'])->toBe($secondManifest['resources']['record_id'])
        ->and($firstManifest['resources']['application_type'])->toBe('renewal')
        ->and($firstManifest['resources']['renewal_policy_status'])->toBe('policy_boundary')
        ->and($firstManifest['resources']['assessment_total_amount_cents'])->toBe(30_000)
        ->and($application->type->value)->toBe('renewal')
        ->and($application->status)->toBe(PermitApplicationStatus::PendingPayment)
        ->and($application->metadata['renewal_policy_boundary']['status'])->toBe('policy_boundary')
        ->and($application->metadata['renewal_policy_boundary']['unresolved_policy'])->toContain('PIL applicability and calculation')
        ->and($application->paymentSchedules()->count())->toBe(1)
        ->and($storyboard['title'])->toBe('Renewal permit lifecycle foundation')
        ->and($storyboard['record']['application_type'])->toBe('renewal')
        ->and($storyboard['record']['renewal_policy_status'])->toBe('policy_boundary');
});

test('amendment permit lifecycle foundation scenario executes through pending payment with policy boundary idempotently', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('amendment_permit_lifecycle_foundation');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'amendment-foundation-test-001');
    $runner = app(PermitApplicationPendingPaymentVisibilityScenario::class);

    $firstManifest = $runner->prepare($scenario, 'amendment-foundation-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'amendment-foundation-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);

    $application = PermitApplication::query()->findOrFail($firstManifest['resources']['record_id']);
    $storyboard = $artifactStore->readJson('storyboard/storyboard.json');

    expect($firstManifest['scenario']['key'])->toBe('amendment_permit_lifecycle_foundation')
        ->and($firstManifest['resources']['record_id'])->toBe($secondManifest['resources']['record_id'])
        ->and($firstManifest['resources']['application_type'])->toBe('amendment')
        ->and($firstManifest['resources']['amendment_policy_status'])->toBe('policy_boundary')
        ->and($firstManifest['resources']['assessment_total_amount_cents'])->toBe(30_000)
        ->and($application->type->value)->toBe('amendment')
        ->and($application->status)->toBe(PermitApplicationStatus::PendingPayment)
        ->and($application->metadata['amendment_policy_boundary']['status'])->toBe('policy_boundary')
        ->and($application->metadata['amendment_policy_boundary']['unresolved_policy'])->toContain('amendment fee and assessment basis')
        ->and($application->paymentSchedules()->count())->toBe(1)
        ->and($storyboard['title'])->toBe('Amendment permit lifecycle foundation')
        ->and($storyboard['record']['application_type'])->toBe('amendment')
        ->and($storyboard['record']['amendment_policy_status'])->toBe('policy_boundary');
});

test('transfer permit lifecycle foundation scenario executes through pending payment with policy boundary idempotently', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('transfer_permit_lifecycle_foundation');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'transfer-foundation-test-001');
    $runner = app(PermitApplicationPendingPaymentVisibilityScenario::class);

    $firstManifest = $runner->prepare($scenario, 'transfer-foundation-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'transfer-foundation-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);

    $application = PermitApplication::query()->findOrFail($firstManifest['resources']['record_id']);
    $storyboard = $artifactStore->readJson('storyboard/storyboard.json');

    expect($firstManifest['scenario']['key'])->toBe('transfer_permit_lifecycle_foundation')
        ->and($firstManifest['resources']['record_id'])->toBe($secondManifest['resources']['record_id'])
        ->and($firstManifest['resources']['application_type'])->toBe('transfer')
        ->and($firstManifest['resources']['transfer_policy_status'])->toBe('policy_boundary')
        ->and($firstManifest['resources']['assessment_total_amount_cents'])->toBe(30_000)
        ->and($application->type->value)->toBe('transfer')
        ->and($application->status)->toBe(PermitApplicationStatus::PendingPayment)
        ->and($application->metadata['transfer_policy_boundary']['status'])->toBe('policy_boundary')
        ->and($application->metadata['transfer_policy_boundary']['unresolved_policy'])->toContain('whether transfer terminates, supersedes, or preserves the prior permit')
        ->and($application->paymentSchedules()->count())->toBe(1)
        ->and($storyboard['title'])->toBe('Transfer permit lifecycle foundation')
        ->and($storyboard['record']['application_type'])->toBe('transfer')
        ->and($storyboard['record']['transfer_policy_status'])->toBe('policy_boundary');
});

test('retirement permit lifecycle foundation scenario executes through pending payment with policy boundary idempotently', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('retirement_permit_lifecycle_foundation');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'retirement-foundation-test-001');
    $runner = app(PermitApplicationPendingPaymentVisibilityScenario::class);

    $firstManifest = $runner->prepare($scenario, 'retirement-foundation-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'retirement-foundation-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);

    $application = PermitApplication::query()->findOrFail($firstManifest['resources']['record_id']);
    $storyboard = $artifactStore->readJson('storyboard/storyboard.json');

    expect($firstManifest['scenario']['key'])->toBe('retirement_permit_lifecycle_foundation')
        ->and($firstManifest['resources']['record_id'])->toBe($secondManifest['resources']['record_id'])
        ->and($firstManifest['resources']['application_type'])->toBe('retirement')
        ->and($firstManifest['resources']['retirement_policy_status'])->toBe('policy_boundary')
        ->and($firstManifest['resources']['assessment_total_amount_cents'])->toBe(30_000)
        ->and($application->type->value)->toBe('retirement')
        ->and($application->status)->toBe(PermitApplicationStatus::PendingPayment)
        ->and($application->metadata['retirement_policy_boundary']['status'])->toBe('policy_boundary')
        ->and($application->metadata['retirement_policy_boundary']['unresolved_policy'])->toContain('retirement effective date and legal closure effect')
        ->and($application->paymentSchedules()->count())->toBe(1)
        ->and($storyboard['title'])->toBe('Retirement permit lifecycle foundation')
        ->and($storyboard['record']['application_type'])->toBe('retirement')
        ->and($storyboard['record']['retirement_policy_status'])->toBe('policy_boundary');
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
        ...paymentPolicyBoundaryBrowserReport(),
        'assessment' => [
            'range_line' => [
                'code' => $manifest['resources']['range_fee_rule_code'],
                'calculation_type' => $manifest['resources']['range_calculation_type'],
                'basis' => $manifest['resources']['range_basis'],
                'basis_amount_cents' => $manifest['resources']['range_basis_amount_cents'],
                'amount_cents' => $manifest['resources']['range_amount_cents'],
            ],
            'business_tax' => [
                'code' => $manifest['resources']['business_tax_code'],
                'name' => $manifest['resources']['business_tax_name'],
                'category' => $manifest['resources']['business_tax_category'],
                'line_of_business' => $manifest['resources']['business_tax_line_of_business'],
                'basis' => $manifest['resources']['business_tax_basis'],
                'declared_gross_sales_cents' => $manifest['resources']['business_tax_declared_gross_sales_cents'],
                'amount_cents' => $manifest['resources']['business_tax_amount_cents'],
            ],
        ],
        'reports' => [
            'unpaid_establishments' => [
                'application_number' => $manifest['resources']['application_number'],
                'business_name' => $manifest['resources']['unpaid_establishment_business_name'],
                'application_visible' => true,
                'csv_export_visible' => true,
            ],
            'top_tax_due' => [
                'application_number' => $manifest['resources']['application_number'],
                'business_name' => $manifest['resources']['top_tax_due_business_name'],
                'tax_due_cents' => $manifest['resources']['top_tax_due_cents'],
                'application_visible' => true,
                'csv_export_visible' => true,
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

test('renewal permit lifecycle foundation audit compares browser policy evidence with canonical state', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('renewal_permit_lifecycle_foundation');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'renewal-foundation-test-002');
    $runner = app(PermitApplicationPendingPaymentVisibilityScenario::class);

    $manifest = $runner->prepare($scenario, 'renewal-foundation-test-002', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $artifactStore->putJson('browser/report.json', [
        'result' => [
            'passed' => true,
        ],
        'renewal_policy' => [
            'status' => 'policy_boundary',
            'unresolved_visible' => true,
        ],
        ...paymentPolicyBoundaryBrowserReport(),
        'assessment' => [
            'range_line' => [
                'code' => $manifest['resources']['range_fee_rule_code'],
                'calculation_type' => $manifest['resources']['range_calculation_type'],
                'basis' => $manifest['resources']['range_basis'],
                'basis_amount_cents' => $manifest['resources']['range_basis_amount_cents'],
                'amount_cents' => $manifest['resources']['range_amount_cents'],
            ],
            'business_tax' => [
                'code' => $manifest['resources']['business_tax_code'],
                'name' => $manifest['resources']['business_tax_name'],
                'category' => $manifest['resources']['business_tax_category'],
                'line_of_business' => $manifest['resources']['business_tax_line_of_business'],
                'basis' => $manifest['resources']['business_tax_basis'],
                'declared_gross_sales_cents' => $manifest['resources']['business_tax_declared_gross_sales_cents'],
                'amount_cents' => $manifest['resources']['business_tax_amount_cents'],
            ],
        ],
        'reports' => [
            'unpaid_establishments' => [
                'application_number' => $manifest['resources']['application_number'],
                'business_name' => $manifest['resources']['unpaid_establishment_business_name'],
                'application_visible' => true,
                'csv_export_visible' => true,
            ],
            'top_tax_due' => [
                'application_number' => $manifest['resources']['application_number'],
                'business_name' => $manifest['resources']['top_tax_due_business_name'],
                'tax_due_cents' => $manifest['resources']['top_tax_due_cents'],
                'application_visible' => true,
                'csv_export_visible' => true,
            ],
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '02-detail' => 'browser/screenshots/02-detail.png',
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

test('amendment permit lifecycle foundation audit compares browser policy evidence with canonical state', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('amendment_permit_lifecycle_foundation');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'amendment-foundation-test-002');
    $runner = app(PermitApplicationPendingPaymentVisibilityScenario::class);

    $manifest = $runner->prepare($scenario, 'amendment-foundation-test-002', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $artifactStore->putJson('browser/report.json', [
        'result' => [
            'passed' => true,
        ],
        'amendment_policy' => [
            'status' => 'policy_boundary',
            'unresolved_visible' => true,
        ],
        ...paymentPolicyBoundaryBrowserReport(),
        'assessment' => [
            'range_line' => [
                'code' => $manifest['resources']['range_fee_rule_code'],
                'calculation_type' => $manifest['resources']['range_calculation_type'],
                'basis' => $manifest['resources']['range_basis'],
                'basis_amount_cents' => $manifest['resources']['range_basis_amount_cents'],
                'amount_cents' => $manifest['resources']['range_amount_cents'],
            ],
            'business_tax' => [
                'code' => $manifest['resources']['business_tax_code'],
                'name' => $manifest['resources']['business_tax_name'],
                'category' => $manifest['resources']['business_tax_category'],
                'line_of_business' => $manifest['resources']['business_tax_line_of_business'],
                'basis' => $manifest['resources']['business_tax_basis'],
                'declared_gross_sales_cents' => $manifest['resources']['business_tax_declared_gross_sales_cents'],
                'amount_cents' => $manifest['resources']['business_tax_amount_cents'],
            ],
        ],
        'reports' => [
            'unpaid_establishments' => [
                'application_number' => $manifest['resources']['application_number'],
                'business_name' => $manifest['resources']['unpaid_establishment_business_name'],
                'application_visible' => true,
                'csv_export_visible' => true,
            ],
            'top_tax_due' => [
                'application_number' => $manifest['resources']['application_number'],
                'business_name' => $manifest['resources']['top_tax_due_business_name'],
                'tax_due_cents' => $manifest['resources']['top_tax_due_cents'],
                'application_visible' => true,
                'csv_export_visible' => true,
            ],
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '02-detail' => 'browser/screenshots/02-detail.png',
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

test('transfer permit lifecycle foundation audit compares browser policy evidence with canonical state', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('transfer_permit_lifecycle_foundation');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'transfer-foundation-test-002');
    $runner = app(PermitApplicationPendingPaymentVisibilityScenario::class);

    $manifest = $runner->prepare($scenario, 'transfer-foundation-test-002', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $artifactStore->putJson('browser/report.json', [
        'result' => [
            'passed' => true,
        ],
        'transfer_policy' => [
            'status' => 'policy_boundary',
            'unresolved_visible' => true,
        ],
        ...paymentPolicyBoundaryBrowserReport(),
        'assessment' => [
            'range_line' => [
                'code' => $manifest['resources']['range_fee_rule_code'],
                'calculation_type' => $manifest['resources']['range_calculation_type'],
                'basis' => $manifest['resources']['range_basis'],
                'basis_amount_cents' => $manifest['resources']['range_basis_amount_cents'],
                'amount_cents' => $manifest['resources']['range_amount_cents'],
            ],
            'business_tax' => [
                'code' => $manifest['resources']['business_tax_code'],
                'name' => $manifest['resources']['business_tax_name'],
                'category' => $manifest['resources']['business_tax_category'],
                'line_of_business' => $manifest['resources']['business_tax_line_of_business'],
                'basis' => $manifest['resources']['business_tax_basis'],
                'declared_gross_sales_cents' => $manifest['resources']['business_tax_declared_gross_sales_cents'],
                'amount_cents' => $manifest['resources']['business_tax_amount_cents'],
            ],
        ],
        'reports' => [
            'unpaid_establishments' => [
                'application_number' => $manifest['resources']['application_number'],
                'business_name' => $manifest['resources']['unpaid_establishment_business_name'],
                'application_visible' => true,
                'csv_export_visible' => true,
            ],
            'top_tax_due' => [
                'application_number' => $manifest['resources']['application_number'],
                'business_name' => $manifest['resources']['top_tax_due_business_name'],
                'tax_due_cents' => $manifest['resources']['top_tax_due_cents'],
                'application_visible' => true,
                'csv_export_visible' => true,
            ],
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '02-detail' => 'browser/screenshots/02-detail.png',
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

test('retirement permit lifecycle foundation audit compares browser policy evidence with canonical state', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('retirement_permit_lifecycle_foundation');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'retirement-foundation-test-002');
    $runner = app(PermitApplicationPendingPaymentVisibilityScenario::class);

    $manifest = $runner->prepare($scenario, 'retirement-foundation-test-002', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $artifactStore->putJson('browser/report.json', [
        'result' => [
            'passed' => true,
        ],
        'retirement_policy' => [
            'status' => 'policy_boundary',
            'unresolved_visible' => true,
        ],
        ...paymentPolicyBoundaryBrowserReport(),
        'assessment' => [
            'range_line' => [
                'code' => $manifest['resources']['range_fee_rule_code'],
                'calculation_type' => $manifest['resources']['range_calculation_type'],
                'basis' => $manifest['resources']['range_basis'],
                'basis_amount_cents' => $manifest['resources']['range_basis_amount_cents'],
                'amount_cents' => $manifest['resources']['range_amount_cents'],
            ],
            'business_tax' => [
                'code' => $manifest['resources']['business_tax_code'],
                'name' => $manifest['resources']['business_tax_name'],
                'category' => $manifest['resources']['business_tax_category'],
                'line_of_business' => $manifest['resources']['business_tax_line_of_business'],
                'basis' => $manifest['resources']['business_tax_basis'],
                'declared_gross_sales_cents' => $manifest['resources']['business_tax_declared_gross_sales_cents'],
                'amount_cents' => $manifest['resources']['business_tax_amount_cents'],
            ],
        ],
        'reports' => [
            'unpaid_establishments' => [
                'application_number' => $manifest['resources']['application_number'],
                'business_name' => $manifest['resources']['unpaid_establishment_business_name'],
                'application_visible' => true,
                'csv_export_visible' => true,
            ],
            'top_tax_due' => [
                'application_number' => $manifest['resources']['application_number'],
                'business_name' => $manifest['resources']['top_tax_due_business_name'],
                'tax_due_cents' => $manifest['resources']['top_tax_due_cents'],
                'application_visible' => true,
                'csv_export_visible' => true,
            ],
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '02-detail' => 'browser/screenshots/02-detail.png',
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

test('revenue code fee catalog visibility audit compares browser evidence with canonical fee rule state', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('revenue_code_fee_catalog_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'fee-catalog-test-002');
    $runner = app(RevenueCodeFeeCatalogVisibilityScenario::class);

    $manifest = $runner->prepare($scenario, 'fee-catalog-test-002', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $artifactStore->putJson('browser/report.json', [
        'result' => [
            'passed' => true,
        ],
        'fee_catalog' => [
            'fee_rule_code' => $manifest['resources']['fee_rule_code'],
            'detail_visible' => true,
            'policy_boundary_visible' => true,
            'policy_boundaries_visible' => $manifest['resources']['policy_boundaries'],
            'application_types_visible' => $manifest['resources']['application_types'],
            'range_amount_visible' => true,
            'legal_basis_visible' => true,
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '02-fee-rule-detail' => 'browser/screenshots/02-fee-rule-detail.png',
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

test('assessment policy boundary visibility audit compares browser evidence with canonical refused assessment state', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('assessment_policy_boundary_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'assessment-policy-boundary-test-002');
    $runner = app(AssessmentPolicyBoundaryVisibilityScenario::class);

    $manifest = $runner->prepare($scenario, 'assessment-policy-boundary-test-002', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);

    $this
        ->actingAs($user)
        ->from(route('staff.permit-applications.assessments.index'))
        ->post(route('staff.permit-applications.assessments.store', $manifest['resources']['record_id']))
        ->assertRedirectBackWithErrors(['assessment_policy']);

    $artifactStore->putJson('browser/report.json', [
        'result' => [
            'passed' => true,
        ],
        'assessment_policy_boundary' => [
            'application_number' => $manifest['resources']['application_number'],
            'boundary_visible' => true,
            'reason_visible' => true,
            'row_boundary_visible' => true,
            'not_assessed_visible' => true,
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '01-assessment-policy-boundary' => 'browser/screenshots/01-assessment-policy-boundary.png',
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
        UserPermission::ViewReports,
        UserPermission::UpdatePermitApplicationStatus,
        UserPermission::CompletePermitClearances,
        UserPermission::ViewFeeRules,
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
