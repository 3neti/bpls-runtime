<?php

use App\Actions\AdvanceLifecycleCleanroom;
use App\Actions\BuildLaboratoryAssessmentReconciliation;
use App\Actions\BuildLifecycleCleanroomIntake;
use App\Actions\CommissionPostPaymentOfficeCertifications;
use App\Actions\CompleteBusinessPermitEvaluationResponsibility;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreatePaymentScheduleForAssessment;
use App\Actions\IssueManualCollectionReceipt;
use App\Actions\IssueSyntheticLifecyclePermit;
use App\Actions\RecordAssessmentDecision;
use App\Actions\RecordBploRoutingDetermination;
use App\Actions\RecordBusinessPermitEvaluationCounterCheck;
use App\Actions\RecordPostPaymentOfficeCertification;
use App\Actions\ReleaseSyntheticLifecyclePermit;
use App\Actions\ResolveLifecycleCleanroomState;
use App\Actions\SubmitCitizenPermitApplication;
use App\Enums\AssessmentDecisionAction;
use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationSource;
use App\Enums\StakeholderPreviewPersona;
use App\Enums\TreasuryCollectionChannel;
use App\Enums\TreasuryCollectionMethod;
use App\Enums\TreasuryCollectionStatus;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LifecycleCleanroomRun;
use App\Models\LifecycleScenarioSpecimen;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\SignatureEvidence;
use App\Models\TreasuryCollection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->withoutVite();
    configureLifecycleLaboratoryPreview();
    Route::middleware('web')->group(base_path('routes/web.php'));
    Route::getRoutes()->refreshNameLookups();
    Route::getRoutes()->refreshActionLookups();
    Storage::fake('local');
    Artisan::call('bpls:install');
});

test('laboratory is fail closed to guests and arbitrary preview accounts', function () {
    $this->get(route('stakeholder-preview.lifecycle-laboratory.index'))->assertRedirect(route('login'));
    $this->get('/stakeholder-preview/lifecycle-laboratory/cleanrooms/1/office-reviews-assigned/2025')->assertRedirect(route('login'));

    $bplo = previewAccount(StakeholderPreviewPersona::Bplo);
    $this->actingAs($bplo)->get(route('stakeholder-preview.lifecycle-laboratory.index'))->assertNotFound();
    $this->actingAs($bplo)->post(route('stakeholder-preview.lifecycle-laboratory.run-next'))->assertNotFound();
    $this->actingAs($bplo)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.start'))->assertNotFound();
    $this->actingAs($bplo)->get('/stakeholder-preview/lifecycle-laboratory/cleanrooms/1/office-reviews-assigned/2025')->assertNotFound();
    $this->actingAs($bplo)->post('/stakeholder-preview/lifecycle-laboratory/cleanrooms/1/office-reviews-assigned/2025/confirm-routine-defaults')->assertNotFound();
    $this->actingAs($bplo)->post('/stakeholder-preview/lifecycle-laboratory/cleanrooms/1/office-reviews-assigned/2025/simulate-office-reviews')->assertNotFound();

    expect(LifecycleScenarioSpecimen::query()->count())->toBe(0)
        ->and(PermitApplication::query()->count())->toBe(0);
});

test('management sees the ordered certified chronology with bounded controls and no reset', function () {
    $this->actingAs(previewAccount(StakeholderPreviewPersona::Management))
        ->get(route('stakeholder-preview.lifecycle-laboratory.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stakeholder-preview/LifecycleLaboratory')
            ->where('laboratory.safety.production_available', false)
            ->where('laboratory.safety.reset_available', false)
            ->where('laboratory.progress.next_scenario_id', NewApplicationHappyPathDefinition::Id)
            ->has('laboratory.scenarios', 2)
            ->where('laboratory.scenarios.0.effective_date', '2025-01-15')
            ->where('laboratory.scenarios.0.events.0.label', 'Citizen created')
            ->where('laboratory.scenarios.0.events.3.label', '2025 New Business Permit lodged')
            ->missing('laboratory.scenarios.0.application_data')
            ->where('laboratory.scenarios.1.effective_date', '2026-01-15')
            ->where('laboratory.scenarios.1.events.0.label', '2026 Renewal lodged')
            ->missing('laboratory.scenarios.1.application_data')
            ->where('laboratory.scenarios.1.financial_working_paper.total_amount_cents', 122_000));
});

test('laboratory segregates interactive work from collapsed automated reference evidence', function () {
    $component = file_get_contents(resource_path('js/pages/stakeholder-preview/LifecycleLaboratory.vue'));
    $intake = file_get_contents(resource_path('js/pages/permit-applications/Create.vue'));

    expect($component)
        ->toContain('data-testid="interactive-laboratory"')
        ->toContain('Start Interactive')
        ->toContain('data-testid="interactive-application-stage"')
        ->toContain('() => props.cleanroom.active?.application_data ?? null')
        ->toContain(':document="cleanroom.active?.application_document"')
        ->toContain("(step) => step.status !== 'pending'")
        ->toContain('v-for="step in visibleCleanroomSteps"')
        ->toContain('step.completed &&')
        ->toContain('will appear here only when completed or ready to act on')
        ->not->toContain('visibleApplicationScenario')
        ->toContain('<details')
        ->toContain('data-testid="certified-regression-evidence"')
        ->not->toContain('<details open')
        ->toContain('Certified Regression Evidence')
        ->toContain('Automated certification specimen')
        ->toContain('data-classification="automated-certification-specimen"')
        ->toContain('Inspect reference as actor')
        ->toContain('Generate next certification');
    expect($intake)
        ->toContain('name="lifecycle_cleanroom_run_id"')
        ->toContain("? 'Lodge application'")
        ->toContain("? 'Lodging application...'")
        ->toContain('One action saves the canonical Application, freezes Page 1, and lodges it.')
        ->toContain("? 'Save application draft'");
});

test('run next uses the certified persisted driver for one continuous two year chronology', function () {
    $management = previewAccount(StakeholderPreviewPersona::Management);

    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.run-next'))
        ->assertRedirect(route('stakeholder-preview.lifecycle-laboratory.index'));

    expect(LifecycleScenarioSpecimen::query()->pluck('scenario_id')->all())->toBe([NewApplicationHappyPathDefinition::Id])
        ->and(BusinessOwner::query()->count())->toBe(1)
        ->and(Business::query()->count())->toBe(1)
        ->and(PermitApplication::query()->count())->toBe(1);

    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.run-next'))
        ->assertRedirect(route('stakeholder-preview.lifecycle-laboratory.index'));

    $applications = PermitApplication::query()->orderBy('application_year')->get();
    expect(LifecycleScenarioSpecimen::query()->count())->toBe(2)
        ->and(BusinessOwner::query()->count())->toBe(1)
        ->and(Business::query()->count())->toBe(1)
        ->and($applications)->toHaveCount(2)
        ->and($applications->pluck('application_year')->all())->toBe([2025, 2026])
        ->and($applications->pluck('business_id')->unique())->toHaveCount(1)
        ->and(data_get($applications->last()->metadata, 'lifecycle_scenario.predecessor_permit_application_id'))->toBe($applications->first()->id)
        ->and($applications->every(fn (PermitApplication $application): bool => $application->paymentSchedules()->sole()->total_amount_cents === 122_000))->toBeTrue();

    $this->actingAs($management)
        ->get(route('stakeholder-preview.lifecycle-laboratory.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('laboratory.progress.complete', true)
            ->where('laboratory.scenarios.0.events.12.status', 'completed')
            ->where('laboratory.scenarios.1.events.9.status', 'completed')
            ->where('laboratory.scenarios.1.financial_working_paper.payable_balance_cents', 122_000));
});

test('run to renewal milestone enforces chronology and rejects arbitrary scenario identifiers', function () {
    $management = previewAccount(StakeholderPreviewPersona::Management);

    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.run-to-milestone'), ['scenario_id' => 'arbitrary-workflow'])
        ->assertSessionHasErrors('scenario_id');
    expect(LifecycleScenarioSpecimen::query()->count())->toBe(0);

    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.run-to-milestone'), ['scenario_id' => RenewalHappyPathDefinition::Id])
        ->assertRedirect(route('stakeholder-preview.lifecycle-laboratory.index'));

    expect(LifecycleScenarioSpecimen::query()->count())->toBe(2)
        ->and(PermitApplication::query()->orderBy('application_year')->pluck('application_year')->all())->toBe([2025, 2026]);
});

test('open as actor authenticates only exact manifest owned scenario identities and lands on real product screens', function () {
    $management = previewAccount(StakeholderPreviewPersona::Management);
    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.run-to-milestone'), ['scenario_id' => RenewalHappyPathDefinition::Id]);

    $specimen = LifecycleScenarioSpecimen::query()
        ->where('scenario_id', NewApplicationHappyPathDefinition::Id)
        ->with('permitApplication.assessments')
        ->sole();
    $application = $specimen->permitApplication;
    $cases = [
        'citizen' => ['email' => 'scenario-citizen@example.test'],
        'health' => ['email' => 'scenario-01-health@example.test'],
        'treasury' => ['email' => 'scenario-01-treasury-counter-check@example.test'],
        'municipal_treasurer' => ['email' => 'scenario-01-municipal-treasurer@example.test'],
    ];

    foreach ($cases as $actor => $expectation) {
        $this->actingAs($management)
            ->post(route('stakeholder-preview.lifecycle-laboratory.enter-actor', [$specimen, $actor]))
            ->assertRedirect(route('stakeholder-preview.lifecycle-application.show', [
                'lifecycleScenarioSpecimen' => $specimen,
                'focus' => $actor,
            ]));
        $this->assertAuthenticatedAs(User::query()->where('email', $expectation['email'])->sole());
        $this->get(route('stakeholder-preview.lifecycle-application.show', $specimen))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stakeholder-preview/LifecycleApplication')
                ->where('application.schema_version', 'bpls.application-data.v1')
                ->where('application.identity.application_id', $application->id));
    }

    $manifest = $specimen->owned_resource_manifest;
    $manifest['production_liability'] = true;
    $specimen->update(['owned_resource_manifest' => $manifest]);
    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.enter-actor', [$specimen, 'health']))
        ->assertNotFound();

    expect(previewAccount(StakeholderPreviewPersona::Citizen)->business_owner_id)->toBeNull();
});

test('management starts a non destructive cleanroom and run next opens the real prefilled citizen intake form', function () {
    $management = previewAccount(StakeholderPreviewPersona::Management);
    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.start'))
        ->assertRedirect(route('stakeholder-preview.lifecycle-laboratory.index'));

    $run = LifecycleCleanroomRun::query()->sole();
    expect($run->new_application_id)->toBeNull()
        ->and(data_get($run->actor_manifest, 'semantic_classification'))->toBe('synthetic_only')
        ->and(data_get($run->owned_resource_manifest, 'permit_application_ids'))->toBe([]);

    $actorManifest = $run->actor_manifest;
    data_set($actorManifest, 'actors.permit_issuer.label', 'Permit Issuance');
    $run->update(['actor_manifest' => $actorManifest]);

    $startedState = app(ResolveLifecycleCleanroomState::class)->handle($run->fresh());
    $visibleStepKeys = collect(data_get($startedState, 'steps'))
        ->where('status', '!=', 'pending')
        ->pluck('key')
        ->all();
    expect($visibleStepKeys)->toBe(['cleanroom_started', 'citizen_intake'])
        ->and(data_get($startedState, 'progress.next_step.key'))->toBe('citizen_intake')
        ->and(collect(data_get($startedState, 'actors'))->where('is_next', true)->pluck('key')->all())->toBe(['citizen'])
        ->and(collect(data_get($startedState, 'actors'))->firstWhere('key', 'citizen')['relationship'])->toBe('next')
        ->and(collect(data_get($startedState, 'actors'))->firstWhere('key', 'intake')['relationship'])->toBe('waiting')
        ->and(collect(data_get($startedState, 'actors'))->firstWhere('key', 'permit_issuer')['label'])->toBe('Mayor Ramses Troy D. Olegario')
        ->and(collect(data_get($startedState, 'steps'))->firstWhere('key', 'assessor_responsibilities')['status'])->toBe('pending');

    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run), [
            'expected_step_key' => 'bplo_routing',
            'expected_actor_key' => 'intake',
        ])
        ->assertRedirect(route('stakeholder-preview.lifecycle-laboratory.index'))
        ->assertSessionHasErrors('cleanroom');
    expect($run->fresh()->new_application_id)->toBeNull();

    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.milestone', $run), ['step_key' => 'arbitrary-workflow'])
        ->assertSessionHasErrors('step_key');

    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run))
        ->assertRedirect(route('citizen.permit-applications.create'));
    $citizen = User::query()->findOrFail(data_get($run->actor_manifest, 'actors.citizen.user_id'));
    $this->assertAuthenticatedAs($citizen);
    $this->get(route('citizen.permit-applications.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Create')
            ->where('currentApplicationYear', 2025)
            ->where('cleanroomIntake.run_id', $run->public_id)
            ->where('cleanroomIntake.lines.0.declared_gross_sales_pesos', '1200000')
            ->has('cleanroomIntake.lines', 2));
});

test('interactive Nelson ceremony drafts before documents and signed lodging', function () {
    $management = previewAccount(StakeholderPreviewPersona::Management);
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.start'), [
        'ceremony' => LifecycleCleanroomRun::CeremonyNelsonReconciliationV1,
    ])->assertRedirect(route('stakeholder-preview.lifecycle-laboratory.index'));

    $run = LifecycleCleanroomRun::query()->sole();
    expect($run->isNelsonReconciliationV1())->toBeTrue();

    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run))
        ->assertRedirect(route('citizen.permit-applications.create'));
    $citizen = User::query()->findOrFail(data_get($run->actor_manifest, 'actors.citizen.user_id'));
    $this->assertAuthenticatedAs($citizen);

    $this->get(route('citizen.permit-applications.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Create')
            ->where('cleanroomIntake.ceremony', LifecycleCleanroomRun::CeremonyNelsonReconciliationV1)
            ->where('cleanroomIntake.business_activity_description', 'General merchandise store selling household goods and liquor, with a small coffee shop.')
            ->where('cleanroomIntake.business_barangay_psgc_code', '0908305023')
            ->missing('cleanroomIntake.lines')
            ->has('barangays', 28)
            ->has('labIntakeFixtures', 1)
            ->has('applicationDocumentTypes', 5)
            ->where('labIntakeFixtures.0.classification', 'synthetic_uat_only'));

    $intake = app(BuildLifecycleCleanroomIntake::class)->handle($run);
    $this->post(route('citizen.permit-applications.store'), [
        ...$intake,
        'type' => 'new',
        'lifecycle_cleanroom_run_id' => $run->public_id,
        'undertaking_accepted' => '1',
        'application_documents' => [[
            'document_type' => 'dti_registration',
            'file' => UploadedFile::fake()->create('dti.pdf', 24, 'application/pdf'),
        ]],
    ])->assertSessionHasNoErrors()
        ->assertRedirect();

    $application = PermitApplication::query()->findOrFail($run->fresh()->new_application_id);
    expect($application->status->value)->toBe('draft')
        ->and($application->submitted_at)->toBeNull()
        ->and($application->lines)->toBeEmpty()
        ->and($application->business_activity_description)->toBe($intake['business_activity_description'])
        ->and($application->business->barangay_psgc_code)->toBe('0908305023')
        ->and($application->documents()->whereNull('removed_at')->sole()->label)->toBe('DTI Registration');

    $this->get(route('citizen.permit-applications.edit', $application))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Create')
            ->where('draft.id', $application->id)
            ->where('draft.commissioned_path', true)
            ->where('canSubmit', true));

    $this->get(route('citizen.permit-applications.show', $application))
        ->assertRedirect(route('citizen.permit-applications.edit', $application));

    $this->put(route('citizen.permit-applications.update', $application), [
        ...$intake,
        'type' => 'new',
        'draft_version' => $application->fresh()->updated_at->toIso8601String(),
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('citizen.permit-applications.edit', $application));

    $this->post(route('citizen.permit-applications.submit', $application), [
        'undertaking_accepted' => '1',
        'signature_facsimile' => UploadedFile::fake()->image('applicant-signature.png'),
    ])->assertRedirect(route('stakeholder-preview.lifecycle-cleanroom-application.show', $run));

    $application->refresh();
    $declaration = $application->declaration()->sole();
    expect($application->submitted_at)->not->toBeNull()
        ->and(data_get($declaration->snapshot, 'applicant_business_activity_description'))->toBe($intake['business_activity_description'])
        ->and(data_get($declaration->snapshot, 'lines_of_business'))->toBe([])
        ->and(data_get($declaration->snapshot, 'applicant_documents_manifest.documents'))->toHaveCount(1)
        ->and(SignatureEvidence::query()->where('signable_type', $declaration->getMorphClass())->where('signable_id', $declaration->id)->where('purpose', 'applicant_lodging')->exists())->toBeTrue();
});

test('cleanroom citizen intake accepts an active municipal catalog activity offered by the form', function () {
    $management = previewAccount(StakeholderPreviewPersona::Management);
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.start'));
    $run = LifecycleCleanroomRun::query()->sole();
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run));
    $intake = app(BuildLifecycleCleanroomIntake::class)->handle($run);

    expect($intake['applicant_printed_name'])->toBe($intake['owner_name']);
    $municipalRetail = LineOfBusiness::query()
        ->where('code', 'MRC-2A-02-B-WHOLESALE-RETAIL')
        ->sole();
    $intake['lines'] = [[
        ...$intake['lines'][0],
        'line_of_business_id' => $municipalRetail->id,
    ]];

    $this->post(route('citizen.permit-applications.store'), [
        ...$intake,
        'type' => 'new',
        'lifecycle_cleanroom_run_id' => $run->public_id,
        'undertaking_accepted' => '1',
    ])->assertRedirect(route('stakeholder-preview.lifecycle-cleanroom-application.show', $run));

    $run->refresh();
    $application = PermitApplication::query()->findOrFail($run->new_application_id);

    expect($application->lines()->sole()->line_of_business_id)->toBe($municipalRetail->id)
        ->and($application->submitted_at)->not->toBeNull();
    $actor = User::query()->findOrFail(data_get($run->actor_manifest, 'actors.intake.user_id'));
    app(RecordBploRoutingDetermination::class)->handle(
        $application->fresh(),
        $actor,
        'Explicit routing proves the incompatible declaration fails closed at responsibility initialization.',
        collect([
            'engineering' => 'Engineering',
            'health' => 'Health',
            'assessor' => 'Municipal Assessor',
            'menro' => 'MENRO',
        ])->map(fn (string $label, string $office): array => [
            'office_code' => $office,
            'office_label' => $label,
            'permit_application_line_id' => $application->lines()->sole()->id,
            'situational_reason' => 'Explicit cleanroom routing test.',
            'required_work' => 'Review the lodged declaration.',
        ])->all(),
    );

    $state = app(ResolveLifecycleCleanroomState::class)->handle($run->fresh());
    expect(data_get($state, 'progress.blocked'))->toBeTrue()
        ->and(data_get($state, 'progress.blocker'))->toContain('no complete certified or source-backed laboratory assessment profile');

    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run))
        ->assertRedirect(route('stakeholder-preview.lifecycle-laboratory.index'))
        ->assertSessionHasErrors('cleanroom');

    expect(fn () => app(AdvanceLifecycleCleanroom::class)->handle($run->fresh()))
        ->toThrow(LogicException::class, 'no complete certified or source-backed laboratory assessment profile');
    expect($application->fresh()->businessPermitEvaluation)->toBeNull();
});

test('source backed registry specimen advances through the complete synthetic permit lifecycle', function () {
    $management = previewAccount(StakeholderPreviewPersona::Management);
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.start'));
    $run = LifecycleCleanroomRun::query()->sole();
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run));
    $intake = app(BuildLifecycleCleanroomIntake::class)->handle($run);
    $municipalRetail = LineOfBusiness::query()->where('code', 'MRC-2A-02-B-WHOLESALE-RETAIL')->sole();
    $intake['lines'] = [[...$intake['lines'][0], 'line_of_business_id' => $municipalRetail->id]];
    $this->post(route('citizen.permit-applications.store'), [...$intake, 'type' => 'new'])->assertSessionHasNoErrors();
    $run->refresh();
    $application = PermitApplication::query()->findOrFail($run->new_application_id);
    $historicalAssessment = sourceBackedHistoricalAssessment();
    $metadata = $application->metadata;
    $metadata['laboratory_assessment_reconciliation'] = [
        'schema_version' => 'bpls.laboratory-assessment-reconciliation.v1',
        'fixture_id' => 'test-source-backed-registry-specimen',
        'source_kind' => 'immutable_production_backup',
        'source_reference' => 'TEST-SOURCE-REFERENCE',
        'source_business_category' => 'REC- SARISARI STORE',
        'semantic_classification' => 'observational_legacy_financial_evidence',
        'historical_assessment' => $historicalAssessment,
        'component_identity_mapping' => 'not_established',
        'operational_authority' => false,
        'production_liability' => false,
    ];
    $application->forceFill(['metadata' => $metadata])->save();
    $this->post(route('citizen.permit-applications.submit', $application), [
        'undertaking_accepted' => '1',
    ])->assertSessionHasNoErrors();

    $this->actingAs($management)
        ->get(route('stakeholder-preview.lifecycle-laboratory.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('cleanroom.active.application_data.identity.application_id', $application->id)
            ->where('cleanroom.active.application_data.declaration.page', 'page_1')
            ->where('cleanroom.active.application_document.declaration.state', 'frozen')
            ->where('cleanroom.active.application_document.page_2_assessment.status', 'awaiting_bplo_routing')
            ->where('cleanroom.active.application_data.actor_context.actor_label', 'Preview Municipal Management')
            ->where('cleanroom.active.application_data.actor_context.work_notes.0.id', 'applicant_submission')
            ->where('cleanroom.active.application_data.actor_context.work_notes.0.state', 'completed')
            ->where('cleanroom.active.application_data.actor_context.work_notes.0.actionable', false)
            ->where('cleanroom.active.application_data.actor_context.work_notes.1.id', 'bplo_routing')
            ->where('cleanroom.active.application_data.actor_context.work_notes.1.state', 'ready')
            ->where('cleanroom.active.application_data.actor_context.work_notes.1.actionable', false)
            ->where('cleanroom.active.application_data.actor_context.work_notes.2.state', 'anticipated'));

    $work = collect([
        'engineering' => 'Engineering',
        'health' => 'Health',
        'assessor' => 'Municipal Assessor',
        'menro' => 'MENRO',
    ])->map(fn (string $label, string $office): array => [
        'office_code' => $office,
        'office_label' => $label,
        'permit_application_line_id' => $application->lines()->sole()->id,
        'situational_reason' => 'Explicit source-backed cleanroom routing test.',
        'required_work' => 'Review the mapped source-backed responsibilities.',
    ])->values()->all();
    app(RecordBploRoutingDetermination::class)->handle(
        $application->fresh(),
        User::query()->findOrFail(data_get($run->actor_manifest, 'actors.intake.user_id')),
        'Source-backed registry specimen routing.',
        $work,
    );

    $state = app(ResolveLifecycleCleanroomState::class)->handle($run->fresh());
    expect(data_get($state, 'progress.blocked'))->toBeFalse()
        ->and(data_get($state, 'progress.profile_kind'))->toBe('registry_source_replay')
        ->and(data_get($state, 'progress.total_steps'))->toBe(23)
        ->and(data_get($state, 'progress.next_step.key'))->toBe('evaluation_initialized');

    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run))
        ->assertRedirect(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.office-reviews-assigned', [$run, 2025]));
    $evaluation = $application->fresh()->businessPermitEvaluation;
    $responsibilities = $evaluation->items()
        ->where('metadata->lifecycle_cleanroom_responsibility', true)
        ->with('revisions')
        ->get();
    $routineDefaults = $responsibilities->filter(
        fn ($item): bool => data_get($item->metadata, 'inspection_required', false) === false,
    );
    expect($responsibilities)->toHaveCount(8)
        ->and($responsibilities->sum(fn ($item): int => (int) data_get($item->revisions->first()?->value, 'amount_cents')))->toBe(482_500);

    $this->get(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.office-reviews-assigned', [$run, 2025]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stakeholder-preview/OfficeReviewsAssigned')
            ->where('handoff.application.id', $application->id)
            ->where('handoff.application.year', 2025)
            ->where('handoff.summary.office_count', 4)
            ->where('handoff.summary.responsibility_count', 8)
            ->where('handoff.summary.resolved_count', 0)
            ->where('handoff.summary.assessment_created', false)
            ->where('handoff.summary.payment_order_count', 0)
            ->where('handoff.summary.routine_default_count', $routineDefaults->count())
            ->where('handoff.summary.inspection_simulation_count', $responsibilities->count() - $routineDefaults->count())
            ->where('handoff.summary.manual_review_count', 0)
            ->has('handoff.offices', 4)
            ->where('handoff.offices.0.status', 'Not started')
            ->where('handoff.offices.0.is_next', true)
            ->where('handoff.offices.0.responsibility_count', 2)
            ->where('handoff.offices.0.action_url', route('stakeholder-preview.lifecycle-laboratory.cleanrooms.enter-actor', [$run, 'assessor'], false))
            ->where('handoff.audit.production_liability', false));

    $this->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.office-reviews-assigned.confirm-routine-defaults', [$run, 2025]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $evaluation->refresh();
    expect($evaluation->items()
        ->where('metadata->lifecycle_cleanroom_responsibility', true)
        ->whereHas('revisions', fn ($query) => $query->where('action', 'confirmation'))
        ->count())->toBe($routineDefaults->count())
        ->and($application->paperlessPaymentOrders()->whereNull('superseded_at')->count())->toBe($routineDefaults->count());
    $this->get(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.office-reviews-assigned', [$run, 2025]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('handoff.offices.1.label', 'Engineering')
            ->where('handoff.offices.1.responsibilities.0.default_amount_cents', 200_000)
            ->where('handoff.offices.1.responsibilities.0.status', 'Inspection pending'));

    $this->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.office-reviews-assigned.simulate-office-reviews', [$run, 2025]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $evaluation->refresh();
    expect($evaluation->items()
        ->where('metadata->lifecycle_cleanroom_responsibility', true)
        ->whereHas('revisions', fn ($query) => $query->where('action', 'confirmation'))
        ->count())->toBe($responsibilities->count())
        ->and($application->paperlessPaymentOrders()->whereNull('superseded_at')->count())->toBe($responsibilities->count())
        ->and($evaluation->items()
            ->where('metadata->lifecycle_cleanroom_responsibility', true)
            ->whereHas('revisions', fn ($query) => $query->where('reason', 'like', 'Synthetic Lifecycle Laboratory inspection simulation%'))
            ->count())->toBe($responsibilities->count() - $routineDefaults->count());

    $state = app(ResolveLifecycleCleanroomState::class)->handle($run->fresh());
    $nextActor = data_get($state, 'progress.next_step.actor');
    expect($nextActor)->toBeString()
        ->and(data_get($state, 'progress.next_step.key'))->toBe('assessment_prepared')
        ->and(collect(data_get($state, 'actors'))->firstWhere('key', $nextActor)['task'])->toMatchArray([
            'key' => 'assessment_prepared',
            'tab' => 'processing',
            'focus' => 'assessment_prepared',
        ]);
    $expectedActor = User::query()->findOrFail(data_get($run->actor_manifest, 'actors.'.$nextActor.'.user_id'));
    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run))
        ->assertRedirect(route('staff.permit-applications.evaluation.show', $application));
    $this->assertAuthenticatedAs($expectedActor);

    foreach ($responsibilities as $responsibility) {
        if ($responsibility->revisions()->where('action', 'confirmation')->exists()) {
            continue;
        }

        $evaluation->refresh();
        $version = $evaluation->currentVersion;
        $proposal = $responsibility->revisions()->oldest('id')->firstOrFail();
        $actor = User::query()->findOrFail(data_get($run->actor_manifest, 'actors.'.$responsibility->responsible_party.'.user_id'));
        app(CompleteBusinessPermitEvaluationResponsibility::class)->handle(
            $responsibility,
            $actor,
            BusinessPermitEvaluationApplicability::Applicable,
            [
                'amount_cents' => data_get($proposal->value, 'amount_cents'),
                'inspection' => [
                    'required' => data_get($responsibility->metadata, 'inspection_required'),
                    'mode' => data_get($responsibility->metadata, 'inspection_required') ? 'physical' : 'document_review',
                    'completed' => true,
                    'findings' => 'Source-backed cleanroom responsibility confirmed.',
                ],
            ],
            BusinessPermitEvaluationSource::ProvisionalUat,
            'Source-backed cleanroom responsibility confirmed.',
            $version->sequence,
            $version->fingerprint,
            $run->public_id.':'.$responsibility->key,
        );
    }

    $assessmentOfficer = User::query()->findOrFail(data_get($run->actor_manifest, 'actors.assessment_officer.user_id'));
    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application->fresh(), $assessmentOfficer);
    expect($assessment->total_amount_cents)->toBe(517_500);
    $reconciliation = app(BuildLaboratoryAssessmentReconciliation::class)->handle($assessment);
    expect($reconciliation)
        ->status->toBe('difference')
        ->and($reconciliation['source']['total_amount_cents'])->toBe(482_500)
        ->and($reconciliation['computed']['total_amount_cents'])->toBe(517_500)
        ->and($reconciliation['comparison']['delta_amount_cents'])->toBe(35_000);

    app(RecordBusinessPermitEvaluationCounterCheck::class)->handle(
        $assessment,
        User::query()->findOrFail(data_get($run->actor_manifest, 'actors.treasury.user_id')),
    );
    app(RecordAssessmentDecision::class)->handle(
        $assessment,
        User::query()->findOrFail(data_get($run->actor_manifest, 'actors.municipal_treasurer.user_id')),
        AssessmentDecisionAction::Approved,
    );
    $schedule = app(CreatePaymentScheduleForAssessment::class)->handle($assessment, $assessmentOfficer);

    $state = app(ResolveLifecycleCleanroomState::class)->handle($run->fresh());
    expect(data_get($state, 'progress.complete'))->toBeFalse()
        ->and(data_get($state, 'progress.completed_steps'))->toBe(12)
        ->and(data_get($state, 'progress.next_step.key'))->toBe('qr_payment_collected')
        ->and($run->fresh()->renewal_application_id)->toBeNull();

    $collection = TreasuryCollection::factory()->for($application)->for($assessment)->for($schedule)->create([
        'status' => TreasuryCollectionStatus::PendingReceipt,
        'channel' => TreasuryCollectionChannel::Online,
        'method' => TreasuryCollectionMethod::QrPh,
        'amount_cents' => 517_500,
    ]);
    $schedule->forceFill(['status' => 'paid', 'paid_amount_cents' => 517_500])->save();

    $state = app(ResolveLifecycleCleanroomState::class)->handle($run->fresh());
    expect(data_get($state, 'progress.completed_steps'))->toBe(13)
        ->and(data_get($state, 'progress.next_step.key'))->toBe('official_receipt_issued')
        ->and(collect(data_get($state, 'actors'))->where('is_next', true)->pluck('key')->all())->toBe(['cashier'])
        ->and(collect(data_get($state, 'actors'))->firstWhere('key', 'cashier')['relationship'])->toBe('next');

    $cashier = User::query()->findOrFail(data_get($run->actor_manifest, 'actors.cashier.user_id'));
    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run), [
            'expected_step_key' => 'official_receipt_issued',
            'expected_actor_key' => 'cashier',
        ])
        ->assertRedirect(route('staff.payment-schedules.show', $schedule));
    $this->assertAuthenticatedAs($cashier);

    app(IssueManualCollectionReceipt::class)->handle($collection, [
        'receipt_number' => '7000001',
        'numbering_authority' => 'manual_synthetic_cleanroom',
    ], $cashier);

    $state = app(ResolveLifecycleCleanroomState::class)->handle($run->fresh());
    expect(data_get($state, 'progress.next_step.key'))->toBe('post_payment_certifications_commissioned')
        ->and(collect(data_get($state, 'actors'))->where('is_next', true)->pluck('key')->all())->toBe(['intake']);

    app(AdvanceLifecycleCleanroom::class)->handle($run->fresh());
    $state = app(ResolveLifecycleCleanroomState::class)->handle($run->fresh());
    expect(data_get($state, 'progress.next_step.key'))->toBe('assessor_post_payment_certified')
        ->and(collect(data_get($state, 'actors'))->where('is_next', true)->pluck('key')->all())->toBe(['assessor']);
    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run), [
            'expected_step_key' => 'assessor_post_payment_certified',
            'expected_actor_key' => 'assessor',
        ])
        ->assertRedirect(route('stakeholder-preview.lifecycle-cleanroom-application.show', $run).'?tab=processing');

    $assessorCertification = $application->fresh()->postPaymentOfficeCertifications->firstWhere('office_code', 'assessor');
    $focusedCertificationUrl = route('stakeholder-preview.lifecycle-cleanroom-application.show', $run).'?tab=processing&certified_office=assessor';
    $this->post(route('stakeholder-preview.lifecycle-cleanroom.post-payment-certifications.store', [$run, $assessorCertification]))
        ->assertRedirect($focusedCertificationUrl)
        ->assertSessionHasNoErrors();
    $this->get($focusedCertificationUrl)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('initialTab', 'processing')
            ->where('recentCertificationOffice', 'assessor')
            ->where('application.attachments.5.state', '1_of_4_certified')
            ->where('application.attachments.5.available', true));

    foreach ($application->fresh()->postPaymentOfficeCertifications as $certification) {
        if ($certification->status === 'completed') {
            continue;
        }

        app(RecordPostPaymentOfficeCertification::class)->handle(
            $certification,
            User::query()->findOrFail(data_get($run->actor_manifest, 'actors.'.$certification->office_code.'.user_id')),
        );
    }

    $state = app(ResolveLifecycleCleanroomState::class)->handle($run->fresh());
    expect(data_get($state, 'progress.next_step.key'))->toBe('permit_issued')
        ->and(collect(data_get($state, 'actors'))->where('is_next', true)->pluck('key')->all())->toBe(['permit_issuer']);
    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run), [
            'expected_step_key' => 'permit_issued',
            'expected_actor_key' => 'permit_issuer',
        ])
        ->assertRedirect(route('stakeholder-preview.lifecycle-cleanroom-application.show', $run).'?tab=permit');

    app(IssueSyntheticLifecyclePermit::class)->handle(
        $application->fresh(),
        User::query()->findOrFail(data_get($run->actor_manifest, 'actors.permit_issuer.user_id')),
    );
    app(ReleaseSyntheticLifecyclePermit::class)->handle(
        $application->fresh(),
        User::query()->findOrFail(data_get($run->actor_manifest, 'actors.releasing_officer.user_id')),
    );

    $state = app(ResolveLifecycleCleanroomState::class)->handle($run->fresh());
    expect(data_get($state, 'progress.complete'))->toBeTrue()
        ->and(data_get($state, 'progress.completed_steps'))->toBe(23)
        ->and($run->fresh()->renewal_application_id)->toBeNull();
});

test('cleanroom citizen form lodges through canonical draft and submit actions in one idempotent request', function () {
    $management = previewAccount(StakeholderPreviewPersona::Management);
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.start'));
    $run = LifecycleCleanroomRun::query()->sole();
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run));
    $intake = app(BuildLifecycleCleanroomIntake::class)->handle($run);

    $lodging = [
        ...$intake,
        'type' => 'new',
        'owner_email' => null,
        'owner_phone' => null,
        'lifecycle_cleanroom_run_id' => $run->public_id,
        'undertaking_accepted' => '1',
        'application_documents' => [[
            'document_type' => 'sec_registration',
            'file' => UploadedFile::fake()->create('sec.pdf', 18, 'application/pdf'),
        ]],
    ];
    $this->post(route('citizen.permit-applications.store'), $lodging)
        ->assertRedirect(route('stakeholder-preview.lifecycle-cleanroom-application.show', $run))
        ->assertSessionHasNoErrors();
    $run->refresh();
    $application = PermitApplication::query()->findOrFail($run->new_application_id);
    expect($application->status->value)->toBe('assessment')
        ->and($application->submitted_at)->not->toBeNull()
        ->and($application->business->owner->name)->toStartWith('Cleanroom Synthetic Owner')
        ->and(data_get($application->metadata, 'applicant_declaration_draft.undertaking.applicant_printed_name'))->toBe($application->business->owner->name)
        ->and(data_get($application->metadata, 'lifecycle_cleanroom.run_id'))->toBe($run->public_id)
        ->and(data_get($application->declaration()->sole()->snapshot, 'undertaking.applicant_printed_name'))->toBe($application->business->owner->name)
        ->and(data_get($application->declaration()->sole()->snapshot, 'applicant_documents_manifest.documents'))->toHaveCount(1)
        ->and($application->documents()->whereNull('removed_at')->sole()->label)->toBe('SEC Registration')
        ->and(data_get($application->metadata, 'status_history'))->toHaveCount(1)
        ->and($run->owned_resource_manifest['permit_application_declaration_ids'])->toBe([$application->declaration()->sole()->id]);
    $lodgedState = app(ResolveLifecycleCleanroomState::class)->handle($run);
    expect(data_get($lodgedState, 'progress.completed_steps'))->toBe(2)
        ->and(data_get($lodgedState, 'progress.next_step.key'))->toBe('bplo_routing')
        ->and(collect(data_get($lodgedState, 'actors'))->where('is_next', true)->pluck('key')->all())->toBe(['intake'])
        ->and(collect(data_get($lodgedState, 'actors'))->firstWhere('key', 'intake')['task'])->toMatchArray([
            'key' => 'bplo_routing',
            'label' => 'BPLO routing',
            'tab' => 'processing',
            'focus' => 'bplo-routing',
        ])
        ->and(collect(data_get($lodgedState, 'steps'))->pluck('key'))->not->toContain('application_submitted')
        ->and(collect(data_get($lodgedState, 'steps'))->firstWhere('key', 'citizen_intake')['label'])->toBe('Application Form completed and lodged');

    $this->post(route('citizen.permit-applications.store'), $lodging)
        ->assertRedirect(route('stakeholder-preview.lifecycle-cleanroom-application.show', $run))
        ->assertSessionHasNoErrors();
    expect(PermitApplication::query()->count())->toBe(1)
        ->and($application->declaration()->count())->toBe(1)
        ->and(data_get($application->fresh()->metadata, 'status_history'))->toHaveCount(1)
        ->and($application->submittedBy->notifications()->where('data->kind', 'permit_application_received')->count())->toBe(1);
    expect(session()->has('lifecycle_cleanroom_intake_run_id'))->toBeFalse();

    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run), [
            'expected_step_key' => 'bplo_routing',
            'expected_actor_key' => 'intake',
        ])
        ->assertRedirect(route('stakeholder-preview.lifecycle-cleanroom-application.show', $run).'?tab=processing&task=bplo-routing');
    $intakeActor = User::query()->findOrFail(data_get($run->actor_manifest, 'actors.intake.user_id'));
    $this->assertAuthenticatedAs($intakeActor);
    $focusedUrl = route('stakeholder-preview.lifecycle-cleanroom-application.show', $run).'?tab=processing&task=bplo-routing';
    $this->get($focusedUrl)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stakeholder-preview/LifecycleApplication')
            ->where('initialTab', 'processing')
            ->where('focus', 'bplo-routing')
            ->where('routingTask.schema_version', 'bpls.bplo-routing-task.v1')
            ->where('routingTask.can_determine', true)
            ->where('routingTask.manual_confirmation_required', true)
            ->where('routingTask.routing', null));
    $citizenActor = User::query()->findOrFail(data_get($run->actor_manifest, 'actors.citizen.user_id'));
    $this->actingAs($citizenActor)->get($focusedUrl)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('initialTab', 'processing')
            ->where('focus', '')
            ->where('routingTask', null));
    recordCleanroomRouting($run, $application);
    $this->actingAs($intakeActor)->get($focusedUrl)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('routingTask.can_determine', false)
            ->where('routingTask.routing.works.0.office_code', 'assessor')
            ->where('document.routing.status', 'determined')
            ->where('document.routing.determined_by', $intakeActor->name)
            ->has('document.routing.works', 5));
    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run))
        ->assertRedirect(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.office-reviews-assigned', [$run, 2025]));
    expect($application->fresh()->businessPermitEvaluation->items()->whereIn('key', collect(app(NewApplicationHappyPathDefinition::class)->responsibilities())->pluck('key'))->count())->toBe(6)
        ->and($run->fresh()->owned_resource_manifest['permit_application_declaration_ids'])->toBe([$application->declaration()->sole()->id])
        ->and(PermitApplication::query()->count())->toBe(1);

    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.close', $run));
    expect($run->fresh()->status)->toBe('closed')
        ->and(PermitApplication::query()->whereKey($application)->exists())->toBeTrue();
});

test('failed one action lodging rolls back the draft and retains the cleanroom intake', function () {
    $management = previewAccount(StakeholderPreviewPersona::Management);
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.start'));
    $run = LifecycleCleanroomRun::query()->sole();
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run));
    $intake = app(BuildLifecycleCleanroomIntake::class)->handle($run);

    mock(SubmitCitizenPermitApplication::class)
        ->shouldReceive('handle')
        ->once()
        ->andThrow(new DomainException('Synthetic submission failure.'));

    $this->post(route('citizen.permit-applications.store'), [
        ...$intake,
        'type' => 'new',
        'lifecycle_cleanroom_run_id' => $run->public_id,
        'undertaking_accepted' => '1',
        'application_documents' => [[
            'document_type' => 'dti_registration',
            'file' => UploadedFile::fake()->create('rollback-dti.pdf', 18, 'application/pdf'),
        ]],
    ])->assertSessionHasErrors('submission');

    expect(PermitApplication::query()->count())->toBe(0)
        ->and(BusinessOwner::query()->count())->toBe(0)
        ->and(Business::query()->count())->toBe(0)
        ->and(PermitApplicationDocument::query()->count())->toBe(0)
        ->and($run->fresh()->new_application_id)->toBeNull()
        ->and(session('lifecycle_cleanroom_intake_run_id'))->toBe($run->id);
    expect(collect(Storage::disk('local')->allFiles())
        ->filter(fn (string $path): bool => str_contains($path, 'rollback-dti.pdf'))
        ->all())->toBe([]);
});

test('cleanroom remains compatible with the canonical two year action semantics through both payables', function () {
    $management = previewAccount(StakeholderPreviewPersona::Management);
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.start'));
    $run = LifecycleCleanroomRun::query()->sole();
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run));
    $intake = app(BuildLifecycleCleanroomIntake::class)->handle($run);
    $this->post(route('citizen.permit-applications.store'), [...$intake, 'type' => 'new'])->assertSessionHasNoErrors();
    $run->refresh();
    $this->post(route('citizen.permit-applications.submit', $run->new_application_id), [
        'undertaking_accepted' => '1',
    ])->assertSessionHasNoErrors();

    foreach (['new_application_id', 'renewal_application_id'] as $applicationKey) {
        if ($applicationKey === 'renewal_application_id') {
            app(AdvanceLifecycleCleanroom::class)->handle($run->fresh());
        }
        $run->refresh();
        $application = PermitApplication::query()->findOrFail($run->{$applicationKey});
        recordCleanroomRouting($run, $application);
        app(AdvanceLifecycleCleanroom::class)->handle($run->fresh());
        $run->refresh();
        $application->refresh();
        $evaluation = $application->businessPermitEvaluation;

        foreach (app(NewApplicationHappyPathDefinition::class)->responsibilities() as $responsibility) {
            $evaluation->refresh();
            $version = $evaluation->currentVersion;
            $actor = User::query()->findOrFail(data_get($run->actor_manifest, 'actors.'.$responsibility['department'].'.user_id'));
            app(CompleteBusinessPermitEvaluationResponsibility::class)->handle(
                $evaluation->items()->where('key', $responsibility['key'])->sole(),
                $actor,
                BusinessPermitEvaluationApplicability::Applicable,
                ['amount_cents' => $responsibility['amount_cents'], 'inspection' => ['required' => $responsibility['inspection_required'], 'mode' => $responsibility['inspection_required'] ? 'physical' : 'document_review', 'completed' => true, 'findings' => $responsibility['reason']]],
                BusinessPermitEvaluationSource::ProvisionalUat,
                $responsibility['reason'],
                $version->sequence,
                $version->fingerprint,
                $run->public_id.':'.$application->application_year.':'.$responsibility['key'],
            );
        }

        $assessmentOfficer = User::query()->findOrFail(data_get($run->actor_manifest, 'actors.assessment_officer.user_id'));
        $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application, $assessmentOfficer);
        expect($assessment->total_amount_cents)->toBe(122_000);
        app(RecordBusinessPermitEvaluationCounterCheck::class)->handle(
            $assessment,
            User::query()->findOrFail(data_get($run->actor_manifest, 'actors.treasury.user_id')),
        );
        app(RecordAssessmentDecision::class)->handle(
            $assessment,
            User::query()->findOrFail(data_get($run->actor_manifest, 'actors.municipal_treasurer.user_id')),
            AssessmentDecisionAction::Approved,
        );
        $schedule = app(CreatePaymentScheduleForAssessment::class)->handle($assessment, $assessmentOfficer);
        expect($schedule->total_amount_cents)->toBe(122_000);

        if ($applicationKey === 'new_application_id') {
            $collection = TreasuryCollection::factory()->for($application)->for($assessment)->for($schedule)->create([
                'status' => TreasuryCollectionStatus::PendingReceipt,
                'channel' => TreasuryCollectionChannel::Online,
                'method' => TreasuryCollectionMethod::QrPh,
                'amount_cents' => 122_000,
            ]);
            $schedule->forceFill(['status' => 'paid', 'paid_amount_cents' => 122_000])->save();
            app(IssueManualCollectionReceipt::class)->handle($collection, [
                'receipt_number' => '7000001',
                'numbering_authority' => 'manual_synthetic_cleanroom',
            ], User::query()->findOrFail(data_get($run->actor_manifest, 'actors.cashier.user_id')));
            foreach (app(CommissionPostPaymentOfficeCertifications::class)->handle($application->fresh()) as $certification) {
                app(RecordPostPaymentOfficeCertification::class)->handle(
                    $certification,
                    User::query()->findOrFail(data_get($run->actor_manifest, 'actors.'.$certification->office_code.'.user_id')),
                );
            }
            app(IssueSyntheticLifecyclePermit::class)->handle(
                $application->fresh(),
                User::query()->findOrFail(data_get($run->actor_manifest, 'actors.permit_issuer.user_id')),
            );
            app(ReleaseSyntheticLifecyclePermit::class)->handle(
                $application->fresh(),
                User::query()->findOrFail(data_get($run->actor_manifest, 'actors.releasing_officer.user_id')),
            );
        }
    }

    $state = app(ResolveLifecycleCleanroomState::class)->handle($run->fresh());
    expect(data_get($state, 'progress.complete'))->toBeTrue()
        ->and(data_get($state, 'progress.completed_steps'))->toBe(34)
        ->and(PermitApplication::query()->whereIn('id', [$run->new_application_id, $run->renewal_application_id])->pluck('application_year')->sort()->values()->all())->toBe([2025, 2026])
        ->and(PermitApplication::query()->whereIn('id', [$run->new_application_id, $run->renewal_application_id])->pluck('business_id')->unique())->toHaveCount(1);
});

function configureLifecycleLaboratoryPreview(): void
{
    config()->set([
        'stakeholder_preview.mode' => true,
        'stakeholder_preview.profile' => 'stakeholder_preview_weekend_v1',
        'stakeholder_preview.data_classification' => 'synthetic_only',
        'stakeholder_preview.pii_mode' => 'synthetic_only',
        'stakeholder_preview.production_migration_enabled' => false,
        'stakeholder_preview.production_integrations' => 'disabled',
    ]);
}

function previewAccount(StakeholderPreviewPersona $persona): User
{
    return User::query()->where('email', $persona->approvedEmail())->sole();
}

function recordCleanroomRouting(LifecycleCleanroomRun $run, PermitApplication $application): void
{
    $application->load('lines.lineOfBusiness');
    $work = collect(app(NewApplicationHappyPathDefinition::class)->responsibilities())
        ->groupBy(fn (array $responsibility): string => $responsibility['department'].'|'.$responsibility['line_of_business_code'])
        ->map(function ($responsibilities) use ($application): array {
            $first = $responsibilities->first();
            $line = $application->lines->first(fn ($line): bool => $line->lineOfBusiness?->code === $first['line_of_business_code']);

            return [
                'office_code' => $first['department'],
                'office_label' => str($first['department'])->headline()->toString(),
                'situational_reason' => $responsibilities->pluck('reason')->implode(' '),
                'required_work' => $responsibilities->pluck('label')->implode('; '),
                'permit_application_line_id' => $line->id,
            ];
        })->values()->all();

    app(RecordBploRoutingDetermination::class)->handle(
        $application,
        User::query()->findOrFail(data_get($run->actor_manifest, 'actors.intake.user_id')),
        'Explicit test BPLO situational determination from the lodged Application and bounded cleanroom circumstances.',
        $work,
    );
}

/** @return array<string, mixed> */
function sourceBackedHistoricalAssessment(): array
{
    $fees = [
        ['name' => 'Health Certificate', 'category' => 'Regulatory Fee', 'amount_cents' => 10_000],
        ['name' => 'Laminated ID', 'category' => 'Other Charges', 'amount_cents' => 2_500],
        ['name' => "Mayor's Permit Fee", 'category' => 'Regulatory Fee', 'amount_cents' => 200_000],
        ['name' => 'Occupation Fee', 'category' => 'Other Charges', 'amount_cents' => 10_000],
        ['name' => 'Sanitary Permit Fee', 'category' => 'Regulatory Fee', 'amount_cents' => 0],
        ['name' => 'Solid Waste Management', 'category' => 'Regulatory Fee', 'amount_cents' => 250_000],
        ['name' => 'Weight & Measure', 'category' => 'Other Charges', 'amount_cents' => 10_000],
        ['name' => 'Business Tax', 'category' => 'Tax', 'amount_cents' => 0],
    ];
    $evidence = [
        'source_status' => 'Released',
        'source_assessed_at' => '2025-01-15T08:00:00.000Z',
        'recorded_total_amount_cents' => 482_500,
        'component_total_amount_cents' => 482_500,
        'source_internal_reconciles' => true,
        'schedules' => [[
            'section' => 1,
            'status' => 'paid',
            'total_amount_cents' => 482_500,
            'paid_amount_cents' => 482_500,
            'fee_total_amount_cents' => 482_500,
            'surcharge_amount_cents' => 0,
            'penalty_amount_cents' => 0,
            'fees' => $fees,
        ]],
    ];
    $normalize = function (mixed $value) use (&$normalize): mixed {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $normalize($item), $value);
    };

    return [
        ...$evidence,
        'source_evidence_hash' => hash('sha256', json_encode(
            $normalize($evidence),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        )),
    ];
}
