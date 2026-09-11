<?php

use App\Enums\UserPermission;
use App\Http\Middleware\RestrictIpilHistoricalUat;

beforeEach(function () {
    config([
        'ipil_historical_uat.enabled' => true,
        'ipil_historical_uat.environment_id' => RestrictIpilHistoricalUat::EnvironmentId,
        'ipil_historical_uat.reviewer_email' => 'synthetic-reviewer@example.test',
        'stakeholder_preview.mode' => false,
    ]);
});

test('historical uat requires normal sign in and does not admit public registration', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->get(route('staff.ipil-history.index'))->assertRedirect(route('login'));
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
    $this->get('/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

test('historical uat denies a different authenticated staff member', function () {
    $staff = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewPermitApplications]);
    $this->actingAs($staff)->get(route('staff.ipil-history.index'))->assertForbidden();
});

test('historical uat keeps the authorized reviewer outside operational routes', function () {
    $staff = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewPermitApplications]);
    config(['ipil_historical_uat.reviewer_email' => $staff->email]);
    $this->actingAs($staff)->get('/dashboard')->assertRedirect(route('staff.ipil-history.index'));
    $this->get(route('staff.ipil-history.index'))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
    $this->get('/staff/permit-applications')->assertNotFound();
    $this->post('/staff/ipil-history')->assertStatus(405);
});

test('historical uat fails closed for incomplete or unsafe configuration', function () {
    config(['ipil_historical_uat.environment_id' => 'workflow-uat']);
    $this->get('/login')->assertServiceUnavailable();
    config(['ipil_historical_uat.environment_id' => RestrictIpilHistoricalUat::EnvironmentId, 'stakeholder_preview.mode' => true]);
    $this->get('/login')->assertServiceUnavailable();
    config(['stakeholder_preview.mode' => false, 'ipil_historical_uat.reviewer_email' => null]);
    $this->get('/login')->assertServiceUnavailable();
});

test('the historical environment cannot silently disable its restriction', function () {
    app()->instance('env', 'historical-uat');
    config(['ipil_historical_uat.enabled' => false]);
    $this->get('/login')->assertServiceUnavailable();
});
