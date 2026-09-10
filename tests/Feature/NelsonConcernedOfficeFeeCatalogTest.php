<?php

use App\Actions\BuildBploRoutingTask;
use App\Actions\InspectBplsInstallation;
use App\Assessment\ApplicableFeeRuleQuery;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationType;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationVersion;
use App\Models\FeeRule;
use App\Models\PermitApplication;
use App\References\NelsonConcernedOfficeFeeCatalog;
use Database\Seeders\NelsonConcernedOfficeFeeCatalogSeeder;

test('versioned Nelson preview catalog materializes idempotent year-bound FeeRules', function () {
    $this->seed(NelsonConcernedOfficeFeeCatalogSeeder::class);
    $this->seed(NelsonConcernedOfficeFeeCatalogSeeder::class);

    $rules = FeeRule::query()
        ->where('metadata->catalog_version', 'nelson-concerned-office-preview-v1')
        ->orderBy('effective_from')
        ->orderBy('code')
        ->get();

    expect($rules)->toHaveCount(46)
        ->and($rules->pluck('effective_from')->map->format('Y')->unique()->values()->all())->toBe(['2025', '2026'])
        ->and($rules->every(fn (FeeRule $rule): bool => $rule->effective_until?->year === $rule->effective_from->year))->toBeTrue()
        ->and($rules->every(fn (FeeRule $rule): bool => data_get($rule->metadata, 'classification') === 'synthetic_preview'))->toBeTrue()
        ->and($rules->every(fn (FeeRule $rule): bool => data_get($rule->metadata, 'production_authority') === false))->toBeTrue()
        ->and($rules->every(fn (FeeRule $rule): bool => strlen((string) data_get($rule->metadata, 'catalog_digest_sha256')) === 64))->toBeTrue()
        ->and(app(ApplicableFeeRuleQuery::class)->forApplicationFacts(PermitApplicationType::New, 2025)
            ->pluck('code')->intersect($rules->pluck('code'))->isEmpty())->toBeTrue()
        ->and(app(InspectBplsInstallation::class)->handle()['price_list']['synthetic_preview_payment_order_fee_rule_count'])->toBe(46);
});

test('concerned offices receive only their current application-year preview fee options', function () {
    $this->seed(NelsonConcernedOfficeFeeCatalogSeeder::class);
    $application = PermitApplication::factory()->create([
        'application_year' => 2025,
        'metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => true]],
    ]);

    $editor = app(BuildBploRoutingTask::class)->handle($application, null)->toArray()['financial_editor'];
    $historicalApplication = PermitApplication::factory()->create([
        'application_year' => 2025,
        'metadata' => [],
    ]);
    $historicalEditor = app(BuildBploRoutingTask::class)->handle($historicalApplication, null)->toArray()['financial_editor'];

    expect($editor['catalog_status'])->toBe('source_reference_received_pending_validation')
        ->and(collect($editor['office_fee_options']['engineering'])->pluck('code')->all())->toContain(
            'LAB-NELSON-ENGINEERING-REGULATORY',
            'LAB-IPIL-ENGINEERING-BUILDING-PERMIT',
            'LAB-IPIL-ENGINEERING-ELECTRICAL-PERMIT',
        )
        ->and(collect($editor['office_fee_options']['health'])->pluck('code')->all())->toBe([
            'LAB-NELSON-HEALTH-CERTIFICATE',
            'LAB-NELSON-HEALTH-SANITARY',
        ])
        ->and(collect($editor['office_fee_options']['menro'])->pluck('code')->all())->toBe([
            'LAB-NELSON-MENRO-SOLID-WASTE',
        ])
        ->and(collect($editor['office_fee_options']['assessor'])->pluck('code')->all())->toContain(
            'LAB-NELSON-ASSESSOR-WEIGHTS-MEASURES',
            'LAB-IPIL-ASSESSOR-CERTIFICATION',
            'LAB-IPIL-ASSESSOR-TRUE-COPY-TAX-DECLARATION',
        )
        ->and(collect($editor['office_fee_options']['engineering'])->firstWhere('code', 'LAB-IPIL-ENGINEERING-BUILDING-PERMIT')['account_code'])->toBe('4-02-01-010-06')
        ->and(collect($editor['office_fee_options'])->flatten(1))->toHaveCount(23);
    expect(collect($historicalEditor['office_fee_options'])->flatten(1))->toBeEmpty();
});

test('catalog refuses prohibited items and unknown concerned-office mappings', function () {
    $catalogPath = config('ipil_references.nelson_concerned_office_fee_catalog.path');
    $contents = file_get_contents($catalogPath);
    $prohibitedCatalog = tempnam(sys_get_temp_dir(), 'nelson-fees-');
    file_put_contents($prohibitedCatalog, str_replace('Engineering Regulatory Fee', 'Business Tax', $contents));

    try {
        config()->set('ipil_references.nelson_concerned_office_fee_catalog.path', $prohibitedCatalog);
        expect(fn () => app(NelsonConcernedOfficeFeeCatalog::class)->load())
            ->toThrow(UnexpectedValueException::class, 'Business Tax and Inspection items are prohibited');

        config()->set('ipil_references.nelson_concerned_office_fee_catalog.path', $catalogPath);
        config()->set('ipil_references.concerned_offices.items.0.fee_rule_codes', ['UNKNOWN-NELSON-FEE']);
        expect(fn () => app(NelsonConcernedOfficeFeeCatalog::class)->load())
            ->toThrow(UnexpectedValueException::class, 'references unknown preview fee');
    } finally {
        @unlink($prohibitedCatalog);
    }
});

test('Payment Order editor keeps preview provenance concise and explains an empty fee menu', function () {
    $taskSheet = file_get_contents(resource_path('js/components/permit-applications/BploRoutingTaskSheet.vue'));
    $editor = file_get_contents(resource_path('js/components/permit-applications/FinancialLineItemEditor.vue'));

    expect($taskSheet)->toContain('Ipil source-backed fee menu')
        ->not->toContain('Synthetic preview fee menu · awaiting Nelson source')
        ->and($editor)->toContain('No preview fee menu is configured for this office. Awaiting the')
        ->and($editor)->toContain('Nelson schedule.')
        ->and($editor)->not->toContain('option.account_code')
        ->and($editor)->not->toContain('money(option.default_amount_cents)');
});

test('Nelson presentation separates applicant description from Treasury classification', function () {
    $evaluation = file_get_contents(resource_path('js/pages/business-permit-evaluations/Show.vue'));
    $paymentOrders = file_get_contents(resource_path('js/components/permit-applications/BploRoutingTaskSheet.vue'));
    $handoff = file_get_contents(resource_path('js/pages/stakeholder-preview/OfficeReviewsAssigned.vue'));

    expect($evaluation)
        ->toContain('Applicant’s frozen business description')
        ->toContain('Treasury Classification / Assigned Lines of')
        ->toContain('v-if="isNelsonPath"')
        ->and($paymentOrders)->toContain('Sign & Confirm Payment Order')
        ->toContain('Confirming Payment Order…')
        ->toContain('officePaymentOrderPending[work.id]')
        ->toContain('onError: (errors) =>')
        ->toContain('onHttpException: (response) =>')
        ->toContain('onNetworkError: () =>')
        ->toContain('BPLS is still confirming this Payment Order.')
        ->toContain('role="alert"')
        ->and($handoff)->toContain('Review details')
        ->and($handoff)->toContain('Open review')
        ->and($handoff)->toContain('allOfficeReviewsComplete')
        ->and($handoff)->not->toContain('No fees have been charged')
        ->and($handoff)->not->toContain('Who acts next')
        ->and($handoff)->not->toContain('Select a fee, review its default amount, optionally')
        ->not->toContain('Confirm, Override, or Not Applicable');
});

test('Nelson evaluation excludes the historical automatic Inspection charge path', function () {
    $application = PermitApplication::factory()->create([
        'type' => PermitApplicationType::New,
        'application_year' => 2025,
        'metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => true]],
    ]);
    FeeRule::factory()->create([
        'code' => 'MRC-3A-04-BUSINESS-INSPECTION',
        'name' => 'Business Inspection Fee',
        'category' => FeeRuleCategory::Fee,
        'scope' => FeeRuleScope::Application,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'amount_cents' => 35_000,
        'effective_from' => '2025-01-01',
        'effective_until' => '2025-12-31',
        'is_active' => true,
        'metadata' => ['application_types' => ['new']],
    ]);
    $evaluation = BusinessPermitEvaluation::factory()->for($application)->create();
    BusinessPermitEvaluationVersion::factory()->for($evaluation, 'evaluation')->create();

    $projection = app(BusinessPermitEvaluationResolver::class)->resolve($evaluation->fresh());

    expect($projection['projected_charges'])->toBe([])
        ->and($projection['pricing_issues'])->toBe([])
        ->and($projection['total_amount_cents'])->toBe(0);
});
