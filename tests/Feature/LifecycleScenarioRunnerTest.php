<?php

use App\Actions\DescribePermitVerificationBoundary;
use App\Actions\DescribeReceiptVoidBoundary;
use App\Actions\StoreCitizenPermitApplicationDocument;
use App\Actions\SubmitCitizenPermitApplication;
use App\Actions\UpdateCitizenPermitApplicationDraft;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitClearanceStatus;
use App\Enums\ReceiptStatus;
use App\Enums\StoryboardExportFormat;
use App\Enums\StoryboardExportStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Jobs\GenerateStoryboardVideo;
use App\LifecycleScenarios\AssessmentPolicyBoundaryVisibilityScenario;
use App\LifecycleScenarios\CitizenPermitAuthorityReviewVisibilityScenario;
use App\LifecycleScenarios\CitizenPermitDraftVisibilityScenario;
use App\LifecycleScenarios\CitizenPermitProcessingVisibilityScenario;
use App\LifecycleScenarios\LifecycleScenarioRegistry;
use App\LifecycleScenarios\ManualCollectionReceiptVisibilityScenario;
use App\LifecycleScenarios\PermitApplicationCancelledVisibilityScenario;
use App\LifecycleScenarios\PermitApplicationPendingPaymentVisibilityScenario;
use App\LifecycleScenarios\RevenueCodeExecutabilitySafetyScenario;
use App\LifecycleScenarios\RevenueCodeFeeCatalogVisibilityScenario;
use App\LifecycleScenarios\ScenarioActorResolver;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\LifecycleScenarios\StoryboardTerminalStateVisibilityScenario;
use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\FeeRule;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\Storyboard;
use App\Models\TreasuryCollection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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

test('scenario registry discovers the citizen permit draft visibility scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_permit_draft_visibility');

    expect($scenario)
        ->key->toBe('citizen_permit_draft_visibility')
        ->label->toBe('Citizen permit draft visibility')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['canonical_state'])->toBe('draft')
        ->and($scenario->expectations['official_application_number'])->toBeNull()
        ->and($scenario->expectations['assessment_count'])->toBe(0)
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('scenario registry discovers citizen existing-business registry safety', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_existing_business_registry_safety');

    expect($scenario)
        ->key->toBe('citizen_existing_business_registry_safety')
        ->label->toBe('Citizen existing-business reuse and registry safety')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['existing_business_reused'])->toBeTrue()
        ->and($scenario->expectations['cross_owner_business_rejected'])->toBeTrue()
        ->and($scenario->expectations['registry_facts_read_only'])->toBeTrue()
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('scenario registry discovers the citizen formal submission visibility scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_permit_submission_visibility');

    expect($scenario)
        ->key->toBe('citizen_permit_submission_visibility')
        ->label->toBe('Citizen permit formal submission visibility')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['prepared_state'])->toBe('draft')
        ->and($scenario->expectations['browser_performs_submission'])->toBeTrue()
        ->and($scenario->expectations['canonical_state'])->toBe('assessment')
        ->and($scenario->expectations['official_application_number'])->toBeNull()
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('scenario registry discovers the citizen-originated permit milestone', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_new_permit_lifecycle_authority_boundary');

    expect($scenario)
        ->key->toBe('citizen_new_permit_lifecycle_authority_boundary')
        ->label->toBe('Citizen-originated new permit lifecycle to authority boundary')
        ->risk->toBe('local transactional')
        ->and($scenario->actors)->toBe([
            'applicant' => 'citizen_applicant',
            'operator' => 'primary_operator',
            'recipient' => 'sample_recipient',
        ])
        ->and($scenario->expectations['official_application_number'])->toBeNull()
        ->and($scenario->expectations['ready_for_authority_review'])->toBeTrue()
        ->and($scenario->expectations['can_release'])->toBeFalse()
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('scenario registry discovers the citizen permit processing visibility scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_permit_processing_visibility');

    expect($scenario)
        ->key->toBe('citizen_permit_processing_visibility')
        ->label->toBe('Citizen permit processing visibility')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['canonical_state'])->toBe('pending_payment')
        ->and($scenario->expectations['assessment_status'])->toBe('computed')
        ->and($scenario->expectations['online_payment_status'])->toBe('blocked')
        ->and($scenario->expectations['can_pay_online'])->toBeFalse()
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('scenario registry discovers the citizen permit authority review visibility scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_permit_authority_review_visibility');

    expect($scenario)
        ->key->toBe('citizen_permit_authority_review_visibility')
        ->label->toBe('Citizen permit authority review visibility')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['payment_schedule_status'])->toBe('paid')
        ->and($scenario->expectations['collection_status'])->toBe('receipted')
        ->and($scenario->expectations['receipt_status'])->toBe('issued')
        ->and($scenario->expectations['ready_for_authority_review'])->toBeTrue()
        ->and($scenario->expectations['can_release'])->toBeFalse()
        ->and($scenario->expectations['permit_artifact_status'])->toBe('generated_artifact_available')
        ->and($scenario->expectations['public_verification_status'])->toBe('artifact_only')
        ->and($scenario->expectations['citizen_payment_detail'])->toBe('read_only')
        ->and($scenario->expectations['can_reconcile_online'])->toBeFalse()
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('scenario registry discovers the citizen permit draft edit visibility scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_permit_draft_edit_visibility');

    expect($scenario)
        ->key->toBe('citizen_permit_draft_edit_visibility')
        ->label->toBe('Citizen permit draft edit visibility')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['browser_performs_edit'])->toBeTrue()
        ->and($scenario->expectations['official_application_number'])->toBeNull()
        ->and($scenario->expectations['assessment_count'])->toBe(0)
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('scenario registry discovers the citizen permit draft document visibility scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_permit_draft_document_visibility');

    expect($scenario)
        ->key->toBe('citizen_permit_draft_document_visibility')
        ->label->toBe('Citizen permit draft document visibility')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['browser_performs_document_upload'])->toBeTrue()
        ->and($scenario->expectations['document_count'])->toBe(1)
        ->and($scenario->expectations['submission_readiness'])->toBe('not_determined')
        ->and($scenario->safety['external_integrations'])->toBeFalse();
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
        ->and($scenario->expectations['schedule_count'])->toBe(4)
        ->and($scenario->expectations['schedule_total_row_count'])->toBe(82)
        ->and($scenario->expectations['schedule_total_overlap_count'])->toBe(3)
        ->and($scenario->safety['external_integrations'])->toBeFalse();
});

test('scenario registry discovers the revenue code executability safety scenario', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('revenue_code_executability_safety');

    expect($scenario)
        ->key->toBe('revenue_code_executability_safety')
        ->label->toBe('Revenue Code executability and reconciliation safety')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['exact_fee_rule_code'])->toBe('MRC-3A-04-BUSINESS-INSPECTION')
        ->and($scenario->expectations['exact_amount_cents'])->toBe(35_000)
        ->and($scenario->expectations['blocked_fee_rule_code'])->toBe('MRC-3A-02-NEW-MAYORS-PERMIT-MICRO')
        ->and($scenario->expectations['blocked_assessment_count'])->toBe(0)
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

test('actor resolver resolves the configured citizen applicant through citizen permissions', function () {
    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    config()->set('lifecycle_scenarios.actors.citizen_applicant.email', $citizen->email);

    $actors = app(ScenarioActorResolver::class)
        ->resolve(app(LifecycleScenarioRegistry::class)->get('citizen_permit_draft_visibility'));

    expect($actors)
        ->toHaveKey('applicant')
        ->and($actors['applicant']->is($citizen))->toBeTrue();
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

test('citizen permit draft scenario uses canonical creation and is idempotent', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_permit_draft_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'citizen-draft-test-001');
    $runner = app(CitizenPermitDraftVisibilityScenario::class);

    $firstManifest = $runner->prepare($scenario, 'citizen-draft-test-001', [
        'applicant' => $citizen,
    ], $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'citizen-draft-test-001', [
        'applicant' => $citizen,
    ], $artifactStore);

    $application = PermitApplication::query()
        ->with('lines')
        ->findOrFail($firstManifest['resources']['record_id']);

    expect($firstManifest['resources']['record_id'])->toBe($secondManifest['resources']['record_id'])
        ->and(PermitApplication::query()->count())->toBe(1)
        ->and($application->submitted_by_id)->toBe($citizen->id)
        ->and($application->status)->toBe(PermitApplicationStatus::Draft)
        ->and($application->application_number)->toBeNull()
        ->and($application->assessments()->count())->toBe(0)
        ->and($application->lines)->toHaveCount(2)
        ->and($firstManifest['resources']['public_reference'])->toBe('Draft #'.$application->id)
        ->and($firstManifest['actors']['applicant']['email'])->toBe('c***@example.test')
        ->and($artifactStore->exists('terminal/prepare.json'))->toBeTrue()
        ->and($artifactStore->exists('storyboard/storyboard.json'))->toBeTrue();
});

test('citizen permit draft scenario audit compares browser evidence with canonical ownership and state', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_permit_draft_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'citizen-draft-test-002');
    $runner = app(CitizenPermitDraftVisibilityScenario::class);
    $manifest = $runner->prepare($scenario, 'citizen-draft-test-002', [
        'applicant' => $citizen,
    ], $artifactStore);
    $artifactStore->putJson('browser/report.json', [
        'result' => ['passed' => true],
        'citizen_draft' => [
            'status' => 'draft',
            'business_activities' => collect($manifest['resources']['business_activities'])
                ->map(fn (array $activity): array => collect($activity)->except('name')->all())
                ->all(),
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '03-citizen-detail' => 'browser/screenshots/03-citizen-detail.png',
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

test('citizen existing-business scenario proves registry reuse and immutable shared identity', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_existing_business_registry_safety');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'citizen-existing-business-test-001');
    $runner = app(CitizenPermitDraftVisibilityScenario::class);
    $manifest = $runner->prepare($scenario, 'citizen-existing-business-test-001', [
        'applicant' => $citizen,
    ], $artifactStore);
    $resumedManifest = $runner->prepare($scenario, 'citizen-existing-business-test-001', [
        'applicant' => $citizen,
    ], $artifactStore);
    $application = PermitApplication::query()
        ->with(['business.owner', 'lines.lineOfBusiness'])
        ->findOrFail($manifest['resources']['record_id']);
    $registry = $manifest['resources']['registry_safety'];
    $expectedEdit = $manifest['resources']['expected_edit'];

    expect($resumedManifest['resources']['record_id'])->toBe($application->id)
        ->and(PermitApplication::query()->count())->toBe(2)
        ->and($application->business_id)->toBe($registry['business_id'])
        ->and($application->business->business_owner_id)->toBe($citizen->refresh()->business_owner_id)
        ->and($application->business->permitApplications()->count())->toBe(2)
        ->and(PermitApplication::query()->where('business_id', $registry['other_business_id'])->count())->toBe(0)
        ->and($registry['cross_owner_rejected'])->toBeTrue()
        ->and(BusinessOwner::query()->findOrFail($registry['other_owner_id'])->id)->not->toBe($citizen->business_owner_id)
        ->and(Business::query()->findOrFail($registry['other_business_id'])->business_owner_id)->toBe($registry['other_owner_id']);

    app(UpdateCitizenPermitApplicationDraft::class)->handle($application, [
        'owner_name' => $application->business->owner->name,
        'owner_phone_sha256' => hash('sha256', (string) $application->business->owner->phone),
        'owner_address' => $application->business->owner->address,
        'business_name' => $application->business->name,
        'trade_name' => $application->business->trade_name,
        'registration_number' => $application->business->registration_number,
        'business_address' => $application->business->address,
        'barangay' => $application->business->barangay,
        'type' => $application->type->value,
        'application_year' => $application->application_year,
        'draft_version' => $application->updated_at->toIso8601String(),
        'lines' => collect($expectedEdit['business_activities'])->map(function (array $activity) use ($application): array {
            $line = $application->lines->firstWhere('lineOfBusiness.code', $activity['code']);

            return [
                'line_of_business_id' => $line->line_of_business_id,
                'declared_gross_sales_cents' => $activity['declared_gross_sales_cents'],
                'capital_investment_cents' => $activity['capital_investment_cents'],
                'quantity' => $activity['quantity'],
                'started_on' => $activity['started_on'],
            ];
        })->all(),
    ], $citizen);
    $artifactStore->putJson('browser/report.json', [
        'result' => ['passed' => true],
        'citizen_registry' => [
            'selected_business_id' => $registry['business_id'],
            'owned_option_visible' => true,
            'other_owner_option_visible' => false,
            'selected_summary_visible' => true,
        ],
        'citizen_draft' => [
            'edit_performed_by_browser' => true,
            'status' => PermitApplicationStatus::Draft->value,
            'business_name' => $registry['business_name'],
            'owner_phone_sha256' => $registry['owner_phone_sha256'],
            'registry_facts_read_only' => true,
            'business_activities' => collect($expectedEdit['business_activities'])
                ->map(fn (array $activity): array => collect($activity)->except('name')->all())
                ->all(),
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '01-citizen-existing-business-selection' => 'browser/screenshots/01-citizen-existing-business-selection.png',
                '03-citizen-draft-after-edit' => 'browser/screenshots/03-citizen-draft-after-edit.png',
            ],
        ],
    ]);

    $audited = $runner->audit($manifest, $artifactStore);

    expect($audited['result'])
        ->terminal->toBe('passed')
        ->browser->toBe('passed')
        ->audit->toBe('passed')
        ->passed->toBeTrue()
        ->and($application->business->refresh()->name)->toBe($registry['business_name'])
        ->and($application->business->owner->refresh()->name)->toBe($registry['owner_name'])
        ->and($artifactStore->exists('summary.html'))->toBeTrue();
});

test('citizen formal submission scenario audits the browser transition against canonical receipt evidence', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_permit_submission_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'citizen-submission-test-001');
    $runner = app(CitizenPermitDraftVisibilityScenario::class);
    $manifest = $runner->prepare($scenario, 'citizen-submission-test-001', [
        'applicant' => $citizen,
    ], $artifactStore);
    $application = PermitApplication::query()->findOrFail($manifest['resources']['record_id']);

    expect($application->status)->toBe(PermitApplicationStatus::Draft)
        ->and($application->submitted_at)->toBeNull()
        ->and($application->application_number)->toBeNull()
        ->and($citizen->business_owner_id)->toBe($application->business->business_owner_id);

    app(SubmitCitizenPermitApplication::class)->handle($application, $citizen);
    $artifactStore->putJson('browser/report.json', [
        'result' => ['passed' => true],
        'citizen_submission' => [
            'status' => PermitApplicationStatus::Assessment->value,
            'citizen_submitted' => true,
            'municipality_received' => true,
            'submit_action_available' => false,
            'edit_action_available' => false,
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '02-citizen-after-submission' => 'browser/screenshots/02-citizen-after-submission.png',
            ],
        ],
    ]);

    $audited = $runner->audit($manifest, $artifactStore);

    expect($audited['result'])
        ->terminal->toBe('passed')
        ->browser->toBe('passed')
        ->audit->toBe('passed')
        ->passed->toBeTrue()
        ->and($application->refresh()->metadata['status_history'])->toHaveCount(1)
        ->and($artifactStore->exists('terminal/audit.json'))->toBeTrue()
        ->and($artifactStore->exists('summary.html'))->toBeTrue();
});

test('citizen permit draft edit scenario audit compares browser edits with canonical state', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_permit_draft_edit_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'citizen-draft-edit-test-001');
    $runner = app(CitizenPermitDraftVisibilityScenario::class);
    $manifest = $runner->prepare($scenario, 'citizen-draft-edit-test-001', [
        'applicant' => $citizen,
    ], $artifactStore);
    $application = PermitApplication::query()
        ->with(['business.owner', 'lines.lineOfBusiness'])
        ->findOrFail($manifest['resources']['record_id']);
    $expectedEdit = $manifest['resources']['expected_edit'];

    app(UpdateCitizenPermitApplicationDraft::class)->handle($application, [
        'owner_name' => $application->business->owner->name,
        'owner_email' => $application->business->owner->email,
        'owner_phone_sha256' => $expectedEdit['owner_phone_sha256'],
        'owner_address' => $application->business->owner->address,
        'business_name' => $expectedEdit['business_name'],
        'trade_name' => $application->business->trade_name,
        'business_address' => $application->business->address,
        'barangay' => $application->business->barangay,
        'type' => $application->type->value,
        'application_year' => $application->application_year,
        'draft_version' => $application->updated_at->toIso8601String(),
        'lines' => collect($expectedEdit['business_activities'])->map(function (array $activity) use ($application): array {
            $line = $application->lines->firstWhere('lineOfBusiness.code', $activity['code']);

            return [
                'line_of_business_id' => $line->line_of_business_id,
                'declared_gross_sales_cents' => $activity['declared_gross_sales_cents'],
                'capital_investment_cents' => $activity['capital_investment_cents'],
                'quantity' => $activity['quantity'],
                'started_on' => $activity['started_on'],
            ];
        })->all(),
    ], $citizen);
    $artifactStore->putJson('browser/report.json', [
        'result' => ['passed' => true],
        'citizen_draft' => [
            'edit_performed_by_browser' => true,
            'status' => 'draft',
            'business_name' => $expectedEdit['business_name'],
            'owner_phone_sha256' => $expectedEdit['owner_phone_sha256'],
            'registry_facts_read_only' => true,
            'business_activities' => collect($expectedEdit['business_activities'])
                ->map(fn (array $activity): array => collect($activity)->except('name')->all())
                ->all(),
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '03-citizen-draft-after-edit' => 'browser/screenshots/03-citizen-draft-after-edit.png',
            ],
        ],
    ]);

    $audited = $runner->audit($manifest, $artifactStore);

    expect($audited['result'])
        ->terminal->toBe('passed')
        ->browser->toBe('passed')
        ->audit->toBe('passed')
        ->passed->toBeTrue()
        ->and($artifactStore->readJson('terminal/audit.json')['canonical']['business_name'])->toBe($expectedEdit['business_name'])
        ->and($artifactStore->exists('summary.html'))->toBeTrue();
});

test('citizen permit draft document scenario audits browser upload against private canonical evidence', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_permit_draft_document_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'citizen-draft-document-test-001');
    $runner = app(CitizenPermitDraftVisibilityScenario::class);
    $manifest = $runner->prepare($scenario, 'citizen-draft-document-test-001', [
        'applicant' => $citizen,
    ], $artifactStore);
    $application = PermitApplication::query()->findOrFail($manifest['resources']['record_id']);
    $expectedDocument = $manifest['resources']['expected_document'];
    $fixturePath = Storage::disk('local')->path(
        $artifactStore->rootRelativePath().'/'.$expectedDocument['fixture_path'],
    );
    $document = app(StoreCitizenPermitApplicationDocument::class)->handle($application, [
        'label' => $expectedDocument['label'],
        'file' => new UploadedFile(
            $fixturePath,
            $expectedDocument['original_name'],
            'application/pdf',
            null,
            true,
        ),
        'remarks' => $expectedDocument['remarks'],
    ], $citizen);
    $artifactStore->putJson('browser/report.json', [
        'result' => ['passed' => true],
        'citizen_draft' => [
            'document_upload_performed_by_browser' => true,
            'status' => 'draft',
            'business_activities' => collect($manifest['resources']['business_activities'])
                ->map(fn (array $activity): array => collect($activity)->except('name')->all())
                ->all(),
            'supporting_document' => [
                'id' => $document->id,
                'label' => $document->label,
                'original_name' => $document->original_name,
                'download_available' => true,
            ],
            'documentary_readiness' => [
                'received_document_count' => 1,
                'submission_readiness' => 'not_determined',
            ],
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '03-citizen-document-after-upload' => 'browser/screenshots/03-citizen-document-after-upload.png',
            ],
        ],
    ]);

    $audited = $runner->audit($manifest, $artifactStore);
    $audit = $artifactStore->readJson('terminal/audit.json');

    expect($audited['result'])
        ->terminal->toBe('passed')
        ->browser->toBe('passed')
        ->audit->toBe('passed')
        ->passed->toBeTrue()
        ->and($audit['canonical']['documents'])->toHaveCount(1)
        ->and($audit['canonical']['documents'][0]['id'])->toBe($document->id)
        ->and($audit['canonical']['documents'][0]['stored_privately'])->toBeTrue()
        ->and($artifactStore->exists($expectedDocument['fixture_path']))->toBeTrue()
        ->and($artifactStore->exists('summary.html'))->toBeTrue();
});

test('citizen permit draft document audit preserves a failed report when canonical evidence is missing', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_permit_draft_document_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'citizen-draft-document-missing-test-001');
    $runner = app(CitizenPermitDraftVisibilityScenario::class);
    $manifest = $runner->prepare($scenario, 'citizen-draft-document-missing-test-001', [
        'applicant' => $citizen,
    ], $artifactStore);
    $artifactStore->putJson('browser/report.json', [
        'result' => ['passed' => true],
        'citizen_draft' => [
            'document_upload_performed_by_browser' => false,
            'status' => 'draft',
            'business_activities' => collect($manifest['resources']['business_activities'])
                ->map(fn (array $activity): array => collect($activity)->except('name')->all())
                ->all(),
        ],
        'checks' => [],
        'artifacts' => ['screenshots' => []],
    ]);

    $audited = $runner->audit($manifest, $artifactStore);
    $audit = $artifactStore->readJson('terminal/audit.json');

    expect($audited['result'])
        ->audit->toBe('failed')
        ->passed->toBeFalse()
        ->and($audit['canonical']['documents'])->toBeEmpty()
        ->and(collect($audit['checks'])->firstWhere('key', 'audit-citizen-supporting-document')['passed'])->toBeFalse()
        ->and($artifactStore->exists('summary.html'))->toBeTrue();
});

test('citizen permit processing scenario audits browser financial state against canonical records', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    $operator = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_permit_processing_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'citizen-processing-test-001');
    $runner = app(CitizenPermitProcessingVisibilityScenario::class);
    $manifest = $runner->prepare($scenario, 'citizen-processing-test-001', [
        'applicant' => $citizen,
        'operator' => $operator,
    ], $artifactStore);
    $artifactStore->putJson('browser/report.json', [
        'result' => ['passed' => true],
        'citizen_processing' => [
            'application_status' => $manifest['resources']['application_status'],
            'assessment_id' => $manifest['resources']['assessment_id'],
            'assessment_status' => $manifest['resources']['assessment_status'],
            'assessment_total_amount_cents' => $manifest['resources']['assessment_total_amount_cents'],
            'payment_schedule_id' => $manifest['resources']['payment_schedule_id'],
            'payment_schedule_status' => $manifest['resources']['payment_schedule_status'],
            'payment_total_amount_cents' => $manifest['resources']['payment_total_amount_cents'],
            'payment_paid_amount_cents' => $manifest['resources']['payment_paid_amount_cents'],
            'payment_balance_amount_cents' => $manifest['resources']['payment_balance_amount_cents'],
            'online_payment_status' => 'blocked',
            'can_pay_online' => false,
            'payment_action_visible' => false,
            'timeline_event_count' => $manifest['resources']['citizen_timeline_event_count'],
            'timeline_event_keys' => $manifest['resources']['citizen_timeline_event_keys'],
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '02-citizen-processing-detail' => 'browser/screenshots/02-citizen-processing-detail.png',
            ],
        ],
    ]);

    $audited = $runner->audit($manifest, $artifactStore);
    $audit = $artifactStore->readJson('terminal/audit.json');

    expect($audited['result'])
        ->terminal->toBe('passed')
        ->browser->toBe('passed')
        ->audit->toBe('passed')
        ->passed->toBeTrue()
        ->and($audit['canonical']['submitted_by_id'])->toBe($citizen->id)
        ->and($audit['canonical']['application_status'])->toBe(PermitApplicationStatus::PendingPayment->value)
        ->and($audit['canonical']['assessment_total_amount_cents'])->toBeGreaterThan(0)
        ->and($audit['canonical']['payment_balance_amount_cents'])->toBe($audit['canonical']['assessment_total_amount_cents'])
        ->and($manifest['resources']['citizen_timeline_event_count'])->toBe(4)
        ->and($manifest['resources']['citizen_timeline_event_keys'])->toBe([
            "application-recorded:{$manifest['resources']['record_id']}",
            "assessment-computed:{$manifest['resources']['assessment_id']}",
            "payment-schedule-prepared:{$manifest['resources']['payment_schedule_id']}",
            'status-transition:0',
        ])
        ->and(collect($manifest['steps'])->firstWhere('key', 'citizen-owned-application-prepared')['actor'])->toBe('applicant')
        ->and(collect($manifest['steps'])->firstWhere('key', 'citizen-owned-application-prepared')['actual']['submitted_by_id'])->toBe($citizen->id)
        ->and($artifactStore->exists('summary.html'))->toBeTrue();
});

test('citizen permit authority review scenario composes domain actions idempotently and audits browser evidence', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    $operator = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_permit_authority_review_visibility');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'citizen-authority-review-test-001');
    $runner = app(CitizenPermitAuthorityReviewVisibilityScenario::class);
    $actors = [
        'applicant' => $citizen,
        'operator' => $operator,
    ];

    $firstManifest = $runner->prepare($scenario, 'citizen-authority-review-test-001', $actors, $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'citizen-authority-review-test-001', $actors, $artifactStore);
    $expectedTimelineKeys = [
        "application-recorded:{$firstManifest['resources']['record_id']}",
        "assessment-computed:{$firstManifest['resources']['assessment_id']}",
        "payment-schedule-prepared:{$firstManifest['resources']['payment_schedule_id']}",
        'status-transition:0',
        "collection-recorded:{$firstManifest['resources']['collection_id']}",
        "receipt-issued:{$firstManifest['resources']['receipt_id']}",
        ...PermitApplication::query()
            ->findOrFail($firstManifest['resources']['record_id'])
            ->clearances()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (int $clearanceId): string => "clearance-completed:{$clearanceId}"),
        "release-blocked:{$firstManifest['resources']['record_id']}",
    ];

    $artifactStore->putJson('browser/report.json', [
        'result' => ['passed' => true],
        'citizen_authority_review' => [
            'collection_id' => $firstManifest['resources']['collection_id'],
            'collection_status' => $firstManifest['resources']['collection_status'],
            'collection_amount_cents' => $firstManifest['resources']['collection_amount_cents'],
            'receipt_id' => $firstManifest['resources']['receipt_id'],
            'receipt_number' => $firstManifest['resources']['receipt_number'],
            'receipt_status' => $firstManifest['resources']['receipt_status'],
            'clearances_completed' => $firstManifest['resources']['clearances_completed'],
            'clearances_total' => $firstManifest['resources']['clearances_total'],
            'ready_for_authority_review' => true,
            'can_release' => false,
            'authority_review_status' => 'ready_for_authority_review',
            'permit_artifact_status' => $firstManifest['resources']['permit_artifact_status'],
            'permit_verification_reference' => $firstManifest['resources']['permit_verification_reference'],
            'permit_verification_status' => $firstManifest['resources']['permit_verification_status'],
            'permit_verification_view_url' => $firstManifest['resources']['permit_verification_view_url'],
            'can_issue' => false,
            'can_make_legally_effective' => false,
            'timeline_event_count' => $firstManifest['resources']['citizen_timeline_event_count'],
            'timeline_event_keys' => $firstManifest['resources']['citizen_timeline_event_keys'],
            'public_page_visible' => true,
            'public_page_can_verify_release' => false,
            'public_page_released' => false,
            'payment_schedule_id' => $firstManifest['resources']['payment_schedule_id'],
            'payment_schedule_status' => $firstManifest['resources']['payment_schedule_status'],
            'payment_total_amount_cents' => $firstManifest['resources']['payment_total_amount_cents'],
            'payment_paid_amount_cents' => $firstManifest['resources']['payment_paid_amount_cents'],
            'payment_balance_amount_cents' => $firstManifest['resources']['payment_balance_amount_cents'],
            'payment_line_count' => $firstManifest['resources']['payment_line_count'],
            'payment_line_codes' => $firstManifest['resources']['payment_line_codes'],
            'payment_collection_count' => $firstManifest['resources']['payment_collection_count'],
            'payment_allocation_count' => $firstManifest['resources']['payment_allocation_count'],
            'payment_policy_status' => $firstManifest['resources']['payment_policy_status'],
            'can_split_installments' => false,
            'online_payment_status' => 'blocked',
            'can_pay_online' => false,
            'can_reconcile_online' => false,
            'payment_detail_action_visible' => false,
            'receipt_download_visible' => false,
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '04-citizen-authority-review' => 'browser/screenshots/04-citizen-authority-review.png',
            ],
        ],
    ]);

    $audited = $runner->audit($firstManifest, $artifactStore);
    $audit = $artifactStore->readJson('terminal/audit.json');

    expect($firstManifest['resources']['record_id'])->toBe($secondManifest['resources']['record_id'])
        ->and($firstManifest['resources']['collection_id'])->toBe($secondManifest['resources']['collection_id'])
        ->and($firstManifest['resources']['receipt_id'])->toBe($secondManifest['resources']['receipt_id'])
        ->and($firstManifest['resources']['application_status'])->toBe(PermitApplicationStatus::PendingPayment->value)
        ->and($firstManifest['resources']['payment_schedule_status'])->toBe('paid')
        ->and($firstManifest['resources']['collection_status'])->toBe(TreasuryCollectionStatus::Receipted->value)
        ->and($firstManifest['resources']['receipt_status'])->toBe(ReceiptStatus::Issued->value)
        ->and($firstManifest['resources']['clearances_completed'])->toBe(3)
        ->and($firstManifest['resources']['ready_for_authority_review'])->toBeTrue()
        ->and($firstManifest['resources']['can_release'])->toBeFalse()
        ->and($firstManifest['resources']['permit_artifact_status'])->toBe('generated_artifact_available')
        ->and($firstManifest['resources']['permit_verification_reference'])->toStartWith('PVA-'.$firstManifest['resources']['record_id'].'-')
        ->and($firstManifest['resources']['permit_verification_status'])->toBe('artifact_only')
        ->and($firstManifest['resources']['permit_verification_view_url'])->toEndWith('/view')
        ->and($firstManifest['resources'])->not->toHaveKey('permit_pdf_url')
        ->and($firstManifest['resources']['can_issue'])->toBeFalse()
        ->and($firstManifest['resources']['can_make_legally_effective'])->toBeFalse()
        ->and($firstManifest['resources']['payment_detail_url'])->toBe('/citizen/payment-schedules/'.$firstManifest['resources']['payment_schedule_id'])
        ->and($firstManifest['resources']['payment_line_count'])->toBe(1)
        ->and($firstManifest['resources']['payment_line_codes'])->toBe(['SCENARIO-CITIZEN-AUTHORITY-FEE'])
        ->and($firstManifest['resources']['payment_collection_count'])->toBe(1)
        ->and($firstManifest['resources']['payment_allocation_count'])->toBe(1)
        ->and($firstManifest['resources']['payment_policy_status'])->toBe('policy_boundary')
        ->and($firstManifest['resources']['can_split_installments'])->toBeFalse()
        ->and($firstManifest['resources']['can_reconcile_online'])->toBeFalse()
        ->and($firstManifest['resources']['citizen_timeline_event_count'])->toBe(10)
        ->and($firstManifest['resources']['citizen_timeline_event_keys'])->toBe($expectedTimelineKeys)
        ->and(PermitApplication::query()->count())->toBe(1)
        ->and(TreasuryCollection::query()->count())->toBe(1)
        ->and(Receipt::query()->count())->toBe(1)
        ->and(collect($audit['checks'])->where('passed', false)->values()->all())->toBe([])
        ->and($audited['result']['audit'])->toBe('passed')
        ->and($audited['result']['passed'])->toBeTrue()
        ->and($audit['canonical']['ready_for_authority_review'])->toBeTrue()
        ->and($audit['canonical']['can_release'])->toBeFalse()
        ->and($audit['canonical']['permit_verification_reference'])->toBe($firstManifest['resources']['permit_verification_reference'])
        ->and(collect($audit['checks'])->firstWhere('key', 'audit-browser-artifact-identity')['passed'])->toBeTrue()
        ->and(collect($audit['checks'])->firstWhere('key', 'audit-browser-payment-evidence')['passed'])->toBeTrue()
        ->and($artifactStore->exists('summary.html'))->toBeTrue();
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
        "document-recorded:{$firstManifest['resources']['supporting_document_id']}",
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
        ->and($firstManifest['resources']['permit_timeline_event_count'])->toBe(11)
        ->and($firstManifest['resources']['permit_timeline_event_keys'])->toBe($expectedTimelineKeys)
        ->and(Receipt::query()->count())->toBe(1)
        ->and(TreasuryCollection::query()->count())->toBe(1)
        ->and(PermitApplicationDocument::query()->count())->toBe(1)
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

test('citizen-originated permit milestone composes the exact submitted record to authority review idempotently', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    $operator = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('citizen_new_permit_lifecycle_authority_boundary');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'citizen-new-permit-milestone-test-001');
    $runner = app(ManualCollectionReceiptVisibilityScenario::class);

    $firstManifest = $runner->prepare($scenario, 'citizen-new-permit-milestone-test-001', [
        'applicant' => $citizen,
        'operator' => $operator,
        'recipient' => $operator,
    ], $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'citizen-new-permit-milestone-test-001', [
        'applicant' => $citizen,
        'operator' => $operator,
        'recipient' => $operator,
    ], $artifactStore);

    $permitApplication = PermitApplication::query()
        ->with(['assessments', 'paymentSchedules', 'treasuryCollections.receipt', 'clearances'])
        ->findOrFail($firstManifest['resources']['permit_application_id']);
    $storyboard = $artifactStore->readJson('storyboard/storyboard.json');

    expect($firstManifest['resources']['permit_application_id'])->toBe($secondManifest['resources']['permit_application_id'])
        ->and($firstManifest['resources']['record_id'])->toBe($secondManifest['resources']['record_id'])
        ->and($permitApplication->submitted_by_id)->toBe($citizen->id)
        ->and($permitApplication->business->business_owner_id)->toBe($citizen->refresh()->business_owner_id)
        ->and($permitApplication->application_number)->toBeNull()
        ->and(data_get($permitApplication->metadata, 'citizen_submission.actor_id'))->toBe($citizen->id)
        ->and(data_get($permitApplication->metadata, 'municipal_receipt.processing_status'))->toBe(PermitApplicationStatus::Assessment->value)
        ->and($permitApplication->status)->toBe(PermitApplicationStatus::PendingPayment)
        ->and($permitApplication->assessments)->toHaveCount(1)
        ->and($permitApplication->paymentSchedules)->toHaveCount(1)
        ->and($permitApplication->treasuryCollections)->toHaveCount(1)
        ->and($permitApplication->treasuryCollections->first()->receipt)->not->toBeNull()
        ->and($permitApplication->clearances)->toHaveCount(3)
        ->and($permitApplication->clearances->where('status', PermitClearanceStatus::Completed))->toHaveCount(3)
        ->and($firstManifest['resources']['application_display_reference'])->toBe('Application #'.$permitApplication->id)
        ->and($firstManifest['resources']['permit_timeline_event_count'])->toBe(14)
        ->and($firstManifest['resources']['citizen_timeline_event_keys'])->toBe($firstManifest['resources']['permit_timeline_event_keys'])
        ->and($storyboard['title'])->toBe('Citizen-originated new permit lifecycle to authority boundary')
        ->and($artifactStore->exists('terminal/prepare.json'))->toBeTrue()
        ->and($artifactStore->exists('storyboard/storyboard.html'))->toBeTrue();
});

test('new permit lifecycle scenario preserves uniqueness for long run references with a shared prefix', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('new_permit_lifecycle_authority_boundary');
    $runner = app(ManualCollectionReceiptVisibilityScenario::class);
    $firstRunId = 'permit-establishment-profile-with-shared-reference-prefix-001';
    $secondRunId = 'permit-establishment-profile-with-shared-reference-prefix-002';

    $firstManifest = $runner->prepare($scenario, $firstRunId, [
        'operator' => $user,
        'recipient' => $user,
    ], new ScenarioArtifactStore($scenario->key, $firstRunId));
    $secondManifest = $runner->prepare($scenario, $secondRunId, [
        'operator' => $user,
        'recipient' => $user,
    ], new ScenarioArtifactStore($scenario->key, $secondRunId));

    expect($firstManifest['resources']['application_number'])
        ->not->toBe($secondManifest['resources']['application_number'])
        ->and($firstManifest['resources']['public_reference'])
        ->not->toBe($secondManifest['resources']['public_reference'])
        ->and(PermitApplication::query()->count())->toBe(2)
        ->and(Receipt::query()->count())->toBe(2);
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
        ->and($firstManifest['resources']['provision_code'])->toBe('MRC-2A-02-B-WHOLESALERS')
        ->and($firstManifest['resources']['provision_status'])->toBe('reconciliation_required')
        ->and($firstManifest['resources']['provision_count'])->toBe(58)
        ->and($firstManifest['resources']['reconciliation_required_count'])->toBe(57)
        ->and($firstManifest['resources']['schedule_matrix']['row_count'])->toBe(24)
        ->and($firstManifest['resources']['schedule_matrix']['overlap_count'])->toBe(1)
        ->and($firstManifest['resources']['schedule_matrix']['gap_count'])->toBe(0)
        ->and($firstManifest['resources']['schedule_matrix']['execution_ready'])->toBeFalse()
        ->and($firstManifest['resources']['schedule_summary']['schedule_count'])->toBe(4)
        ->and($firstManifest['resources']['schedule_summary']['row_count'])->toBe(82)
        ->and($firstManifest['resources']['schedule_summary']['overlap_count'])->toBe(3)
        ->and($firstManifest['resources']['schedule_summary']['gap_count'])->toBe(0)
        ->and($firstManifest['resources']['schedule_summary']['reconciliation_required_count'])->toBe(7)
        ->and($firstManifest['resources']['schedule_summary']['ceiling_count'])->toBe(4)
        ->and($firstManifest['resources']['schedule_summary']['execution_ready_count'])->toBe(0)
        ->and($firstManifest['resources']['schedule_matrices'])->toHaveCount(4)
        ->and($firstManifest['resources']['schedule_matrices']['MRC-2A-02-A-MANUFACTURERS']['row_count'])->toBe(20)
        ->and($firstManifest['resources']['schedule_matrices']['MRC-2A-02-E-CONTRACTORS']['overlap_count'])->toBe(1)
        ->and($firstManifest['resources']['schedule_matrices']['MRC-2A-02-G-ENUMERATED-SERVICES']['ceiling_count'])->toBe(1)
        ->and($firstManifest['resources']['schedule_findings'])->toHaveCount(4)
        ->and($firstManifest['resources']['policy_boundary_summary'])->toBe([
            'provision_count' => 56,
            'clause_count' => 302,
            'reconciliation_required_count' => 302,
            'ceiling_count' => 5,
            'execution_ready_count' => 0,
        ])
        ->and($firstManifest['resources']['policy_boundary_clause_codes'])->toHaveCount(302)
        ->and($firstManifest['resources']['overlap_row_code'])->toBe('MRC-2A-02-B-ROW-08')
        ->and($firstManifest['resources']['policy_boundaries'])->toContain('new_business_initial_local_business_tax_exemption')
        ->and($feeRule->ranges)->toHaveCount(23)
        ->and($storyboard['title'])->toBe('Revenue Code fee catalog visibility')
        ->and($storyboard['record']['type'])->toBe('fee_rule')
        ->and($artifactStore->exists('terminal/prepare.json'))->toBeTrue()
        ->and($artifactStore->exists('terminal/execution.json'))->toBeTrue()
        ->and($artifactStore->exists('storyboard/storyboard.html'))->toBeTrue();
});

test('revenue code executability safety scenario prepares exact execution and blocked precondition idempotently', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('revenue_code_executability_safety');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'revenue-execution-test-001');
    $runner = app(RevenueCodeExecutabilitySafetyScenario::class);

    $firstManifest = $runner->prepare($scenario, 'revenue-execution-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);
    $secondManifest = $runner->prepare($scenario, 'revenue-execution-test-001', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);

    $assessment = Assessment::query()->with('lines')->findOrFail($firstManifest['resources']['exact_assessment_id']);
    $blockedApplication = PermitApplication::query()->findOrFail($firstManifest['resources']['blocked_application_id']);
    $storyboard = $artifactStore->readJson('storyboard/storyboard.json');

    expect($firstManifest['scenario']['key'])->toBe('revenue_code_executability_safety')
        ->and($firstManifest['resources']['exact_assessment_id'])->toBe($secondManifest['resources']['exact_assessment_id'])
        ->and($firstManifest['resources']['blocked_application_id'])->toBe($secondManifest['resources']['blocked_application_id'])
        ->and($firstManifest['resources']['exact_application_id'])->not->toBe($firstManifest['resources']['blocked_application_id'])
        ->and($firstManifest['resources']['exact_application_number'])->not->toBe($firstManifest['resources']['blocked_application_number'])
        ->and($assessment->total_amount_cents)->toBe(35_000)
        ->and($assessment->lines)->toHaveCount(1)
        ->and($assessment->lines->sole()->code)->toBe('MRC-3A-04-BUSINESS-INSPECTION')
        ->and($assessment->lines->sole()->rule_snapshot['reconciliation']['execution_status'])->toBe('executable')
        ->and($blockedApplication->assessments()->count())->toBe(0)
        ->and($firstManifest['resources']['blocked_reconciliation_status'])->toBe('blocked')
        ->and($storyboard['title'])->toBe('Revenue Code executability and reconciliation safety')
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

test('command prepares the revenue code executability safety scenario', function () {
    Storage::fake('local');

    configuredScenarioUser('test@example.com');

    $this->artisan('lifecycle:scenario', [
        'scenario' => 'revenue_code_executability_safety',
        '--run-id' => 'revenue-execution-command-test-001',
        '--phase' => 'prepare',
    ])->assertSuccessful();

    $artifactStore = new ScenarioArtifactStore('revenue_code_executability_safety', 'revenue-execution-command-test-001');
    $manifest = $artifactStore->readJson('manifest.json');

    expect($manifest['scenario']['key'])->toBe('revenue_code_executability_safety')
        ->and($manifest['result']['terminal'])->toBe('passed')
        ->and($manifest['resources']['exact_assessment_total_amount_cents'])->toBe(35_000)
        ->and($manifest['resources']['blocked_reconciliation_status'])->toBe('blocked')
        ->and($artifactStore->exists('storyboard/storyboard.json'))->toBeTrue();
});

test('command prepares the citizen permit draft visibility scenario', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    config()->set('lifecycle_scenarios.actors.citizen_applicant.email', $citizen->email);

    $this->artisan('lifecycle:scenario', [
        'scenario' => 'citizen_permit_draft_visibility',
        '--run-id' => 'citizen-draft-command-test-001',
        '--phase' => 'prepare',
    ])->assertSuccessful();

    $artifactStore = new ScenarioArtifactStore('citizen_permit_draft_visibility', 'citizen-draft-command-test-001');
    $manifest = $artifactStore->readJson('manifest.json');

    expect($manifest['scenario']['key'])->toBe('citizen_permit_draft_visibility')
        ->and($manifest['result']['terminal'])->toBe('passed')
        ->and($manifest['resources']['public_reference'])->toStartWith('Draft #')
        ->and($artifactStore->exists('storyboard/storyboard.json'))->toBeTrue();
});

test('command prepares citizen existing-business registry safety', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    config()->set('lifecycle_scenarios.actors.citizen_applicant.email', $citizen->email);

    $this->artisan('lifecycle:scenario', [
        'scenario' => 'citizen_existing_business_registry_safety',
        '--run-id' => 'citizen-existing-business-command-test-001',
        '--phase' => 'prepare',
    ])->assertSuccessful();

    $artifactStore = new ScenarioArtifactStore('citizen_existing_business_registry_safety', 'citizen-existing-business-command-test-001');
    $manifest = $artifactStore->readJson('manifest.json');

    expect($manifest['scenario']['key'])->toBe('citizen_existing_business_registry_safety')
        ->and($manifest['result']['terminal'])->toBe('passed')
        ->and($manifest['resources']['registry_safety']['cross_owner_rejected'])->toBeTrue()
        ->and($manifest['resources']['record_id'])->not->toBe($manifest['resources']['registry_safety']['bootstrap_application_id'])
        ->and($artifactStore->exists('storyboard/storyboard.json'))->toBeTrue();
});

test('command prepares the citizen permit draft document visibility scenario', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    config()->set('lifecycle_scenarios.actors.citizen_applicant.email', $citizen->email);

    $this->artisan('lifecycle:scenario', [
        'scenario' => 'citizen_permit_draft_document_visibility',
        '--run-id' => 'citizen-draft-document-command-test-001',
        '--phase' => 'prepare',
    ])->assertSuccessful();

    $artifactStore = new ScenarioArtifactStore('citizen_permit_draft_document_visibility', 'citizen-draft-document-command-test-001');
    $manifest = $artifactStore->readJson('manifest.json');

    expect($manifest['scenario']['key'])->toBe('citizen_permit_draft_document_visibility')
        ->and($manifest['result']['terminal'])->toBe('passed')
        ->and($manifest['resources']['expected_document']['submission_readiness'])->toBe('not_determined')
        ->and($artifactStore->exists($manifest['resources']['expected_document']['fixture_path']))->toBeTrue()
        ->and($artifactStore->exists('storyboard/storyboard.json'))->toBeTrue();
});

test('command prepares the citizen permit processing visibility scenario', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    $operator = configuredScenarioUser('operator@example.test');
    config()->set('lifecycle_scenarios.actors.citizen_applicant.email', $citizen->email);
    config()->set('lifecycle_scenarios.actors.primary_operator.email', $operator->email);

    $this->artisan('lifecycle:scenario', [
        'scenario' => 'citizen_permit_processing_visibility',
        '--run-id' => 'citizen-processing-command-test-001',
        '--phase' => 'prepare',
    ])->assertSuccessful();

    $artifactStore = new ScenarioArtifactStore('citizen_permit_processing_visibility', 'citizen-processing-command-test-001');
    $manifest = $artifactStore->readJson('manifest.json');

    expect($manifest['scenario']['key'])->toBe('citizen_permit_processing_visibility')
        ->and($manifest['result']['terminal'])->toBe('passed')
        ->and($manifest['resources']['application_status'])->toBe(PermitApplicationStatus::PendingPayment->value)
        ->and($manifest['resources']['payment_balance_amount_cents'])->toBe($manifest['resources']['assessment_total_amount_cents'])
        ->and($artifactStore->exists('storyboard/storyboard.json'))->toBeTrue();
});

test('command prepares the citizen permit authority review visibility scenario', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    $operator = configuredScenarioUser('operator@example.test');
    config()->set('lifecycle_scenarios.actors.citizen_applicant.email', $citizen->email);
    config()->set('lifecycle_scenarios.actors.primary_operator.email', $operator->email);

    $this->artisan('lifecycle:scenario', [
        'scenario' => 'citizen_permit_authority_review_visibility',
        '--run-id' => 'citizen-authority-review-command-test-001',
        '--phase' => 'prepare',
    ])->assertSuccessful();

    $artifactStore = new ScenarioArtifactStore('citizen_permit_authority_review_visibility', 'citizen-authority-review-command-test-001');
    $manifest = $artifactStore->readJson('manifest.json');

    expect($manifest['scenario']['key'])->toBe('citizen_permit_authority_review_visibility')
        ->and($manifest['result']['terminal'])->toBe('passed')
        ->and($manifest['resources']['collection_status'])->toBe(TreasuryCollectionStatus::Receipted->value)
        ->and($manifest['resources']['receipt_status'])->toBe(ReceiptStatus::Issued->value)
        ->and($manifest['resources']['ready_for_authority_review'])->toBeTrue()
        ->and($manifest['resources']['can_release'])->toBeFalse()
        ->and($manifest['resources']['permit_artifact_status'])->toBe('generated_artifact_available')
        ->and($manifest['resources']['permit_verification_status'])->toBe('artifact_only')
        ->and($manifest['resources']['payment_detail_url'])->toBe('/citizen/payment-schedules/'.$manifest['resources']['payment_schedule_id'])
        ->and($manifest['resources']['payment_line_count'])->toBe(1)
        ->and($manifest['resources']['payment_collection_count'])->toBe(1)
        ->and($manifest['resources']['can_reconcile_online'])->toBeFalse()
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

test('command prepares the citizen-originated permit milestone', function () {
    Storage::fake('local');

    $citizen = configuredCitizenScenarioUser('citizen@example.test');
    $operator = configuredScenarioUser('operator@example.test');
    config()->set('lifecycle_scenarios.actors.citizen_applicant.email', $citizen->email);
    config()->set('lifecycle_scenarios.actors.primary_operator.email', $operator->email);
    config()->set('lifecycle_scenarios.actors.sample_recipient.email', $operator->email);

    $this->artisan('lifecycle:scenario', [
        'scenario' => 'citizen_new_permit_lifecycle_authority_boundary',
        '--run-id' => 'citizen-new-permit-command-test-001',
        '--phase' => 'prepare',
    ])->assertSuccessful();

    $artifactStore = new ScenarioArtifactStore('citizen_new_permit_lifecycle_authority_boundary', 'citizen-new-permit-command-test-001');
    $manifest = $artifactStore->readJson('manifest.json');

    expect($manifest['scenario']['key'])->toBe('citizen_new_permit_lifecycle_authority_boundary')
        ->and($manifest['result']['terminal'])->toBe('passed')
        ->and($manifest['resources']['application_number'])->toBeNull()
        ->and($manifest['resources']['submitted_by_id'])->toBe($citizen->id)
        ->and($manifest['resources']['ready_for_authority_review'])->toBeTrue()
        ->and($manifest['resources']['can_release'])->toBeFalse()
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
        'supporting_document' => [
            'id' => $manifest['resources']['supporting_document_id'],
            'label' => $manifest['resources']['supporting_document_label'],
            'original_name' => $manifest['resources']['supporting_document_name'],
            'download_url' => $manifest['resources']['supporting_document_download_url'],
            'panel_visible' => true,
            'download_available' => true,
            'mobile_visible' => true,
            'mobile_horizontal_overflow' => false,
        ],
        'establishment_profile' => [
            'ownership_type' => $manifest['resources']['establishment_ownership_type'],
            'occupancy' => $manifest['resources']['establishment_occupancy'],
            'business_area_square_meters' => $manifest['resources']['establishment_business_area_square_meters'],
            'male_employee_count' => $manifest['resources']['establishment_male_employee_count'],
            'female_employee_count' => $manifest['resources']['establishment_female_employee_count'],
            'started_on' => $manifest['resources']['establishment_started_on'],
            'panel_visible' => true,
            'intake_form_visible' => true,
            'intake_form_mobile_visible' => true,
            'mobile_visible' => true,
        ],
        'business_activities' => [
            'intake_add_remove_verified' => true,
            'intake_mobile_visible' => true,
            'detail_visible' => true,
            'activities' => $manifest['resources']['business_activities'],
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
        ->and($firstManifest['resources']['transfer_legal_section_references'])->toBe([
            'Section 2E.04(g)',
            'Section 2E.04(f) retirement procedures',
        ])
        ->and($firstManifest['resources']['transfer_legal_execution_status'])->toBe('recorded_non_executable')
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
        ->and($firstManifest['resources']['retirement_legal_section_references'])->toBe([
            'Section 2E.04(f) retirement provisions',
            'Section 2E.04 retirement procedures (a)-(c)',
        ])
        ->and($firstManifest['resources']['retirement_legal_execution_status'])->toBe('recorded_non_executable')
        ->and($firstManifest['resources']['assessment_total_amount_cents'])->toBe(30_000)
        ->and($application->type->value)->toBe('retirement')
        ->and($application->status)->toBe(PermitApplicationStatus::PendingPayment)
        ->and($application->metadata['retirement_policy_boundary']['status'])->toBe('policy_boundary')
        ->and($application->metadata['retirement_policy_boundary']['unresolved_policy'])->toContain('authority and evidence for the actual cessation date and legal retirement effect')
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
            'legal_evidence_visible' => true,
            'legal_section_references' => $manifest['resources']['transfer_legal_section_references'],
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
            'legal_evidence_visible' => true,
            'legal_section_references' => $manifest['resources']['retirement_legal_section_references'],
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
            'provision_visible' => true,
            'reconciliation_required_visible' => true,
            'linked_rule_visible' => true,
            'matrix_visible' => true,
            'overlap_visible' => true,
            'malformed_visible' => true,
            'ceiling_visible' => true,
            'execution_refused_visible' => true,
            'schedule_provision_codes' => $manifest['resources']['schedule_provision_codes'],
            'all_schedules_execution_refused' => true,
            'policy_boundary_register_visible' => true,
            'policy_boundary_clause_codes' => $manifest['resources']['policy_boundary_clause_codes'],
            'all_policy_boundary_clauses_execution_refused' => true,
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

test('revenue code executability safety audit compares browser evidence with exact and refused canonical state', function () {
    Storage::fake('local');

    $user = configuredScenarioUser('operator@example.test');
    $scenario = app(LifecycleScenarioRegistry::class)->get('revenue_code_executability_safety');
    $artifactStore = new ScenarioArtifactStore($scenario->key, 'revenue-execution-test-002');
    $runner = app(RevenueCodeExecutabilitySafetyScenario::class);

    $manifest = $runner->prepare($scenario, 'revenue-execution-test-002', [
        'operator' => $user,
        'recipient' => $user,
    ], $artifactStore);

    $this
        ->actingAs($user)
        ->from(route('staff.permit-applications.assessments.index'))
        ->post(route('staff.permit-applications.assessments.store', $manifest['resources']['blocked_application_id']))
        ->assertRedirectBackWithErrors(['assessment_policy']);

    $artifactStore->putJson('browser/report.json', [
        'result' => ['passed' => true],
        'revenue_code_execution' => [
            'exact_visible' => true,
            'blocked_visible' => true,
            'refusal_visible' => true,
            'not_assessed_visible' => true,
        ],
        'checks' => [],
        'artifacts' => [
            'screenshots' => [
                '01-exact-assessment' => 'browser/screenshots/01-exact-assessment.png',
                '04-blocked-assessment-refusal' => 'browser/screenshots/04-blocked-assessment-refusal.png',
            ],
        ],
    ]);

    $audited = $runner->audit($manifest, $artifactStore);

    expect($audited['result'])
        ->terminal->toBe('passed')
        ->browser->toBe('passed')
        ->audit->toBe('passed')
        ->passed->toBeTrue()
        ->and(Assessment::query()->where('permit_application_id', $manifest['resources']['blocked_application_id'])->count())->toBe(0)
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

function configuredCitizenScenarioUser(string $email): User
{
    $permissions = collect([
        UserPermission::AccessCitizen,
        UserPermission::CreateOwnPermitApplications,
        UserPermission::EditOwnPermitApplications,
        UserPermission::SubmitOwnPermitApplications,
        UserPermission::UploadOwnPermitApplicationDocuments,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationDocuments,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ])->map(fn (UserPermission $permission): int => Permission::factory()->create([
        'code' => $permission->value,
    ])->id);
    $role = Role::factory()->create([
        'code' => 'citizen',
    ]);
    $role->permissions()->sync($permissions->all());

    return User::factory()->create([
        'role_id' => $role->id,
        'email' => $email,
    ]);
}
