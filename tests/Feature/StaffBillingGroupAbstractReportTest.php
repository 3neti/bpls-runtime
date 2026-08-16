<?php

use App\Enums\BillingGroupEvidenceType;
use App\Enums\UserPermission;
use App\Models\BillingGroup;
use App\Models\BillingGroupField;
use App\Models\BillingGroupReconciliation;
use App\Models\BillingGroupRecord;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('billing group abstract preserves the legacy contract while refusing draft records as collections', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);
    $billingGroup = BillingGroup::factory()->create(['name' => 'Miscellaneous Fees']);
    BillingGroupField::factory()
        ->count(3)
        ->for($billingGroup)
        ->sequence(
            ['sort_order' => 1],
            ['sort_order' => 2],
            ['sort_order' => 3],
        )
        ->create();
    BillingGroupRecord::factory()->count(2)->for($billingGroup)->for($user, 'createdBy')->create();
    BillingGroupReconciliation::factory()->for($billingGroup)->for($user, 'recordedBy')->create([
        'version' => 2,
        'evidence_type' => BillingGroupEvidenceType::ObservedTreasuryPractice,
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.billing-groups.abstract.index', $billingGroup))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/BillingGroupAbstract')
            ->where('status', 'blocked')
            ->where('can_generate', false)
            ->where('can_export', false)
            ->where('official_row_count', 0)
            ->where('billing_group.id', $billingGroup->id)
            ->where('billing_group.name', 'Miscellaneous Fees')
            ->where('billing_group.acceptance_status', 'provisional')
            ->where('billing_group.field_count', 3)
            ->where('billing_group.record_count', 2)
            ->where('billing_group.draft_record_count', 2)
            ->where('report.key', 'billing_group_abstract')
            ->where('base_columns', fn ($columns): bool => $columns->count() === 5)
            ->where('readiness', fn ($requirements): bool => $requirements->count() === 7)
            ->where('readiness.1.status', 'recorded')
            ->where('current_reconciliation.version', 2)
            ->where('current_reconciliation.execution_status', 'blocked')
            ->where('blocked_by', fn ($blocked): bool => $blocked->contains('authoritative_collection_records'))
            ->missing('rows.0')
        );

    expect(Route::has('staff.reports.billing-groups.abstract.download'))->toBeFalse();
});

test('billing group abstract does not expose draft references as receipt numbers', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);
    $billingGroup = BillingGroup::factory()->create();
    BillingGroupRecord::factory()->for($billingGroup)->for($user, 'createdBy')->create([
        'draft_reference' => 'BGRD-NOT-AN-OFFICIAL-RECEIPT',
    ]);

    $this->actingAs($user)
        ->get(route('staff.reports.billing-groups.abstract.index', $billingGroup))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('official_row_count', 0)
            ->where('billing_group.draft_record_count', 1)
            ->where('can_generate', false)
            ->missing('rows.0')
        );
});

test('billing group abstract requires report permission', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewBillingGroups]);
    $billingGroup = BillingGroup::factory()->create();

    $this->actingAs($user)
        ->get(route('staff.reports.billing-groups.abstract.index', $billingGroup))
        ->assertForbidden();
});
