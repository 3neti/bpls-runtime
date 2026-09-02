<?php

use App\Enums\PermitApplicationStatus;
use App\Enums\UserPermission;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('annex c dnfbp exposes the legacy contract while refusing official rows and export', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);

    $this->actingAs($user)
        ->get(route('staff.reports.annex-c-dnfbp.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/AnnexCDnfbp')
            ->where('status', 'blocked')
            ->where('can_generate', false)
            ->where('can_export', false)
            ->where('row_count', 0)
            ->where('report.key', 'annex_c_dnfbp')
            ->where('report.date_basis', 'Official permit issue date within an accepted reporting semester')
            ->where('columns', fn ($columns): bool => $columns->count() === 9)
            ->where('columns.0.source_status', 'classification_mapping_unresolved')
            ->where('columns.3.source_status', 'registry_available')
            ->where('columns.7.source_status', 'authority_blocked')
            ->where('columns.8.source_status', 'authority_blocked')
            ->where('authority_boundary.report_is_authority_bearing', true)
            ->where('projection_boundary.partial_official_rows_allowed', false)
            ->where('legacy_evidence.period_filter', 'The report was titled semestral but accepted no semester or date parameters.')
            ->missing('rows.0')
        );

    expect(Route::has('staff.reports.annex-c-dnfbp.download'))->toBeFalse();
});

test('annex c dnfbp does not manufacture an official row from a raw released status', function () {
    PermitApplication::factory()->withStatus(PermitApplicationStatus::Released)->create([
        'application_number' => 'UNAUTHORIZED-PERMIT-NUMBER',
        'status' => PermitApplicationStatus::Released,
    ]);
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);

    $this->actingAs($user)
        ->get(route('staff.reports.annex-c-dnfbp.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('row_count', 0)
            ->where('can_generate', false)
            ->where('authority_boundary.released_status_alone_is_not_sufficient', true)
            ->missing('rows.0')
        );
});

test('annex c dnfbp requires report permission', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReceipts]);

    $this->actingAs($user)
        ->get(route('staff.reports.annex-c-dnfbp.index'))
        ->assertForbidden();
});
