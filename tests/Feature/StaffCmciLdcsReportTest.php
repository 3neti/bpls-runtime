<?php

use App\Enums\PermitApplicationStatus;
use App\Enums\UserPermission;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('cmci ldcs exposes the official contract while refusing generation and export', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);

    $this->actingAs($user)
        ->get(route('staff.reports.cmci-ldcs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/CmciLdcs')
            ->where('status', 'blocked')
            ->where('can_generate', false)
            ->where('can_export', false)
            ->where('row_count', 0)
            ->where('report.key', 'cmci_ldcs_annex_b')
            ->where('report.eligible_application_types', ['New', 'Renewal'])
            ->where('report.date_basis', 'Official permit issue date')
            ->where('report.grain', 'One row per legally released permit')
            ->where('columns', fn ($columns): bool => $columns->count() === 18)
            ->where('columns.17.key', 'permit_number')
            ->where('columns.17.source_status', 'authority_blocked')
            ->where('authority_boundary.artifact_is_not_issued_permit', true)
            ->where('authority_boundary.released_status_alone_is_not_sufficient', true)
            ->where('municipality_evidence.acceptance_status', 'unverified_for_official_export')
            ->missing('rows.0')
        );

    expect(Route::has('staff.reports.cmci-ldcs.download'))->toBeFalse();
});

test('cmci ldcs does not manufacture an official row from a raw released status', function () {
    PermitApplication::factory()->create([
        'application_number' => 'UNAUTHORIZED-PERMIT-NUMBER',
        'status' => PermitApplicationStatus::Released,
    ]);
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);

    $this->actingAs($user)
        ->get(route('staff.reports.cmci-ldcs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('row_count', 0)
            ->where('can_generate', false)
            ->where('authority_boundary.released_status_alone_is_not_sufficient', true)
            ->missing('rows.0')
        );
});

test('cmci ldcs requires report permission', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReceipts]);

    $this->actingAs($user)
        ->get(route('staff.reports.cmci-ldcs.index'))
        ->assertForbidden();
});
