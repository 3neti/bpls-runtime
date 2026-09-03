<?php

use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\RenderAssessmentPdf;
use App\Enums\AssessmentStatus;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\UserPermission;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\ProvisionalUatPermitCompletion;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;

test('guests are redirected away from staff permit assessments', function () {
    $this->get(route('staff.permit-applications.assessments.index'))
        ->assertRedirect(route('login'));
});

test('users without staff access cannot view the staff permit assessment index', function () {
    $this->actingAs(userWithPermissions([]))
        ->get(route('staff.permit-applications.assessments.index'))
        ->assertForbidden();
});

test('staff users with view permission can view the staff permit assessment index', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-2026-00001',
    ]);

    PermitApplicationLine::factory()
        ->for($application)
        ->for(LineOfBusiness::factory()->create(['name' => 'Retail Store']))
        ->create();

    $this->actingAs($user)
        ->get(route('staff.permit-applications.assessments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Assessments/Index')
            ->where('permitApplications.data.0.application_number', 'APP-2026-00001')
            ->where('permitApplications.data.0.line_count', 1)
            ->where('permitApplications.data.0.latest_assessment', null)
            ->where('can.assess_permit_applications', false)
        );
});

test('staff users with assess permission can compute an assessment from the staff surface', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::AssessPermitApplications,
    ]);

    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
    ]);

    FeeRule::factory()->create([
        'code' => 'APPLICATION-FEE',
        'scope' => FeeRuleScope::Application,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'amount_cents' => 25_000,
        'effective_from' => '2026-01-01',
    ]);

    $response = $this->actingAs($user)
        ->post(route('staff.permit-applications.assessments.store', $application));

    $assessment = Assessment::query()->sole();

    $response->assertRedirect(route('staff.permit-applications.assessments.show', $assessment));

    expect($assessment->total_amount_cents)->toBe(25_000);
});

test('assessment recomputation is refused after payment scheduling or preview release', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::AssessPermitApplications,
    ]);
    $application = PermitApplication::factory()->create(['application_year' => 2026]);
    $assessment = Assessment::factory()->for($application)->create([
        'status' => AssessmentStatus::Computed,
        'superseded_at' => null,
    ]);
    PaymentSchedule::factory()->create([
        'permit_application_id' => $application->id,
        'assessment_id' => $assessment->id,
    ]);

    $this->from(route('staff.permit-applications.show', $application))
        ->actingAs($user)
        ->post(route('staff.permit-applications.assessments.store', $application))
        ->assertRedirect(route('staff.permit-applications.show', $application))
        ->assertSessionHasErrors(['assessment_policy']);

    expect($application->assessments()->count())->toBe(1)
        ->and($assessment->fresh()->superseded_at)->toBeNull();

    $previewApplication = PermitApplication::factory()->create(['application_year' => 2026]);
    ProvisionalUatPermitCompletion::factory()->for($previewApplication)->create([
        'status' => 'released_in_preview',
        'released_at' => now(),
    ]);

    expect(fn () => app(CreateAssessmentForPermitApplication::class)->handle($previewApplication, $user))
        ->toThrow(LogicException::class, 'preview-completed permit');
});

test('staff assessment surface records unsupported formula policy boundary without creating an assessment', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::AssessPermitApplications,
    ]);

    $application = PermitApplication::factory()->create([
        'application_number' => 'APP-FORMULA-BOUNDARY',
        'application_year' => 2026,
    ]);

    FeeRule::factory()->create([
        'code' => 'FORMULA-FEE',
        'scope' => FeeRuleScope::Application,
        'calculation_type' => FeeRuleCalculationType::Formula,
        'effective_from' => '2026-01-01',
    ]);

    $expectedMessage = 'Formula assessment policy is not implemented for fee rule [FORMULA-FEE].';

    $this
        ->from(route('staff.permit-applications.assessments.index'))
        ->actingAs($user)
        ->post(route('staff.permit-applications.assessments.store', $application))
        ->assertRedirectBackWithErrors(['assessment_policy']);

    $application->refresh();

    expect(Assessment::query()->count())->toBe(0)
        ->and($application->metadata['assessment_policy_boundary']['status'])->toBe('blocked')
        ->and($application->metadata['assessment_policy_boundary']['reason'])->toBe($expectedMessage);

    $this->actingAs($user)
        ->get(route('staff.permit-applications.assessments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Assessments/Index')
            ->where('permitApplications.data.0.application_number', 'APP-FORMULA-BOUNDARY')
            ->where('permitApplications.data.0.latest_assessment', null)
            ->where('permitApplications.data.0.assessment_policy_boundary.status', 'blocked')
            ->where('permitApplications.data.0.assessment_policy_boundary.reason', $expectedMessage)
        );
});

test('staff users without assess permission cannot compute an assessment', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $application = PermitApplication::factory()->create();

    $this->actingAs($user)
        ->post(route('staff.permit-applications.assessments.store', $application))
        ->assertForbidden();
});

test('staff users with view permission can review a computed assessment', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::AssessPermitApplications,
    ]);

    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
    ]);

    FeeRule::factory()->create([
        'code' => 'APPLICATION-FEE',
        'name' => 'Application Fee',
        'scope' => FeeRuleScope::Application,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'amount_cents' => 25_000,
        'effective_from' => '2026-01-01',
    ]);

    $this->actingAs($user)
        ->post(route('staff.permit-applications.assessments.store', $application));

    $assessment = Assessment::query()->sole();

    $this->actingAs($user)
        ->get(route('staff.permit-applications.assessments.show', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Assessments/Show')
            ->where('assessment.total_amount_cents', 25_000)
            ->where('assessment.lines.0.code', 'APPLICATION-FEE')
            ->where('assessment.lines.0.name', 'Application Fee')
            ->where('assessmentReconciliation', null)
            ->where('can.view_assessment_documents', true)
            ->where('assessmentDocumentGaps.0', 'The generated assessment document shows the recorded assessment lines only; it does not recalculate fees or taxes.')
        );
});

test('assessment review compares a bound legacy specimen with the immutable computed assessment', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);
    $application = PermitApplication::factory()->create([
        'metadata' => [
            'laboratory_assessment_reconciliation' => laboratoryAssessmentReconciliationMetadata(765_432),
        ],
    ]);
    $assessment = Assessment::factory()->for($application)->create([
        'total_amount_cents' => 122_000,
    ]);
    AssessmentLine::factory()->for($assessment)->create([
        'code' => 'BUSINESS-TAX',
        'name' => 'Business Tax',
        'category' => FeeRuleCategory::Tax,
        'amount_cents' => 33_000,
    ]);
    AssessmentLine::factory()->for($assessment)->create([
        'code' => 'APPLICATION-FEES',
        'name' => 'Application-wide Fees',
        'category' => FeeRuleCategory::Fee,
        'amount_cents' => 89_000,
    ]);

    $this->actingAs($user)
        ->get(route('staff.permit-applications.assessments.show', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Assessments/Show')
            ->where('assessmentReconciliation.status', 'difference')
            ->where('assessmentReconciliation.comparable', true)
            ->where('assessmentReconciliation.source.total_amount_cents', 765_432)
            ->where('assessmentReconciliation.source.component_total_amount_cents', 765_432)
            ->where('assessmentReconciliation.source.internally_reconciles', true)
            ->where('assessmentReconciliation.computed.total_amount_cents', 122_000)
            ->where('assessmentReconciliation.computed.component_total_amount_cents', 122_000)
            ->where('assessmentReconciliation.computed.internally_reconciles', true)
            ->where('assessmentReconciliation.comparison.delta_amount_cents', -643_432)
            ->where('assessmentReconciliation.comparison.absolute_delta_amount_cents', 643_432)
            ->where('assessmentReconciliation.comparison.direction', 'legacy_source_higher')
            ->where('assessmentReconciliation.comparison.component_identity_mapping', 'not_established')
            ->where('assessmentReconciliation.operational_effect', false)
            ->has('assessmentReconciliation.source.schedules.0.fees', 2)
            ->has('assessmentReconciliation.computed.lines', 2)
        );
});

test('assessment reconciliation refuses altered legacy source evidence', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);
    $metadata = laboratoryAssessmentReconciliationMetadata(765_432);
    $metadata['historical_assessment']['recorded_total_amount_cents'] = 1;
    $application = PermitApplication::factory()->create([
        'metadata' => ['laboratory_assessment_reconciliation' => $metadata],
    ]);
    $assessment = Assessment::factory()->for($application)->create([
        'total_amount_cents' => 122_000,
    ]);

    $this->actingAs($user)
        ->get(route('staff.permit-applications.assessments.show', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assessmentReconciliation.status', 'source_evidence_invalid')
            ->where('assessmentReconciliation.comparable', false)
            ->where('assessmentReconciliation.operational_effect', false)
            ->missing('assessmentReconciliation.source')
            ->missing('assessmentReconciliation.computed')
        );
});

test('assessment reconciliation identifies an exact total match without mapping fee identities', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);
    $application = PermitApplication::factory()->create([
        'metadata' => [
            'laboratory_assessment_reconciliation' => laboratoryAssessmentReconciliationMetadata(765_432),
        ],
    ]);
    $assessment = Assessment::factory()->for($application)->create([
        'total_amount_cents' => 765_432,
    ]);
    AssessmentLine::factory()->for($assessment)->create([
        'amount_cents' => 765_432,
    ]);

    $this->actingAs($user)
        ->get(route('staff.permit-applications.assessments.show', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assessmentReconciliation.status', 'exact_match')
            ->where('assessmentReconciliation.comparison.delta_amount_cents', 0)
            ->where('assessmentReconciliation.comparison.direction', 'equal')
            ->where('assessmentReconciliation.comparison.component_identity_mapping', 'not_established')
        );
});

test('staff users with view permission can open an assessment pdf artifact', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $assessment = assessmentDocumentFixture();

    $response = $this->actingAs($user)
        ->get(route('staff.permit-applications.assessments.pdf', $assessment))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="app-2026-assessment-assessment-1.pdf"');

    $pdf = $response->getContent();

    expect($pdf)
        ->toStartWith('%PDF-1.4')
        ->toContain('COMPUTATION/ASSESSMENT SLIP')
        ->toContain('Reference: not officially assigned')
        ->toContain('APP-2026-ASSESSMENT')
        ->toContain('Assessment Artifact Store')
        ->toContain('Assessment Owner')
        ->toContain('LINE OF BUSINESS')
        ->toContain('Business Tax')
        ->toContain('Php. 420.00')
        ->toContain('SCHEDULE OF PAYMENTS')
        ->toContain('BLOCKED - MUNICIPAL FISCAL DECISION.')
        ->and(assessmentPdfPageCount($pdf))->toBe(1);
});

test('assessment pdf output is deterministic for the same persisted assessment facts', function () {
    $assessment = assessmentDocumentFixture();

    $renderer = app(RenderAssessmentPdf::class);

    expect($renderer->handle($assessment))->toBe($renderer->handle($assessment->fresh()));
});

test('staff users without view permission cannot open assessment pdf artifacts', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::AssessPermitApplications,
    ]);

    $assessment = Assessment::factory()->create();

    $this->actingAs($user)
        ->get(route('staff.permit-applications.assessments.pdf', $assessment))
        ->assertForbidden();
});

function assessmentDocumentFixture(): Assessment
{
    $owner = BusinessOwner::factory()->create([
        'name' => 'Assessment Owner',
        'email' => 'assessment-owner@example.test',
    ]);
    $business = Business::factory()->for($owner, 'owner')->create([
        'name' => 'Assessment Artifact Store',
        'trade_name' => 'Assessment Store',
        'registration_number' => 'BN-ASSESSMENT-001',
        'address' => 'Ipil Assessment Road',
        'barangay' => 'Poblacion',
    ]);
    $application = PermitApplication::factory()->for($business)->create([
        'application_number' => 'APP-2026-ASSESSMENT',
        'application_year' => 2026,
    ]);
    $lineOfBusiness = LineOfBusiness::factory()->create([
        'name' => 'Retail Store',
        'code' => 'RETAIL',
    ]);
    $applicationLine = PermitApplicationLine::factory()
        ->for($application)
        ->for($lineOfBusiness)
        ->create([
            'declared_gross_sales_cents' => 12_500_000,
            'capital_investment_cents' => 25_000_000,
            'quantity' => 2,
        ]);

    $assessment = Assessment::factory()->for($application)->create([
        'sequence' => 1,
        'total_amount_cents' => 42_000,
        'assessed_at' => now()->startOfSecond(),
        'source_snapshot' => [
            'policy' => 'persisted assessment fixture',
        ],
    ]);

    AssessmentLine::factory()->for($assessment)->create([
        'permit_application_line_id' => $applicationLine->id,
        'line_of_business_id' => $lineOfBusiness->id,
        'code' => 'BUSINESS-TAX',
        'name' => 'Business Tax',
        'category' => FeeRuleCategory::Tax,
        'calculation_type' => FeeRuleCalculationType::Range,
        'basis' => 'gross_sales',
        'basis_amount_cents' => 12_500_000,
        'amount_cents' => 29_500,
        'legal_basis' => 'Revenue Code fixture',
    ]);

    AssessmentLine::factory()->for($assessment)->create([
        'permit_application_line_id' => $applicationLine->id,
        'line_of_business_id' => $lineOfBusiness->id,
        'code' => 'MAYOR-PERMIT',
        'name' => 'Mayor Permit Fee',
        'category' => FeeRuleCategory::Fee,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'basis' => 'application',
        'basis_amount_cents' => 0,
        'amount_cents' => 12_500,
        'legal_basis' => 'Revenue Code fixture',
    ]);

    return $assessment;
}

function assessmentPdfPageCount(string $pdf): int
{
    preg_match_all('/\/Type \/Page\b/', $pdf, $matches);

    return count($matches[0]);
}

/** @return array<string, mixed> */
function laboratoryAssessmentReconciliationMetadata(int $recordedTotalAmountCents): array
{
    $historicalAssessment = [
        'source_status' => 'Released',
        'source_assessed_at' => '2025-02-10T08:00:00.000Z',
        'recorded_total_amount_cents' => $recordedTotalAmountCents,
        'component_total_amount_cents' => $recordedTotalAmountCents,
        'source_internal_reconciles' => true,
        'schedules' => [[
            'section' => 1,
            'status' => 'paid',
            'total_amount_cents' => $recordedTotalAmountCents,
            'paid_amount_cents' => $recordedTotalAmountCents,
            'fee_total_amount_cents' => $recordedTotalAmountCents,
            'surcharge_amount_cents' => 0,
            'penalty_amount_cents' => 0,
            'fees' => [
                [
                    'name' => "Mayor's Permit Fee",
                    'category' => 'Regulatory Fee',
                    'amount_cents' => 700_000,
                ],
                [
                    'name' => 'Health Certificate',
                    'category' => 'Regulatory Fee',
                    'amount_cents' => $recordedTotalAmountCents - 700_000,
                ],
            ],
        ]],
    ];
    $historicalAssessment['source_evidence_hash'] = hash(
        'sha256',
        json_encode(
            laboratoryAssessmentEvidenceNormalize($historicalAssessment),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        ),
    );

    return [
        'schema_version' => 'bpls.laboratory-assessment-reconciliation.v1',
        'fixture_id' => 'legacy-ipil-fixture',
        'source_kind' => 'immutable_production_backup',
        'source_reference' => 'LEGACY-2025-0001',
        'source_business_category' => 'REC- GROCERY',
        'semantic_classification' => 'observational_legacy_financial_evidence',
        'historical_assessment' => $historicalAssessment,
        'component_identity_mapping' => 'not_established',
        'operational_authority' => false,
        'production_liability' => false,
    ];
}

function laboratoryAssessmentEvidenceNormalize(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    if (! array_is_list($value)) {
        ksort($value);
    }

    return array_map(fn (mixed $item): mixed => laboratoryAssessmentEvidenceNormalize($item), $value);
}
