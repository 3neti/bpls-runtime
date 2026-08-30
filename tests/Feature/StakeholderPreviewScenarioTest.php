<?php

use App\Enums\StakeholderPreviewPersona;
use App\Enums\UserPermission;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\LifecycleScenarios\LifecycleScenarioRegistry;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\Models\AssessmentLine;
use App\Models\BillingGroup;
use App\Models\BillingGroupRecord;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\FeeRule;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use App\Models\User;
use Database\Seeders\RevenueCodeFeeCatalogSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('stakeholder preview is a local synthetic composition of existing lifecycle behavior', function () {
    $scenario = app(LifecycleScenarioRegistry::class)->get('stakeholder_preview_cycle_1');

    expect($scenario)
        ->key->toBe('stakeholder_preview_cycle_1')
        ->risk->toBe('local transactional')
        ->and($scenario->expectations['ready_for_authority_review'])->toBeTrue()
        ->and($scenario->expectations['qr_golden_payment_schedule_status'])->toBe('pending')
        ->and($scenario->expectations['qr_golden_positive_unpaid_count'])->toBe(1)
        ->and($scenario->expectations['can_release'])->toBeFalse()
        ->and($scenario->expectations['official_application_number'])->toBeNull()
        ->and($scenario->safety['external_integrations'])->toBeFalse()
        ->and($scenario->safety['irreversible_actions'])->toBeFalse();
});

test('preview preparation creates synthetic role accounts and policy-bound evidence without storing credentials', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);
    Storage::fake('local');
    $password = 'Stakeholder-Preview-Only-2026';
    configureSafeStakeholderPreview($password);
    config()->set('services.x_change', [
        'base_url' => 'https://x-change.example.test',
        'client_id' => 'synthetic-client',
        'client_secret' => 'synthetic-secret',
    ]);
    Http::preventStrayRequests();

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
            'LIFECYCLE_PREVIEW_CASHIER_EMAIL',
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
    $completedGolden = PermitApplication::query()
        ->where('metadata->business_permit_evaluation->uat_run_id', 'stakeholder-preview-test-001')
        ->where('metadata->business_permit_evaluation->case', 'completed-assessment-conformance-golden')
        ->sole();
    $completedProjection = app(BusinessPermitEvaluationResolver::class)->resolve($completedGolden->businessPermitEvaluation);
    $completedAssessment = $completedGolden->assessments()->whereNull('superseded_at')->with('decision')->sole();
    $billingGroup = BillingGroup::query()->where('metadata->scenario_run_id', 'stakeholder-preview-test-001')->sole();
    $record = BillingGroupRecord::query()->where('source_snapshot->scenario_run_id', 'stakeholder-preview-test-001')->sole();
    $qrGoldenApplications = PermitApplication::query()
        ->with(['assessments.decision', 'paymentSchedules.treasuryCollections.receipt'])
        ->get()
        ->filter(fn (PermitApplication $application): bool => data_get($application->metadata, 'stakeholder_preview_scenario.member') === 'qr_ph_golden')
        ->values();
    $qrGoldenApplication = $qrGoldenApplications->sole();
    $qrGoldenAssessment = $qrGoldenApplication->assessments->sole();
    $qrGoldenSchedule = $qrGoldenApplication->paymentSchedules->sole();
    $paidSchedule = PaymentSchedule::query()->findOrFail($manifest['resources']['payment_schedule_id']);
    $paidCollection = TreasuryCollection::query()->with('receipt')->findOrFail($manifest['resources']['collection_id']);
    $countsBeforeRepeat = [
        'applications' => PermitApplication::query()->count(),
        'schedules' => PaymentSchedule::query()->count(),
        'collections' => TreasuryCollection::query()->count(),
        'receipts' => Receipt::query()->count(),
        'fee_rules' => FeeRule::query()->count(),
        'evaluation_items' => BusinessPermitEvaluationItem::query()->count(),
        'assessment_lines' => AssessmentLine::query()->count(),
    ];

    expect($accounts)->toHaveCount(14)
        ->and($accounts)->each(fn ($user) => $user->password->not->toBe($password))
        ->and(Hash::check($password, $accounts['stakeholder.preview.citizen@example.test']->password))->toBeTrue()
        ->and($accounts['stakeholder.preview.citizen@example.test']->role?->code)->toBe('preview_citizen')
        ->and($accounts['stakeholder.preview.bplo@example.test']->role?->code)->toBe('preview_bplo')
        ->and($accounts['stakeholder.preview.assessment-officer@example.test']->role?->code)->toBe('preview_assessment_officer')
        ->and($accounts['stakeholder.preview.treasury@example.test']->role?->code)->toBe('preview_treasury')
        ->and($accounts['stakeholder.preview.municipal-treasurer@example.test']->role?->code)->toBe('preview_municipal_treasurer')
        ->and($accounts['stakeholder.preview.treasury@example.test']->can(UserPermission::ApproveAssessments->value))->toBeFalse()
        ->and($accounts['stakeholder.preview.municipal-treasurer@example.test']->can(UserPermission::ApproveAssessments->value))->toBeTrue()
        ->and($accounts['stakeholder.preview.cashier@example.test']->role?->code)->toBe('preview_cashier')
        ->and($accounts['stakeholder.preview.management@example.test']->role?->code)->toBe('preview_management')
        ->and($accounts['stakeholder.preview.bplo@example.test']->can(UserPermission::AssessPermitApplications->value))->toBeFalse()
        ->and($accounts['stakeholder.preview.bplo@example.test']->can(UserPermission::ViewUsers->value))->toBeFalse()
        ->and($accounts['stakeholder.preview.assessment-officer@example.test']->can(UserPermission::AssessPermitApplications->value))->toBeTrue()
        ->and($accounts['stakeholder.preview.assessment-officer@example.test']->can(UserPermission::ApproveAssessments->value))->toBeFalse()
        ->and($accounts['stakeholder.preview.treasury@example.test']->can(UserPermission::IssueReceipts->value))->toBeFalse()
        ->and($accounts['stakeholder.preview.treasury@example.test']->can(UserPermission::ViewUsers->value))->toBeFalse()
        ->and($accounts['stakeholder.preview.cashier@example.test']->can(UserPermission::IssueReceipts->value))->toBeTrue()
        ->and($accounts['stakeholder.preview.cashier@example.test']->can(UserPermission::ApproveAssessments->value))->toBeFalse()
        ->and($accounts['stakeholder.preview.management@example.test']->can(UserPermission::ViewReports->value))->toBeTrue()
        ->and($accounts['stakeholder.preview.management@example.test']->can(UserPermission::ViewMunicipalityConfiguration->value))->toBeTrue()
        ->and($accounts['stakeholder.preview.management@example.test']->can(UserPermission::RecordCollections->value))->toBeFalse()
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
        ->and($manifest['resources']['assessment_prepared_by_id'])->toBe($accounts['stakeholder.preview.assessment-officer@example.test']->id)
        ->and($manifest['resources']['assessment_approved_by_id'])->toBe($accounts['stakeholder.preview.municipal-treasurer@example.test']->id)
        ->and($manifest['resources']['assessment_approver_distinct_from_preparer'])->toBeTrue()
        ->and($manifest['resources']['can_reconcile_online'])->toBeTrue()
        ->and($qrGoldenApplications)->toHaveCount(1)
        ->and(data_get($qrGoldenApplication->metadata, 'stakeholder_preview_scenario.collection_policy'))->toBe('leave_unpaid_for_live_qr_ph_walkthrough')
        ->and(data_get($qrGoldenApplication->metadata, 'stakeholder_preview_scenario.generalizes_municipal_policy'))->toBeFalse()
        ->and($qrGoldenAssessment->assessed_by_id)->toBe($accounts['stakeholder.preview.assessment-officer@example.test']->id)
        ->and($qrGoldenAssessment->decision?->decided_by_id)->toBe($accounts['stakeholder.preview.municipal-treasurer@example.test']->id)
        ->and($qrGoldenAssessment->decision?->total_amount_cents)->toBe($qrGoldenAssessment->total_amount_cents)
        ->and($qrGoldenSchedule->total_amount_cents)->toBeGreaterThan(0)
        ->and($qrGoldenSchedule->paid_amount_cents)->toBe(0)
        ->and($qrGoldenSchedule->status->value)->toBe('pending')
        ->and($qrGoldenSchedule->treasuryCollections)->toHaveCount(0)
        ->and($manifest['resources']['qr_golden_payment_schedule_id'])->toBe($qrGoldenSchedule->id)
        ->and($manifest['resources']['qr_golden_pre_approval_payment_schedule_exists'])->toBeFalse()
        ->and($manifest['resources']['qr_golden_can_pay_online'])->toBeTrue()
        ->and($manifest['resources']['qr_golden_collection_count'])->toBe(0)
        ->and($manifest['resources']['qr_golden_receipt_count'])->toBe(0)
        ->and(PaymentSchedule::query()->whereKey($qrGoldenSchedule->id)->where('status', 'pending')->where('total_amount_cents', '>', 0)->where('paid_amount_cents', 0)->count())->toBe(1)
        ->and($paidSchedule->status->value)->toBe('paid')
        ->and($paidCollection->status->value)->toBe('receipted')
        ->and($paidCollection->receipt)->not->toBeNull()
        ->and($manifest['resources']['collection_received_by_id'])->toBe($accounts['stakeholder.preview.cashier@example.test']->id)
        ->and($manifest['resources']['receipt_issued_by_id'])->toBe($accounts['stakeholder.preview.cashier@example.test']->id)
        ->and($manifest['resources']['office_charge_contribution_count'])->toBe(5)
        ->and($manifest['resources']['provisional_uat_permit_status'])->toBe('released_in_preview')
        ->and(data_get($completedProjection, 'financial_working_paper.line_sections.0.subtotal_amount_cents'))->toBe(22_500)
        ->and(data_get($completedProjection, 'financial_working_paper.line_sections.1.subtotal_amount_cents'))->toBe(33_500)
        ->and(data_get($completedProjection, 'financial_working_paper.line_sections.2.subtotal_amount_cents'))->toBe(14_800)
        ->and(data_get($completedProjection, 'financial_working_paper.application_subtotal_amount_cents'))->toBe(45_000)
        ->and(data_get($completedProjection, 'financial_working_paper.grand_total_amount_cents'))->toBe(115_800)
        ->and($completedAssessment->total_amount_cents)->toBeInt()->toBe(115_800)
        ->and($completedAssessment->decision?->total_amount_cents)->toBe(115_800);

    $this->assertSame(0, Artisan::call('lifecycle:prepare-stakeholder-preview', [
        '--run-id' => 'stakeholder-preview-test-001',
        '--phase' => 'prepare',
    ]), Artisan::output());

    expect([
        'applications' => PermitApplication::query()->count(),
        'schedules' => PaymentSchedule::query()->count(),
        'collections' => TreasuryCollection::query()->count(),
        'receipts' => Receipt::query()->count(),
        'fee_rules' => FeeRule::query()->count(),
        'evaluation_items' => BusinessPermitEvaluationItem::query()->count(),
        'assessment_lines' => AssessmentLine::query()->count(),
    ])->toBe($countsBeforeRepeat);

    Http::assertNothingSent();

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
