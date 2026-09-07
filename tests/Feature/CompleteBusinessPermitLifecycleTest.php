<?php

use App\Actions\CommissionPostPaymentOfficeCertifications;
use App\Actions\IssueManualCollectionReceipt;
use App\Actions\IssueSyntheticLifecyclePermit;
use App\Actions\ProjectPermitReadiness;
use App\Actions\RecordPostPaymentOfficeCertification;
use App\Actions\ReleaseSyntheticLifecyclePermit;
use App\Actions\RenderPermitPdf;
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
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;
use App\Models\PermitApplicationLine;
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
    $business = Business::factory()->for($owner, 'owner')->create([
        'name' => 'Long Horizon Trading and Community Food Services',
        'address' => null,
    ]);
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
    foreach (range(1, 18) as $index) {
        $lineOfBusiness = LineOfBusiness::factory()->create([
            'code' => sprintf('LOB-%02d', $index),
            'name' => 'Municipal Supply and Specialist Service Category '.$index.' with Extended Description',
        ]);
        PermitApplicationLine::factory()->for($application)->for($lineOfBusiness)->create();
    }
    PermitApplicationDeclaration::factory()->for($application)->create([
        'snapshot' => [
            'business' => ['name' => $business->name],
            'business_address' => [
                'building_name' => 'Long Horizon Building',
                'street' => 'Purok Masigla',
                'barangay' => 'Poblacion',
                'city_municipality' => 'Ipil',
                'province' => 'Zamboanga Sibugay',
            ],
        ],
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

    $mayoralAuthorizationData = app(ApplicationDataResolver::class)
        ->resolve($application->fresh(), cleanroomActor($run, 'permit_issuer'))
        ->toArray();
    $mayoralAuthorizationNote = collect($mayoralAuthorizationData['actor_context']['work_notes'])
        ->firstWhere('id', 'permit_authority_review');
    expect($mayoralAuthorizationData['actor_context']['actor_label'])->toBe('Mayor Ramses Troy D. Olegario')
        ->and($mayoralAuthorizationNote['actor_label'])->toBe('Mayor Ramses Troy D. Olegario')
        ->and($mayoralAuthorizationNote['instruction'])->toBe('Record synthetic Mayoral Authorization and issue the Business Permit specimen')
        ->and($mayoralAuthorizationNote['state_label'])->toBe('Ready for Mayoral Authorization')
        ->and($mayoralAuthorizationNote['blocking_reason'])->toContain('Mayor Olegario did not log in, sign, or authorize')
        ->and(collect($mayoralAuthorizationData['actor_context']['current_tasks'])->firstWhere('key', 'issue_synthetic_permit')['label'])->toBe('Record synthetic Mayoral Authorization');

    expect(fn () => app(ReleaseSyntheticLifecyclePermit::class)->handle($application->fresh(), cleanroomActor($run, 'releasing_officer')))
        ->toThrow(DomainException::class, 'issue the specimen');

    $issued = app(IssueSyntheticLifecyclePermit::class)->handle($application->fresh(), cleanroomActor($run, 'permit_issuer'));
    $documentIssuedOn = sprintf('%d-%s', $application->application_year, $issued->issued_at->format('m-d'));
    expect($issued->permit_number)->toMatch('/^BP-2025-\d{4}$/')
        ->and($issued->issued_at)->not->toBeNull()
        ->and($issued->issued_at->toDateString())->not->toBe($documentIssuedOn)
        ->and(data_get($issued->source_snapshot, 'synthetic_permit_calendar.document_issued_on'))->toBe($documentIssuedOn)
        ->and(data_get($issued->source_snapshot, 'synthetic_permit_calendar.audit_issued_at'))->toBe($issued->issued_at->toIso8601String())
        ->and(data_get($issued->source_snapshot, 'synthetic_permit_calendar.production_authority'))->toBeFalse()
        ->and($issued->released_at)->toBeNull()
        ->and(data_get($issued->source_snapshot, 'official_numbering_authority'))->toBeFalse()
        ->and(data_get($issued->source_snapshot, 'mayoral_authorization.officeholder_name'))->toBe('Ramses Troy D. Olegario')
        ->and(data_get($issued->source_snapshot, 'mayoral_authorization.method'))->toBe('synthetic_reference')
        ->and(data_get($issued->source_snapshot, 'mayoral_authorization.personally_performed_by_officeholder'))->toBeFalse()
        ->and(data_get($issued->source_snapshot, 'mayoral_authorization.production_authority'))->toBeFalse()
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
        ->and($data['permit']['issued_on'])->toBe($documentIssuedOn)
        ->and($data['permit']['valid_until'])->toBe('2025-12-31')
        ->and($data['permit']['business_address'])->toBe('Long Horizon Building, Purok Masigla, Poblacion, Ipil, Zamboanga Sibugay')
        ->and($data['permit']['verification']['reference'])->toBe($issuedVerificationReference)
        ->and($data['permit']['verification']['reference'])->toStartWith('BPV-'.$application->id.'-')
        ->and($data['permit']['production_authority'])->toBeFalse()
        ->and($data['permit']['issuing_authority']['name'])->toBe('Ramses Troy D. Olegario')
        ->and($data['permit']['issuing_authority']['real_mayor_login_or_signature_used'])->toBeFalse()
        ->and($data['permit']['issuing_authority']['production_authority'])->toBeFalse()
        ->and($data['post_payment']['certifications'])->toHaveCount(3)
        ->and(collect($data['post_payment']['certifications'])->every(fn (array $item): bool => $item['receipt_reviewed'] && $item['production_authority'] === false))->toBeTrue();

    $this->get($data['permit']['verification']['url'])
        ->assertSuccessful()
        ->assertJsonPath('permit.permit_number', $issued->permit_number)
        ->assertJsonPath('permit.official_receipt_number', '7654321')
        ->assertJsonPath('permit.issued_on', $documentIssuedOn)
        ->assertJsonPath('permit.valid_until', '2025-12-31')
        ->assertJsonPath('permit.business_address', 'Long Horizon Building, Purok Masigla, Poblacion, Ipil, Zamboanga Sibugay')
        ->assertJsonPath('permit.identity_scope', 'exact_synthetic_permit_identity_only')
        ->assertJsonPath('permit.production_authority', false)
        ->assertJsonPath('permit.legal_effect', false);

    $permitPdf = app(RenderPermitPdf::class)->handle($application->fresh());
    preg_match_all('/\/Type \/Page\b/', $permitPdf, $permitPdfPages);
    expect($permitPdf)
        ->toStartWith('%PDF-1.4')
        ->toContain('BUSINESS PERMIT')
        ->toContain('LABORATORY SPECIMEN - NOT FOR OFFICIAL USE')
        ->toContain($issued->permit_number)
        ->toContain('MUNICIPAL SUPPLY AND SPECIALIST SERVICE CATEGORY 1 WITH EXTENDED DESCRIPTION')
        ->toContain('MUNICIPAL SUPPLY AND SPECIALIST SERVICE CATEGORY 18 WITH EXTENDED DESCRIPTION')
        ->not->toContain('LOB-01-')
        ->toContain('HON. RAMSES TROY D. OLEGARIO')
        ->toContain('NO MAYORAL SIGNATURE APPLIED')
        ->toContain('OFFICIAL RECEIPTS:')
        ->toContain('7654321')
        ->toContain(strtoupper('Long Horizon Building, Purok Masigla, Poblacion, Ipil, Zamboanga Sibugay'))
        ->toContain('SCAN TO VERIFY IDENTITY')
        ->toContain($data['permit']['verification']['reference'])
        ->not->toContain('DEMEGILLO MATERNITY CLINIC')
        ->and($permitPdfPages[0])->toHaveCount(1);
});

function cleanroomActor(LifecycleCleanroomRun $run, string $key): User
{
    return User::query()->findOrFail(data_get($run->actor_manifest, 'actors.'.$key.'.user_id'));
}
