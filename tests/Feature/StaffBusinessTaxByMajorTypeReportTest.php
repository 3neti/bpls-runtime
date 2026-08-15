<?php

use App\Enums\FeeRuleCategory;
use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\CollectionAllocation;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLine;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Inertia\Testing\AssertableInertia as Assert;

test('business tax report groups collected tax by the first activity major type and preserves zero categories', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);
    LineOfBusiness::factory()->create(['major_category' => 'Manufacturing']);
    $retail = businessTaxCollection([
        'primary_major_type' => 'Retail',
        'secondary_major_type' => 'Services',
        'receipt_number' => 'OR-0100',
        'received_at' => '2026-08-15 09:00:00',
        'tax_amount_cents' => 75_000,
        'fee_amount_cents' => 20_000,
    ]);
    businessTaxCollection([
        'primary_major_type' => 'Retail',
        'receipt_number' => 'OR-0101',
        'received_at' => '2026-08-15 10:00:00',
        'tax_amount_cents' => 25_000,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.business-tax-by-major-type.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/BusinessTaxByMajorType')
            ->where('summary.allocation_count', 2)
            ->where('summary.receipt_count', 2)
            ->where('summary.total_amount_cents', 100_000)
            ->where('summary.classification_basis', 'first_permit_application_line_major_category')
            ->where('rows.0.major_type', 'Manufacturing')
            ->where('rows.0.amount_cents', 0)
            ->where('rows.1.major_type', 'Retail')
            ->where('rows.1.amount_cents', 100_000)
            ->where('rows.1.allocation_count', 2)
            ->where('rows.2.major_type', 'Services')
            ->where('rows.2.amount_cents', 0)
            ->missing('rows.3')
        );

    expect($retail['tax_allocation']->amount_cents)->toBe(75_000)
        ->and($retail['fee_allocation']?->amount_cents)->toBe(20_000);
});

test('business tax report excludes non tax pending receipt and voided receipt allocations', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);
    businessTaxCollection([
        'primary_major_type' => 'Services',
        'receipt_number' => 'OR-0200',
        'received_at' => '2026-08-15 09:00:00',
        'tax_amount_cents' => 40_000,
        'fee_amount_cents' => 15_000,
    ]);
    businessTaxCollection([
        'primary_major_type' => 'Retail',
        'receipt_number' => null,
        'received_at' => '2026-08-15 10:00:00',
        'tax_amount_cents' => 80_000,
        'collection_status' => TreasuryCollectionStatus::PendingReceipt,
    ]);
    businessTaxCollection([
        'primary_major_type' => 'Manufacturing',
        'receipt_number' => 'OR-0202',
        'received_at' => '2026-08-15 11:00:00',
        'tax_amount_cents' => 90_000,
        'receipt_status' => ReceiptStatus::Voided,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.business-tax-by-major-type.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.allocation_count', 1)
            ->where('summary.receipt_count', 1)
            ->where('summary.total_amount_cents', 40_000)
            ->where('rows.0.major_type', 'Manufacturing')
            ->where('rows.0.amount_cents', 0)
            ->where('rows.1.major_type', 'Retail')
            ->where('rows.1.amount_cents', 0)
            ->where('rows.2.major_type', 'Services')
            ->where('rows.2.amount_cents', 40_000)
        );
});

test('business tax report filters by collection date and receipt number range', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);
    businessTaxCollection(['primary_major_type' => 'Retail', 'receipt_number' => 'OR-0300', 'received_at' => '2026-08-14 23:59:59', 'tax_amount_cents' => 10_000]);
    businessTaxCollection(['primary_major_type' => 'Retail', 'receipt_number' => 'OR-0301', 'received_at' => '2026-08-15 08:00:00', 'tax_amount_cents' => 20_000]);
    businessTaxCollection(['primary_major_type' => 'Retail', 'receipt_number' => 'OR-0302', 'received_at' => '2026-08-15 09:00:00', 'tax_amount_cents' => 30_000]);

    $this->actingAs($user)
        ->get(route('staff.reports.business-tax-by-major-type.index', [
            'date_from' => '2026-08-15',
            'date_to' => '2026-08-15',
            'receipt_from' => 'OR-0301',
            'receipt_to' => 'OR-0301',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.date_from', '2026-08-15')
            ->where('filters.date_to', '2026-08-15')
            ->where('summary.allocation_count', 1)
            ->where('summary.total_amount_cents', 20_000)
            ->where('rows.0.amount_cents', 20_000)
        );
});

test('business tax report places missing primary classification under unclassified', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);
    businessTaxCollection([
        'primary_major_type' => null,
        'receipt_number' => 'OR-0400',
        'received_at' => '2026-08-15 09:00:00',
        'tax_amount_cents' => 55_000,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.business-tax-by-major-type.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_amount_cents', 55_000)
            ->where('rows.0.major_type', 'Unclassified')
            ->where('rows.0.amount_cents', 55_000)
        );
});

test('business tax report requires report permission', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReceipts]);

    $this->actingAs($user)
        ->get(route('staff.reports.business-tax-by-major-type.index'))
        ->assertForbidden();
});

test('business tax report exports the grouped rows and total as csv', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);
    businessTaxCollection([
        'primary_major_type' => 'Services',
        'receipt_number' => 'OR-0500',
        'received_at' => '2026-08-15 09:00:00',
        'tax_amount_cents' => 65_432,
    ]);

    $response = $this->actingAs($user)
        ->get(route('staff.reports.business-tax-by-major-type.download', [
            'date_from' => '2026-08-15',
            'date_to' => '2026-08-15',
        ]))
        ->assertOk()
        ->assertDownload('business-tax-by-major-type-2026-08-15-to-2026-08-15.csv');

    expect($response->streamedContent())
        ->toContain('Major Type')
        ->toContain('Services')
        ->toContain('654.32')
        ->toContain('Total Amount');
});

/**
 * @param  array{
 *     primary_major_type: string|null,
 *     secondary_major_type?: string|null,
 *     receipt_number: string|null,
 *     received_at: string,
 *     tax_amount_cents: int,
 *     fee_amount_cents?: int,
 *     collection_status?: TreasuryCollectionStatus,
 *     receipt_status?: ReceiptStatus
 * }  $attributes
 * @return array{tax_allocation: CollectionAllocation, fee_allocation: CollectionAllocation|null}
 */
function businessTaxCollection(array $attributes): array
{
    $owner = BusinessOwner::factory()->create();
    $business = Business::factory()->for($owner, 'owner')->create();
    $application = PermitApplication::factory()->for($business)->create();
    $primaryLineOfBusiness = LineOfBusiness::factory()->create([
        'major_category' => $attributes['primary_major_type'],
    ]);
    $primaryApplicationLine = PermitApplicationLine::factory()
        ->for($application)
        ->for($primaryLineOfBusiness, 'lineOfBusiness')
        ->create();

    if (array_key_exists('secondary_major_type', $attributes)) {
        $secondaryLineOfBusiness = LineOfBusiness::factory()->create([
            'major_category' => $attributes['secondary_major_type'],
        ]);
        PermitApplicationLine::factory()
            ->for($application)
            ->for($secondaryLineOfBusiness, 'lineOfBusiness')
            ->create();
    }

    $assessment = Assessment::factory()->for($application)->create();
    $schedule = PaymentSchedule::factory()->for($application, 'permitApplication')->for($assessment)->create();
    $taxLine = PaymentScheduleLine::factory()->for($schedule)->create([
        'permit_application_line_id' => $primaryApplicationLine->id,
        'line_of_business_id' => $primaryLineOfBusiness->id,
        'category' => FeeRuleCategory::Tax,
        'amount_cents' => $attributes['tax_amount_cents'],
        'paid_amount_cents' => $attributes['tax_amount_cents'],
    ]);
    $collection = TreasuryCollection::factory()->create([
        'payment_schedule_id' => $schedule->id,
        'permit_application_id' => $application->id,
        'assessment_id' => $assessment->id,
        'status' => $attributes['collection_status'] ?? TreasuryCollectionStatus::Receipted,
        'amount_cents' => $attributes['tax_amount_cents'] + ($attributes['fee_amount_cents'] ?? 0),
        'received_at' => $attributes['received_at'],
    ]);
    $taxAllocation = CollectionAllocation::factory()->for($collection, 'treasuryCollection')->for($taxLine, 'paymentScheduleLine')->create([
        'amount_cents' => $attributes['tax_amount_cents'],
    ]);
    $feeAllocation = null;

    if (($attributes['fee_amount_cents'] ?? 0) > 0) {
        $feeLine = PaymentScheduleLine::factory()->for($schedule)->create([
            'category' => FeeRuleCategory::Fee,
            'amount_cents' => $attributes['fee_amount_cents'],
            'paid_amount_cents' => $attributes['fee_amount_cents'],
        ]);
        $feeAllocation = CollectionAllocation::factory()->for($collection, 'treasuryCollection')->for($feeLine, 'paymentScheduleLine')->create([
            'amount_cents' => $attributes['fee_amount_cents'],
        ]);
    }

    if ($attributes['receipt_number'] !== null) {
        Receipt::factory()->for($collection, 'treasuryCollection')->create([
            'payment_schedule_id' => $schedule->id,
            'permit_application_id' => $application->id,
            'assessment_id' => $assessment->id,
            'status' => $attributes['receipt_status'] ?? ReceiptStatus::Issued,
            'receipt_number' => $attributes['receipt_number'],
            'amount_cents' => $collection->amount_cents,
        ]);
    }

    return ['tax_allocation' => $taxAllocation, 'fee_allocation' => $feeAllocation];
}
