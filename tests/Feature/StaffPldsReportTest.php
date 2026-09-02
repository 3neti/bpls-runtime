<?php

use App\Enums\PermitApplicationStatus;
use App\Enums\UserPermission;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('plds exposes the legacy contract while refusing official rows and export', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);

    $this->actingAs($user)
        ->get(route('staff.reports.plds.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Plds')
            ->where('status', 'blocked')
            ->where('can_generate', false)
            ->where('can_export', false)
            ->where('row_count', 0)
            ->where('report.key', 'plds')
            ->where('report.date_basis', 'Official permit issue date')
            ->where('report.grain', 'One row per legally released permit application')
            ->where('columns', fn ($columns): bool => $columns->count() === 23)
            ->where('columns.0.key', 'date_registered_to_lgu')
            ->where('columns.0.source_status', 'authority_blocked')
            ->where('columns.7.source_status', 'classification_mapping_unresolved')
            ->where('columns.18.source_status', 'not_collected')
            ->where('columns.22.key', 'assets')
            ->where('columns.22.source_status', 'financial_mapping_unresolved')
            ->where('authority_boundary.released_status_alone_is_not_sufficient', true)
            ->where('projection_boundary.partial_official_rows_allowed', false)
            ->missing('rows.0')
        );

    expect(Route::has('staff.reports.plds.download'))->toBeFalse();
});

test('plds does not manufacture an official row from a raw released status', function () {
    PermitApplication::factory()->withStatus(PermitApplicationStatus::Released)->create([
        'application_number' => 'UNAUTHORIZED-PERMIT-NUMBER',
        'status' => PermitApplicationStatus::Released,
    ]);
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);

    $this->actingAs($user)
        ->get(route('staff.reports.plds.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('row_count', 0)
            ->where('can_generate', false)
            ->where('authority_boundary.released_status_alone_is_not_sufficient', true)
            ->missing('rows.0')
        );
});

test('plds requires report permission', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReceipts]);

    $this->actingAs($user)
        ->get(route('staff.reports.plds.index'))
        ->assertForbidden();
});
