<?php

use App\Actions\ApplyDueBploRoutingSuggestions;
use App\Actions\ArmBploRoutingSentinel;
use App\Actions\CancelPermitApplication;
use App\Actions\RecordBploRoutingDetermination;
use App\Actions\SubmitCitizenPermitApplication;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\StakeholderPreviewPersona;
use App\Models\Assessment;
use App\Models\BploRoutingDetermination;
use App\Models\BploRoutingSuggestion;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\User;
use App\Notifications\BploRoutingDefaulted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('local');
    config()->set([
        'stakeholder_preview.mode' => true,
        'stakeholder_preview.profile' => 'stakeholder_preview_weekend_v1',
        'stakeholder_preview.data_classification' => 'synthetic_only',
        'stakeholder_preview.pii_mode' => 'synthetic_only',
        'stakeholder_preview.production_migration_enabled' => false,
        'stakeholder_preview.production_integrations' => 'disabled',
        'bplo.routing_sentinel.enabled' => true,
        'bplo.routing_sentinel.review_minutes' => 15,
        'bplo.routing_sentinel.clock' => 'elapsed',
    ]);
    Artisan::call('bpls:install');
    Carbon::setTestNow('2026-09-03 10:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('lodging time arms one immutable configurable sari-sari routing suggestion', function (): void {
    $application = sariSariApplication();

    $suggestion = app(ArmBploRoutingSentinel::class)->handle($application);

    expect($suggestion)->toBeInstanceOf(BploRoutingSuggestion::class)
        ->and($suggestion->lodged_at->toIso8601String())->toBe($application->submitted_at->toIso8601String())
        ->and($suggestion->review_due_at->toIso8601String())->toBe(now()->addMinutes(15)->toIso8601String())
        ->and($suggestion->status)->toBe(BploRoutingSuggestion::AwaitingConfirmation)
        ->and($suggestion->profile_version)->toBe('ipil-laboratory-routing-v1')
        ->and($suggestion->profile_keys)->toBe(['sari_sari_store'])
        ->and(collect($suggestion->suggested_work)->pluck('office_code')->sort()->values()->all())
        ->toBe(['assessor', 'engineering', 'health', 'menro'])
        ->and(data_get($suggestion->application_facts_snapshot, 'suggestion_is_determination'))->toBeFalse()
        ->and($application->bploRoutingDetermination()->exists())->toBeFalse();

    config(['bplo.routing_sentinel.review_minutes' => 60]);

    expect(app(ArmBploRoutingSentinel::class)->handle($application)->is($suggestion))->toBeTrue()
        ->and($suggestion->fresh()->review_due_at->toIso8601String())->toBe(now()->addMinutes(15)->toIso8601String());
});

test('canonical citizen lodging automatically arms the routing sentinel', function (): void {
    $owner = BusinessOwner::factory()->create();
    $business = Business::factory()->for($owner, 'owner')->create();
    $citizen = User::factory()->create(['business_owner_id' => $owner->id]);
    $application = PermitApplication::factory()
        ->for($business)
        ->for($citizen, 'submittedBy')
        ->create([
            'application_number' => null,
            'type' => PermitApplicationType::New,
            'status' => PermitApplicationStatus::Draft,
            'submitted_at' => null,
            'metadata' => [
                'citizen_intake' => ['registry_owner_id' => $owner->id],
                'laboratory_assessment_reconciliation' => [
                    'source_business_category' => 'REC- SARISARI STORE',
                ],
            ],
        ]);
    PermitApplicationLine::factory()->for($application)->create([
        'line_of_business_id' => LineOfBusiness::query()
            ->where('code', 'MRC-2A-02-B-WHOLESALE-RETAIL')
            ->sole()
            ->id,
    ]);

    $submitted = app(SubmitCitizenPermitApplication::class)->handle($application, $citizen);
    $suggestion = $submitted->bploRoutingSuggestion()->sole();

    expect($suggestion->lodged_at->toIso8601String())->toBe($submitted->submitted_at?->toIso8601String())
        ->and($suggestion->review_due_at->toIso8601String())->toBe($submitted->submitted_at?->copy()->addMinutes(15)->toIso8601String())
        ->and($suggestion->status)->toBe(BploRoutingSuggestion::AwaitingConfirmation)
        ->and($submitted->bploRoutingDetermination()->exists())->toBeFalse();
});

test('BPLO evaluator presents checked suggestions and the persisted countdown boundary', function (): void {
    $application = sariSariApplication();
    $suggestion = app(ArmBploRoutingSentinel::class)->handle($application);
    $bplo = previewBplo();

    $this->actingAs($bplo)
        ->get(route('staff.permit-applications.evaluation.show', $application))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('business-permit-evaluations/Show')
            ->where('application.submitted_at', $application->submitted_at->toIso8601String())
            ->where('bploRouting', null)
            ->where('routingSuggestion.id', $suggestion->id)
            ->where('routingSuggestion.status', BploRoutingSuggestion::AwaitingConfirmation)
            ->where('routingSuggestion.review_due_at', now()->addMinutes(15)->toIso8601String())
            ->has('routingSuggestion.suggested_work', 4)
            ->where('routingSuggestion.production_authority', false));
});

test('the evaluator refresh applies a due sentinel without requiring a local scheduler process', function (): void {
    $application = sariSariApplication();
    app(ArmBploRoutingSentinel::class)->handle($application);
    Carbon::setTestNow(now()->addMinutes(15));

    $this->actingAs(previewBplo())
        ->get(route('staff.permit-applications.evaluation.show', $application))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('bploRouting.origin', BploRoutingSuggestion::SystemDefaulted)
            ->where('routingSuggestion.status', BploRoutingSuggestion::SystemDefaulted));

    expect($application->bploRoutingDetermination()->count())->toBe(1)
        ->and($application->assessments()->count())->toBe(0)
        ->and($application->paymentSchedules()->count())->toBe(0);
});

test('human BPLO confirmation wins before the sentinel deadline', function (): void {
    $application = sariSariApplication();
    $suggestion = app(ArmBploRoutingSentinel::class)->handle($application);

    $determination = app(RecordBploRoutingDetermination::class)->handle(
        $application,
        previewBplo(),
        'BPLO reviewed and confirmed the provisional laboratory route.',
        $suggestion->suggested_work,
    );
    Carbon::setTestNow(now()->addMinutes(16));
    $result = app(ApplyDueBploRoutingSuggestions::class)->handle();

    expect($determination->application_facts_snapshot['routing_origin'])->toBe(BploRoutingSuggestion::BploConfirmed)
        ->and($suggestion->fresh()->status)->toBe(BploRoutingSuggestion::BploConfirmed)
        ->and($suggestion->fresh()->routing_determination_id)->toBe($determination->id)
        ->and($result['defaulted'])->toBe(0)
        ->and(BploRoutingDetermination::query()->count())->toBe(1);
});

test('sentinel defaults an expired suggestion exactly once without downstream authority', function (): void {
    $application = sariSariApplication();
    $suggestion = app(ArmBploRoutingSentinel::class)->handle($application);

    Carbon::setTestNow(now()->addMinutes(14)->addSeconds(59));
    expect(app(ApplyDueBploRoutingSuggestions::class)->handle()['defaulted'])->toBe(0);

    Carbon::setTestNow(now()->addSecond());
    $first = app(ApplyDueBploRoutingSuggestions::class)->handle();
    $second = app(ApplyDueBploRoutingSuggestions::class)->handle();
    $determination = $application->bploRoutingDetermination()->with('works')->sole();

    expect($first['defaulted'])->toBe(1)
        ->and($second['defaulted'])->toBe(0)
        ->and($suggestion->fresh()->status)->toBe(BploRoutingSuggestion::SystemDefaulted)
        ->and($determination->application_facts_snapshot['routing_origin'])->toBe(BploRoutingSuggestion::SystemDefaulted)
        ->and($determination->application_facts_snapshot['silence_is_office_approval'])->toBeFalse()
        ->and($determination->application_facts_snapshot['silence_creates_financial_authority'])->toBeFalse()
        ->and($determination->works)->toHaveCount(4)
        ->and(BploRoutingDetermination::query()->count())->toBe(1)
        ->and(Assessment::query()->count())->toBe(0)
        ->and(PaymentSchedule::query()->count())->toBe(0)
        ->and(previewBplo()->notifications()->where('type', BploRoutingDefaulted::class)->count())->toBe(1);
});

test('scheduled sweeps never retroactively arm an older unreviewed application', function (): void {
    $application = sariSariApplication();

    Carbon::setTestNow(now()->addDay());
    $result = app(ApplyDueBploRoutingSuggestions::class)->handle();

    expect($result)->toBe(['armed' => 0, 'defaulted' => 0, 'invalidated' => 0])
        ->and($application->bploRoutingSuggestion()->exists())->toBeFalse()
        ->and($application->bploRoutingDetermination()->exists())->toBeFalse();
});

test('sentinel invalidates stale or cancelled suggestions instead of routing them', function (string $change): void {
    $application = sariSariApplication();
    $suggestion = app(ArmBploRoutingSentinel::class)->handle($application);

    if ($change === 'facts changed') {
        $line = $application->lines()->sole();
        $line->update(['capital_investment_cents' => $line->capital_investment_cents + 1]);
    } else {
        app(CancelPermitApplication::class)->handle($application, previewBplo(), 'Cancelled during the laboratory routing review window.');
    }

    Carbon::setTestNow(now()->addMinutes(15));
    $result = app(ApplyDueBploRoutingSuggestions::class)->handle();

    expect($result['invalidated'])->toBe(1)
        ->and($suggestion->fresh()->status)->toBe(BploRoutingSuggestion::Invalidated)
        ->and($application->bploRoutingDetermination()->exists())->toBeFalse();
})->with(['facts changed', 'application cancelled']);

function sariSariApplication(): PermitApplication
{
    $application = PermitApplication::factory()
        ->withStatus(PermitApplicationStatus::Assessment)
        ->create([
            'submitted_at' => now(),
            'metadata' => [
                'laboratory_assessment_reconciliation' => [
                    'source_business_category' => 'REC- SARISARI STORE',
                    'semantic_classification' => 'observational_legacy_financial_evidence',
                    'operational_authority' => false,
                    'production_liability' => false,
                ],
            ],
        ]);
    PermitApplicationLine::factory()->for($application)->create([
        'line_of_business_id' => LineOfBusiness::query()
            ->where('code', 'MRC-2A-02-B-WHOLESALE-RETAIL')
            ->sole()
            ->id,
        'declared_gross_sales_cents' => 48_000_000,
        'capital_investment_cents' => 12_500_000,
    ]);

    return $application->refresh();
}

function previewBplo(): User
{
    return User::query()->where('email', StakeholderPreviewPersona::Bplo->approvedEmail())->sole();
}
