<?php

use App\Enums\PermitApplicationStatus;
use App\Enums\UserPermission;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('bsp exposes the legacy contract while refusing official rows and export', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);

    $this->actingAs($user)
        ->get(route('staff.reports.bsp.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Bsp')
            ->where('status', 'blocked')
            ->where('can_generate', false)
            ->where('can_export', false)
            ->where('row_count', 0)
            ->where('report.key', 'bsp_non_bank_entities')
            ->where('report.date_basis', 'Official permit issue date')
            ->where('report.grain', 'One row per legally released permit application')
            ->where('report.default_coverage', 'Money Service Business')
            ->where('columns', fn ($columns): bool => $columns->count() === 16)
            ->where('columns.1.source_status', 'not_collected')
            ->where('columns.6.source_status', 'classification_mapping_unresolved')
            ->where('columns.10.source_status', 'authority_blocked')
            ->where('columns.11.source_status', 'authority_blocked')
            ->where('columns.14.source_status', 'status_semantics_unavailable')
            ->where('authority_boundary.classification_is_regulatory_assertion', true)
            ->where('projection_boundary.partial_official_rows_allowed', false)
            ->missing('rows.0')
        );

    expect(Route::has('staff.reports.bsp.download'))->toBeFalse();
});

test('bsp does not manufacture an official row from a raw released status', function () {
    PermitApplication::factory()->create([
        'application_number' => 'UNAUTHORIZED-PERMIT-NUMBER',
        'status' => PermitApplicationStatus::Released,
    ]);
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);

    $this->actingAs($user)
        ->get(route('staff.reports.bsp.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('row_count', 0)
            ->where('can_generate', false)
            ->where('authority_boundary.released_status_alone_is_not_sufficient', true)
            ->missing('rows.0')
        );
});

test('bsp requires report permission', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReceipts]);

    $this->actingAs($user)
        ->get(route('staff.reports.bsp.index'))
        ->assertForbidden();
});
