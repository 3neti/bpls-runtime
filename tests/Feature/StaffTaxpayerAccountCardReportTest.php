<?php

use App\Enums\UserPermission;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('taxpayer account card exposes its template without manufacturing an official report', function () {
    $user = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewReports]);

    $this->actingAs($user)
        ->get(route('staff.reports.taxpayer-account-card.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/TaxpayerAccountCard')
            ->where('status', 'blocked')
            ->where('can_generate', false)
            ->where('can_export', false)
            ->where('row_count', 0)
            ->where('report.key', 'taxpayer_account_card')
            ->where('report.grain', 'One taxpayer account across tax years')
            ->where('sections', fn ($sections): bool => $sections->count() === 4)
            ->missing('rows.0')
        );

    expect(Route::has('staff.reports.taxpayer-account-card.download'))->toBeFalse();
});

test('taxpayer account card requires report permission', function () {
    $user = userWithPermissions([UserPermission::AccessStaff]);

    $this->actingAs($user)
        ->get(route('staff.reports.taxpayer-account-card.index'))
        ->assertForbidden();
});
