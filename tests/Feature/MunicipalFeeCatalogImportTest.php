<?php

use App\Enums\FeeCatalogVersionStatus;
use App\Enums\FeeDeterminationChannel;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\BusinessDivision;
use App\Models\FeeCatalogVersion;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\RevenueAccount;
use Database\Seeders\MunicipalFeeCatalogSeeder;
use Inertia\Testing\AssertableInertia as Assert;

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
