<?php

use App\Actions\AdvanceLifecycleCleanroom;
use App\Actions\BuildLaboratoryAssessmentReconciliation;
use App\Actions\BuildLifecycleCleanroomIntake;
use App\Actions\CompleteBusinessPermitEvaluationResponsibility;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreatePaymentScheduleForAssessment;
use App\Actions\RecordAssessmentDecision;
use App\Actions\RecordBploRoutingDetermination;
use App\Actions\RecordBusinessPermitEvaluationCounterCheck;
use App\Actions\ResolveLifecycleCleanroomState;
use App\Enums\AssessmentDecisionAction;
use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationSource;
use App\Enums\StakeholderPreviewPersona;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LifecycleCleanroomRun;
use App\Models\LifecycleScenarioSpecimen;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

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

    $bplo = previewAccount(StakeholderPreviewPersona::Bplo);
    $this->actingAs($bplo)->get(route('stakeholder-preview.lifecycle-laboratory.index'))->assertNotFound();
    $this->actingAs($bplo)->post(route('stakeholder-preview.lifecycle-laboratory.run-next'))->assertNotFound();
    $this->actingAs($bplo)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.start'))->assertNotFound();

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
            ->where('laboratory.scenarios.1.effective_date', '2026-01-15')
            ->where('laboratory.scenarios.1.events.0.label', '2026 Renewal lodged')
            ->where('laboratory.scenarios.1.financial_working_paper.total_amount_cents', 122_000));
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
        'citizen' => ['email' => 'scenario-citizen@example.test', 'destination' => route('citizen.permit-applications.show', $application)],
        'health' => ['email' => 'scenario-01-health@example.test', 'destination' => route('staff.permit-applications.evaluation.show', $application)],
        'treasury' => ['email' => 'scenario-01-treasury-counter-check@example.test', 'destination' => route('staff.permit-applications.evaluation.show', $application)],
        'municipal_treasurer' => ['email' => 'scenario-01-municipal-treasurer@example.test', 'destination' => route('staff.permit-applications.assessments.show', $application->assessments->sole())],
    ];

    foreach ($cases as $actor => $expectation) {
        $this->actingAs($management)
            ->post(route('stakeholder-preview.lifecycle-laboratory.enter-actor', [$specimen, $actor]))
            ->assertRedirect($expectation['destination']);
        $this->assertAuthenticatedAs(User::query()->where('email', $expectation['email'])->sole());
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

test('cleanroom citizen intake accepts an active municipal catalog activity offered by the form', function () {
    $management = previewAccount(StakeholderPreviewPersona::Management);
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.start'));
    $run = LifecycleCleanroomRun::query()->sole();
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run));
    $intake = app(BuildLifecycleCleanroomIntake::class)->handle($run);
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
    ])->assertSessionHasNoErrors();

    $run->refresh();
    $application = PermitApplication::query()->findOrFail($run->new_application_id);

    expect($application->lines()->sole()->line_of_business_id)->toBe($municipalRetail->id);

    $this->post(route('citizen.permit-applications.submit', $application))->assertSessionHasNoErrors();
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

test('source backed registry specimen advances through canonical actions to an auditable single application payable', function () {
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
    $this->post(route('citizen.permit-applications.submit', $application))->assertSessionHasNoErrors();

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
        ->and(data_get($state, 'progress.total_steps'))->toBe(13)
        ->and(data_get($state, 'progress.next_step.key'))->toBe('evaluation_initialized');

    app(AdvanceLifecycleCleanroom::class)->handle($run->fresh());
    $evaluation = $application->fresh()->businessPermitEvaluation;
    $responsibilities = $evaluation->items()
        ->where('metadata->lifecycle_cleanroom_responsibility', true)
        ->with('revisions')
        ->get();
    expect($responsibilities)->toHaveCount(8)
        ->and($responsibilities->sum(fn ($item): int => (int) data_get($item->revisions->first()?->value, 'amount_cents')))->toBe(482_500);

    foreach ($responsibilities as $responsibility) {
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
    app(CreatePaymentScheduleForAssessment::class)->handle($assessment, $assessmentOfficer);

    $state = app(ResolveLifecycleCleanroomState::class)->handle($run->fresh());
    expect(data_get($state, 'progress.complete'))->toBeTrue()
        ->and(data_get($state, 'progress.completed_steps'))->toBe(13)
        ->and($run->fresh()->renewal_application_id)->toBeNull();
});

test('cleanroom citizen form uses canonical draft and submit actions before canonical responsibility creation', function () {
    $management = previewAccount(StakeholderPreviewPersona::Management);
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.start'));
    $run = LifecycleCleanroomRun::query()->sole();
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run));
    $intake = app(BuildLifecycleCleanroomIntake::class)->handle($run);

    $this->post(route('citizen.permit-applications.store'), [
        ...$intake,
        'type' => 'new',
        'owner_email' => null,
        'owner_phone' => null,
    ])->assertSessionHasNoErrors();
    $run->refresh();
    $application = PermitApplication::query()->findOrFail($run->new_application_id);
    expect($application->status->value)->toBe('draft')
        ->and($application->submitted_at)->toBeNull()
        ->and($application->business->owner->name)->toStartWith('Cleanroom Synthetic Owner')
        ->and(data_get($application->metadata, 'lifecycle_cleanroom.run_id'))->toBe($run->public_id);

    $this->post(route('citizen.permit-applications.submit', $application))->assertSessionHasNoErrors();
    expect($application->fresh()->submitted_at)->not->toBeNull();

    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run))
        ->assertRedirect(route('staff.permit-applications.evaluation.show', $application));
    recordCleanroomRouting($run, $application);
    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run))
        ->assertRedirect(route('stakeholder-preview.lifecycle-laboratory.index'));
    expect($application->fresh()->businessPermitEvaluation->items()->whereIn('key', collect(app(NewApplicationHappyPathDefinition::class)->responsibilities())->pluck('key'))->count())->toBe(6)
        ->and($run->fresh()->owned_resource_manifest['permit_application_declaration_ids'])->toBe([$application->declaration()->sole()->id])
        ->and(PermitApplication::query()->count())->toBe(1);

    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.close', $run));
    expect($run->fresh()->status)->toBe('closed')
        ->and(PermitApplication::query()->whereKey($application)->exists())->toBeTrue();
});

test('cleanroom remains compatible with the canonical two year action semantics through both payables', function () {
    $management = previewAccount(StakeholderPreviewPersona::Management);
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.start'));
    $run = LifecycleCleanroomRun::query()->sole();
    $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run));
    $intake = app(BuildLifecycleCleanroomIntake::class)->handle($run);
    $this->post(route('citizen.permit-applications.store'), [...$intake, 'type' => 'new'])->assertSessionHasNoErrors();
    $run->refresh();
    $this->post(route('citizen.permit-applications.submit', $run->new_application_id))->assertSessionHasNoErrors();

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
    }

    $state = app(ResolveLifecycleCleanroomState::class)->handle($run->fresh());
    expect(data_get($state, 'progress.complete'))->toBeTrue()
        ->and(data_get($state, 'progress.completed_steps'))->toBe(24)
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
