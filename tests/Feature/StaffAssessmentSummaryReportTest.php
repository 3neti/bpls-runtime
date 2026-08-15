<?php

use App\Enums\AssessmentStatus;
use App\Enums\FeeRuleCategory;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\PermitApplication;
use Inertia\Testing\AssertableInertia as Assert;

test('staff users with report permission can view current assessment snapshot totals', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    $assessment = assessmentSummaryRecord([
        'application_number' => 'APP-ASSESSMENT-SUMMARY-001',
        'business_name' => 'Assessment Summary Store',
        'owner_name' => 'Assessment Summary Owner',
        'type' => PermitApplicationType::Renewal,
        'tax_amount_cents' => 75_000,
        'fee_amount_cents' => 12_500,
        'clearance_amount_cents' => 3_500,
        'other_amount_cents' => 1_000,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.assessment-summary.index', [
            'year' => 2026,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/AssessmentSummary')
            ->where('summary.row_count', 1)
            ->where('summary.business_count', 1)
            ->where('summary.total_amount_cents', 92_000)
            ->where('summary.tax_amount_cents', 75_000)
            ->where('summary.fee_amount_cents', 12_500)
            ->where('summary.clearance_amount_cents', 3_500)
            ->where('summary.other_amount_cents', 1_000)
            ->where('rows.0.assessment_id', $assessment->id)
            ->where('rows.0.application_number', 'APP-ASSESSMENT-SUMMARY-001')
            ->where('rows.0.business_name', 'Assessment Summary Store')
            ->where('rows.0.line_count', 4)
            ->where('rows.0.total_amount_cents', 92_000)
        );
});

test('assessment summary filters exact current snapshots and excludes drafts voids and superseded versions', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    assessmentSummaryRecord([
        'application_number' => 'APP-INCLUDED-RENEWAL',
        'business_name' => 'Included Renewal Store',
        'owner_name' => 'Included Owner',
        'type' => PermitApplicationType::Renewal,
        'tax_amount_cents' => 20_000,
        'fee_amount_cents' => 10_000,
    ]);
    assessmentSummaryRecord([
        'application_number' => 'APP-WRONG-TYPE',
        'business_name' => 'Wrong Type Store',
        'owner_name' => 'Wrong Type Owner',
        'type' => PermitApplicationType::New,
        'tax_amount_cents' => 40_000,
        'fee_amount_cents' => 10_000,
    ]);
    assessmentSummaryRecord([
        'application_number' => 'APP-SUPERSEDED',
        'business_name' => 'Superseded Renewal Store',
        'owner_name' => 'Superseded Owner',
        'type' => PermitApplicationType::Renewal,
        'tax_amount_cents' => 50_000,
        'fee_amount_cents' => 10_000,
        'superseded_at' => now(),
    ]);
    assessmentSummaryRecord([
        'application_number' => 'APP-DRAFT',
        'business_name' => 'Draft Renewal Store',
        'owner_name' => 'Draft Owner',
        'type' => PermitApplicationType::Renewal,
        'tax_amount_cents' => 60_000,
        'fee_amount_cents' => 10_000,
        'status' => AssessmentStatus::Draft,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.assessment-summary.index', [
            'year' => 2026,
            'type' => PermitApplicationType::Renewal->value,
            'q' => 'included',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.type', PermitApplicationType::Renewal->value)
            ->where('filters.q', 'included')
            ->where('summary.row_count', 1)
            ->where('summary.total_amount_cents', 30_000)
            ->where('rows.0.application_number', 'APP-INCLUDED-RENEWAL')
            ->missing('rows.1')
        );
});

test('staff users without report permission cannot view assessment summary', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReceipts,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.assessment-summary.index'))
        ->assertForbidden();
});

test('assessment summary exports matching snapshot rows as csv', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewReports,
    ]);

    assessmentSummaryRecord([
        'application_number' => 'APP-ASSESSMENT-CSV',
        'business_name' => 'CSV Assessment Store',
        'owner_name' => 'CSV Assessment Owner',
        'type' => PermitApplicationType::New,
        'tax_amount_cents' => 54_321,
        'fee_amount_cents' => 12_345,
    ]);

    $response = $this->actingAs($user)
        ->get(route('staff.reports.assessment-summary.download', [
            'year' => 2026,
        ]))
        ->assertOk()
        ->assertDownload('assessment-summary-2026.csv');

    expect($response->streamedContent())
        ->toContain('Assessment ID')
        ->toContain('APP-ASSESSMENT-CSV')
        ->toContain('CSV Assessment Store')
        ->toContain('543.21')
        ->toContain('666.66');
});

/**
 * @param  array{
 *     application_number: string,
 *     business_name: string,
 *     owner_name: string,
 *     type: PermitApplicationType,
 *     tax_amount_cents: int,
 *     fee_amount_cents: int,
 *     clearance_amount_cents?: int,
 *     other_amount_cents?: int,
 *     status?: AssessmentStatus,
 *     superseded_at?: mixed
 * }  $attributes
 */
function assessmentSummaryRecord(array $attributes): Assessment
{
    $owner = BusinessOwner::factory()->create([
        'name' => $attributes['owner_name'],
    ]);
    $business = Business::factory()
        ->for($owner, 'owner')
        ->create([
            'name' => $attributes['business_name'],
        ]);
    $permitApplication = PermitApplication::factory()
        ->for($business)
        ->create([
            'application_number' => $attributes['application_number'],
            'type' => $attributes['type'],
            'application_year' => 2026,
        ]);
    $lineAmounts = [
        FeeRuleCategory::Tax->value => $attributes['tax_amount_cents'],
        FeeRuleCategory::Fee->value => $attributes['fee_amount_cents'],
        FeeRuleCategory::Clearance->value => $attributes['clearance_amount_cents'] ?? 0,
        FeeRuleCategory::Other->value => $attributes['other_amount_cents'] ?? 0,
    ];
    $assessment = Assessment::factory()
        ->for($permitApplication)
        ->create([
            'status' => $attributes['status'] ?? AssessmentStatus::Computed,
            'superseded_at' => $attributes['superseded_at'] ?? null,
            'total_amount_cents' => array_sum($lineAmounts),
        ]);

    foreach ($lineAmounts as $category => $amountCents) {
        if ($amountCents === 0) {
            continue;
        }

        AssessmentLine::factory()
            ->for($assessment)
            ->create([
                'code' => 'TEST-'.str($category)->upper(),
                'category' => $category,
                'amount_cents' => $amountCents,
            ]);
    }

    return $assessment;
}
