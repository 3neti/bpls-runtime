<?php

use App\Enums\UserPermission;
use App\Enums\UserRole;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    expect(auth()->user()->role->code)->toBe(UserRole::Citizen->value)
        ->and(auth()->user()->can(UserPermission::AccessCitizen->value))->toBeTrue()
        ->and(auth()->user()->can(UserPermission::CreateOwnPermitApplications->value))->toBeTrue()
        ->and(auth()->user()->can(UserPermission::EditOwnPermitApplications->value))->toBeTrue()
        ->and(auth()->user()->can(UserPermission::ViewOwnPermitApplications->value))->toBeTrue();
});
