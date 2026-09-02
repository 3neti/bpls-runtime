<?php

use App\Actions\AdvanceLifecycleCleanroom;
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
