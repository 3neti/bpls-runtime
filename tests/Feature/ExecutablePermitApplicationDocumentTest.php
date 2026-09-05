<?php

use App\Actions\BuildExecutablePermitApplicationDocument;
use App\Actions\ExecutePersistedLifecycleScenario;
use App\Actions\RenderApplicationFormPdf;
use App\Enums\PermitApplicationType;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Artisan::call('bpls:install');
});

test('lodging freezes a granular applicant declaration that later lifecycle actions do not rewrite', function (): void {
    app(ExecutePersistedLifecycleScenario::class)->handle(NewApplicationHappyPathDefinition::Id);

    $application = PermitApplication::query()->with(['declaration', 'lines'])->sole();
    $declaration = $application->declaration;

    expect($declaration)->toBeInstanceOf(PermitApplicationDeclaration::class)
        ->and($declaration->snapshot_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and(data_get($declaration->snapshot, 'taxpayer.last_name'))->toBe('Owner')
        ->and(data_get($declaration->snapshot, 'taxpayer.first_name'))->toBe('Scenario')
        ->and(data_get($declaration->snapshot, 'taxpayer.middle_name'))->toBe('Synthetic')
        ->and(data_get($declaration->snapshot, 'business_address.street'))->toBe('Synthetic Ipil product laboratory address')
        ->and(data_get($declaration->snapshot, 'owner_address.street'))->toBe('Synthetic Ipil product laboratory address')
        ->and(data_get($declaration->snapshot, 'lines_of_business.0'))->toHaveKeys([
            'code', 'name', 'number_of_units', 'capitalization_cents',
            'essential_gross_sales_cents', 'non_essential_gross_sales_cents',
        ])
        ->and(data_get($declaration->snapshot, 'lines_of_business.0.essential_gross_sales_cents'))->toBe(0)
        ->and(data_get($declaration->snapshot, 'lines_of_business.0.non_essential_gross_sales_cents'))->toBeInt();

    $frozenHash = $declaration->snapshot_hash;
    $application->forceFill(['metadata' => [...$application->metadata, 'later_evaluation_note' => 'Municipal truth changed later.']])->save();

    expect($declaration->fresh()->snapshot_hash)->toBe($frozenHash)
        ->and(fn () => $declaration->forceFill(['snapshot_hash' => str_repeat('0', 64)])->save())
        ->toThrow(LogicException::class, 'immutable');
});

test('the same executable document progressively projects immutable assessment treasury and treasurer truth', function (): void {
    app(ExecutePersistedLifecycleScenario::class)->handle(NewApplicationHappyPathDefinition::Id);
    $application = PermitApplication::query()->where('type', PermitApplicationType::New)->sole();
    $document = app(BuildExecutablePermitApplicationDocument::class)->handle($application);
    $pdf = app(RenderApplicationFormPdf::class)->handle($application);
    $offices = collect(data_get($document, 'page_2_assessment.offices'));

    expect(data_get($document, 'declaration.state'))->toBe('frozen')
        ->and(data_get($document, 'page_2_assessment.status'))->toBe('assessment_prepared')
        ->and(data_get($document, 'page_2_assessment.populated_from_canonical_assessment'))->toBeTrue()
        ->and(data_get($document, 'page_2_assessment.emerging_total_amount_cents'))->toBe(122_000)
        ->and(data_get($document, 'page_2_assessment.required_unresolved_charge_count'))->toBe(0)
        ->and($offices->pluck('code')->all())->toBe(['assessor', 'engineering', 'health', 'menro'])
        ->and($offices->sum('payment_order_count'))->toBe(6)
        ->and($offices->sum('total_amount_cents'))->toBe(87_000)
        ->and($offices->every(fn (array $office): bool => $office['status'] === 'certified'))->toBeTrue()
        ->and($offices->every(fn (array $office): bool => data_get($office, 'certification.statement') === 'Electronically certified'))->toBeTrue()
        ->and(data_get($document, 'computation_assessment_slip.total_amount_cents'))->toBe(122_000)
        ->and(data_get($document, 'computation_assessment_slip.line_count'))->toBe(7)
        ->and(data_get($document, 'treasury_counter_check.statement'))->toBe('Counter-check completed - no correction')
        ->and(data_get($document, 'municipal_treasurer.exact_approval'))->toBeTrue()
        ->and(data_get($document, 'payment_reference.payable.total_amount_cents'))->toBe(122_000)
        ->and(data_get($document, 'official_receipt_reference'))->toBeNull()
        ->and(data_get($document, 'permit_reference.permit_number'))->toBeNull()
        ->and(data_get($document, 'permit'))->toBe([
            'status' => 'not_issued',
            'statement' => 'Permit not yet issued',
            'mayor_signature_authority' => 'unresolved',
        ])
        ->and($pdf)->toContain('MUNICIPAL PROCESSING CONTINUATION SHEET')
        ->and($pdf)->toContain('OFFICE DETERMINATIONS AND PAYMENT ORDERS')
        ->and($pdf)->toContain('WORKING TOTAL PHP 1,220.00')
        ->and($pdf)->toContain('ASSESSMENT REFERENCE')
        ->and($pdf)->toContain('ASSESSED AMOUNT')
        ->and($pdf)->toContain('PAGE 2-A')
        ->and($pdf)->toContain('PHP 1,220.00');
});

test('new and renewal documents preserve one owner and business chronology with independent frozen declarations', function (): void {
    app(ExecutePersistedLifecycleScenario::class)->handle(NewApplicationHappyPathDefinition::Id);
    app(ExecutePersistedLifecycleScenario::class)->handle(RenewalHappyPathDefinition::Id);

    $applications = PermitApplication::query()->with('declaration')->orderBy('application_year')->get();

    expect($applications)->toHaveCount(2)
        ->and($applications->pluck('application_year')->all())->toBe([2025, 2026])
        ->and($applications->pluck('business_id')->unique())->toHaveCount(1)
        ->and($applications->every(fn (PermitApplication $application): bool => $application->declaration instanceof PermitApplicationDeclaration))->toBeTrue()
        ->and($applications->pluck('declaration.snapshot_hash')->unique())->toHaveCount(2)
        ->and(data_get($applications->first()->declaration->snapshot, 'application.type'))->toBe('new')
        ->and(data_get($applications->last()->declaration->snapshot, 'application.type'))->toBe('renewal')
        ->and($applications->every(fn (PermitApplication $application): bool => app(BuildExecutablePermitApplicationDocument::class)->handle($application)['computation_assessment_slip']['total_amount_cents'] === 122_000))->toBeTrue();
});

test('the citizen document page receives the executable projection rather than recalculating it in the browser', function (): void {
    app(ExecutePersistedLifecycleScenario::class)->handle(NewApplicationHappyPathDefinition::Id);
    $application = PermitApplication::query()->where('type', PermitApplicationType::New)->sole();
    $citizen = User::query()->where('email', 'scenario-citizen@example.test')->sole();

    $this->withoutVite()
        ->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('citizen/permit-applications/Show')
            ->where('executableDocument.declaration.state', 'frozen')
            ->where('executableDocument.page_2_assessment.populated_from_canonical_assessment', true)
            ->where('executableDocument.page_2_assessment.offices.0.label', 'Municipal Assessor')
            ->where('executableDocument.page_2_assessment.offices.0.certification.statement', 'Electronically certified')
            ->where('executableDocument.computation_assessment_slip.total_amount_cents', 122_000)
            ->where('executableDocument.treasury_counter_check.result', 'no_correction')
            ->where('executableDocument.municipal_treasurer.action', 'approved')
            ->where('executableDocument.permit.statement', 'Permit not yet issued'));
});

test('the executable html preserves the Ipil document nouns and mobile line grammar', function (): void {
    $create = file_get_contents(resource_path('js/pages/permit-applications/Create.vue'));
    $document = file_get_contents(resource_path('js/components/permit-applications/IpilExecutableDocument.vue'));
    $processingSheet = file_get_contents(resource_path('js/components/permit-applications/IpilMunicipalProcessingSheet.vue'));

    expect($create)->toContain('Application Form for Business Permit')
        ->and($create)->toContain('owner_last_name')
        ->and($create)->toContain('owner_first_name')
        ->and($create)->toContain('owner_middle_name')
        ->and($create)->toContain('name="application_year"')
        ->and($create)->toContain('Business Address')
        ->and($create)->toContain("Owner's Address")
        ->and($create)->toContain('Gross Sales Essential')
        ->and($create)->toContain('Gross Sales Non-Essential')
        ->and($create)->toContain('Fill remaining fields')
        ->and($create)->toContain('Legacy Ipil specimen pool')
        ->and($create)->toContain('Source specimen')
        ->and($create)->toContain('Load selected legacy specimen')
        ->and($create)->toContain('Anything you have')
        ->and($create)->toContain('already entered stays unchanged')
        ->and($create)->not->toContain('eyebrow="Step 1"')
        ->and($document)->toContain('IpilMunicipalProcessingSheet')
        ->and($document)->not->toContain('Not used by Ipil')
        ->and($document)->toContain('const snapshot = computed')
        ->and($document)->toContain('lg:hidden')
        ->and($processingSheet)->toContain('Municipal Processing Continuation Sheet')
        ->and($processingSheet)->toContain('Office Determinations and Payment Orders')
        ->and($processingSheet)->toContain('Processing working total')
        ->and($processingSheet)->toContain('Assessment Reference')
        ->and($processingSheet)->toContain('BPLS system-generated municipal processing record')
        ->and($processingSheet)->toContain('index += 8')
        ->and($processingSheet)->toContain('Page 2-')
        ->and($processingSheet)->not->toContain('Ready for Assessment preparation')
        ->and($processingSheet)->not->toContain('Awaiting the mandatory BPLO routing determination.');
});

test('application page 2 waits visibly for the mandatory BPLO routing determination', function (): void {
    $application = PermitApplication::factory()->create();
    $document = app(BuildExecutablePermitApplicationDocument::class)->handle($application);

    expect(data_get($document, 'page_2_assessment.status'))->toBe('awaiting_bplo_routing')
        ->and(data_get($document, 'page_2_assessment.statement'))->toBe('Awaiting the mandatory BPLO routing determination.')
        ->and(data_get($document, 'page_2_assessment.offices'))->toBe([])
        ->and(data_get($document, 'page_2_assessment.populated_from_canonical_assessment'))->toBeFalse()
        ->and(data_get($document, 'official_receipt_reference'))->toBeNull()
        ->and(data_get($document, 'permit_reference.permit_number'))->toBeNull();
});
