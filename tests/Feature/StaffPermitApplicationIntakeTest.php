<?php

use App\Actions\CompletePermitClearance;
use App\Actions\DescribePermitDocumentConfiguration;
use App\Actions\DescribePermitReleaseReadiness;
use App\Actions\DescribePermitVerificationBoundary;
use App\Actions\EnsurePermitApplicationClearances;
use App\Actions\RenderApplicationFormPdf;
use App\Actions\RenderPermitPdf;
use App\Enums\AssessmentStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\PermitClearanceStatus;
use App\Enums\UserPermission;
use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\PermitClearance;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected away from staff permit applications', function () {
    $this->get(route('staff.permit-applications.index'))
        ->assertRedirect(route('login'));
});

test('users without staff access cannot view staff permit applications', function () {
    $this->actingAs(userWithPermissions([]))
        ->get(route('staff.permit-applications.index'))
        ->assertForbidden();
});

test('staff users with view permission can list permit applications', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-2026-00010',
    ]);

    PermitApplicationLine::factory()
        ->for($application)
        ->for(LineOfBusiness::factory()->create(['name' => 'Retail Store']))
        ->create([
            'declared_gross_sales_cents' => 125_000_00,
        ]);

    $this->actingAs($user)
        ->get(route('staff.permit-applications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Index')
            ->where('permitApplications.data.0.application_number', 'APP-2026-00010')
            ->where('permitApplications.data.0.business.name', $application->business->name)
            ->where('permitApplications.data.0.lines.0.line_of_business.name', 'Retail Store')
            ->where('can.create_permit_applications', false)
            ->where('can.assess_permit_applications', false)
        );
});

test('staff users with create permission can view the intake form', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::CreatePermitApplications,
    ]);

    LineOfBusiness::factory()->create([
        'name' => 'Restaurant',
        'code' => 'RESTAURANT',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('staff.permit-applications.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Create')
            ->where('currentApplicationYear', now()->year)
            ->where('applicationTypes.0.value', PermitApplicationType::New->value)
            ->where('lineOfBusinesses.0.name', 'Restaurant')
        );
});

test('staff users with create permission can record a permit application', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::CreatePermitApplications,
        UserPermission::ViewPermitApplications,
    ]);

    $lineOfBusiness = LineOfBusiness::factory()->create([
        'name' => 'Retail Store',
        'code' => 'RETAIL',
    ]);

    $response = $this->actingAs($user)
        ->post(route('staff.permit-applications.store'), [
            'owner_name' => 'Ana Cruz',
            'owner_email' => 'ana@example.test',
            'owner_phone' => '09171234567',
            'owner_address' => 'Poblacion, Ipil',
            'business_name' => 'Ana Trading',
            'trade_name' => 'Ana Store',
            'registration_number' => 'DTI-12345',
            'business_address' => 'Market Road',
            'barangay' => 'Poblacion',
            'application_number' => 'APP-2026-00011',
            'type' => PermitApplicationType::New->value,
            'application_year' => 2026,
            'line_of_business_id' => $lineOfBusiness->id,
            'declared_gross_sales_pesos' => '1234.50',
            'capital_investment_pesos' => '5000.75',
            'quantity' => 2,
        ]);

    $application = PermitApplication::query()
        ->where('application_number', 'APP-2026-00011')
        ->sole();

    $response->assertRedirect(route('staff.permit-applications.show', $application));

    expect(BusinessOwner::query()->where('name', 'Ana Cruz')->exists())->toBeTrue()
        ->and(Business::query()->where('name', 'Ana Trading')->exists())->toBeTrue()
        ->and($application->submitted_by_id)->toBe($user->id)
        ->and($application->lines()->sole()->declared_gross_sales_cents)->toBe(123_450)
        ->and($application->lines()->sole()->capital_investment_cents)->toBe(500_075)
        ->and($application->lines()->sole()->quantity)->toBe(2);
});

test('staff users can record a renewal application with explicit renewal policy boundary', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::CreatePermitApplications,
        UserPermission::ViewPermitApplications,
    ]);

    $lineOfBusiness = LineOfBusiness::factory()->create([
        'name' => 'Renewal Retail',
        'code' => 'RENEWAL-RETAIL',
    ]);

    $response = $this->actingAs($user)
        ->post(route('staff.permit-applications.store'), [
            'owner_name' => 'Renewal Owner',
            'business_name' => 'Renewal Trading',
            'application_number' => 'APP-RENEWAL-2026-0001',
            'type' => PermitApplicationType::Renewal->value,
            'application_year' => 2026,
            'line_of_business_id' => $lineOfBusiness->id,
            'declared_gross_sales_pesos' => '125000.00',
            'capital_investment_pesos' => '75000.00',
            'quantity' => 1,
        ]);

    $application = PermitApplication::query()
        ->where('application_number', 'APP-RENEWAL-2026-0001')
        ->sole();

    $response->assertRedirect(route('staff.permit-applications.show', $application));

    expect($application->type)->toBe(PermitApplicationType::Renewal)
        ->and($application->metadata['renewal_policy_boundary']['status'])->toBe('policy_boundary')
        ->and($application->metadata['renewal_policy_boundary']['unresolved_policy'])->toContain('PIL applicability and calculation');

    $this->actingAs($user)
        ->get(route('staff.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Show')
            ->where('permitApplication.type', PermitApplicationType::Renewal->value)
            ->where('permitApplication.renewal_policy_boundary.status', 'policy_boundary')
            ->where('permitApplication.renewal_policy_boundary.software_knows.gross_receipts_basis_is_relevant', true)
        );
});

test('staff users can record an amendment application with explicit amendment policy boundary', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::CreatePermitApplications,
        UserPermission::ViewPermitApplications,
    ]);

    $lineOfBusiness = LineOfBusiness::factory()->create([
        'name' => 'Amendment Retail',
        'code' => 'AMENDMENT-RETAIL',
    ]);

    $response = $this->actingAs($user)
        ->post(route('staff.permit-applications.store'), [
            'owner_name' => 'Amendment Owner',
            'business_name' => 'Amendment Trading',
            'application_number' => 'APP-AMENDMENT-2026-0001',
            'type' => PermitApplicationType::Amendment->value,
            'application_year' => 2026,
            'line_of_business_id' => $lineOfBusiness->id,
            'declared_gross_sales_pesos' => '125000.00',
            'capital_investment_pesos' => '75000.00',
            'quantity' => 1,
        ]);

    $application = PermitApplication::query()
        ->where('application_number', 'APP-AMENDMENT-2026-0001')
        ->sole();

    $response->assertRedirect(route('staff.permit-applications.show', $application));

    expect($application->type)->toBe(PermitApplicationType::Amendment)
        ->and($application->metadata['amendment_policy_boundary']['status'])->toBe('policy_boundary')
        ->and($application->metadata['amendment_policy_boundary']['unresolved_policy'])->toContain('amendment fee and assessment basis');

    $this->actingAs($user)
        ->get(route('staff.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Show')
            ->where('permitApplication.type', PermitApplicationType::Amendment->value)
            ->where('permitApplication.amendment_policy_boundary.status', 'policy_boundary')
            ->where('permitApplication.amendment_policy_boundary.software_knows.amended_fields_are_not_yet_structured', true)
        );
});

test('staff users with view permission can review a permit application', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-2026-00012',
    ]);

    PermitApplicationLine::factory()
        ->for($application)
        ->for(LineOfBusiness::factory()->create(['name' => 'Restaurant']))
        ->create();

    $this->actingAs($user)
        ->get(route('staff.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Show')
            ->where('permitApplication.application_number', 'APP-2026-00012')
            ->where('permitApplication.lines.0.line_of_business.name', 'Restaurant')
            ->where('can.assess_permit_applications', false)
            ->where('can.update_permit_application_status', false)
            ->where('can.view_permit_documents', true)
            ->where('permitDocumentGaps.0', 'Generated application form artifact captures current rescue intake facts only.')
            ->where('permitApplication.verification_boundary.can_verify_release', false)
        );
});

test('staff users with status permission can cancel a permit application', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::UpdatePermitApplicationStatus,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-2026-CANCEL',
        'status' => PermitApplicationStatus::Assessment,
    ]);

    $this->actingAs($user)
        ->post(route('staff.permit-applications.cancel', $application), [
            'reason' => 'Applicant requested cancellation before payment.',
        ])
        ->assertRedirect(route('staff.permit-applications.show', $application));

    $application->refresh();

    expect($application->status)->toBe(PermitApplicationStatus::Cancelled)
        ->and($application->metadata['terminal_state']['status'])->toBe(PermitApplicationStatus::Cancelled->value)
        ->and($application->metadata['terminal_state']['can_continue'])->toBeFalse()
        ->and($application->metadata['terminal_state']['reason'])->toBe('Applicant requested cancellation before payment.')
        ->and($application->metadata['status_history'][0]['from'])->toBe(PermitApplicationStatus::Assessment->value)
        ->and($application->metadata['status_history'][0]['to'])->toBe(PermitApplicationStatus::Cancelled->value)
        ->and($application->metadata['status_history'][0]['actor_id'])->toBe($user->id);
});

test('permit release attempt records unresolved policy boundary without releasing application', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::UpdatePermitApplicationStatus,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-2026-RELEASE-BLOCKED',
        'status' => PermitApplicationStatus::PendingPayment,
    ]);

    PaymentSchedule::factory()->for($application, 'permitApplication')->create([
        'status' => 'paid',
        'total_amount_cents' => 42_000,
        'paid_amount_cents' => 42_000,
    ]);

    $this->actingAs($user)
        ->from(route('staff.permit-applications.show', $application))
        ->post(route('staff.permit-applications.release', $application))
        ->assertRedirectBackWithErrors(['release_policy']);

    $application->refresh();

    expect($application->status)->toBe(PermitApplicationStatus::PendingPayment)
        ->and($application->metadata['release_policy_boundary']['blocked_transition'])->toBe(PermitApplicationStatus::Released->value)
        ->and($application->metadata['release_policy_boundary']['is_paid'])->toBeTrue()
        ->and($application->metadata['release_policy_boundary']['reason'])->toContain('Clearance completion');
});

test('release readiness evidence can be ready for authority review without permitting release', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::UpdatePermitApplicationStatus,
    ]);
    $application = permitDocumentFixtureWithCompletedClearances($user);
    $paymentSchedule = PaymentSchedule::factory()->for($application, 'permitApplication')->create([
        'status' => 'paid',
        'total_amount_cents' => 42_000,
        'paid_amount_cents' => 42_000,
    ]);
    $collection = TreasuryCollection::factory()
        ->for($paymentSchedule, 'paymentSchedule')
        ->for($application, 'permitApplication')
        ->for($paymentSchedule->assessment)
        ->create([
            'status' => 'receipted',
            'amount_cents' => 42_000,
        ]);
    Receipt::factory()
        ->for($collection, 'treasuryCollection')
        ->for($paymentSchedule, 'paymentSchedule')
        ->for($application, 'permitApplication')
        ->for($paymentSchedule->assessment)
        ->create([
            'receipt_number' => 'OR-RELEASE-READY',
            'amount_cents' => 42_000,
        ]);

    $readiness = app(DescribePermitReleaseReadiness::class)->handle($application);

    expect($readiness['ready_for_authority_review'])->toBeTrue()
        ->and($readiness['can_release'])->toBeFalse()
        ->and($readiness['prerequisites']['payment_schedule_paid'])->toBeTrue()
        ->and($readiness['prerequisites']['receipt_issued'])->toBeTrue()
        ->and($readiness['prerequisites']['clearances_completed'])->toBeTrue()
        ->and($readiness['blocked_by'])->toContain('official_signatories')
        ->and($readiness['authority_boundary']['status'])->toBe('ready_for_authority_review')
        ->and($readiness['authority_boundary']['software_knows']['payment_completed'])->toBeTrue()
        ->and($readiness['authority_boundary']['human_authority_decides'])->toContain('permit_legally_issued')
        ->and($readiness['authority_boundary']['software_records'])->toContain('authority_decision')
        ->and($readiness['authority_boundary']['artifact_statement'])->toContain('do not issue, release, or make a permit legally effective');

    $this->actingAs($user)
        ->get(route('staff.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Show')
            ->where('permitApplication.release_readiness.ready_for_authority_review', true)
            ->where('permitApplication.release_readiness.can_release', false)
            ->where('permitApplication.release_readiness.prerequisites.receipt_issued', true)
            ->where('permitApplication.release_readiness.authority_boundary.status', 'ready_for_authority_review')
            ->where('permitApplication.release_readiness.authority_boundary.software_knows.payment_completed', true)
        );
});

test('staff users without status permission cannot attempt permit release', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);
    $application = PermitApplication::factory()->create([
        'status' => PermitApplicationStatus::PendingPayment,
    ]);

    $this->actingAs($user)
        ->post(route('staff.permit-applications.release', $application))
        ->assertForbidden();

    expect($application->refresh()->metadata['release_policy_boundary'] ?? null)->toBeNull();
});

test('staff users without status permission cannot cancel a permit application', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);
    $application = PermitApplication::factory()->create([
        'status' => PermitApplicationStatus::Assessment,
    ]);

    $this->actingAs($user)
        ->post(route('staff.permit-applications.cancel', $application), [
            'reason' => 'Attempted cancellation.',
        ])
        ->assertForbidden();

    expect($application->refresh()->status)->toBe(PermitApplicationStatus::Assessment);
});

test('cancelled permit applications expose terminal state and unavailable continuation actions', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::AssessPermitApplications,
        UserPermission::UpdatePermitApplicationStatus,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-2026-CANCELLED',
        'status' => PermitApplicationStatus::Cancelled,
        'metadata' => [
            'terminal_state' => [
                'status' => PermitApplicationStatus::Cancelled->value,
                'is_terminal' => true,
                'can_continue' => false,
                'reason' => 'Applicant requested cancellation before payment.',
                'actor_id' => $user->id,
                'occurred_at' => now()->toIso8601String(),
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('staff.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Show')
            ->where('permitApplication.status', PermitApplicationStatus::Cancelled->value)
            ->where('permitApplication.can_continue', false)
            ->where('permitApplication.terminal_state.reason', 'Applicant requested cancellation before payment.')
            ->where('can.assess_permit_applications', true)
            ->where('can.update_permit_application_status', true)
        );

    $this->actingAs($user)
        ->get(route('staff.permit-applications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Index')
            ->where('permitApplications.data.0.application_number', 'APP-2026-CANCELLED')
            ->where('permitApplications.data.0.status', PermitApplicationStatus::Cancelled->value)
            ->where('permitApplications.data.0.can_continue', false)
        );
});

test('permit application review exposes release policy boundary evidence', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::UpdatePermitApplicationStatus,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-2026-RELEASE-EVIDENCE',
        'status' => PermitApplicationStatus::PendingPayment,
        'metadata' => [
            'release_policy_boundary' => [
                'status' => PermitApplicationStatus::PendingPayment->value,
                'payment_schedule_id' => 100,
                'payment_schedule_status' => 'paid',
                'is_paid' => true,
                'receipt_count' => 1,
                'blocked_transition' => PermitApplicationStatus::Released->value,
                'reason' => 'Clearance completion, permit issuance authority, signatories, QR verification, and legacy Released status semantics remain unresolved.',
                'occurred_at' => now()->toIso8601String(),
            ],
        ],
    ]);
    PaymentSchedule::factory()->for($application, 'permitApplication')->create([
        'status' => 'paid',
        'total_amount_cents' => 42_000,
        'paid_amount_cents' => 42_000,
    ]);

    $this->actingAs($user)
        ->get(route('staff.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Show')
            ->where('permitApplication.release_policy_boundary.blocked_transition', PermitApplicationStatus::Released->value)
            ->where('permitApplication.release_policy_boundary.is_paid', true)
            ->where('permitApplication.release_policy_boundary.receipt_count', 1)
            ->where('can.attempt_release', true)
        );
});

test('permit application review initializes and exposes clearance checklist evidence', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::CompletePermitClearances,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-2026-CLEARANCE',
        'status' => PermitApplicationStatus::PendingPayment,
    ]);
    PermitClearance::factory()->for($application, 'permitApplication')->create([
        'code' => 'bplo_review',
        'label' => 'BPLO review',
        'source_snapshot' => [
            'policy_note' => 'Represents BPLO staff review evidence only; final release authority remains unresolved.',
        ],
    ]);
    PermitClearance::factory()->for($application, 'permitApplication')->create([
        'code' => 'treasury_payment',
        'label' => 'Treasury payment evidence',
        'source_snapshot' => [
            'policy_note' => 'Represents visible payment and receipt evidence; reconciliation policy remains unresolved.',
        ],
    ]);
    PermitClearance::factory()->for($application, 'permitApplication')->create([
        'code' => 'release_authority',
        'label' => 'Release authority',
        'source_snapshot' => [
            'policy_note' => 'Represents the unresolved release/signatory authority boundary, not actual permit issuance.',
        ],
    ]);

    $this->actingAs($user)
        ->get(route('staff.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Show')
            ->where('permitApplication.clearance_summary.completed', 0)
            ->where('permitApplication.clearance_summary.total', 3)
            ->where('permitApplication.clearance_summary.all_completed', false)
            ->where('permitApplication.clearances.0.code', 'bplo_review')
            ->where('can.complete_clearances', true)
        );

    expect($application->clearances()->count())->toBe(3);
});

test('staff users with clearance permission can complete a clearance without releasing application', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::CompletePermitClearances,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-2026-CLEARANCE-COMPLETE',
        'status' => PermitApplicationStatus::PendingPayment,
    ]);

    app(EnsurePermitApplicationClearances::class)->handle($application);

    $clearance = $application->clearances()->where('code', 'bplo_review')->sole();

    $this->actingAs($user)
        ->post(route('staff.permit-applications.clearances.complete', [$application, $clearance]), [
            'remarks' => 'BPLO review evidence confirmed.',
        ])
        ->assertRedirect(route('staff.permit-applications.show', $application));

    $application->refresh();
    $clearance->refresh();

    expect($clearance->status)->toBe(PermitClearanceStatus::Completed)
        ->and($clearance->completed_by_id)->toBe($user->id)
        ->and($clearance->remarks)->toBe('BPLO review evidence confirmed.')
        ->and($clearance->source_snapshot['completion']['policy_note'])->toContain('does not release')
        ->and($application->status)->toBe(PermitApplicationStatus::PendingPayment);
});

test('staff users without clearance permission cannot complete clearances', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);
    $application = PermitApplication::factory()->create([
        'status' => PermitApplicationStatus::PendingPayment,
    ]);
    $clearance = PermitClearance::factory()->for($application, 'permitApplication')->create();

    $this->actingAs($user)
        ->post(route('staff.permit-applications.clearances.complete', [$application, $clearance]), [
            'remarks' => 'Unauthorized.',
        ])
        ->assertForbidden();

    expect($clearance->refresh()->status)->toBe(PermitClearanceStatus::Pending);
});

test('staff users with view permission can open an application form pdf artifact', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $application = permitDocumentFixture();

    $response = $this->actingAs($user)
        ->get(route('staff.permit-applications.application-form.pdf', $application))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="app-2026-00013-application-form.pdf"');

    $pdf = $response->getContent();

    expect($pdf)
        ->toStartWith('%PDF-1.4')
        ->toContain('Business Application Form Artifact')
        ->toContain('APP-2026-00013')
        ->toContain('Permit Artifact Store')
        ->toContain('Permit Owner')
        ->toContain('permit-owner@example.test')
        ->toContain('RETAIL')
        ->toContain('PHP 125,000.00')
        ->toContain('Application form artifact renders currently captured intake facts')
        ->toContain('Documentary requirements, uploaded files, and checklist evidence are not yet')
        ->toContain('represented in this artifact.')
        ->and(permitPdfPageCount($pdf))->toBe(1);
});

test('application form pdf output is deterministic for the same persisted permit facts', function () {
    $application = permitDocumentFixture();

    $renderer = app(RenderApplicationFormPdf::class);

    expect($renderer->handle($application))->toBe($renderer->handle($application->fresh()));
});

test('staff users without view permission cannot open application form pdf artifacts', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::CreatePermitApplications,
    ]);

    $application = PermitApplication::factory()->create();

    $this->actingAs($user)
        ->get(route('staff.permit-applications.application-form.pdf', $application))
        ->assertForbidden();
});

test('staff users with view permission can open a permit pdf artifact', function () {
    config()->set('municipality.signatories.permit', [
        [
            'role' => 'Municipal Mayor',
            'name' => 'Hon. Ipil Mayor',
            'title' => 'Municipal Mayor',
            'authority_status' => 'unverified',
        ],
        [
            'role' => 'BPLO Officer',
            'name' => 'Maria BPLO',
            'title' => 'BPLO Officer',
            'authority_status' => 'unverified',
        ],
    ]);

    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $application = permitDocumentFixtureWithCompletedClearances($user);

    $response = $this->actingAs($user)
        ->get(route('staff.permit-applications.permit.pdf', $application))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="app-2026-00013-permit.pdf"');

    $pdf = $response->getContent();

    expect($pdf)
        ->toStartWith('%PDF-1.4')
        ->toContain("Mayor's Permit Artifact")
        ->toContain('APP-2026-00013')
        ->toContain('Permit Artifact Store')
        ->toContain('Permit Owner')
        ->toContain('RETAIL')
        ->toContain('PHP 125,000.00')
        ->toContain('CLEARANCE EVIDENCE')
        ->toContain('BPLO review')
        ->toContain('Treasury payment evidence')
        ->toContain('Release authority')
        ->toContain('Completed')
        ->toContain('Clearance completion evidence is informational')
        ->toContain('Actual permit release remains blocked')
        ->toContain('DOCUMENT SIGNATORY CONFIGURATION')
        ->toContain('Hon. Ipil Mayor')
        ->toContain('Maria BPLO')
        ->toContain('Configured signatories are document evidence only')
        ->toContain('AUTHORITY BOUNDARY')
        ->toContain('Software knows')
        ->toContain('Human authority decides')
        ->toContain('Generated permit artifacts support authority review')
        ->toContain('VERIFICATION BOUNDARY')
        ->toContain('PVA-'.$application->id.'-')
        ->toContain(route('public.permits.verify', [
            'permitApplication' => $application,
            'verificationCode' => app(DescribePermitVerificationBoundary::class)->handle($application)['reference'],
        ]))
        ->toContain('Public verification currently confirms artifact identity only')
        ->toContain('Generated permit artifact; this route does not release or issue a permit.')
        ->toContain('verification remains unresolved.')
        ->and(permitPdfPageCount($pdf))->toBeGreaterThanOrEqual(1);
});

test('permit verification boundary provides a deterministic public artifact reference without releasing', function () {
    $application = permitDocumentFixture();

    $verification = app(DescribePermitVerificationBoundary::class)->handle($application);

    expect($verification['reference'])->toStartWith('PVA-'.$application->id.'-')
        ->and($verification['url'])->toBe(route('public.permits.verify', [
            'permitApplication' => $application,
            'verificationCode' => $verification['reference'],
        ]))
        ->and($verification['status'])->toBe('artifact_only')
        ->and($verification['released'])->toBeFalse()
        ->and($verification['can_verify_release'])->toBeFalse()
        ->and(app(DescribePermitVerificationBoundary::class)->handle($application->fresh()))->toBe($verification);
});

test('public permit verification confirms artifact identity but not release', function () {
    $application = permitDocumentFixture();
    $verification = app(DescribePermitVerificationBoundary::class)->handle($application);

    $this->get($verification['url'])
        ->assertSuccessful()
        ->assertJson([
            'schema_version' => 'bpls.permit-verification-boundary.v1',
            'verification' => [
                'reference' => $verification['reference'],
                'status' => 'artifact_only',
                'can_verify_release' => false,
                'released' => false,
            ],
            'permit' => [
                'application_number' => 'APP-2026-00013',
                'application_year' => 2026,
                'application_status' => PermitApplicationStatus::Draft->value,
                'business_name' => 'Permit Artifact Store',
                'trade_name' => 'Artifact Store',
            ],
            'release_readiness' => [
                'can_release' => false,
                'authority_boundary' => [
                    'status' => 'awaiting_prerequisites',
                    'software_knows' => [
                        'permit_artifact_generated' => true,
                    ],
                    'artifact_statement' => 'Generated permit artifacts support authority review but do not issue, release, or make a permit legally effective.',
                ],
            ],
        ]);
});

test('public permit verification refuses mismatched references', function () {
    $application = permitDocumentFixture();

    $this->get(route('public.permits.verify', [
        'permitApplication' => $application,
        'verificationCode' => 'PVA-'.$application->id.'-invalid-reference',
    ]))->assertNotFound();
});

test('permit document configuration keeps signatory authority explicit', function () {
    config()->set('municipality.name', 'Municipality of Ipil');
    config()->set('municipality.province', 'Zamboanga Sibugay');
    config()->set('municipality.signatories.permit', [
        [
            'role' => 'Municipal Mayor',
            'name' => 'Hon. Ipil Mayor',
            'title' => 'Municipal Mayor',
            'authority_status' => 'verified',
        ],
        [
            'role' => 'BPLO Officer',
            'name' => 'Maria BPLO',
            'title' => 'BPLO Officer',
            'authority_status' => 'unverified',
        ],
    ]);

    $configuration = app(DescribePermitDocumentConfiguration::class)->handle();

    expect($configuration['municipality']['name'])->toBe('Municipality of Ipil')
        ->and($configuration['municipality']['province'])->toBe('Zamboanga Sibugay')
        ->and($configuration['authority_verified'])->toBeFalse()
        ->and($configuration['permit_signatories'][0]['name'])->toBe('Hon. Ipil Mayor')
        ->and($configuration['permit_signatories'][1]['authority_status'])->toBe('unverified')
        ->and($configuration['policy_note'])->toContain('do not authorize permit release');
});

test('permit pdf output is deterministic for the same persisted permit facts', function () {
    $application = permitDocumentFixture();

    $renderer = app(RenderPermitPdf::class);

    expect($renderer->handle($application))->toBe($renderer->handle($application->fresh()));
});

test('staff users without view permission cannot open permit pdf artifacts', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::CreatePermitApplications,
    ]);

    $application = PermitApplication::factory()->create();

    $this->actingAs($user)
        ->get(route('staff.permit-applications.permit.pdf', $application))
        ->assertForbidden();
});

test('staff users without create permission cannot record a permit application', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $lineOfBusiness = LineOfBusiness::factory()->create();

    $this->actingAs($user)
        ->post(route('staff.permit-applications.store'), [
            'owner_name' => 'Ana Cruz',
            'business_name' => 'Ana Trading',
            'type' => PermitApplicationType::New->value,
            'application_year' => 2026,
            'line_of_business_id' => $lineOfBusiness->id,
            'declared_gross_sales_pesos' => '100.00',
            'capital_investment_pesos' => '100.00',
            'quantity' => 1,
        ])
        ->assertForbidden();
});

function permitDocumentFixture(): PermitApplication
{
    $owner = BusinessOwner::factory()->create([
        'name' => 'Permit Owner',
        'email' => 'permit-owner@example.test',
        'phone' => '555-0101',
        'address' => 'Owner Permit Address',
    ]);
    $business = Business::factory()->for($owner, 'owner')->create([
        'name' => 'Permit Artifact Store',
        'trade_name' => 'Artifact Store',
        'registration_number' => 'BN-PERMIT-001',
        'address' => 'Ipil Central',
        'barangay' => 'Poblacion',
    ]);
    $application = PermitApplication::factory()->for($business)->create([
        'application_number' => 'APP-2026-00013',
        'application_year' => 2026,
        'submitted_at' => now()->startOfSecond(),
    ]);

    PermitApplicationLine::factory()
        ->for($application)
        ->for(LineOfBusiness::factory()->create([
            'name' => 'Retail Store',
            'code' => 'RETAIL',
        ]))
        ->create([
            'declared_gross_sales_cents' => 12_500_000,
            'capital_investment_cents' => 25_000_000,
            'quantity' => 2,
        ]);

    Assessment::factory()->for($application)->create([
        'sequence' => 1,
        'status' => AssessmentStatus::Computed,
        'total_amount_cents' => 42_000,
        'assessed_at' => now()->startOfSecond(),
    ]);

    return $application;
}

function permitDocumentFixtureWithCompletedClearances(User $user): PermitApplication
{
    $application = permitDocumentFixture();
    $application = app(EnsurePermitApplicationClearances::class)->handle($application);

    foreach ($application->clearances as $clearance) {
        app(CompletePermitClearance::class)->handle($clearance, $user, 'Permit artifact clearance evidence.');
    }

    return $application->refresh();
}

function permitPdfPageCount(string $pdf): int
{
    preg_match_all('/\/Type \/Page\b/', $pdf, $matches);

    return count($matches[0]);
}
