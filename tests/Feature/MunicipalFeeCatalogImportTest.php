<?php

use App\Actions\BuildBploRoutingTask;
use App\Actions\ConfirmOfficePaymentOrder;
use App\Assessment\ConcernedOfficeFeeApplicability;
use App\Enums\FeeCatalogVersionStatus;
use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleScope;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\BploRoutingDetermination;
use App\Models\BploRoutingWork;
use App\Models\BusinessDivision;
use App\Models\FeeCatalogVersion;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PaperlessPaymentOrder;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;
use App\Models\PermitApplicationLine;
use App\Models\RevenueAccount;
use App\Models\SignatureEvidence;
use Database\Seeders\MunicipalFeeCatalogSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('inapplicable Health confirmation rolls back without altering existing orders or signature authority', function (): void {
    Storage::fake('local');
    $this->seed(MunicipalFeeCatalogSeeder::class);
    $actor = userWithPermissions([UserPermission::ContributeBusinessPermitEvaluations]);
    $application = PermitApplication::factory()->create(['application_year' => 2026, 'type' => 'new', 'metadata' => []]);
    $determination = BploRoutingDetermination::factory()->for($application)->create();
    $work = BploRoutingWork::factory()->for($determination, 'determination')->create([
        'office_code' => 'health',
        'context_snapshot' => ['authorized_actor_id' => $actor->id],
    ]);
    foreach (['assessor' => 10_000, 'engineering' => 15_000] as $office => $amount) {
        $existingWork = BploRoutingWork::factory()->for($determination, 'determination')->create(['office_code' => $office]);
        PaperlessPaymentOrder::factory()->for($existingWork, 'routingWork')->create([
            'permit_application_id' => $application->id, 'total_amount_cents' => $amount,
        ]);
    }
    $orders = PaperlessPaymentOrder::query()->orderBy('id')->get()->toJson();
    $rule = FeeRule::query()->where('code', 'IPIL-LEGACY-0C9F4E23DDB45739')->sole();
    expect(fn () => app(ConfirmOfficePaymentOrder::class)->handle($work, [[
        'fee_rule_id' => $rule->id, 'amount_cents' => 10_000,
    ]], $actor, UploadedFile::fake()->image('signature.png')))->toThrow(LogicException::class, 'not applicable');
    expect(PaperlessPaymentOrder::query()->orderBy('id')->get()->toJson())->toBe($orders)
        ->and(SignatureEvidence::query()->count())->toBe(0);
    $stranger = userWithRole($actor->primaryRole());
    expect(fn () => app(ConfirmOfficePaymentOrder::class)->handle($work, [[
        'fee_rule_id' => $rule->id, 'amount_cents' => 10_000,
    ]], $stranger, UploadedFile::fake()->image('signature.png')))->toThrow(LogicException::class, 'authorized routed');
    expect(SignatureEvidence::query()->count())->toBe(0)
        ->and(PaperlessPaymentOrder::query()->orderBy('id')->get()->toJson())->toBe($orders);
});

test('unclassified 2026 applications receive only applicable application-wide Health identities without mutating catalogue evidence', function (): void {
    $this->seed(MunicipalFeeCatalogSeeder::class);
    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
        'type' => 'new',
        'metadata' => [],
    ]);
    PermitApplicationDeclaration::factory()->for($application)->create([
        'snapshot' => [
            'schema_version' => 1,
            'establishment' => [
                'male_employees' => 1,
                'female_employees' => 0,
                'business_area_square_meters' => '12.00',
            ],
        ],
    ]);
    $before = FeeRule::query()->orderBy('id')->get()->toJson();
    $editor = app(BuildBploRoutingTask::class)->handle($application, null)->toArray()['financial_editor'];
    $health = collect($editor['office_fee_options']['health'])->keyBy('code');

    expect($health->keys()->sort()->values()->all())->toBe([
        'IPIL-LEGACY-04845A0127A00E12',
        'IPIL-LEGACY-99C7F1CE5E8189C8',
    ])
        ->and($health['IPIL-LEGACY-99C7F1CE5E8189C8']['default_amount_cents'])->toBe(10_000)
        ->and($health['IPIL-LEGACY-04845A0127A00E12']['default_amount_cents'])->toBe(20_000)
        ->and(collect($editor['office_fee_options'])->except('health')->flatten(1)->pluck('code')->intersect($health->keys()))->toBeEmpty()
        ->and(FeeRule::query()->orderBy('id')->get()->toJson())->toBe($before);
});

test('office applicability preserves distinct eligible identities and rejects unsupported scope type office and version', function (): void {
    $this->seed(MunicipalFeeCatalogSeeder::class);
    $application = PermitApplication::factory()->create(['application_year' => 2026, 'type' => 'new', 'metadata' => []]);
    $applicationRule = FeeRule::query()->where('code', 'IPIL-LEGACY-99C7F1CE5E8189C8')->sole();
    PermitApplicationDeclaration::factory()->for($application)->create([
        'snapshot' => ['schema_version' => 1, 'establishment' => [
            'male_employees' => 1, 'female_employees' => 0, 'business_area_square_meters' => '12.00',
        ]],
    ]);
    $lobRule = FeeRule::query()->where('code', 'IPIL-LEGACY-0C9F4E23DDB45739')->sole();
    $resolver = app(ConcernedOfficeFeeApplicability::class);
    expect($resolver->matches($applicationRule, $application, 'health'))->toBeTrue()
        ->and($resolver->matches($applicationRule, $application, 'engineering'))->toBeFalse()
        ->and($resolver->matches($lobRule, $application, 'health'))->toBeFalse();

    PermitApplicationLine::factory()->for($application)->create(['line_of_business_id' => $lobRule->line_of_business_id]);
    $application->unsetRelation('lines');
    expect($resolver->matches($lobRule, $application, 'health'))->toBeTrue();
    $fees = collect(app(BuildBploRoutingTask::class)->handle($application, null)->toArray()['financial_editor']['office_fee_options']['health']);
    expect($fees->pluck('id'))->toContain($applicationRule->id, $lobRule->id)
        ->and($fees->firstWhere('id', $lobRule->id)['name'])->toContain('Gasoline Station', $lobRule->code)
        ->and($fees->firstWhere('id', $applicationRule->id)['name'])->toContain($applicationRule->code);

    foreach ([['application_types' => ['renewal']], ['application_types' => 'new']] as $metadata) {
        $candidate = clone $applicationRule;
        $candidate->metadata = [...$candidate->metadata, ...$metadata];
        expect($resolver->matches($candidate, $application, 'health'))->toBeFalse();
    }
    foreach (['is_active' => false, 'effective_from' => '2027-01-01', 'effective_until' => '2025-12-31'] as $attribute => $value) {
        $candidate = clone $applicationRule;
        $candidate->{$attribute} = $value;
        expect($resolver->matches($candidate, $application, 'health'))->toBeFalse();
    }
    $candidate = clone $applicationRule;
    $version = clone $applicationRule->catalogVersion;
    $version->status = FeeCatalogVersionStatus::Superseded;
    $candidate->setRelation('catalogVersion', $version);
    expect($resolver->matches($candidate, $application, 'health'))->toBeFalse();
});

test('the municipal fee editor resolves accepted employee and area defaults exactly once for the application', function (): void {
    $this->seed(MunicipalFeeCatalogSeeder::class);
    $application = PermitApplication::factory()->create([
        'application_year' => 2025,
        'metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => true]],
    ]);
    PermitApplicationDeclaration::factory()->for($application)->create([
        'snapshot' => [
            'schema_version' => 1,
            'establishment' => [
                'male_employees' => 3,
                'female_employees' => 4,
                'business_area_square_meters' => '84.50',
            ],
        ],
    ]);

    $task = app(BuildBploRoutingTask::class)->handle($application, null)->toArray();
    $healthOptions = collect(data_get($task, 'financial_editor.office_fee_options.health'));
    $menroOptions = collect(data_get($task, 'financial_editor.office_fee_options.menro'));
    $sariSari = collect(data_get($task, 'financial_editor.line_of_business_options'))
        ->firstWhere('code', 'LOB-F4F644287B2E8261');
    $treasuryItems = collect($sariSari['default_items']);
    $freshFishItems = collect(data_get($task, 'financial_editor.line_of_business_options'))
        ->firstWhere('code', 'LOB-3A9A93CA46967768')['default_items'];

    expect($healthOptions->firstWhere('code', 'IPIL-LEGACY-99C7F1CE5E8189C8')['default_amount_cents'])->toBe(70_000)
        ->and($healthOptions->firstWhere('code', 'IPIL-LEGACY-04845A0127A00E12')['default_amount_cents'])->toBe(40_000)
        ->and($menroOptions->firstWhere('code', 'IPIL-LEGACY-98CDCAD9D28055FB')['default_amount_cents'])->toBe(350_000)
        ->and($treasuryItems->firstWhere('code', 'IPIL-LEGACY-A9B730041C0AE6F6')['amount_cents'])->toBe(17_500)
        ->and($treasuryItems->firstWhere('code', 'IPIL-LEGACY-FEE443B6D6004315')['amount_cents'])->toBe(70_000)
        ->and($treasuryItems->where('exact_once_key', 'laminated-id'))->toHaveCount(1)
        ->and($treasuryItems->where('exact_once_key', 'occupation-fee'))->toHaveCount(1)
        ->and(collect($freshFishItems)->where('name', "Mayor's Permit Fee"))->toHaveCount(1)
        ->and(collect($freshFishItems)->pluck('code'))->toContain('IPIL-LEGACY-5F028B76EEBEF485')
        ->not->toContain('IPIL-LEGACY-D39CD82C3AEAE153');
});

test('the versioned municipal YAML deterministically builds the normalized fee catalogue', function (): void {
    $this->seed(MunicipalFeeCatalogSeeder::class);
    $this->seed(MunicipalFeeCatalogSeeder::class);

    $version = FeeCatalogVersion::query()->where('code', 'ipil-municipal-fees-v1')->sole();

    expect($version->status)->toBe(FeeCatalogVersionStatus::Active)
        ->and(BusinessDivision::query()->count())->toBe(20)
        ->and(LineOfBusiness::query()->where('metadata->catalog_version', $version->code)->count())->toBe(822)
        ->and(RevenueAccount::query()->count())->toBe(26)
        ->and(FeeRule::query()->whereBelongsTo($version, 'catalogVersion')->count())->toBe(171)
        ->and(FeeRule::query()->whereBelongsTo($version, 'catalogVersion')->where('determination_channel', FeeDeterminationChannel::ConcernedOfficePaymentOrder)->count())->toBe(77)
        ->and(FeeRule::query()->whereBelongsTo($version, 'catalogVersion')->where('determination_channel', FeeDeterminationChannel::TreasuryLineOfBusiness)->count())->toBe(86)
        ->and(FeeRule::query()->whereBelongsTo($version, 'catalogVersion')->where('determination_channel', FeeDeterminationChannel::ReferenceOnly)->count())->toBe(8)
        ->and(BusinessDivision::query()->where('code', 'DIV-EDD6388C02DAE431')->sole()->name)->toBe('Banking Services')
        ->and(BusinessDivision::query()->where('code', 'DIV-EDD6388C02DAE431')->sole()->metadata['source_name'])->toBe('BANKING SERVICES')
        ->and(LineOfBusiness::query()->where('code', 'LOB-EDD6388C02DAE431')->sole()->name)->toBe('Banking Services')
        ->and(LineOfBusiness::query()->where('code', 'LOB-EDD6388C02DAE431')->sole()->metadata['source_name'])->toBe('BANKING SERVICES')
        ->and(BusinessDivision::query()->where('code', 'DIV-72BA48EE08E75420')->sole()->name)->toBe('LPG Dealer')
        ->and(BusinessDivision::query()->where('code', 'DIV-FF529405F0153E4C')->sole()->name)->toBe('For Hospital and Clinic')
        ->and(FeeRule::query()->where('code', 'IPIL-LEGACY-F8EC2C47251FB9FA')->sole()->name)->toBe('Laminated ID');

    $acceptedRules = FeeRule::query()->whereIn('code', [
        'IPIL-LEGACY-99C7F1CE5E8189C8',
        'IPIL-LEGACY-A9B730041C0AE6F6',
        'IPIL-LEGACY-FEE443B6D6004315',
        'IPIL-LEGACY-04845A0127A00E12',
        'IPIL-LEGACY-98CDCAD9D28055FB',
    ])->get()->keyBy('code');

    expect($acceptedRules)->toHaveCount(5)
        ->and($acceptedRules->every(fn (FeeRule $rule): bool => $rule->scope === FeeRuleScope::Application))->toBeTrue()
        ->and($acceptedRules['IPIL-LEGACY-A9B730041C0AE6F6']->basis)->toBe('employee_count')
        ->and($acceptedRules['IPIL-LEGACY-A9B730041C0AE6F6']->metadata['unit_amount_minor'])->toBe(2_500)
        ->and($acceptedRules['IPIL-LEGACY-04845A0127A00E12']->basis)->toBe('business_area_square_meters')
        ->and($acceptedRules['IPIL-LEGACY-04845A0127A00E12']->metadata['manual_amount_required'])->toBeFalse()
        ->and($acceptedRules['IPIL-LEGACY-98CDCAD9D28055FB']->metadata['exact_once_key'])->toBe('solid-waste-management');
});

test('staff can search and filter by submitted revenue code and owning office', function (): void {
    $this->seed(MunicipalFeeCatalogSeeder::class);
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewFeeRules], UserRole::Bplo);

    $this->actingAs($user)
        ->get(route('staff.fee-rules.index', ['q' => 'Building Permit', 'office' => 'engineering', 'status' => 'active']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'Building Permit')
            ->where('filters.office', 'engineering')
            ->where('summary.catalogue_fees', 171)
            ->where('summary.available_fees', 163)
            ->where('summary.incomplete_fees', 8)
            ->where('summary.payment_order_fees', 77)
            ->where('summary.treasury_lob_fees', 86)
            ->where('summary.revenue_code_recorded', 27)
            ->where('summary.revenue_code_missing', 144)
            ->has('feeRules.data', 1)
            ->where('feeRules.data.0.name', 'Building Permit')
            ->where('feeRules.data.0.revenue_code', '4-02-01-010-06')
            ->where('feeRules.data.0.owner', 'Municipal Engineering Office')
            ->where('feeRules.data.0.display_status', 'Active'));
});

test('submitted revenue codes are first class and fee ownership channels never overlap', function (): void {
    $this->seed(MunicipalFeeCatalogSeeder::class);

    $buildingPermit = FeeRule::query()->with(['revenueAccount', 'officeAssignments'])->where('name', 'Building Permit')->sole();
    $treasuryRulesWithOfficeOwnership = FeeRule::query()
        ->where('determination_channel', FeeDeterminationChannel::TreasuryLineOfBusiness)
        ->whereHas('officeAssignments')
        ->count();

    expect($buildingPermit->revenueAccount?->code)->toBe('4-02-01-010-06')
        ->and($buildingPermit->determination_channel)->toBe(FeeDeterminationChannel::ConcernedOfficePaymentOrder)
        ->and($buildingPermit->officeAssignments->sole()->office_code)->toBe('engineering')
        ->and($treasuryRulesWithOfficeOwnership)->toBe(0)
        ->and(FeeRule::query()->where('code', 'like', 'IPIL-LEGACY-%')->whereNotNull('revenue_account_id')->count())->toBe(0);
});

test('staff catalogue presents consistent applicability ranges formulas and missing revenue codes', function (): void {
    $this->seed(MunicipalFeeCatalogSeeder::class);
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewFeeRules], UserRole::Bplo);

    $this->actingAs($user)
        ->get(route('staff.fee-rules.index', [
            'q' => 'IPIL-LEGACY-8933D52998E06BD1',
            'business_division' => 'DIV-EDD6388C02DAE431',
            'status' => 'active',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('feeRules.data', 1)
            ->where('feeRules.data.0.business_division.name', 'Banking Services')
            ->where('feeRules.data.0.business_division.source_name', 'BANKING SERVICES')
            ->where('feeRules.data.0.applies_to.0', 'All Banking Services')
            ->where('feeRules.data.0.amount_display', '0.5775% of gross sales')
            ->where('feeRules.data.0.amount_basis', 'Formula')
            ->where('feeRules.data.0.raw_formula', 'grossSales * 0.01 * .5775'));

    $this->actingAs($user)
        ->get(route('staff.fee-rules.index', ['q' => 'IPIL-LEGACY-96FF6E27890C2EBB', 'status' => 'active']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('feeRules.data', 1)
            ->where('feeRules.data.0.amount_display', '₱11.33–₱6,294.75')
            ->where('feeRules.data.0.amount_basis', 'Based on gross sales')
            ->where('feeRules.data.0.range_count', 23));

    $this->actingAs($user)
        ->get(route('staff.fee-rules.index', ['revenue_code' => 'missing', 'status' => 'active']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.revenue_code', 'missing')
            ->where('feeRules.data.0.revenue_code', null));
});
