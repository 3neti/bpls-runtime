<?php

use App\Enums\StakeholderPreviewPersona;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\Models\Business;
use App\Models\BusinessOwner;
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
        'citizen' => ['email' => 'scenario-citizen@example.test', 'destination' => route('citizen.businesses.show', $application->business_id)],
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
