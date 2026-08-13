<?php

use App\Actions\RenderApplicationFormPdf;
use App\Actions\RenderPermitPdf;
use App\Enums\AssessmentStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
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
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $application = permitDocumentFixture();

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
        ->toContain('Generated permit artifact; this route does not release or issue a permit.')
        ->toContain('QR verification route and public verification behavior are not yet implemented.')
        ->and(permitPdfPageCount($pdf))->toBe(1);
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

function permitPdfPageCount(string $pdf): int
{
    preg_match_all('/\/Type \/Page\b/', $pdf, $matches);

    return count($matches[0]);
}
