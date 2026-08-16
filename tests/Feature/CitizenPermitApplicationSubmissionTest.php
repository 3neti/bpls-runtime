<?php

use App\Actions\BuildPermitApplicationTimeline;
use App\Actions\SubmitCitizenPermitApplication;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PermitApplicationReceived;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('formal citizen submission records separate submitted and received facts without inventing downstream behavior', function () {
    [$citizen, $application] = citizenSubmissionDraft();

    $response = $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.submit', $application));

    $application->refresh();

    $response->assertRedirect(route('citizen.permit-applications.show', $application));
    expect($application->status)->toBe(PermitApplicationStatus::Assessment)
        ->and($application->submitted_at)->not->toBeNull()
        ->and($application->application_number)->toBeNull()
        ->and($application->tracking_reference)->toMatch('/^SUB-[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and(data_get($application->metadata, 'citizen_submission.actor_id'))->toBe($citizen->id)
        ->and(data_get($application->metadata, 'citizen_submission.submitted_at'))->toBe($application->submitted_at->toIso8601String())
        ->and(data_get($application->metadata, 'municipal_receipt.received_at'))->toBe($application->submitted_at->toIso8601String())
        ->and(data_get($application->metadata, 'submission_policy_boundary.official_application_number_assigned'))->toBeFalse()
        ->and(data_get($application->metadata, 'submission_policy_boundary.tracking_reference_is_official_number'))->toBeFalse()
        ->and(data_get($application->metadata, 'submission_policy_boundary.documentary_sufficiency_determined'))->toBeFalse()
        ->and(data_get($application->metadata, 'submission_policy_boundary.payment_mode_committed'))->toBeFalse()
        ->and($application->metadata['status_history'])->toHaveCount(1)
        ->and($application->assessments()->count())->toBe(0)
        ->and($application->paymentSchedules()->count())->toBe(0)
        ->and($application->treasuryCollections()->count())->toBe(0);

    $timeline = collect(app(BuildPermitApplicationTimeline::class)->handle($application));

    expect($timeline->where('title', 'Citizen submitted application'))->toHaveCount(1)
        ->and($timeline->where('title', 'Municipality received application'))->toHaveCount(1);
});

test('formal citizen submission is idempotent for the same application', function () {
    [$citizen, $application] = citizenSubmissionDraft();
    $submit = app(SubmitCitizenPermitApplication::class);

    $first = $submit->handle($application, $citizen);
    $firstSubmittedAt = $first->submitted_at?->toIso8601String();
    $firstTrackingReference = $first->tracking_reference;
    $second = $submit->handle($application, $citizen);

    expect($second->submitted_at?->toIso8601String())->toBe($firstSubmittedAt)
        ->and($second->tracking_reference)->toBe($firstTrackingReference)
        ->and($second->metadata['status_history'])->toHaveCount(1)
        ->and($citizen->notifications()->where('type', PermitApplicationReceived::class)->count())->toBe(1)
        ->and($second->assessments()->count())->toBe(0)
        ->and(PermitApplication::query()->count())->toBe(1);
});

test('citizen submission requires explicit permission and an owned registry-linked draft', function () {
    Notification::fake();
    [$citizen, $application] = citizenSubmissionDraft();
    $citizen->role->permissions()
        ->where('code', UserPermission::SubmitOwnPermitApplications->value)
        ->detach();

    $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.submit', $application))
        ->assertForbidden();

    $otherRole = Role::factory()->create(['code' => 'citizen-submission-other']);
    $otherRole->permissions()->attach(Permission::query()
        ->whereIn('code', [
            UserPermission::AccessCitizen->value,
            UserPermission::SubmitOwnPermitApplications->value,
        ])
        ->pluck('id'));
    $otherCitizen = User::factory()->create(['role_id' => $otherRole->id]);

    $this->actingAs($otherCitizen)
        ->post(route('citizen.permit-applications.submit', $application))
        ->assertNotFound();

    expect($application->refresh()->status)->toBe(PermitApplicationStatus::Draft)
        ->and($application->submitted_at)->toBeNull();

    Notification::assertNothingSent();
});

test('citizen submission rejects a draft whose business is not linked to the citizen legal identity', function () {
    [$citizen, $application] = citizenSubmissionDraft();
    $citizen->forceFill(['business_owner_id' => BusinessOwner::factory()->create()->id])->save();

    $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.submit', $application))
        ->assertSessionHasErrors('submission');

    expect($application->refresh()->status)->toBe(PermitApplicationStatus::Draft)
        ->and($application->submitted_at)->toBeNull();
});

test('citizen detail exposes the formal submission boundary before and after receipt', function () {
    [$citizen, $application] = citizenSubmissionDraft();

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permitApplication.can_submit', true)
            ->where('permitApplication.submission_boundary.citizen_submitted_at', null)
            ->where('permitApplication.submission_boundary.municipality_received_at', null)
            ->where('permitApplication.submission_boundary.documentary_sufficiency_determined', false)
        );

    app(SubmitCitizenPermitApplication::class)->handle($application, $citizen);

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permitApplication.can_submit', false)
            ->where('permitApplication.status', PermitApplicationStatus::Assessment->value)
            ->where('permitApplication.submission_boundary.citizen_submitted_at', $application->refresh()->submitted_at?->toIso8601String())
            ->where('permitApplication.submission_boundary.municipality_received_at', $application->submitted_at?->toIso8601String())
            ->where('permitApplication.processing.has_entered_municipal_processing', true)
            ->where('permitApplication.application_number', null)
            ->where('permitApplication.display_reference', $application->tracking_reference)
        );
});

/**
 * @return array{User, PermitApplication}
 */
function citizenSubmissionDraft(): array
{
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::EditOwnPermitApplications,
        UserPermission::SubmitOwnPermitApplications,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $owner = BusinessOwner::factory()->create();
    $business = Business::factory()->for($owner, 'owner')->create();
    $citizen->forceFill(['business_owner_id' => $owner->id])->save();
    $application = PermitApplication::factory()
        ->for($business)
        ->for($citizen, 'submittedBy')
        ->create([
            'application_number' => null,
            'type' => PermitApplicationType::New,
            'status' => PermitApplicationStatus::Draft,
            'submitted_at' => null,
            'metadata' => [
                'citizen_intake' => [
                    'registry_owner_id' => $owner->id,
                    'saved_as_draft' => true,
                ],
            ],
        ]);
    PermitApplicationLine::factory()->for($application)->create();

    return [$citizen->refresh(), $application];
}
