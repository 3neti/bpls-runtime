<?php

use App\Actions\SubmitCitizenPermitApplication;
use App\Enums\PermitApplicationStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\PermitApplication;
use App\Models\User;
use App\Notifications\PermitApplicationReceived;
use Inertia\Testing\AssertableInertia as Assert;

test('formal submission records one factual in-app receipt notice', function () {
    [$citizen, $application] = citizenNotificationDraft();

    app(SubmitCitizenPermitApplication::class)->handle($application, $citizen);
    $application->refresh();
    $notification = $citizen->notifications()->sole();

    expect($notification->type)->toBe(PermitApplicationReceived::class)
        ->and($notification->data['permit_application_id'])->toBe($application->id)
        ->and($notification->data['tracking_reference'])->toBe($application->tracking_reference)
        ->and($notification->data['kind'])->toBe('permit_application_received')
        ->and($notification->data['message'])->toContain('received your application for processing')
        ->and($notification->data['message'])->toContain('does not mean')
        ->and($notification->read_at)->toBeNull();

    app(SubmitCitizenPermitApplication::class)->handle($application, $citizen);

    expect($citizen->notifications()->count())->toBe(1);
});

test('citizen sees only owned notices and may mark an owned notice as read', function () {
    [$citizen, $application] = citizenNotificationDraft();
    $otherCitizen = User::factory()->create(['role_id' => $citizen->role_id]);
    [$otherCitizen, $otherApplication] = citizenNotificationDraft($otherCitizen);
    app(SubmitCitizenPermitApplication::class)->handle($application, $citizen);
    app(SubmitCitizenPermitApplication::class)->handle($otherApplication, $otherCitizen);
    $application->refresh();

    $notification = $citizen->notifications()->sole();

    $this->actingAs($citizen)
        ->get(route('citizen.notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('citizen/notifications/Index')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.id', $notification->id)
            ->where('notifications.data.0.kind', 'permit_application_received')
            ->where('notifications.data.0.tracking_reference', $application->tracking_reference)
            ->where('notifications.data.0.permit_application_url', route('citizen.permit-applications.show', $application, false))
            ->where('notifications.data.0.read_at', null)
        );

    $this->actingAs($citizen)
        ->patch(route('citizen.notifications.update', $notification))
        ->assertRedirect();

    expect($notification->refresh()->read_at)->not->toBeNull();
});

test('citizen cannot read or update another users notice', function () {
    [$citizen] = citizenNotificationDraft();
    $otherCitizen = User::factory()->create(['role_id' => $citizen->role_id]);
    [$otherCitizen, $otherApplication] = citizenNotificationDraft($otherCitizen);
    app(SubmitCitizenPermitApplication::class)->handle($otherApplication, $otherCitizen);
    $otherNotification = $otherCitizen->notifications()->sole();

    $this->actingAs($citizen)
        ->patch(route('citizen.notifications.update', $otherNotification))
        ->assertNotFound();

    expect($otherNotification->refresh()->read_at)->toBeNull();
});

/**
 * @return array{User, PermitApplication}
 */
function citizenNotificationDraft(?User $citizen = null): array
{
    $citizen ??= userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::SubmitOwnPermitApplications,
        UserPermission::ViewOwnPermitApplications,
    ], UserRole::Citizen);
    $owner = BusinessOwner::factory()->create();
    $business = Business::factory()->for($owner, 'owner')->create();
    $citizen->forceFill(['business_owner_id' => $owner->id])->save();
    $application = PermitApplication::factory()
        ->for($business)
        ->for($citizen, 'submittedBy')
        ->create([
            'application_number' => null,
            'tracking_reference' => null,
            'status' => PermitApplicationStatus::Draft,
            'submitted_at' => null,
            'metadata' => [],
        ]);

    return [$citizen->refresh(), $application];
}
