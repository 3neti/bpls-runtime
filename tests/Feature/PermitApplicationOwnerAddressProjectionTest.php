<?php

use App\Actions\ResolvePermitOwnerAddress;
use App\Enums\PermitApplicationStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;
use Inertia\Testing\AssertableInertia as Assert;

test('staff and citizen application details prefer the frozen owner address without mutating registry identity', function () {
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);
    $staff = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);
    $owner = BusinessOwner::factory()->create(['address' => 'Registry Address']);
    $business = Business::factory()->for($owner, 'owner')->create();
    $citizen->forceFill(['business_owner_id' => $owner->id])->save();
    $application = PermitApplication::factory()->withStatus(PermitApplicationStatus::Assessment)->for($business)->create([
        'submitted_by_id' => $citizen->id,
        'metadata' => [
            'applicant_declaration_draft' => [
                'owner_address' => addressSnapshot('Draft Street'),
            ],
        ],
    ]);
    $snapshot = [
        'owner_address' => addressSnapshot('Frozen Street'),
    ];
    $snapshotHash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    $declaration = PermitApplicationDeclaration::factory()->for($application)->create([
        'declared_by_id' => $citizen->id,
        'snapshot' => $snapshot,
        'snapshot_hash' => $snapshotHash,
    ]);

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permitApplication.owner.address', '117, Frozen Street, Don Andres, Ipil, Zamboanga Sibugay'));

    $this->actingAs($staff)
        ->get(route('staff.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permitApplication.business.owner.address', '117, Frozen Street, Don Andres, Ipil, Zamboanga Sibugay'));

    expect($owner->refresh()->address)->toBe('Registry Address')
        ->and($declaration->refresh()->snapshot_hash)->toBe($snapshotHash);
});

test('owner address projection uses the draft only before a declaration is frozen', function () {
    $owner = BusinessOwner::factory()->create(['address' => 'Registry Address']);
    $business = Business::factory()->for($owner, 'owner')->create();
    $application = PermitApplication::factory()->for($business)->create([
        'metadata' => [
            'applicant_declaration_draft' => [
                'owner_address' => addressSnapshot('Draft Street'),
            ],
        ],
    ]);

    expect(app(ResolvePermitOwnerAddress::class)->handle($application))
        ->toBe('117, Draft Street, Don Andres, Ipil, Zamboanga Sibugay')
        ->and($owner->refresh()->address)->toBe('Registry Address');
});

test('owner address projection falls back to registry evidence when declaration address is unavailable', function () {
    $owner = BusinessOwner::factory()->create(['address' => 'Historical Registry Address']);
    $business = Business::factory()->for($owner, 'owner')->create();
    $application = PermitApplication::factory()->for($business)->create([
        'metadata' => ['applicant_declaration_draft' => ['owner_address' => []]],
    ]);

    expect(app(ResolvePermitOwnerAddress::class)->handle($application))
        ->toBe('Historical Registry Address');
});

/** @return array<string, string> */
function addressSnapshot(string $street): array
{
    return [
        'house_or_building_number' => '117',
        'building_name' => $street,
        'street' => $street,
        'barangay' => 'Don Andres',
        'city_municipality' => 'Ipil',
        'province' => 'Zamboanga Sibugay',
    ];
}
