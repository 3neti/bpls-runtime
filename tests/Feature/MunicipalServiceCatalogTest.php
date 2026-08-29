<?php

use App\Actions\BuildMunicipalPriceList;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Assessment\ApplicableFeeRuleQuery;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\FeeRulePublicationSource;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Models\FeeRule;
use App\Models\FeeRuleReconciliation;
use App\Models\OfficeChargeContribution;
use App\Models\PermitApplication;
use App\Models\User;
use Database\Seeders\RevenueCodeFeeCatalogSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function municipalPriceList(bool $internal = false): array
{
    return app(BuildMunicipalPriceList::class)->handle(
        includeInternalEvidence: $internal,
        asOf: Carbon::parse('2026-08-28'),
    );
}

it('publishes exactly the five approved BPLS service offerings and only New can start online', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $priceList = municipalPriceList();
    $services = collect($priceList['services']);

    expect($services->pluck('name')->all())->toBe([
        'New Business Permit',
        'Renewal',
        'Amendment',
        'Transfer',
        'Retirement / Closure',
    ])->and($services->pluck('availability_label')->all())->toBe([
        'Available online',
        'Staff-assisted / being completed',
        'Staff-assisted / being completed',
        'Staff-assisted / being completed',
        'Staff-assisted / being completed',
    ])->and($services->where('can_start_online', true)->pluck('code')->all())
        ->toBe(['new_business_permit'])
        ->and($services->pluck('application_type'))->not->toContain('additional');

    expect(json_encode($priceList))
        ->not->toContain('MTOP')
        ->not->toContain('Occupational Permit')
        ->not->toContain('Additional');
});

it('publishes the confirmed annual inspection charge without implying it is the total price', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $newService = collect(municipalPriceList()['services'])->firstWhere('code', 'new_business_permit');
    $confirmedCharge = collect($newService['pricing']['confirmed_charges'])->sole();

    expect($confirmedCharge)
        ->label->toBe('Business Inspection Fee')
        ->amount_cents->toBe(35_000)
        ->cadence->toBe('year')
        ->and($newService['pricing']['other_charges_heading'])->toBe('Other charges may apply')
        ->and($newService['pricing']['other_charges_message'])->toBe(
            'Business tax, permit fees, and charges from concerned municipal offices may depend on the business information and applicable municipal rules.',
        );

    expect(json_encode($newService))->not->toContain('20000')->not->toContain('30000');
});

it('traces every exact published amount to the same rule and snapshot facts used by assessment', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    FeeRule::query()->whereIn('code', [
        'MRC-3A-02-NEW-MAYORS-PERMIT-MICRO',
        'MRC-3A-05-BUSINESS-REGISTRATION-PLATE',
    ])->update(['is_active' => false]);

    $newService = collect(municipalPriceList()['services'])->firstWhere('code', 'new_business_permit');
    $publishedCharge = collect($newService['pricing']['confirmed_charges'])->sole();
    $traceability = $publishedCharge['traceability'];
    $application = PermitApplication::factory()->create([
        'type' => PermitApplicationType::New,
        'application_year' => 2026,
    ]);
    $selectedRule = app(ApplicableFeeRuleQuery::class)
        ->forPermitApplication($application)
        ->sole(fn (FeeRule $feeRule): bool => $feeRule->code === 'MRC-3A-04-BUSINESS-INSPECTION');
    $assessmentLine = app(CreateAssessmentForPermitApplication::class)->handle($application)->lines->sole();
    $snapshot = $assessmentLine->rule_snapshot;

    expect($publishedCharge['amount_cents'])->toBe($assessmentLine->amount_cents)
        ->and($traceability['fee_rule_id'])->toBe($selectedRule->id)->toBe($snapshot['fee_rule_id'])
        ->and($traceability['rule_code'])->toBe($snapshot['code'])
        ->and($traceability['scope'])->toBe($snapshot['scope'])
        ->and($traceability['line_of_business_id'])->toBe($snapshot['line_of_business_id'])
        ->and($traceability['effective_from'])->toBe($snapshot['effective_from'])
        ->and($traceability['effective_until'])->toBe($snapshot['effective_until'])
        ->and($traceability['legal_basis'])->toBe($snapshot['legal_basis'])
        ->and($traceability['legal_source_id'])->toBe($snapshot['legacy_source_id'])
        ->and($traceability['source_classification'])->toBe(
            FeeRulePublicationSource::forRule($selectedRule)->value,
        )
        ->and($traceability['reconciliation_id'])->toBe($snapshot['reconciliation']['fee_rule_reconciliation_id'])
        ->and($traceability['reconciliation_version'])->toBe($snapshot['reconciliation']['version'])
        ->and($traceability['reconciliation_effective_from'])->toBe($snapshot['reconciliation']['effective_from'])
        ->and($traceability['reconciliation_effective_until'])->toBe($snapshot['reconciliation']['effective_until'])
        ->and($traceability['legal_authority'])->toBe($snapshot['reconciliation']['legal_authority'])
        ->and($traceability['evidence_reference'])->toBe($snapshot['reconciliation']['evidence_reference'])
        ->and($traceability['execution_status'])->toBe($snapshot['reconciliation']['execution_status'])->toBe('executable')
        ->and($traceability['application_type'])->toBe($application->type->value)->toBe('new')
        ->and($traceability['application_year'])->toBe($application->application_year)->toBe(2026);
});

it('keeps unrelated accepted municipal rules out of the bounded BPLS publication scope', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $unrelatedRule = FeeRule::factory()->create([
        'code' => 'MTOP-ACCEPTED-EXACT',
        'name' => 'MTOP Accepted Exact Candidate',
        'amount_cents' => 76_543,
        'effective_from' => '2023-01-01',
        'legal_basis' => 'An accepted source outside the BPLS service catalog.',
        'legacy_source_id' => 'LEGAL-MRC-001:MTOP',
        'metadata' => [
            'application_types' => ['new'],
            'price_list_source_classification' => FeeRulePublicationSource::AcceptedMunicipalAuthority->value,
            'reconciliation_required' => true,
        ],
    ]);
    FeeRuleReconciliation::factory()->for($unrelatedRule)->create([
        'execution_status' => FeeRuleExecutionStatus::Executable,
    ]);

    $publicPayload = json_encode(municipalPriceList());
    $internalNewService = collect(municipalPriceList(internal: true)['services'])
        ->firstWhere('code', 'new_business_permit');

    expect($publicPayload)->not->toContain('MTOP-ACCEPTED-EXACT')
        ->not->toContain('76543')
        ->and(collect($internalNewService['internal']['rules'])->pluck('code'))
        ->toContain('MTOP-ACCEPTED-EXACT');
});

it('fails closed for synthetic provisional historical mock legacy and lifecycle sources even with plausible municipal labels', function (string $semanticClassification, int $amountCents) {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $rule = FeeRule::factory()->create([
        'code' => 'PLAUSIBLE-INSPECTION-'.str($semanticClassification)->upper(),
        'name' => 'Business Inspection Fee',
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'amount_cents' => $amountCents,
        'effective_from' => '2023-01-01',
        'legal_basis' => 'Plausible municipal wording must not override source semantics.',
        'legacy_source_id' => 'LEGAL-MRC-001:PLAUSIBLE',
        'metadata' => [
            'application_types' => ['new'],
            'catalog_status' => 'executable_reconciled',
            'price_list_source_classification' => FeeRulePublicationSource::AcceptedMunicipalAuthority->value,
            'semantic_classification' => $semanticClassification,
            'reconciliation_required' => true,
        ],
    ]);
    FeeRuleReconciliation::factory()->for($rule)->create([
        'execution_status' => FeeRuleExecutionStatus::Executable,
    ]);

    $publicConfirmedAmounts = collect(municipalPriceList()['services'])
        ->flatMap(fn (array $service): array => $service['pricing']['confirmed_charges'])
        ->pluck('amount_cents');
    $internalRule = collect(municipalPriceList(internal: true)['services'])
        ->flatMap(fn (array $service): array => $service['internal']['rules'])
        ->firstWhere('id', $rule->id);

    expect($publicConfirmedAmounts)->not->toContain($amountCents)
        ->and($internalRule['recorded_amount_cents'])->toBeNull()
        ->and($internalRule['publication_status'])->toBe('not_published_exact');
})->with([
    'synthetic' => ['synthetic', 91_101],
    'provisional UAT' => ['provisional_uat', 91_102],
    'historical' => ['historical', 91_103],
    'mock' => ['mock', 91_104],
    'legacy evidence only' => ['legacy_evidence_only', 91_105],
    'lifecycle test' => ['lifecycle_test', 91_106],
    'generic test source' => ['test', 91_107],
    'unknown semantic source' => ['new_scenario_class', 91_108],
]);

it('fails closed for unknown or unclassified exact-price sources', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    foreach ([null, 'future_unclassified_source'] as $classification) {
        $rule = FeeRule::factory()->create([
            'code' => 'UNCLASSIFIED-'.($classification ?? 'MISSING'),
            'name' => 'Confirmed-looking permit fee',
            'amount_cents' => 88_800,
            'effective_from' => '2023-01-01',
            'legal_basis' => 'Plausible but unclassified evidence.',
            'legacy_source_id' => 'LEGAL-MRC-001:UNCLASSIFIED',
            'metadata' => array_filter([
                'application_types' => ['new'],
                'price_list_source_classification' => $classification,
                'reconciliation_required' => true,
            ]),
        ]);
        FeeRuleReconciliation::factory()->for($rule)->create();
    }

    $confirmedAmounts = collect(municipalPriceList()['services'])
        ->flatMap(fn (array $service): array => $service['pricing']['confirmed_charges'])
        ->pluck('amount_cents');

    expect($confirmedAmounts)->not->toContain(88_800);
});

it('never projects provisional concerned-office UAT contributions as municipal prices', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    foreach ([12_501, 12_502, 12_503, 12_504, 12_505] as $index => $amountCents) {
        OfficeChargeContribution::factory()->create([
            'office_code' => ['engineering', 'mpdo', 'assessor', 'health', 'menro'][$index],
            'amount_cents' => $amountCents,
            'semantic_classification' => 'provisional_uat',
        ]);
    }

    $serializedPublicPayload = json_encode(municipalPriceList());
    $serializedInternalPayload = json_encode(municipalPriceList(internal: true));

    foreach ([12_501, 12_502, 12_503, 12_504, 12_505] as $amountCents) {
        expect($serializedPublicPayload)->not->toContain((string) $amountCents)
            ->and($serializedInternalPayload)->not->toContain((string) $amountCents);
    }

    expect($serializedPublicPayload)->toContain('Determined by the concerned municipal office when applicable.');
});

it('refuses coherent exact publication when applicable rule versions overlap', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $overlappingRule = FeeRule::factory()->create([
        'code' => 'MRC-3A-04-BUSINESS-INSPECTION',
        'name' => 'Business Inspection Fee',
        'amount_cents' => 36_000,
        'effective_from' => '2025-01-01',
        'effective_until' => null,
        'legal_basis' => 'Conflicting effective version for overlap refusal testing.',
        'legacy_source_id' => 'LEGAL-MRC-001:SECTION-3A.04-CONFLICT',
        'metadata' => [
            'price_list_source_classification' => 'accepted_municipal_authority',
            'reconciliation_required' => true,
        ],
    ]);
    FeeRuleReconciliation::factory()->for($overlappingRule)->create();

    $publicNewService = collect(municipalPriceList()['services'])->firstWhere('code', 'new_business_permit');
    $internalNewService = collect(municipalPriceList(internal: true)['services'])->firstWhere('code', 'new_business_permit');

    expect($publicNewService['pricing']['confirmed_charges'])->toBeEmpty()
        ->and($internalNewService['internal']['ambiguous_rule_keys'])
        ->toContain('MRC-3A-04-BUSINESS-INSPECTION|application|application')
        ->and(collect($internalNewService['internal']['rules'])
            ->where('code', 'MRC-3A-04-BUSINESS-INSPECTION')
            ->every(fn (array $rule): bool => $rule['overlap_ambiguous'] === true))
        ->toBeTrue();
});

it('exposes recorded Line of Business schedules to staff only, scoped to the matching application type', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $internalServices = collect(municipalPriceList(internal: true)['services']);
    $renewal = $internalServices->firstWhere('code', 'renewal');
    $newBusinessPermit = $internalServices->firstWhere('code', 'new_business_permit');

    $schedule = collect($renewal['internal']['line_of_business_pricing'])
        ->sole(fn (array $rule): bool => $rule['code'] === 'MRC-2A-02-B-RETAIL-BUSINESS-TAX');

    expect($schedule['line_of_business']['name'])
        ->toBe('Wholesalers, Retailers, Dealers or Distributors')
        ->and($schedule['selected_by_assessment'])->toBeFalse()
        ->and($schedule['automatic_assessment_status'])->toBe('not_available_for_automatic_assessment')
        ->and($schedule['automatic_assessment_label'])->toBe('Browsable pricing knowledge only')
        ->and($schedule['source_classification'])->toBe('municipal_confirmation_required')
        ->and($schedule['publication_status'])->toBe('not_published_exact')
        ->and($schedule['range_count'])->toBe(23)
        ->and($schedule['ranges'])->toHaveCount(23)
        ->and($schedule['ranges'][0])
        ->min_basis_cents->toBe(0)
        ->amount_cents->toBe(2_266)
        ->and($newBusinessPermit['internal']['line_of_business_pricing'])->toBeEmpty();

    $publicPayload = json_encode(municipalPriceList());

    expect($publicPayload)->not->toContain('line_of_business_pricing');
});

it('anchors browsable Line of Business pricing to the same application-year date used by Assessment', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $midYearRule = FeeRule::factory()->create([
        'code' => 'LOB-MIDYEAR-NOT-2026',
        'scope' => 'line_of_business',
        'line_of_business_id' => FeeRule::query()
            ->whereNotNull('line_of_business_id')
            ->value('line_of_business_id'),
        'effective_from' => '2026-06-01',
        'metadata' => ['application_types' => ['renewal']],
    ]);

    $renewal = collect(municipalPriceList(internal: true)['services'])->firstWhere('code', 'renewal');

    expect(collect($renewal['internal']['line_of_business_pricing'])->pluck('id'))
        ->not->toContain($midYearRule->id);
});

it('surfaces the recorded policy note for a ceiling amount so it is not shown as an exact figure', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $newService = collect(municipalPriceList(internal: true)['services'])->firstWhere('code', 'new_business_permit');
    $registrationPlate = collect($newService['internal']['rules'])
        ->sole(fn (array $rule): bool => $rule['code'] === 'MRC-3A-05-BUSINESS-REGISTRATION-PLATE');

    expect($registrationPlate['policy_note'])
        ->toBe('Ordinance states not to exceed PHP 300.00; production configuration must confirm the exact charged amount.');
});

it('serves the public catalog read-only and authorizes the internal catalog through existing generic access', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $this->get(route('services-and-fees.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/ServicesAndFees')
            ->where('priceList.catalog.audience', 'public')
            ->where('priceList.catalog.read_only', true));

    $staff = userWithPermissions([UserPermission::AccessStaff]);

    $this->actingAs($staff)
        ->get(route('staff.services-and-fees.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('services-and-fees/Internal')
            ->where('priceList.catalog.audience', 'internal')
            ->where('priceList.catalog.read_only', true));

    $this->actingAs(User::factory()->create())
        ->get(route('staff.services-and-fees.index'))
        ->assertForbidden();

    expect(collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains((string) $route->getName(), 'services-and-fees'))
        ->flatMap(fn ($route): array => $route->methods())
        ->unique()
        ->values()
        ->all())->toBe(['GET', 'HEAD']);
});
