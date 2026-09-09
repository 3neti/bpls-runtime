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
        ->post(route('citizen.permit-applications.submit', $application), [
            'undertaking_accepted' => '1',
        ]);

    $application->refresh();

    $response->assertRedirect(route('citizen.permit-applications.show', $application));
    expect($application->status)->toBe(PermitApplicationStatus::Assessment)
        ->and($application->submitted_at)->not->toBeNull()
        ->and($application->application_number)->toBeNull()
        ->and($application->tracking_reference)->toMatch('/^SUB-[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and(data_get($application->metadata, 'citizen_submission.actor_id'))->toBe($citizen->id)
        ->and(data_get($application->metadata, 'citizen_submission.submitted_at'))->toBe($application->submitted_at->toIso8601String())
        ->and(data_get($application->metadata, 'municipal_receipt.received_at'))->toBe($application->submitted_at->toIso8601String())
        ->and(data_get($application->metadata, 'undertaking_confirmation.accepted'))->toBeTrue()
        ->and(data_get($application->metadata, 'undertaking_confirmation.actor_id'))->toBe($citizen->id)
        ->and(data_get($application->metadata, 'undertaking_confirmation.accepted_at'))->toBe($application->submitted_at->toIso8601String())
        ->and(data_get($application->metadata, 'undertaking_confirmation.declaration_snapshot_hash'))->toBe($application->declaration()->sole()->snapshot_hash)
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

    $first = $submit->handle($application, $citizen, true);
    $firstSubmittedAt = $first->submitted_at?->toIso8601String();
    $firstTrackingReference = $first->tracking_reference;
    $second = $submit->handle($application, $citizen, true);

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
    $citizen->primaryRole()->revokePermissionTo(UserPermission::SubmitOwnPermitApplications->value);

    $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.submit', $application), ['undertaking_accepted' => '1'])
        ->assertForbidden();

    $otherRole = Role::factory()->create(['code' => 'citizen-submission-other']);
    $otherRole->permissions()->attach(Permission::query()
        ->whereIn('code', [
            UserPermission::AccessCitizen->value,
            UserPermission::SubmitOwnPermitApplications->value,
        ])
        ->pluck('id'));
    $otherCitizen = userWithRole($otherRole);

    $this->actingAs($otherCitizen)
        ->post(route('citizen.permit-applications.submit', $application), ['undertaking_accepted' => '1'])
        ->assertNotFound();

    expect($application->refresh()->status)->toBe(PermitApplicationStatus::Draft)
        ->and($application->submitted_at)->toBeNull();

    Notification::assertNothingSent();
});

test('citizen submission rejects a draft whose business is not linked to the citizen legal identity', function () {
    [$citizen, $application] = citizenSubmissionDraft();
    $citizen->forceFill(['business_owner_id' => BusinessOwner::factory()->create()->id])->save();

    $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.submit', $application), ['undertaking_accepted' => '1'])
        ->assertNotFound();

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

    app(SubmitCitizenPermitApplication::class)->handle($application, $citizen, true);

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permitApplication.can_submit', false)
            ->where('permitApplication.status', PermitApplicationStatus::Assessment->value)
            ->where('permitApplication.submission_boundary.citizen_submitted_at', $application->refresh()->submitted_at?->toIso8601String())
            ->where('permitApplication.submission_boundary.municipality_received_at', $application->submitted_at?->toIso8601String())
            ->where('permitApplication.submission_boundary.undertaking_confirmed_at', $application->submitted_at?->toIso8601String())
            ->where('permitApplication.processing.has_entered_municipal_processing', true)
            ->where('permitApplication.processing.current_stage', 'submitted_awaiting_municipal_intake')
            ->where('permitApplication.application_number', null)
            ->where('permitApplication.display_reference', $application->tracking_reference)
        );
});

test('citizen submission requires a fresh explicit undertaking confirmation', function (array $payload) {
    [$citizen, $application] = citizenSubmissionDraft();

    $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.submit', $application), $payload)
        ->assertSessionHasErrors('undertaking_accepted');

    expect($application->refresh()->status)->toBe(PermitApplicationStatus::Draft)
        ->and($application->submitted_at)->toBeNull()
        ->and(data_get($application->metadata, 'undertaking_confirmation'))->toBeNull();
})->with([
    'missing confirmation' => [[]],
    'declined confirmation' => [['undertaking_accepted' => '0']],
]);

test('signature facsimile capture uses a drawing canvas and keeps a visible confirmed preview', function () {
    $component = file_get_contents(resource_path('js/components/SignatureFacsimileCapture.vue'));

    expect($component)
        ->toContain('data-testid="signature-facsimile-canvas"')
        ->toContain('canvas.value.toBlob')
        ->toContain("new File([blob], 'signature-facsimile.png'")
        ->toContain('data-testid="signature-facsimile-preview"')
        ->toContain('Preview of the captured signature facsimile')
        ->toContain('Change signature')
        ->toContain('Remove');
});

test('nelson application form explains every disabled submission prerequisite', function () {
    $component = file_get_contents(resource_path('js/pages/permit-applications/Create.vue'));

    expect($component)
        ->toContain('const submissionBlockers = computed')
        ->toContain("blockers.push('Check the Oath of Undertaking.')")
        ->toContain("blockers.push('Capture and use your signature.')")
        ->toContain('data-testid="submission-readiness"')
        ->toContain('Before you can Sign & Submit:')
        ->toContain('v-model="')
        ->toContain('submissionForm.undertaking_accepted');
});

test('commissioned draft save leaves undertaking optional while sign and submit keeps both lodging gates', function () {
    $component = file_get_contents(resource_path('js/pages/permit-applications/Create.vue'));

    expect($component)
        ->toContain(':required="!isCommissionedApplication"')
        ->toContain('!submissionForm.undertaking_accepted ||')
        ->toContain('!submissionForm.signature_facsimile')
        ->toContain('submissionForm.post(citizenSubmit.url(props.draft.id)');
});

test('commissioned application form makes document and request invalidation visible', function () {
    $component = file_get_contents(resource_path('js/pages/permit-applications/Create.vue'));

    expect($component)
        ->toContain('data-testid="lodging-ceremony-notice"')
        ->toContain('data-testid="lodging-request-failure"')
        ->toContain('onHttpException: (response) =>')
        ->toContain('response.status === 401 || response.status === 419')
        ->toContain('onNetworkError: () =>')
        ->toContain('Log in and return to this Draft');
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
