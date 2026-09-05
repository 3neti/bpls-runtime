<?php

use App\Actions\CommissionPostPaymentOfficeCertifications;
use App\Actions\IssueManualCollectionReceipt;
use App\Actions\IssueSyntheticLifecyclePermit;
use App\Actions\ProjectPermitReadiness;
use App\Actions\RecordPostPaymentOfficeCertification;
use App\Actions\ReleaseSyntheticLifecyclePermit;
use App\Actions\StartLifecycleCleanroom;
use App\Data\Application\ApplicationDataResolver;
use App\Enums\AssessmentDecisionAction;
use App\Enums\AssessmentStatus;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\TreasuryCollectionChannel;
use App\Enums\TreasuryCollectionMethod;
use App\Enums\TreasuryCollectionStatus;
use App\Models\Assessment;
use App\Models\AssessmentDecision;
use App\Models\BploRoutingDetermination;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LifecycleCleanroomRun;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;
use App\Models\TreasuryCollection;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

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
    Route::middleware('web')->group(base_path('routes/web.php'));
    Route::getRoutes()->refreshNameLookups();
    Route::getRoutes()->refreshActionLookups();
    Artisan::call('bpls:install');
});

test('one Application executes routing-derived certification readiness issuance release and exact public identity', function () {
    $operator = User::factory()->create();
    $run = app(StartLifecycleCleanroom::class)->handle($operator);
    $citizen = cleanroomActor($run, 'citizen');
    $owner = BusinessOwner::factory()->create();
    $business = Business::factory()->for($owner, 'owner')->create(['name' => 'Long Horizon Trading and Community Food Services']);
    $application = PermitApplication::factory()->withStatus(PermitApplicationStatus::PendingPayment)->for($business)->for($citizen, 'submittedBy')->create([
        'application_year' => 2025,
        'status' => PermitApplicationStatus::PendingPayment,
        'submitted_at' => now(),
        'metadata' => [
            'lifecycle_cleanroom' => [
                'run_id' => $run->public_id,
                'semantic_classification' => 'synthetic_only',
                'production_liability' => false,
            ],
        ],
    ]);
    $run->update(['new_application_id' => $application->id]);
    PermitApplicationDeclaration::factory()->for($application)->create([
        'snapshot' => ['business' => ['name' => $business->name]],
        'snapshot_hash' => hash('sha256', $business->name),
    ]);
    $declarationHash = $application->declaration()->sole()->snapshot_hash;

    $routing = BploRoutingDetermination::factory()->for($application)->create();
    foreach (['engineering' => 'Engineering', 'health' => 'Health', 'menro' => 'MENRO'] as $code => $label) {
        $routing->works()->create([
            'office_code' => $code,
            'office_label' => $label,
            'situational_reason' => 'The lodged Application requires this office.',
            'required_work' => 'Review declared business activity.',
            'context_snapshot' => ['source' => 'test_explicit_routing'],
        ]);
    }
    $assessment = Assessment::factory()->for($application)->create([
        'status' => AssessmentStatus::Computed,
        'total_amount_cents' => 122_000,
    ]);
    AssessmentDecision::factory()->for($assessment)->create([
        'action' => AssessmentDecisionAction::Approved,
    ]);
    $schedule = PaymentSchedule::factory()->for($application)->for($assessment)->create([
        'status' => PaymentScheduleStatus::Paid,
        'total_amount_cents' => 122_000,
        'paid_amount_cents' => 122_000,
    ]);
    $collection = TreasuryCollection::factory()->for($application)->for($assessment)->for($schedule)->create([
        'status' => TreasuryCollectionStatus::PendingReceipt,
        'channel' => TreasuryCollectionChannel::Online,
        'method' => TreasuryCollectionMethod::QrPh,
        'amount_cents' => 122_000,
    ]);

    $beforeReceipt = app(ProjectPermitReadiness::class)->handle($application->fresh());
    expect($beforeReceipt['ready'])->toBeFalse()
        ->and($beforeReceipt['blocked_by'])->toContain('issued_official_receipt');
    expect(fn () => app(CommissionPostPaymentOfficeCertifications::class)->handle($application->fresh()))
        ->toThrow(LogicException::class);

    $receipt = app(IssueManualCollectionReceipt::class)->handle($collection, [
        'receipt_number' => '7654321',
        'numbering_authority' => 'manual_synthetic_cleanroom',
    ], cleanroomActor($run, 'cashier'));
    $certifications = app(CommissionPostPaymentOfficeCertifications::class)->handle($application->fresh());

    expect(collect($certifications)->pluck('office_code')->sort()->values()->all())->toBe(['engineering', 'health', 'menro'])
        ->and(collect($certifications)->every(fn ($certification): bool => $certification->receipt_id === $receipt->id))->toBeTrue()
        ->and(app(ProjectPermitReadiness::class)->handle($application->fresh())['ready'])->toBeFalse();

    $engineeringData = app(ApplicationDataResolver::class)->resolve($application->fresh(), cleanroomActor($run, 'engineering'))->toArray();
    $healthData = app(ApplicationDataResolver::class)->resolve($application->fresh(), cleanroomActor($run, 'health'))->toArray();
    $engineeringTask = 'post_payment_certification_'.collect($certifications)->firstWhere('office_code', 'engineering')->id;
    expect(collect($engineeringData['actor_context']['current_tasks'])->pluck('key'))->toContain($engineeringTask)
        ->and(collect($healthData['actor_context']['current_tasks'])->pluck('key'))->not->toContain($engineeringTask)
        ->and($engineeringData['permit']['printable_artifact_url'])->toBeNull();

    foreach ($certifications as $certification) {
        app(RecordPostPaymentOfficeCertification::class)->handle(
            $certification,
            cleanroomActor($run, $certification->office_code),
            remarks: 'Synthetic-only certification grounded in routing and the reviewed OR.',
        );
    }

    $readiness = app(ProjectPermitReadiness::class)->handle($application->fresh());
    expect($readiness['ready'])->toBeTrue()
        ->and($readiness['required_offices'])->toBe(['engineering', 'health', 'menro'])
        ->and($readiness['receipt_number'])->toBe('7654321')
        ->and($readiness['production_authority'])->toBeFalse();

    expect(fn () => app(ReleaseSyntheticLifecyclePermit::class)->handle($application->fresh(), cleanroomActor($run, 'releasing_officer')))
        ->toThrow(DomainException::class, 'issue the specimen');

    $issued = app(IssueSyntheticLifecyclePermit::class)->handle($application->fresh(), cleanroomActor($run, 'permit_issuer'));
    expect($issued->permit_number)->toMatch('/^BP-2025-\d{4}$/')
        ->and($issued->issued_at)->not->toBeNull()
        ->and($issued->released_at)->toBeNull()
        ->and(data_get($issued->source_snapshot, 'official_numbering_authority'))->toBeFalse()
        ->and(data_get($issued->source_snapshot, 'real_mayor_login_or_signature_used'))->toBeFalse();

    $issuedVerificationReference = app(ApplicationDataResolver::class)
        ->resolve($application->fresh(), $citizen)
        ->permit
        ->verification['reference'];

    app(ReleaseSyntheticLifecyclePermit::class)->handle($application->fresh(), cleanroomActor($run, 'releasing_officer'));
    $data = app(ApplicationDataResolver::class)->resolve($application->fresh(), $citizen)->toArray();
    expect($data['schema_version'])->toBe('bpls.application-data.v1')
        ->and($application->declaration()->sole()->snapshot_hash)->toBe($declarationHash)
        ->and($data['permit']['issued'])->toBeTrue()
        ->and($data['permit']['released'])->toBeTrue()
        ->and($data['permit']['valid'])->toBeFalse()
        ->and($data['permit']['official_receipt_number'])->toBe('7654321')
        ->and($data['permit']['verification']['reference'])->toBe($issuedVerificationReference)
        ->and($data['permit']['verification']['reference'])->toStartWith('BPV-'.$application->id.'-')
        ->and($data['permit']['production_authority'])->toBeFalse()
        ->and($data['post_payment']['certifications'])->toHaveCount(3)
        ->and(collect($data['post_payment']['certifications'])->every(fn (array $item): bool => $item['receipt_reviewed'] && $item['production_authority'] === false))->toBeTrue();

    $this->get($data['permit']['verification']['url'])
        ->assertSuccessful()
        ->assertJsonPath('permit.permit_number', $issued->permit_number)
        ->assertJsonPath('permit.official_receipt_number', '7654321')
        ->assertJsonPath('permit.identity_scope', 'exact_synthetic_permit_identity_only')
        ->assertJsonPath('permit.production_authority', false)
        ->assertJsonPath('permit.legal_effect', false);
});

function cleanroomActor(LifecycleCleanroomRun $run, string $key): User
{
    return User::query()->findOrFail(data_get($run->actor_manifest, 'actors.'.$key.'.user_id'));
}
