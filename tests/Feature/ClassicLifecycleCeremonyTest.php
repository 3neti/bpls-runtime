<?php

use App\Actions\AdvanceClassicLifecycleSystemSteps;
use App\Actions\AssignTreasuryLinesOfBusiness;
use App\Actions\BuildBploRoutingTask;
use App\Actions\BuildLifecycleCleanroomIntake;
use App\Actions\BuildMunicipalWorkInbox;
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
use App\Actions\StartClassicLifecycleCleanroom;
use App\Data\Application\ApplicationDataResolver;
use App\Enums\AssessmentDecisionAction;
use App\Enums\PermitApplicationStatus;
use App\Enums\StakeholderPreviewPersona;
use App\Enums\TreasuryCollectionStatus;
use App\Models\LifecycleCleanroomCeremonyEvent;
use App\Models\LifecycleCleanroomRegistrationInvitation;
use App\Models\LifecycleCleanroomRun;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Database\Seeders\MunicipalFeeCatalogSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    config()->set([
        'stakeholder_preview.mode' => true,
        'stakeholder_preview.profile' => StakeholderPreviewSafety::Profile,
        'stakeholder_preview.data_classification' => 'synthetic_only',
        'stakeholder_preview.pii_mode' => 'synthetic_only',
        'stakeholder_preview.production_migration_enabled' => false,
        'stakeholder_preview.production_integrations' => 'disabled',
    ]);
    Artisan::call('bpls:install');
    $this->seed(MunicipalFeeCatalogSeeder::class);
    config()->set('cache.default', 'array');
    config()->set('services.x_change', [
        'base_url' => 'https://x-change.example.test',
        'token_endpoint' => '/oauth/token',
        'client_id' => 'synthetic-client',
        'client_secret' => 'synthetic-secret',
        'scope' => 'pay-codes:estimate pay-codes:issue pay-codes:read pay-codes:pay',
        'settlement_rail' => 'INSTAPAY',
        'token_refresh_leeway_seconds' => 60,
        'connect_timeout_seconds' => 2,
        'timeout_seconds' => 5,
    ]);
    Cache::flush();
});

test('classic cleanroom begins with one-time Citizen registration and disables actor switching', function () {
    $management = classicPreviewAccount(StakeholderPreviewPersona::Management);
    $response = $this->actingAs($management)->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.start'), [
        'ceremony' => LifecycleCleanroomRun::CeremonyClassicLifecycleV1,
    ]);

    $run = LifecycleCleanroomRun::query()->sole();
    expect($run->isClassicLifecycleV1())->toBeTrue();
    $this->assertGuest();
    $location = $response->headers->get('Location');
    expect($location)->toBeString()->toContain('/register?classic_cleanroom_invitation=');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
    $token = $query['classic_cleanroom_invitation'] ?? null;
    expect($token)->toBeString()->toHaveLength(64);

    $this->get($location)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Register')
            ->where('classicCleanroomInvitation', $token));

    $this->post(route('register.store'), [
        'name' => 'Classic Lifecycle Citizen',
        'email' => 'classic-citizen@bpls-runtime.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'classic_cleanroom_invitation' => $token,
    ])->assertRedirect(route('dashboard', absolute: false));

    $citizen = User::query()->where('email', 'classic-citizen@bpls-runtime.test')->sole();
    $run->refresh();
    expect($run->actor('citizen')['user_id'])->toBe($citizen->id)
        ->and($citizen->hasRole('citizen'))->toBeTrue()
        ->and(session('lifecycle_cleanroom_intake_run_id'))->toBe($run->id)
        ->and(LifecycleCleanroomRegistrationInvitation::query()->sole()->claimed_by_id)->toBe($citizen->id)
        ->and(LifecycleCleanroomCeremonyEvent::query()->pluck('event')->all())->toContain('classic_cleanroom_started', 'citizen_registered', 'logged_in');

    $this->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();
    expect(LifecycleCleanroomCeremonyEvent::query()->where('event', 'logged_out')->where('actor_user_id', $citizen->id)->exists())->toBeTrue();

    $this->post(route('register.store'), [
        'name' => 'Replay Citizen',
        'email' => 'replay-citizen@bpls-runtime.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'classic_cleanroom_invitation' => $token,
    ])->assertSessionHasErrors('classic_cleanroom_invitation');
    expect(User::query()->where('email', 'replay-citizen@bpls-runtime.test')->exists())->toBeFalse();

    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.next', $run))
        ->assertSessionHasErrors('cleanroom');
});

test('classic ceremony records normal Citizen and BPLO login inbox Application and logout boundaries', function () {
    $management = classicPreviewAccount(StakeholderPreviewPersona::Management);
    $classic = app(StartClassicLifecycleCleanroom::class)->handle($management);
    $run = $classic['run'];
    parse_str((string) parse_url($classic['registration_url'], PHP_URL_QUERY), $query);
    $token = $query['classic_cleanroom_invitation'];

    $this->post(route('register.store'), [
        'name' => 'Classic Lifecycle Citizen',
        'email' => 'classic-citizen@bpls-runtime.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'classic_cleanroom_invitation' => $token,
    ]);
    $intake = app(BuildLifecycleCleanroomIntake::class)->handle($run->fresh());
    $this->get(route('citizen.permit-applications.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Create')
            ->where('cleanroomIntake.ceremony', LifecycleCleanroomRun::CeremonyClassicLifecycleV1)
            ->where('cleanroomIntake.staged_citizen_intake', true)
            ->where('cleanroomIntake.run_id', $run->public_id));
    $this->post(route('citizen.permit-applications.store'), [
        ...$intake,
        'type' => 'new',
        'business_activity_description' => 'Retail sale of fresh fish at the Ipil public market.',
        'lifecycle_cleanroom_run_id' => $run->public_id,
    ])->assertSessionHasNoErrors();
    $run->refresh();
    $application = PermitApplication::query()->findOrFail($run->new_application_id);
    $this->post(route('citizen.permit-applications.submit', $application), [
        'undertaking_accepted' => '1',
        'signature_facsimile' => UploadedFile::fake()->image('applicant-signature.png'),
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('citizen.permit-applications.show', $application));
    $this->post(route('logout'));

    $this->post(route('login.store'), ['email' => 'intake@bpls-runtime.test', 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->get(route('staff.work.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workItems.data.0.task_type', 'bplo_routing')
            ->where('workItems.data.0.application.id', $application->id));
    $this->get(route('staff.permit-applications.evaluation.show', $application))->assertOk();

    app(RecordBploRoutingDetermination::class)->handle(
        $application->fresh(),
        auth()->user(),
        'Concerned offices selected by the Classic ceremony.',
        collect(['assessor', 'engineering', 'health', 'menro'])->map(fn (string $office): array => [
            'office_code' => $office,
            'office_label' => str($office)->headline()->toString(),
            'situational_reason' => 'Selected by BPLO checklist.',
            'required_work' => 'Prepare office Payment Order.',
            'permit_application_line_id' => null,
        ])->all(),
    );
    app(AdvanceClassicLifecycleSystemSteps::class)->handle($run->fresh());
    $this->post(route('logout'));

    expect(LifecycleCleanroomCeremonyEvent::query()->where('actor_key', 'citizen')->pluck('event')->all())
        ->toContain('application_form_opened', 'action_request_completed', 'canonical_state_advanced', 'logged_out')
        ->and(LifecycleCleanroomCeremonyEvent::query()->where('actor_key', 'intake')->pluck('event')->all())
        ->toContain('logged_in', 'inbox_opened', 'application_opened', 'logged_out')
        ->and(data_get(app(ResolveLifecycleCleanroomState::class)->handle($run->fresh()), 'progress.next_step.key'))
        ->toBe('assessor_responsibilities');
});

test('classic ceremony completes the canonical lifecycle through each municipal inbox', function () {
    $management = classicPreviewAccount(StakeholderPreviewPersona::Management);
    $run = app(StartClassicLifecycleCleanroom::class)->handle($management)['run'];
    $intake = app(BuildLifecycleCleanroomIntake::class)->handle($run);
    $citizen = User::query()->findOrFail(data_get($run->actor_manifest, 'actors.citizen.user_id'));

    $this->actingAs($citizen)->post(route('citizen.permit-applications.store'), [
        ...$intake,
        'type' => 'new',
        'business_activity_description' => 'Retail sale of fresh fish at the Ipil public market.',
        'lifecycle_cleanroom_run_id' => $run->public_id,
    ])->assertSessionHasNoErrors();
    $run->refresh();
    $application = PermitApplication::query()->findOrFail($run->new_application_id);
    $this->post(route('citizen.permit-applications.submit', $application), [
        'undertaking_accepted' => '1',
        'signature_facsimile' => UploadedFile::fake()->image('applicant-signature.png'),
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('citizen.permit-applications.show', $application));

    $actor = fn (string $key): User => User::query()->findOrFail(data_get($run->actor_manifest, 'actors.'.$key.'.user_id'));
    $assertInbox = function (string $actorKey, string $taskType) use ($actor, $application): array {
        $items = app(BuildMunicipalWorkInbox::class)->handle($actor($actorKey))['items'];
        expect($items->pluck('task_type')->all())->toContain($taskType)
            ->and($items->firstWhere('task_type', $taskType)['application']['id'])->toBe($application->id);

        return $items->firstWhere('task_type', $taskType);
    };

    $assertInbox('intake', 'bplo_routing');
    app(RecordBploRoutingDetermination::class)->handle(
        $application,
        $actor('intake'),
        'Concerned offices selected from the lodged Application.',
        collect(['assessor', 'engineering', 'health', 'menro'])->map(fn (string $office): array => [
            'office_code' => $office,
            'office_label' => str($office)->headline()->toString(),
            'situational_reason' => 'Selected by BPLO checklist.',
            'required_work' => 'Prepare office Payment Order.',
            'permit_application_line_id' => null,
        ])->all(),
    );
    app(AdvanceClassicLifecycleSystemSteps::class)->handle($run->fresh());

    $financialEditor = app(BuildBploRoutingTask::class)->handle($application->fresh(), $actor('engineering'))->toArray();
    foreach ($application->fresh()->bploRoutingDetermination->works as $work) {
        $assertInbox($work->office_code, 'payment_order');
        $fees = match ($work->office_code) {
            'health' => collect(data_get($financialEditor, 'financial_editor.office_fee_options.health'))
                ->whereIn('code', ['IPIL-LEGACY-99C7F1CE5E8189C8', 'IPIL-LEGACY-04845A0127A00E12']),
            'menro' => collect(data_get($financialEditor, 'financial_editor.office_fee_options.menro'))
                ->where('code', 'IPIL-LEGACY-98CDCAD9D28055FB'),
            'engineering' => collect(data_get($financialEditor, 'financial_editor.office_fee_options.engineering'))
                ->where('code', 'FEE-C2E404D2D1B97545')
                ->map(fn (array $fee): array => [...$fee, 'default_amount_cents' => 15_000]),
            'assessor' => collect(data_get($financialEditor, 'financial_editor.office_fee_options.assessor'))
                ->where('code', 'IPIL-LEGACY-E5B97AA20294C7AA')
                ->map(fn (array $fee): array => [...$fee, 'default_amount_cents' => 10_000]),
        };
        $this->actingAs($actor($work->office_code))
            ->post(route('staff.permit-applications.office-payment-orders.store', [$application, $work]), [
                'items' => $fees->map(fn (array $fee): array => [
                    'fee_rule_id' => $fee['id'],
                    'amount_cents' => $fee['default_amount_cents'],
                ])->values()->all(),
                'signature_facsimile' => UploadedFile::fake()->image($work->office_code.'-signature.png'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    $assertInbox('treasury', 'treasury_classification');
    $line = LineOfBusiness::query()->where('code', 'LOB-3A9A93CA46967768')->sole();
    $defaults = collect(data_get($financialEditor, 'financial_editor.line_of_business_options'))
        ->firstWhere('code', $line->code)['default_items'];
    app(AssignTreasuryLinesOfBusiness::class)->handle($application, [[
        'line_of_business_id' => $line->id,
        'items' => collect($defaults)
            ->whereIn('code', ['IPIL-LEGACY-A9B730041C0AE6F6', 'IPIL-LEGACY-FEE443B6D6004315', 'IPIL-LEGACY-5F028B76EEBEF485'])
            ->map(fn (array $item): array => [
                'fee_rule_id' => $item['fee_rule_id'],
                'amount_cents' => $item['code'] === 'IPIL-LEGACY-5F028B76EEBEF485' ? 100_000 : $item['amount_cents'],
                'reason' => $item['code'] === 'IPIL-LEGACY-5F028B76EEBEF485' ? 'Source-observed CAL-2026-001 amount.' : null,
                'authority' => $item['code'] === 'IPIL-LEGACY-5F028B76EEBEF485' ? 'Municipality-supplied operational specimen.' : null,
            ])->values()->all(),
    ]], $actor('treasury'));

    $assertInbox('assessment_officer', 'assessment_preparation');
    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application->fresh(), $actor('assessment_officer'));
    $assertInbox('treasury', 'treasury_counter_check');
    app(RecordBusinessPermitEvaluationCounterCheck::class)->handle($assessment, $actor('treasury'));
    $assertInbox('municipal_treasurer', 'treasurer_decision');
    app(RecordAssessmentDecision::class)->handle($assessment, $actor('municipal_treasurer'), AssessmentDecisionAction::Approved);
    $assertInbox('assessment_officer', 'payment_schedule');
    $schedule = app(CreatePaymentScheduleForAssessment::class)->handle($assessment, $actor('assessment_officer'));

    expect(data_get(app(ResolveLifecycleCleanroomState::class)->handle($run->fresh()), 'progress.next_step.key'))
        ->toBe('qr_payment_requested')
        ->and(app(BuildMunicipalWorkInbox::class)->handle($actor('cashier'))['items']->pluck('task_type')->all())
        ->not->toContain('collection');

    $this->actingAs($actor('cashier'))
        ->postJson(route('staff.payment-schedules.qr-ph.initiate', $schedule))
        ->assertForbidden();
    $this->actingAs($management)
        ->post(route('stakeholder-preview.lifecycle-laboratory.cleanrooms.simulate-qr-ph-payment', $run))
        ->assertForbidden();
    expect($schedule->treasuryCollections()->count())->toBe(0);

    fakeClassicQrPhIssue($schedule);
    $this->actingAs($citizen)
        ->postJson(route('citizen.payment-schedules.qr-ph.initiate', $schedule))
        ->assertOk()
        ->assertJsonPath('amount_cents', $schedule->total_amount_cents)
        ->assertJsonPath('status', 'awaiting_payment');

    $expiredAttempt = $schedule->refresh()->xChangePayment->attempts()->sole();
    $expiredAttempt->update(['status' => 'expired', 'expires_at' => now()->subMinute()]);
    expect(data_get(app(ResolveLifecycleCleanroomState::class)->handle($run->fresh()), 'progress.next_step.key'))
        ->toBe('qr_payment_requested')
        ->and(app(BuildMunicipalWorkInbox::class)->handle($actor('cashier'))['items']->pluck('task_type')->all())
        ->not->toContain('collection');

    $this->actingAs($citizen)
        ->postJson(route('citizen.payment-schedules.qr-ph.initiate', $schedule))
        ->assertOk()
        ->assertJsonPath('amount_cents', $schedule->total_amount_cents);
    expect($schedule->refresh()->xChangePayment->attempts()->count())->toBe(2);

    expect(data_get(app(ResolveLifecycleCleanroomState::class)->handle($run->fresh()), 'progress.next_step.key'))
        ->toBe('qr_payment_collected');

    $assertInbox('cashier', 'collection');
    $this->actingAs($actor('cashier'))
        ->get(route('staff.payment-schedules.show', $schedule))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.initiate_qr_ph', false)
            ->where('can.simulate_classic_payment', true)
            ->where('classicPaymentHandoff.pay_code', 'CLSC')
            ->where('classicPaymentHandoff.external_reference', fn (string $reference): bool => $reference !== '')
            ->where('classicPaymentHandoff.attempt.reference', 'SYNTHETIC-CLASSIC-'.$schedule->id.'-2')
            ->where('classicPaymentHandoff.attempt.provider', 'netbank')
            ->where('classicPaymentHandoff.amount_cents', $schedule->total_amount_cents));
    $this->actingAs($actor('cashier'))
        ->post(route('staff.payment-schedules.classic-payment-simulation.store', $schedule))
        ->assertRedirect(route('staff.payment-schedules.show', $schedule));
    $collection = $schedule->treasuryCollections()->sole();
    $receiptGroups = $collection->allocations()->pluck('receipt_group_key')->unique()->sort()->values();
    foreach ($receiptGroups as $index => $receiptGroup) {
        app(IssueManualCollectionReceipt::class)->handle($collection->fresh(), [
            'receipt_group_key' => $receiptGroup,
            'receipt_number' => (string) (8_100_001 + $index),
            'series' => '2025',
            'numbering_authority' => 'synthetic_classic_cleanroom',
        ], $actor('cashier'));
    }
    expect($collection->fresh()->status)->toBe(TreasuryCollectionStatus::Receipted);
    app(AdvanceClassicLifecycleSystemSteps::class)->handle($run->fresh());

    foreach ($application->fresh()->postPaymentOfficeCertifications as $certification) {
        $inboxItem = $assertInbox($certification->office_code, 'post_payment_certification');
        expect($inboxItem['action_url'])->toContain('/stakeholder-preview/lifecycle-laboratory/cleanrooms/');
        app(RecordPostPaymentOfficeCertification::class)->handle($certification, $actor($certification->office_code));
    }
    $issuanceItem = $assertInbox('permit_issuer', 'permit_issuance');
    expect($issuanceItem['action_url'])->toContain('/stakeholder-preview/lifecycle-laboratory/cleanrooms/');
    $permit = app(IssueSyntheticLifecyclePermit::class)->handle($application->fresh(), $actor('permit_issuer'));
    expect($permit->issued_at)->not->toBeNull()->and($permit->released_at)->toBeNull();
    $releaseItem = $assertInbox('releasing_officer', 'permit_release');
    expect($releaseItem['action_url'])->toContain('/stakeholder-preview/lifecycle-laboratory/cleanrooms/');
    app(ReleaseSyntheticLifecyclePermit::class)->handle($application->fresh(), $actor('releasing_officer'));

    $state = app(ResolveLifecycleCleanroomState::class)->handle($run->fresh());
    $data = app(ApplicationDataResolver::class)->resolve($application->fresh(), $citizen)->toArray();
    $parity = [
        data_get($data, 'schedule_of_payment.grand_total_minor'),
        data_get($data, 'schedule_of_payment.price_report_total_minor'),
        $assessment->total_amount_cents,
        $schedule->total_amount_cents,
        $collection->amount_cents,
        $collection->receipts()->sum('amount_cents'),
    ];
    expect(data_get($state, 'progress.completed_steps'))->toBe(25)
        ->and(data_get($state, 'progress.complete'))->toBeTrue()
        ->and($parity)->each->toBe(417_500)
        ->and($receiptGroups)->toHaveCount(6)
        ->and(data_get($data, 'identity.status'))->toBe(PermitApplicationStatus::Released->value)
        ->and(data_get($data, 'permit.issued'))->toBeTrue()
        ->and(data_get($data, 'permit.released'))->toBeTrue()
        ->and(data_get($data, 'payment.reconciliation.status'))->toBe('fully_reconciled');
    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permitApplication.processing.current_stage', PermitApplicationStatus::Released->value));
    $this->get(data_get($data, 'permit.verification.url'))
        ->assertSuccessful()
        ->assertJsonPath('permit.permit_number', $permit->permit_number);
    $this->actingAs($citizen)->get(route('citizen.permit-applications.show', $application))->assertSuccessful();
});

function classicPreviewAccount(StakeholderPreviewPersona $persona): User
{
    return User::query()->where('email', $persona->approvedEmail())->sole();
}

function fakeClassicQrPhIssue(PaymentSchedule $schedule): void
{
    $attemptNumber = 0;

    Http::fake(function (Request $request) use ($schedule, &$attemptNumber) {
        if (str_ends_with($request->url(), '/oauth/token')) {
            return Http::response(['expires_in' => 900, 'access_token' => 'synthetic-token']);
        }

        if (str_ends_with($request->url(), '/payment-attempts')) {
            $attemptNumber++;

            return Http::response([
                'data' => ['attempt' => [
                    'reference' => 'SYNTHETIC-CLASSIC-'.$schedule->id.'-'.$attemptNumber,
                    'status' => 'awaiting_payment',
                    'provider' => 'netbank',
                    'amount_minor' => $schedule->total_amount_cents,
                    'expires_at' => now()->addMinutes(15)->toIso8601String(),
                    'qr_code' => [
                        'mime_type' => 'image/png',
                        'base64_payload' => base64_encode("\x89PNG\r\n\x1a\nclassic"),
                    ],
                ]],
            ], 201);
        }

        return Http::response([
            'data' => [
                'voucher_id' => 283,
                'code' => 'CLSC',
                'external_reference' => $request['external_reference'],
                'consumer_status' => 'payable',
                'links' => ['pay' => 'https://x-change.example.test/x/pay/CLSC'],
            ],
        ], 201);
    });
}
